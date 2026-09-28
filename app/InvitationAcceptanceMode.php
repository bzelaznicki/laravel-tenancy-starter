<?php

namespace App;

/**
 * Which acceptance form the viewer of a valid invitation link is shown.
 */
enum InvitationAcceptanceMode: string
{
    /** The invited address has no account yet, so the viewer creates one. */
    case Register = 'register';

    /** The invitee is signed in and only has to confirm joining. */
    case Confirm = 'confirm';

    /** The invited address already has an account the viewer is not signed in as. */
    case SignIn = 'sign_in';
}
