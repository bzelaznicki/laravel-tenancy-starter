<?php

namespace App\Http\Resources;

use App\Models\Invitation;
use App\Policies\MembershipPermissions;
use App\TenantRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invitation
 */
class InvitationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{
     *     id: string,
     *     email: string,
     *     role: string,
     *     invitedAt: string|null,
     *     expiresAt: string,
     *     resendAvailableAt: string|null,
     *     invitedBy: array{name: string}|null,
     *     capabilities: array{canRevoke: bool, canResend: bool}
     * }
     */
    public function toArray(Request $request): array
    {
        $invitation = $this->resource;
        $actorRoleValue = $request->attributes->get('tenant_role');
        $actorRole = is_string($actorRoleValue)
            ? TenantRole::tryFrom($actorRoleValue)
            : null;

        $mayManage = MembershipPermissions::canInvite($actorRole, $invitation->role);

        $resendAvailableAt = $invitation->resendAvailableAt()?->toIso8601String();

        return [
            'id' => $invitation->getKey(),
            'email' => $invitation->email,
            'role' => $invitation->role->value,
            'invitedAt' => $invitation->created_at?->toIso8601String(),
            'expiresAt' => $invitation->expires_at->toIso8601String(),
            'resendAvailableAt' => $resendAvailableAt,
            'invitedBy' => $invitation->inviter
                ? ['name' => $invitation->inviter->name]
                : null,
            'capabilities' => [
                'canRevoke' => $mayManage,
                'canResend' => $mayManage,
            ],
        ];
    }
}
