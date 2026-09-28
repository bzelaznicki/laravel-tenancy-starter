<?php

return [
    'resend_cooldown_seconds' => (int) env(
        'INVITATION_RESEND_COOLDOWN_SECONDS',
        60,
    ),
];
