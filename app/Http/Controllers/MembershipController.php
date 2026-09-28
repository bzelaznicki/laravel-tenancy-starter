<?php

namespace App\Http\Controllers;

use App\Http\Resources\InvitationResource;
use App\Http\Resources\MembershipResource;
use App\Models\Invitation;
use App\Models\Membership;
use App\TenantRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MembershipController extends Controller
{
    public function index(Request $request): Response
    {

        $memberships = Membership::query()
            ->where('tenant_id', tenant()->getKey())
            ->with('user')
            ->get();

        $request->attributes->set(
            'tenant_owner_count',
            $memberships->where('role', TenantRole::Owner)->count(),
        );

        $pendingInvitations = Invitation::query()
            ->forTenant(tenant()->getKey())
            ->pending()
            ->where('expires_at', '>', now())
            ->with('inviter')
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('settings/members', [
            'memberships' => MembershipResource::collection($memberships)->resolve($request),
            'pendingInvitations' => InvitationResource::collection($pendingInvitations)->resolve($request),
        ]);
    }

    public function update(Request $request, string $membership): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['required', Rule::enum(TenantRole::class)],
        ]);

        $intendedRole = TenantRole::from($validated['role']);
        $membershipId = $membership;

        DB::transaction(function () use ($membershipId, $intendedRole): void {
            Membership::query()
                ->where('tenant_id', tenant()->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $membership = Membership::query()
                ->where('tenant_id', tenant()->getKey())
                ->findOrFail($membershipId);

            Gate::authorize('update', [$membership, $intendedRole]);

            $membership->role = $intendedRole;
            $membership->save();
        });

        return back();
    }

    public function destroy(string $membership): RedirectResponse
    {
        $membershipId = $membership;

        DB::transaction(function () use ($membershipId): void {
            Membership::query()
                ->where('tenant_id', tenant()->getKey())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $membership = Membership::query()
                ->where('tenant_id', tenant()->getKey())
                ->findOrFail($membershipId);

            Gate::authorize('delete', $membership);

            $membership->delete();
        });

        return back();
    }
}
