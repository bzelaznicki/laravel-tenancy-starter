<?php

namespace App\Policies;

use App\Models\Invitation;
use App\Models\User;

class InvitationPolicy
{
    /**
     * Revoking an invitation is the same authority as having sent it: an actor
     * may only withdraw an invitation whose intended role they could grant.
     */
    public function revoke(User $user, Invitation $invitation): bool
    {
        return MembershipPermissions::canInvite(
            $user->roleFor($invitation->tenant),
            $invitation->role,
        );
    }

    /**
     * Resending rotates the token on an invitation the actor could have sent.
     */
    public function resend(User $user, Invitation $invitation): bool
    {
        return $this->revoke($user, $invitation);
    }
}
