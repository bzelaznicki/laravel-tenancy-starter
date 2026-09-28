<?php

return [
    'prune_unverified_after_days' => (int) env(
        'SIGNUP_PRUNE_UNVERIFIED_AFTER_DAYS',
        14,
    ),
];
