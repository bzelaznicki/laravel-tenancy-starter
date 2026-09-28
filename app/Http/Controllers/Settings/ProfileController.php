<?php

namespace App\Http\Controllers\Settings;

use App\Actions\DeleteTenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\Membership;
use App\Models\Tenant;
use App\TenantRole;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Profile updated.')]);

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile. Workspaces where the user is the only member are
     * deleted with it. Workspaces where the user is the last owner of other members
     * block the deletion, so that no workspace is left without an owner.
     */
    public function destroy(ProfileDeleteRequest $request, DeleteTenant $deleteTenant): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $deleteTenant): void {
            $tenantIds = Membership::query()
                ->where('user_id', $user->getKey())
                ->pluck('tenant_id');

            $membershipsByTenant = Membership::query()
                ->whereIn('tenant_id', $tenantIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->groupBy('tenant_id');

            $soleMemberTenantIds = $membershipsByTenant
                ->filter(fn (Collection $memberships): bool => $memberships->count() === 1)
                ->keys();

            $lastOwnerTenantIds = $membershipsByTenant
                ->filter(function (Collection $memberships) use ($user): bool {
                    $owners = $memberships->where('role', TenantRole::Owner);

                    return $memberships->count() > 1
                        && $owners->count() === 1
                        && $owners->first()->user_id === $user->getKey();
                })
                ->keys();

            if ($lastOwnerTenantIds->isNotEmpty()) {
                $tenantNames = Tenant::query()
                    ->whereIn('id', $lastOwnerTenantIds)
                    ->orderBy('name')
                    ->pluck('name')
                    ->join(', ');

                throw ValidationException::withMessages([
                    'account' => __('You are the only owner of :workspaces. Make another member an owner, or remove the other members, before you delete your account.', [
                        'workspaces' => $tenantNames,
                    ]),
                ]);
            }

            foreach ($soleMemberTenantIds as $tenantId) {
                $deleteTenant->handle(Tenant::query()->findOrFail($tenantId));
            }

            Auth::logout();

            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
