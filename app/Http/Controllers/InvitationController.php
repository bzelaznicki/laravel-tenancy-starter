<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\InvitationAcceptanceMode;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\User;
use App\Notifications\TenantInvitation;
use App\TenantRole;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    use PasswordValidationRules, ProfileValidationRules;

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(
            [
                'email' => ['required', 'email', 'max:255'],
                'role' => ['required', Rule::enum(TenantRole::class)],
                'message' => ['nullable', 'string', 'max:255'],
            ]
        );

        $email = Str::lower(trim($validated['email']));

        $role = TenantRole::from($validated['role']);

        Gate::authorize('invite', [
            Membership::class,
            tenant(),
            $role,
        ]);
        $tenantId = tenant()->getKey();

        $this->guardAgainstInvitingAnExistingMember($email);

        $plainTextToken = Str::random(64);
        try {
            DB::transaction(function () use ($tenantId, $email, $role, $validated, $request, $plainTextToken): void {
                $matchingInvitations = Invitation::query()
                    ->forTenant($tenantId)
                    ->where('email', $email)
                    ->lockForUpdate()
                    ->get();
                $currentInvitation = $matchingInvitations->first(
                    fn (Invitation $invitation): bool => $invitation->accepted_at === null
                        && $invitation->revoked_at === null
                        && $invitation->expired_at === null,
                );

                if ($currentInvitation !== null) {
                    if ($currentInvitation->expires_at->isPast()) {
                        $currentInvitation->update([
                            'expired_at' => now(),
                        ]);
                    } else {
                        throw ValidationException::withMessages([
                            'email' => 'A pending invitation already exists for this email.',
                        ]);
                    }
                }

                $latestSentInvitation = $matchingInvitations->filter(
                    fn (Invitation $invitation): bool => $invitation->last_sent_at !== null,
                )
                    ->sortByDesc(
                        fn (Invitation $invitation): int => $invitation->last_sent_at->getTimestamp(),
                    )
                    ->first();

                $resendAvailableAt = $latestSentInvitation?->resendAvailableAt();

                if ($resendAvailableAt !== null && now()->lt($resendAvailableAt)) {
                    throw ValidationException::withMessages([
                        'email' => 'This user had an invitation sent recently. Please wait before sending it.',
                    ]);
                }

                $invitation = Invitation::query()->create(
                    [
                        'tenant_id' => $tenantId,
                        'invited_by' => $request->user()->getKey(),
                        'email' => $email,
                        'role' => $role,
                        'message' => $validated['message'] ?? null,
                        'token_hash' => hash('sha256', $plainTextToken),
                        'expires_at' => now()->addDays(7),
                    ]
                );

                Notification::route('mail', $email)
                    ->notify(new TenantInvitation($invitation, $plainTextToken));

                $invitation->update([
                    'last_sent_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException $exception) {
            $isPendingInvitationConstraint = $exception->index === 'invitations_one_open_per_tenant_email'
                || $exception->columns === ['tenant_id', 'email'];

            if (! $isPendingInvitationConstraint) {
                throw $exception;
            }
            throw ValidationException::withMessages([
                'email' => 'A pending invitation already exists for this email',
            ]);
        }

        return back();
    }

    public function show(Request $request, string $invitation): Response
    {
        $token = $request->query('token');

        abort_unless(is_string($token) && $token !== '', 404);

        $invitation = Invitation::query()
            ->with(['tenant', 'inviter'])
            ->where('tenant_id', tenant()->getKey())
            ->findOrFail($invitation);

        abort_unless(hash_equals($invitation->token_hash, hash('sha256', $token)), 404);
        abort_if(
            $invitation->accepted_at !== null
                || $invitation->revoked_at !== null
                || $invitation->expired_at !== null
                || $invitation->expires_at->lessThanOrEqualTo(now()),
            404,
        );

        $mode = $this->acceptanceMode($request, $invitation);

        if ($mode === InvitationAcceptanceMode::SignIn && $request->user() === null) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return Inertia::render('invitations/accept', [
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'message' => $invitation->message,
                'role' => $invitation->role->value,
                'expiresAt' => $invitation->expires_at->toIso8601String(),
                'invitedBy' => $invitation->inviter
                    ? ['name' => $invitation->inviter->name]
                    : null,
                'tenant' => [
                    'name' => $invitation->tenant->name,
                ],
            ],
            'token' => $token,
            'mode' => $mode->value,
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]);
    }

    /**
     * Decide which acceptance form the viewer of an invitation link should be shown.
     *
     * The invited address owning no account means the viewer still has to create
     * one; otherwise only the invitee themselves may confirm, and everyone else
     * (guest or a different signed-in user) is sent through sign-in first.
     */
    private function acceptanceMode(Request $request, Invitation $invitation): InvitationAcceptanceMode
    {
        $invitee = User::query()
            ->where('email', $invitation->email)
            ->first();

        if ($invitee === null) {
            return InvitationAcceptanceMode::Register;
        }

        return $request->user()?->is($invitee) === true
            ? InvitationAcceptanceMode::Confirm
            : InvitationAcceptanceMode::SignIn;
    }

    public function accept(Request $request, string $invitation): RedirectResponse
    {
        $token = $request->input('token');

        abort_unless(is_string($token) && $token !== '', 404);

        return DB::transaction(function () use ($request, $invitation, $token): RedirectResponse {
            $invitation = Invitation::query()
                ->where('tenant_id', tenant()->getKey())
                ->lockForUpdate()
                ->findOrFail($invitation);

            abort_unless(hash_equals($invitation->token_hash, hash('sha256', $token)), 404);
            abort_if(
                $invitation->accepted_at !== null
                    || $invitation->revoked_at !== null
                    || $invitation->expired_at !== null
                    || $invitation->expires_at->lessThanOrEqualTo(now()),
                404,
            );

            $tenant = tenant();

            $existingUser = User::query()
                ->where('email', $invitation->email)
                ->first();

            if ($existingUser !== null) {
                if ($request->user() === null) {
                    return redirect()->route('login');
                }

                abort_unless($request->user()->is($existingUser), 403);

                if ($existingUser->roleFor($tenant) === null) {
                    $tenant->users()->attach($existingUser, [
                        'role' => $invitation->role->value,
                    ]);
                }

                $invitation->update([
                    'accepted_at' => now(),
                ]);

                return redirect()->route('dashboard');
            }

            $validated = $request->validate([
                'name' => $this->nameRules(),
                'password' => $this->passwordRules(),
            ]);

            $user = User::create(
                [
                    'name' => $validated['name'],
                    'email' => $invitation->email,
                    'password' => $validated['password'],
                ]
            );

            $user->markEmailAsVerified();

            $tenant->users()->attach($user, [
                'role' => $invitation->role->value,
            ]);

            $invitation->update([
                'accepted_at' => now(),
            ]);

            return redirect()->route('login');
        });
    }

    /**
     * Inviting somebody who already belongs here is always a mistake: the
     * invitation would be accepted into a membership they already hold.
     */
    private function guardAgainstInvitingAnExistingMember(string $email): void
    {
        $existing = User::query()
            ->where('email', $email)
            ->first();

        $role = $existing?->roleFor(tenant());

        if ($role === null) {
            return;
        }

        $article = match ($role) {
            TenantRole::Owner, TenantRole::Admin => 'an',
            TenantRole::Member, TenantRole::Viewer => 'a',
        };

        throw ValidationException::withMessages([
            'email' => "{$existing->name} is already {$article} {$role->label()} here — change their role instead.",
        ]);
    }

    /**
     * Rotate the token on a pending invitation and mail the new link, leaving
     * any link already in the invitee's inbox dead.
     */
    public function resend(Request $request, string $invitation): RedirectResponse
    {
        $plainTextToken = Str::random(64);

        DB::transaction(function () use ($request, $invitation, $plainTextToken): void {
            $invitation = Invitation::query()->forTenant(tenant()->getKey())->pending()
                ->lockForUpdate()
                ->findOrFail($invitation);

            Gate::authorize('resend', $invitation);

            $resendAvailableAt = $invitation->resendAvailableAt();

            if ($resendAvailableAt !== null && now()->lt($resendAvailableAt)) {
                throw ValidationException::withMessages([
                    'resend' => 'This invitation was sent recently. Please wait before resending it.',
                ]);
            }

            $invitation->update([
                'token_hash' => hash('sha256', $plainTextToken),
                'invited_by' => $request->user()->getKey(),
                'expires_at' => now()->addDays(7),
            ]);

            Notification::route('mail', $invitation->email)
                ->notify(new TenantInvitation($invitation, $plainTextToken));

            $invitation->update([
                'last_sent_at' => now(),
            ]);
        });

        return back();
    }

    /**
     * Revoke a pending invitation. The emailed link stops working immediately.
     */
    public function destroy(string $invitation): RedirectResponse
    {
        DB::transaction(function () use ($invitation): void {
            $invitation = Invitation::query()->forTenant(tenant()->getKey())->pending()
                ->lockForUpdate()
                ->findOrFail($invitation);

            Gate::authorize('revoke', $invitation);

            $invitation->update([
                'revoked_at' => now(),
            ]);
        });

        return back();
    }
}
