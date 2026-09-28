<?php

use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantInvitation;
use App\TenantRole;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;

it('allows an owner to invite every tenant role', function (TenantRole $role): void {
    $email = 'dinesh@piedpiper.com';
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($owner, TenantRole::Owner)->create();

    $this->actingAs($owner);

    $response = $this->post(
        tenantRoute($tenant, 'invitations.store'),
        [
            'email' => $email,
            'role' => $role->value,
            'message' => 'Welcome to the team!',
        ]
    );

    $response->assertRedirect();

    $createdInvitation = Invitation::query()
        ->where('tenant_id', $tenant->id)
        ->where('email', $email)
        ->sole();

    expect($createdInvitation)->not->toBeNull()
        ->and($createdInvitation->email)->toBe($email)
        ->and($createdInvitation->role)->toBe($role)
        ->and($createdInvitation->invited_by)->toBe($owner->id)
        ->and($createdInvitation->message)->toBe('Welcome to the team!');
})->with(TenantRole::cases());

it('allows an admin to invite member and viewer roles', function (TenantRole $role): void {
    $email = 'dinesh@piedpiper.com';
    $admin = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($admin, TenantRole::Admin)->create();

    $this->actingAs($admin);

    $response = $this->post(
        tenantRoute($tenant, 'invitations.store'),
        [
            'email' => $email,
            'role' => $role->value,
            'message' => 'Welcome to the team!',
        ]
    );

    $response->assertRedirect();

    $createdInvitation = Invitation::query()
        ->where('tenant_id', $tenant->id)
        ->where('email', $email)
        ->sole();

    expect($createdInvitation->email)->toBe($email)
        ->and($createdInvitation->role)->toBe($role)
        ->and($createdInvitation->message)->toBe('Welcome to the team!');
})
    ->with([
        TenantRole::Member,
        TenantRole::Viewer,
    ]);

it('prevents an admin from inviting owner and admin roles', function (TenantRole $role): void {
    Notification::fake();
    $email = 'dinesh@piedpiper.com';
    $admin = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($admin, TenantRole::Admin)->create();

    $this->actingAs($admin);

    $response = $this->post(
        tenantRoute($tenant, 'invitations.store'),
        [
            'email' => $email,
            'role' => $role->value,
            'message' => 'Welcome to the team!',
        ]
    );

    $response->assertForbidden();

    $this->assertDatabaseMissing('invitations', [
        'tenant_id' => $tenant->id,
        'email' => $email,
    ]);

    Notification::assertNothingSent();
})->with([
    TenantRole::Owner,
    TenantRole::Admin,
]);

it('prevents members and viewers from creating invitations', function (TenantRole $actorRole, TenantRole $targetRole): void {
    Notification::fake();
    $email = 'dinesh@piedpiper.com';
    $actor = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($actor, $actorRole)->create();

    $this->actingAs($actor);

    $response = $this->post(
        tenantRoute($tenant, 'invitations.store'),
        [
            'email' => $email,
            'role' => $targetRole->value,
            'message' => 'Welcome to the team!',
        ]
    );

    $response->assertForbidden();

    $this->assertDatabaseMissing('invitations', [
        'tenant_id' => $tenant->id,
        'email' => $email,
    ]);

    Notification::assertNothingSent();
})->with(
    [
        TenantRole::Viewer,
        TenantRole::Member,
    ],
    TenantRole::cases(),
);

it('validates invitation details', function (array $overrides, string $errorField): void {
    Notification::fake();
    $actor = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($actor, TenantRole::Owner)->create();

    $this->actingAs($actor);

    $payload = array_replace([
        'email' => 'jared@piedpiper.com',
        'role' => TenantRole::Member->value,
        'message' => 'Welcome to the team!',
    ], $overrides);

    $response = $this->post(tenantRoute($tenant, 'invitations.store'), $payload);

    $response->assertSessionHasErrors([$errorField]);

    $this->assertDatabaseEmpty('invitations');

    Notification::assertNothingSent();
})->with([
    'empty email' => [['email' => ''], 'email'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'empty role' => [['role' => ''], 'role'],
    'unknown role' => [['role' => 'superadmin'], 'role'],
    'non-string message' => [['message' => ['hello']], 'message'],
    'message too long' => [['message' => str_repeat('a', 256)], 'message'],
]);

it('requires invitation fields', function (string $field): void {
    Notification::fake();
    $owner = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->create();

    $payload = [
        'email' => 'jared@piedpiper.com',
        'role' => TenantRole::Member->value,
    ];

    unset($payload[$field]);

    $this->actingAs($owner)
        ->post(tenantRoute($tenant, 'invitations.store'), $payload)
        ->assertSessionHasErrors([$field]);

    $this->assertDatabaseEmpty('invitations');

    Notification::assertNothingSent();
})->with(['email', 'role']);

it('normalizes the invited email address', function (): void {
    $actor = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($actor, TenantRole::Owner)->create();

    $this->actingAs($actor);

    $response = $this->post(tenantRoute($tenant, 'invitations.store'), [
        'email' => '      BiGHeaD@pIeDPiPer.com         ',
        'role' => TenantRole::Member->value,
    ]);

    $response->assertRedirect();

    $createdInvitation = Invitation::query()
        ->where('tenant_id', $tenant->id)
        ->sole();

    expect($createdInvitation->email)->toBe('bighead@piedpiper.com');
});

it('rejects a duplicate pending invitation with a deterministic validation error', function (): void {
    Notification::fake();
    $email = 'jared@piedpiper.com';
    $actor = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($actor, TenantRole::Owner)->create();

    $invitation = Invitation::factory()->forTenant($tenant, $actor)->create(['email' => $email]);
    $originalTokenHash = $invitation->token_hash;
    $originalRole = $invitation->role;
    $originalExpiresAt = $invitation->expires_at->copy();

    $this->actingAs($actor);

    $response = $this->post(tenantRoute($tenant, 'invitations.store'), [
        'email' => $email,
        'role' => TenantRole::Member->value,
    ]);

    $response->assertSessionHasErrors(['email']);
    $invitation->refresh();

    expect($invitation->token_hash)->toBe($originalTokenHash)
        ->and($invitation->role)->toBe($originalRole)
        ->and($invitation->expires_at->equalTo($originalExpiresAt))->toBeTrue();

    $this->assertDatabaseCount('invitations', 1);

    Notification::assertNothingSent();
});

it('marks an overdue invitation as expired and creates a new pending invitation', function (): void {
    $email = 'jared@piedpiper.com';
    $actor = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($actor, TenantRole::Owner)->create();
    $invitation = Invitation::factory()
        ->forTenant($tenant, $actor)
        ->create([
            'email' => $email,
            'expires_at' => now()->subMinute(),
            'expired_at' => null,
        ]);

    $this->actingAs($actor);

    $response = $this->post(tenantRoute($tenant, 'invitations.store'), [
        'email' => $email,
        'role' => TenantRole::Member->value,
    ]);

    $response->assertRedirect()->assertSessionHasNoErrors();

    $this->assertDatabaseCount('invitations', 2);

    $invitation->refresh();

    $newInvitation = Invitation::query()
        ->where('email', $email)
        ->where('tenant_id', $tenant->id)
        ->whereNull('expired_at')
        ->sole();
    expect($invitation->expired_at)->not->toBeNull()
        ->and($newInvitation->token_hash)->not->toBe($invitation->token_hash)
        ->and($newInvitation->expires_at->isFuture())->toBeTrue()
        ->and($newInvitation->accepted_at)->toBeNull()
        ->and($newInvitation->revoked_at)->toBeNull()
        ->and($newInvitation->expired_at)->toBeNull();
});

it('rolls back invitation expiry when creating its replacement fails', function (): void {
    Notification::fake();
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($owner, TenantRole::Owner)->create();
    $invitation = Invitation::factory()->forTenant($tenant, $owner)->create([
        'email' => 'jared@piedpiper.com',
        'expires_at' => now()->subMinute(),
        'expired_at' => null,
    ]);
    $originalTokenHash = $invitation->token_hash;

    Exceptions::fake();

    Invitation::creating(function (Invitation $replacement) use ($tenant): void {
        if ($replacement->tenant_id === $tenant->id) {
            throw new RuntimeException('Forced invitation creation failure.');
        }
    });

    $this->actingAs($owner)
        ->post(tenantRoute($tenant, 'invitations.store'), [
            'email' => $invitation->email,
            'role' => TenantRole::Member->value,
        ])
        ->assertServerError();

    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'Forced invitation creation failure.');

    $invitation->refresh();

    expect($invitation->expired_at)->toBeNull()
        ->and($invitation->token_hash)->toBe($originalTokenHash);

    $this->assertDatabaseCount('invitations', 1);

    Notification::assertNothingSent();
});

it('creates only one pending invitation and returns a validation error for the competing request', function (): void {
    Notification::fake();
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($owner, TenantRole::Owner)->create();
    $email = 'jared.concurrent@piedpiper.com';
    $winningInvitation = Invitation::factory()->forTenant($tenant, $owner)->make([
        'email' => $email,
        'role' => TenantRole::Viewer,
    ]);
    $connection = DB::connection();
    $competingConnection = 'invitation_competitor';

    config()->set('database.connections.'.$competingConnection, $connection->getConfig());
    $connection->commit();

    try {
        $insertedCompetitor = false;

        Invitation::creating(function (Invitation $invitation) use ($tenant, $winningInvitation, $competingConnection, &$insertedCompetitor): void {
            if ($invitation->tenant_id !== $tenant->id || $insertedCompetitor) {
                return;
            }

            $insertedCompetitor = true;
            $winningInvitation->setConnection($competingConnection)->saveQuietly();
        });

        $this->actingAs($owner)
            ->post(tenantRoute($tenant, 'invitations.store'), [
                'email' => $email,
                'role' => TenantRole::Member->value,
            ])
            ->assertRedirect()
            ->assertSessionHasErrors(['email']);

        Notification::assertNothingSent();

        expect($insertedCompetitor)->toBeTrue();

        $invitation = Invitation::query()->where('tenant_id', $tenant->id)->sole();

        expect($invitation->id)->toBe($winningInvitation->id)
            ->and($invitation->token_hash)->toBe($winningInvitation->token_hash)
            ->and($invitation->role)->toBe(TenantRole::Viewer)
            ->and($invitation->accepted_at)->toBeNull()
            ->and($invitation->revoked_at)->toBeNull()
            ->and($invitation->expired_at)->toBeNull()
            ->and($invitation->expires_at->isFuture())->toBeTrue();
    } finally {
        DB::purge($competingConnection);
        DB::table('invitations')->where('tenant_id', $tenant->id)->delete();
        DB::table('tenant_user')->where('tenant_id', $tenant->id)->delete();
        DB::table('domains')->where('tenant_id', $tenant->id)->delete();
        DB::table('tenants')->where('id', $tenant->id)->delete();
        DB::table('users')->where('id', $owner->id)->delete();
        $connection->beginTransaction();
    }
});

it('returns a validation error when the database reports the pending invitation constraint by columns', function (): void {
    Notification::fake();
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($owner, TenantRole::Owner)->create();

    Invitation::creating(function (Invitation $invitation) use ($tenant): void {
        if ($invitation->tenant_id !== $tenant->id) {
            return;
        }

        throw (new UniqueConstraintViolationException(
            'testing',
            'insert into invitations',
            [],
            new PDOException('Unique constraint violation.'),
        ))->setColumns(['tenant_id', 'email']);
    });

    $this->actingAs($owner)
        ->post(tenantRoute($tenant, 'invitations.store'), [
            'email' => 'jared@piedpiper.com',
            'role' => TenantRole::Member->value,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors(['email']);

    $this->assertDatabaseMissing('invitations', [
        'tenant_id' => $tenant->id,
        'email' => 'jared@piedpiper.com',
    ]);

    Notification::assertNothingSent();
});

it('ignores forged tenant and inviter IDs when creating an invitation', function (): void {
    $email = 'gilfoyle@piedpiper.com';
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($owner, TenantRole::Owner)->create();
    $otherOwner = User::factory()->create();
    $otherTenant = Tenant::factory()->withMember($otherOwner, TenantRole::Owner)->create();

    $this->actingAs($owner)
        ->post(tenantRoute($tenant, 'invitations.store'), [
            'email' => $email,
            'role' => TenantRole::Member->value,
            'tenant_id' => $otherTenant->id,
            'invited_by' => $otherOwner->id,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $createdInvitation = Invitation::query()->sole();

    expect($createdInvitation->tenant_id)->toBe($tenant->id)
        ->and($createdInvitation->invited_by)->toBe($owner->id)
        ->and($createdInvitation->email)->toBe($email)
        ->and($createdInvitation->role)->toBe(TenantRole::Member);
});

it('sends exactly one invitation notification to the normalized invited email', function (): void {
    Notification::fake();
    $email = 'bighead@piedpiper.com';
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($owner, TenantRole::Owner)->create();

    $this->actingAs($owner);
    $response = $this->post(
        tenantRoute($tenant, 'invitations.store'),
        [
            'email' => '  BiGHeAd@PiedPiper.com  ',
            'role' => TenantRole::Member->value,
            'message' => 'Welcome to the team!',
        ]
    );

    $response->assertRedirect()->assertSessionHasNoErrors();

    Notification::assertSentOnDemand(
        TenantInvitation::class,
        function (TenantInvitation $notification, array $channels, object $notifiable) use ($email): bool {
            return in_array('mail', $channels, true)
                && $notifiable->routes['mail'] === $email;
        }
    );
    Notification::assertCount(1);
});

it('leaves a failed invitation send retryable', function (): void {
    $email = 'bighead@piedpiper.com';
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($owner, TenantRole::Owner)->create();

    $failDelivery = true;
    Event::listen(NotificationSending::class, function () use (&$failDelivery): void {
        if ($failDelivery) {
            throw new RuntimeException('Forced invitation notification failure.');
        }
    });
    Exceptions::fake();

    $this->actingAs($owner)
        ->post(tenantRoute($tenant, 'invitations.store'), [
            'email' => $email,
            'role' => TenantRole::Member->value,
        ])
        ->assertServerError();

    $this->assertDatabaseMissing('invitations', [
        'tenant_id' => $tenant->id,
        'email' => $email,
    ]);

    $failDelivery = false;
    Notification::fake();

    $this->post(tenantRoute($tenant, 'invitations.store'), [
        'email' => $email,
        'role' => TenantRole::Member->value,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(Invitation::query()->sole()->last_sent_at)->not->toBeNull();
    Notification::assertCount(1);
});

it(
    'includes the tenant name inviter role and expiry in the invitation email',
    function (): void {
        Notification::fake();
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->withDomain()->withMember($owner, TenantRole::Owner)->create();

        $this->actingAs($owner);
        $response = $this->post(
            tenantRoute($tenant, 'invitations.store'),
            [
                'email' => 'bighead@piedpiper.com',
                'role' => TenantRole::Member->value,
                'message' => 'Welcome to the team!',
            ]
        );

        $response->assertRedirect()->assertSessionHasNoErrors();

        $invitation = Invitation::query()->sole();

        Notification::assertSentOnDemand(
            TenantInvitation::class,
            function (
                TenantInvitation $notification,
                array $channels,
                object $notifiable,
            ) use ($tenant, $owner, $invitation): bool {
                $mail = $notification->toMail($notifiable);

                $html = (string) $mail->render();
                $text = view($mail->view['text'], $mail->viewData)->render();

                foreach ([[$html, e(...)], [$text, fn (string $value): string => $value]] as [$body, $encode]) {
                    expect($body)
                        ->toContain($encode($tenant->name))
                        ->toContain($encode($owner->name))
                        ->toContain('Member')
                        ->toContain($invitation->expires_at->format('j F Y, H:i'))
                        ->toContain('Welcome to the team!')
                        ->toContain($encode($mail->actionUrl));
                }

                return true;
            },
        );
    }
);

it('includes an acceptance link on the invited tenant host with its invitation ID', function (): void {
    Notification::fake();
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($owner, TenantRole::Owner)->create();

    $this->actingAs($owner)
        ->post(tenantRoute($tenant, 'invitations.store'), [
            'email' => 'bighead@piedpiper.com',
            'role' => TenantRole::Member->value,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $invitation = Invitation::query()->sole();

    Notification::assertSentOnDemand(
        TenantInvitation::class,
        function (TenantInvitation $notification, array $channels, object $notifiable) use ($tenant, $invitation): bool {
            $mail = $notification->toMail($notifiable);

            expect($mail->actionUrl)->toBeString()->not->toBeEmpty();

            expect($mail->viewData['inviteMessage'])->toBeNull();

            expect(parse_url($mail->actionUrl, PHP_URL_SCHEME))->toBe('https')
                ->and(parse_url($mail->actionUrl, PHP_URL_HOST))->toBe(tenantHost($tenant))
                ->and(parse_url($mail->actionUrl, PHP_URL_PATH))->toBe('/invitations/'.$invitation->id.'/accept')
                ->and($mail->actionText)->toBe('Accept invitation');

            return true;
        },
    );
});

it('includes a plaintext token in the acceptance link that matches the stored token hash', function (): void {
    Notification::fake();
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($owner, TenantRole::Owner)->create();

    $this->actingAs($owner)
        ->post(tenantRoute($tenant, 'invitations.store'), [
            'email' => 'bighead@piedpiper.com',
            'role' => TenantRole::Member->value,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $invitation = Invitation::query()->sole();

    Notification::assertSentOnDemand(
        TenantInvitation::class,
        function (TenantInvitation $notification, array $channels, object $notifiable) use ($invitation): bool {
            $mail = $notification->toMail($notifiable);

            expect($mail->actionUrl)->toBeString()->not->toBeEmpty();

            parse_str(
                parse_url($mail->actionUrl, PHP_URL_QUERY) ?? '',
                $query,
            );

            expect($query)->toHaveKey('token');

            $token = $query['token'];

            expect($token)->toBeString()->not->toBeEmpty()
                ->and($token)->not->toBe($invitation->token_hash)
                ->and(hash('sha256', $token))->toBe($invitation->token_hash);

            return true;
        },
    );
});

it('does not allow an invitation to be created for another tenant', function (): void {
    Notification::fake();
    $email = 'gilfoyle@piedpiper.com';
    $actor = User::factory()->create();
    Tenant::factory()->withMember($actor, TenantRole::Owner)->create();
    $targetTenant = Tenant::factory()->withDomain()->create();

    $this->actingAs($actor);

    $response = $this->post(tenantRoute($targetTenant, 'invitations.store'), [
        'email' => $email,
        'role' => TenantRole::Member->value,
    ]);

    $response->assertForbidden();

    $this->assertDatabaseEmpty('invitations');

    Notification::assertNothingSent();
});

it('starts the resend cooldown when an invitation is created', function (): void {
    Notification::fake();

    $owner = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($owner, TenantRole::Owner)->create();

    $this->actingAs($owner);

    $response = $this->post(
        tenantRoute($tenant, 'invitations.store'),
        [
            'email' => 'dinesh@piedpiper.com',
            'role' => TenantRole::Member->value,
        ]
    );

    $response->assertRedirect()->assertSessionHasNoErrors();

    $invitation = Invitation::query()->sole();

    $this->post(
        tenantRoute($tenant, 'invitations.resend', [
            'invitation' => $invitation->id,
        ])
    )->assertRedirect();

    Notification::assertCount(1);
});

it('does not let revocation and recreation bypass the resend cooldown', function (): void {
    Notification::fake();
    $this->freezeTime();

    $owner = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($owner, TenantRole::Owner)->create();

    $this->actingAs($owner);

    $response = $this->post(
        tenantRoute($tenant, 'invitations.store'),
        [
            'email' => 'dinesh@piedpiper.com',
            'role' => TenantRole::Member->value,
        ]
    );

    $response->assertRedirect()->assertSessionHasNoErrors();

    $invitation = Invitation::query()->sole();

    $this->delete(
        tenantRoute($tenant, 'invitations.destroy', [
            'invitation' => $invitation->id,
        ]),
    )->assertRedirect()->assertSessionHasNoErrors();

    $this->post(tenantRoute($tenant, 'invitations.store'), [
        'email' => '   DInesh@PiedPiper.com   ',
        'role' => TenantRole::Member->value,
    ])->assertSessionHasErrors('email');

    Notification::assertCount(1);
    $this->assertDatabaseCount('invitations', 1);
});

it('keeps invitation cooldowns independent between tenants and recipients', function (): void {
    Notification::fake();
    $this->freezeTime();

    $owner = User::factory()->create();
    $firstTenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->create();
    $secondTenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->create();

    $this->actingAs($owner);

    foreach (
        [
            [$firstTenant, 'dinesh@piedpiper.com'],
            [$firstTenant, 'gilfoyle@piedpiper.com'],
            [$secondTenant, 'dinesh@piedpiper.com'],
        ] as [$tenant, $email]
    ) {
        $this->post(tenantRoute($tenant, 'invitations.store'), [
            'email' => $email,
            'role' => TenantRole::Member->value,
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    Notification::assertCount(3);
    $this->assertDatabaseCount('invitations', 3);
});

it('accepts an invitation email of exactly 255 characters', function (): void {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($owner, TenantRole::Owner)->create();
    $email = str_repeat('a', 255 - strlen('@piedpiper.com')).'@piedpiper.com';

    $this->actingAs($owner)
        ->post(tenantRoute($tenant, 'invitations.store'), [
            'email' => $email,
            'role' => TenantRole::Member->value,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();
    $createdInvitation = Invitation::query()
        ->where('tenant_id', $tenant->id)
        ->where('email', $email)
        ->sole();
    expect($createdInvitation->email)->toBe($email);
});

it('rejects an invitation email longer than 255 characters without saving or notifying', function (): void {
    Notification::fake();
    $owner = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($owner, TenantRole::Owner)->create();
    $email = str_repeat('a', 256 - strlen('@piedpiper.com')).'@piedpiper.com';

    $this->actingAs($owner)
        ->post(tenantRoute($tenant, 'invitations.store'), [
            'email' => $email,
            'role' => TenantRole::Member->value,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('email');

    Notification::assertNothingSent();

    $this->assertDatabaseCount('invitations', 0);
});
