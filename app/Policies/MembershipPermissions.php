<?php

namespace App\Policies;

use App\TenantRole;

final class MembershipPermissions
{
    public static function canInvite(?TenantRole $actorRole, TenantRole $intendedRole): bool
    {
        return match ($actorRole) {
            TenantRole::Owner => true,
            TenantRole::Admin => $intendedRole->level() < TenantRole::Admin->level(),
            default => false,
        };
    }

    public static function canUpdate(
        ?TenantRole $actorRole,
        TenantRole $currentRole,
        TenantRole $intendedRole,
        int $ownerCount,
    ): bool {
        $mayUpdate = match ($actorRole) {
            TenantRole::Owner => true,
            TenantRole::Admin => $currentRole->level() < TenantRole::Admin->level()
                && $intendedRole->level() < TenantRole::Admin->level(),
            default => false,
        };

        if (! $mayUpdate) {
            return false;
        }

        return $currentRole !== TenantRole::Owner
            || $intendedRole === TenantRole::Owner
            || $ownerCount > 1;
    }

    public static function canDelete(
        ?TenantRole $actorRole,
        TenantRole $currentRole,
        int $ownerCount,
    ): bool {
        $mayDelete = match ($actorRole) {
            TenantRole::Owner => true,
            TenantRole::Admin => $currentRole->level() < TenantRole::Admin->level(),
            default => false,
        };

        return $mayDelete
            && ($currentRole !== TenantRole::Owner || $ownerCount > 1);
    }
}
