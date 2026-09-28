<?php

namespace App\Policies;

use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\TenantRole;

class MembershipPolicy
{
    public function invite(User $user, Tenant $tenant, TenantRole $intendedRole): bool
    {
        return MembershipPermissions::canInvite($user->roleFor($tenant), $intendedRole);
    }

    public function update(User $user, Membership $membership, TenantRole $intendedRole): bool
    {
        return MembershipPermissions::canUpdate(
            $user->roleFor($membership->tenant),
            $membership->role,
            $intendedRole,
            $this->ownerCount($membership),
        );
    }

    public function delete(User $user, Membership $membership): bool
    {
        return MembershipPermissions::canDelete(
            $user->roleFor($membership->tenant),
            $membership->role,
            $this->ownerCount($membership),
        );
    }

    private function ownerCount(Membership $membership): int
    {
        return Membership::query()
            ->where('tenant_id', $membership->tenant_id)
            ->where('role', TenantRole::Owner->value)
            ->count();
    }
}
