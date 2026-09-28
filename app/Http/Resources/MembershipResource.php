<?php

namespace App\Http\Resources;

use App\Models\Membership;
use App\Policies\MembershipPermissions;
use App\TenantRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Membership
 */
class MembershipResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: int,
     *     role: string,
     *     joinedAt: string|null,
     *     user: array{id: string, name: string, email: string},
     *     capabilities: array{assignableRoles: list<string>, canDelete: bool}
     * }
     */
    public function toArray(Request $request): array
    {

        $membership = $this->resource;
        $user = $membership->user;
        $actorRoleValue = $request->attributes->get('tenant_role');
        $actorRole = is_string($actorRoleValue)
            ? TenantRole::tryFrom($actorRoleValue)
            : null;
        $ownerCount = $request->attributes->getInt('tenant_owner_count');

        $assignableRoles = array_values(array_map(
            fn (TenantRole $role): string => $role->value,
            array_filter(
                TenantRole::cases(),
                fn (TenantRole $role): bool => MembershipPermissions::canUpdate(
                    $actorRole,
                    $membership->role,
                    $role,
                    $ownerCount,
                ),
            ),
        ));

        return [
            'id' => $membership->getKey(),
            'role' => $membership->role->value,
            'joinedAt' => $membership->created_at?->toIso8601String(),
            'user' => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,

            ],
            'capabilities' => [
                'assignableRoles' => $assignableRoles,
                'canDelete' => MembershipPermissions::canDelete(
                    $actorRole,
                    $membership->role,
                    $ownerCount,
                ),
            ],
        ];
    }
}
