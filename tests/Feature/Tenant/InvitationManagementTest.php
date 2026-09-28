<?php

use App\Http\Controllers\InvitationController;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantInvitation;
use App\TenantRole;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Concurrency;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

it('lists pending invitations with management capabilities on the members page', function (): void {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->create();
    $invitation = Invitation::factory()
        ->forTenant($tenant, $owner)
        ->create([
            'email' => 'dinesh@piedpiper.com',
            'role' => TenantRole::Member,
        ]);

    $this->actingAs($owner)
        ->get(tenantRoute($tenant, 'memberships.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('settings/members')
                ->has('pendingInvitations', 1)
                ->where('pendingInvitations.0.id', $invitation->id)
                ->where('pendingInvitations.0.email', 'dinesh@piedpiper.com')
                ->where('pendingInvitations.0.role', TenantRole::Member->value)
                ->where('pendingInvitations.0.invitedBy.name', $owner->name)
                ->where('pendingInvitations.0.capabilities.canRevoke', true)
                ->where('pendingInvitations.0.capabilities.canResend', true)
                ->missing('pendingInvitations.0.token_hash')
        );
});

it('leaves closed and overdue invitations off the members page', function (string $state): void {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->create();

    $factory = Invitation::factory()->forTenant($tenant, $owner);

    match ($state) {
        'accepted' => $factory->accepted()->create(),
        'revoked' => $factory->revoked()->create(),
        'expired' => $factory->expired()->create(),
        'overdue but unmarked' => $factory->create(['expires_at' => now()->subMinute()]),
    };

    $this->actingAs($owner)
        ->get(tenantRoute($tenant, 'memberships.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('pendingInvitations', 0));
})->with(['accepted', 'revoked', 'expired', 'overdue but unmarked']);

it('never shows another tenant\'s pending invitations', function (): void {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->create();
    $otherTenant = Tenant::factory()->withDomain()->create();
    Invitation::factory()
        ->forTenant($otherTenant, User::factory()->create())
        ->create();

    $this->actingAs($owner)
        ->get(tenantRoute($tenant, 'memberships.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('pendingInvitations', 0));
});

it('hides management of an owner invitation from an admin', function (): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->withMember($admin, TenantRole::Admin)
        ->create();
    Invitation::factory()
        ->forTenant($tenant, $owner)
        ->create(['role' => TenantRole::Owner]);

    $this->actingAs($admin)
        ->get(tenantRoute($tenant, 'memberships.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where('pendingInvitations.0.capabilities.canRevoke', false)
                ->where('pendingInvitations.0.capabilities.canResend', false)
        );
});

it('rotates the token and extends the deadline when an owner resends', function (): void {
    Notification::fake();
    $owner = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->create();
    $originalToken = Str::random(64);
    $invitation = Invitation::factory()
        ->forTenant($tenant, $owner)
        ->create([
            'email' => 'dinesh@piedpiper.com',
            'token_hash' => hash('sha256', $originalToken),
            'expires_at' => now()->addDay(),
        ]);

    $this->actingAs($owner)
        ->post(tenantRoute($tenant, 'invitations.resend', ['invitation' => $invitation->id]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $invitation->refresh();

    expect($invitation->token_hash)->not->toBe(hash('sha256', $originalToken));
    expect($invitation->expires_at->greaterThan(now()->addDays(6)))->toBeTrue();
    expect($invitation->accepted_at)->toBeNull();

    Notification::assertSentOnDemand(
        TenantInvitation::class,
        fn (TenantInvitation $notification, array $channels, object $notifiable): bool => in_array('mail', $channels, true)
            && $notifiable->routes['mail'] === 'dinesh@piedpiper.com'
    );
    Notification::assertCount(1);
});

it('kills the superseded link when an invitation is resent', function (): void {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->create();
    $originalToken = Str::random(64);
    $invitation = Invitation::factory()
        ->forTenant($tenant, $owner)
        ->create(['token_hash' => hash('sha256', $originalToken)]);

    $this->actingAs($owner)
        ->post(tenantRoute($tenant, 'invitations.resend', ['invitation' => $invitation->id]));

    $this->post(
        tenantRoute($tenant, 'invitations.accept.store', ['invitation' => $invitation->id]),
        ['token' => $originalToken],
    )->assertNotFound();
});

it('stops the emailed link working the moment an invitation is revoked', function (): void {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->create();
    $token = Str::random(64);
    $invitation = Invitation::factory()
        ->forTenant($tenant, $owner)
        ->create(['token_hash' => hash('sha256', $token)]);

    $this->actingAs($owner)
        ->delete(tenantRoute($tenant, 'invitations.destroy', ['invitation' => $invitation->id]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($invitation->refresh()->revoked_at)->not->toBeNull();

    $this->get(tenantRoute($tenant, 'invitations.accept', [
        'invitation' => $invitation->id,
        'token' => $token,
    ]))->assertNotFound();
});

it('refuses to let an admin resend or revoke an owner invitation', function (string $verb): void {
    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->withMember($admin, TenantRole::Admin)
        ->create();
    $invitation = Invitation::factory()
        ->forTenant($tenant, $owner)
        ->create(['role' => TenantRole::Owner]);

    $this->actingAs($admin);

    $response = $verb === 'resend'
        ? $this->post(tenantRoute($tenant, 'invitations.resend', ['invitation' => $invitation->id]))
        : $this->delete(tenantRoute($tenant, 'invitations.destroy', ['invitation' => $invitation->id]));

    $response->assertForbidden();

    expect($invitation->refresh()->revoked_at)->toBeNull();
})->with(['resend', 'revoke']);

it('resends an invitation whose inviter was deleted', function (): void {
    Notification::fake();
    $owner = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->create();
    $invitation = Invitation::factory()
        ->for($tenant)
        ->create(['invited_by' => null]);

    $this->actingAs($owner)
        ->post(tenantRoute($tenant, 'invitations.resend', ['invitation' => $invitation->id]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($invitation->refresh()->invited_by)->toBe($owner->id);

    Notification::assertSentOnDemand(
        TenantInvitation::class,
        function (TenantInvitation $notification, array $channels, object $notifiable) use ($owner): bool {
            $mail = $notification->toMail($notifiable);

            expect($mail->subject)->toStartWith("{$owner->name} invited you to")
                ->and($mail->viewData['inviterName'])->toBe($owner->name)
                ->and($mail->viewData['inviterEmail'])->toBe($owner->email);

            return true;
        }
    );
});

it('reassigns the inviter to the user that re-sent the invitation', function (): void {
    Notification::fake();

    $originalInviter = User::factory()->create();
    $owner = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($originalInviter, TenantRole::Admin)
        ->withMember($owner, TenantRole::Owner)
        ->create();
    $invitation = Invitation::factory()->forTenant($tenant, $originalInviter)->create();

    $this->actingAs($owner)
        ->post(tenantRoute($tenant, 'invitations.resend', ['invitation' => $invitation->id]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($invitation->refresh()->invited_by)->toBe($owner->id);

    Notification::assertSentOnDemand(
        TenantInvitation::class,
        function (TenantInvitation $notification, array $channels, object $notifiable) use ($owner): bool {
            $mail = $notification->toMail($notifiable);

            expect($mail->subject)->toStartWith("{$owner->name} invited you to")
                ->and($mail->viewData['inviterName'])->toBe($owner->name)
                ->and($mail->viewData['inviterEmail'])->toBe($owner->email);

            return true;
        }
    );
});

it('refuses invitation management to members and viewers', function (TenantRole $actorRole, string $verb): void {
    $owner = User::factory()->create();
    $actor = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->withMember($actor, $actorRole)
        ->create();
    $invitation = Invitation::factory()
        ->forTenant($tenant, $owner)
        ->create(['role' => TenantRole::Viewer]);

    $this->actingAs($actor);

    $response = $verb === 'resend'
        ? $this->post(tenantRoute($tenant, 'invitations.resend', ['invitation' => $invitation->id]))
        : $this->delete(tenantRoute($tenant, 'invitations.destroy', ['invitation' => $invitation->id]));

    $response->assertForbidden();

    expect($invitation->refresh()->revoked_at)->toBeNull();
})->with([
    [TenantRole::Member, 'resend'],
    [TenantRole::Member, 'revoke'],
    [TenantRole::Viewer, 'resend'],
    [TenantRole::Viewer, 'revoke'],
]);

it('refuses to manage an invitation belonging to another tenant', function (string $verb): void {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->create();
    $otherTenant = Tenant::factory()->withDomain()->create();
    $invitation = Invitation::factory()
        ->forTenant($otherTenant, User::factory()->create())
        ->create();

    $this->actingAs($owner);

    $response = $verb === 'resend'
        ? $this->post(tenantRoute($tenant, 'invitations.resend', ['invitation' => $invitation->id]))
        : $this->delete(tenantRoute($tenant, 'invitations.destroy', ['invitation' => $invitation->id]));

    $response->assertNotFound();

    expect($invitation->refresh()->revoked_at)->toBeNull();
})->with(['resend', 'revoke']);

it('refuses to resend or revoke an invitation that is already closed', function (string $state, string $verb): void {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->create();
    $factory = Invitation::factory()->forTenant($tenant, $owner);
    $invitation = match ($state) {
        'accepted' => $factory->accepted()->create(),
        'revoked' => $factory->revoked()->create(),
        'expired' => $factory->expired()->create(),
    };

    $this->actingAs($owner);

    $response = $verb === 'resend'
        ? $this->post(tenantRoute($tenant, 'invitations.resend', ['invitation' => $invitation->id]))
        : $this->delete(tenantRoute($tenant, 'invitations.destroy', ['invitation' => $invitation->id]));

    $response->assertNotFound();
})->with(['accepted', 'revoked', 'expired'])->with(['resend', 'revoke']);

it('refuses to invite somebody who already belongs to the workspace', function (): void {
    Notification::fake();
    $owner = User::factory()->create();
    $member = User::factory()->create([
        'name' => 'Jared Dunn',
        'email' => 'jared@piedpiper.com',
    ]);
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->withMember($member, TenantRole::Admin)
        ->create();

    $this->actingAs($owner)
        ->post(tenantRoute($tenant, 'invitations.store'), [
            'email' => '  JARED@piedpiper.com ',
            'role' => TenantRole::Member->value,
        ])
        ->assertSessionHasErrors([
            'email' => 'Jared Dunn is already an Admin here — change their role instead.',
        ]);

    $this->assertDatabaseCount('invitations', 0);
    Notification::assertNothingSent();
});

it('still invites somebody who only belongs to a different workspace', function (): void {
    Notification::fake();
    $owner = User::factory()->create();
    $outsider = User::factory()->create(['email' => 'gilfoyle@aviato.com']);
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->create();
    Tenant::factory()->withDomain()->withMember($outsider, TenantRole::Owner)->create();

    $this->actingAs($owner)
        ->post(tenantRoute($tenant, 'invitations.store'), [
            'email' => $outsider->email,
            'role' => TenantRole::Member->value,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->assertDatabaseHas('invitations', [
        'tenant_id' => $tenant->id,
        'email' => 'gilfoyle@aviato.com',
    ]);
});

it('includes the server-calculated resend time on pending invitations', function (): void {
    $this->freezeTime();
    config()->set('invitations.resend_cooldown_seconds', 60);

    $owner = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->create();
    Invitation::factory()
        ->forTenant($tenant, $owner)
        ->create([
            'last_sent_at' => now(),
        ]);

    $this->actingAs($owner)
        ->get(tenantRoute($tenant, 'memberships.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->where(
                    'pendingInvitations.0.resendAvailableAt',
                    now()->addSeconds(60)->toIso8601String(),
                )
        );
});

it('blocks a resend during cooldown without sending mail or changing the invitation', function (): void {
    Notification::fake();
    $this->freezeTime();
    config()->set('invitations.resend_cooldown_seconds', 60);

    $owner = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->create();
    $originalTokenHash = hash('sha256', Str::random(64));
    $invitation = Invitation::factory()
        ->forTenant($tenant, $owner)
        ->create([
            'token_hash' => $originalTokenHash,
            'expires_at' => now()->addDay(),
            'last_sent_at' => now(),
        ]);
    $originalExpiresAt = $invitation->expires_at->copy();
    $originalLastSentAt = $invitation->last_sent_at->copy();

    $this->actingAs($owner)
        ->post(tenantRoute($tenant, 'invitations.resend', ['invitation' => $invitation->id]))
        ->assertRedirect()
        ->assertSessionHasErrors('resend');

    $invitation->refresh();

    expect($invitation->token_hash)->toBe($originalTokenHash)
        ->and($invitation->expires_at->equalTo($originalExpiresAt))->toBeTrue()
        ->and($invitation->last_sent_at->equalTo($originalLastSentAt))->toBeTrue();

    Notification::assertNothingSent();
});

it('does not extend the cooldown when a resend is rejected', function (): void {
    Notification::fake();
    $this->freezeTime();
    config()->set('invitations.resend_cooldown_seconds', 60);

    $owner = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->create();
    $originalSentAt = now();
    $invitation = Invitation::factory()
        ->forTenant($tenant, $owner)
        ->create([
            'last_sent_at' => $originalSentAt,
        ]);

    $this->actingAs($owner);
    $this->travel(30)->seconds();

    $this->post(tenantRoute($tenant, 'invitations.resend', ['invitation' => $invitation->id]))
        ->assertRedirect()
        ->assertSessionHasErrors('resend');

    expect($invitation->refresh()->last_sent_at->toIso8601String())
        ->toBe($originalSentAt->toIso8601String());

    $this->travel(30)->seconds();

    $this->post(tenantRoute($tenant, 'invitations.resend', ['invitation' => $invitation->id]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($invitation->refresh()->last_sent_at->toIso8601String())
        ->toBe(now()->toIso8601String());
    Notification::assertCount(1);
});

it('allows a resend at the cooldown boundary and starts the next cooldown', function (): void {
    Notification::fake();
    $this->freezeTime();
    config()->set('invitations.resend_cooldown_seconds', 60);

    $owner = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->create();
    $invitation = Invitation::factory()
        ->forTenant($tenant, $owner)
        ->create([
            'last_sent_at' => now()->subSeconds(60),
        ]);

    $this->actingAs($owner)
        ->post(tenantRoute($tenant, 'invitations.resend', ['invitation' => $invitation->id]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($invitation->refresh()->last_sent_at->toIso8601String())
        ->toBe(now()->toIso8601String());

    $this->post(tenantRoute($tenant, 'invitations.resend', ['invitation' => $invitation->id]))
        ->assertRedirect()
        ->assertSessionHasErrors('resend');

    expect($invitation->refresh()->last_sent_at->toIso8601String())
        ->toBe(now()->toIso8601String());
    Notification::assertCount(1);
});

it('rolls back resend state when notification delivery fails', function (): void {
    $this->freezeTime();
    config()->set('invitations.resend_cooldown_seconds', 60);

    $owner = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->create();
    $originalTokenHash = hash('sha256', Str::random(64));
    $invitation = Invitation::factory()
        ->forTenant($tenant, $owner)
        ->create([
            'token_hash' => $originalTokenHash,
            'expires_at' => now()->addDay(),
            'last_sent_at' => now()->subSeconds(60),
        ]);
    $originalExpiresAt = $invitation->expires_at->copy();
    $originalLastSentAt = $invitation->last_sent_at->copy();

    $failDelivery = true;
    Event::listen(NotificationSending::class, function () use (&$failDelivery): void {
        if ($failDelivery) {
            throw new RuntimeException('Forced invitation notification failure.');
        }
    });
    Exceptions::fake();

    $this->actingAs($owner)
        ->post(tenantRoute($tenant, 'invitations.resend', ['invitation' => $invitation->id]))
        ->assertServerError();

    $invitation->refresh();

    expect($invitation->token_hash)->toBe($originalTokenHash)
        ->and($invitation->expires_at->equalTo($originalExpiresAt))->toBeTrue()
        ->and($invitation->last_sent_at->equalTo($originalLastSentAt))->toBeTrue();

    $failDelivery = false;
    Notification::fake();

    $this->post(tenantRoute($tenant, 'invitations.resend', ['invitation' => $invitation->id]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $invitation->refresh();

    expect($invitation->token_hash)->not->toBe($originalTokenHash)
        ->and($invitation->expires_at->toIso8601String())->toBe(now()->addDays(7)->toIso8601String())
        ->and($invitation->last_sent_at->toIso8601String())->toBe(now()->toIso8601String());
    Notification::assertCount(1);
});

it('shares the resend cooldown between authorized senders', function (): void {
    Notification::fake();
    $this->freezeTime();
    config()->set('invitations.resend_cooldown_seconds', 60);

    $owner = User::factory()->create();
    $admin = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->withMember($admin, TenantRole::Admin)
        ->create();
    $invitation = Invitation::factory()
        ->forTenant($tenant, $owner)
        ->create([
            'role' => TenantRole::Member,
            'last_sent_at' => now()->subSeconds(60),
        ]);

    $this->actingAs($owner)
        ->post(tenantRoute($tenant, 'invitations.resend', ['invitation' => $invitation->id]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->post(tenantRoute($tenant, 'invitations.resend', ['invitation' => $invitation->id]))
        ->assertRedirect()
        ->assertSessionHasErrors('resend');

    Notification::assertCount(1);
});

it('allows only one of two concurrent resend attempts to send mail', function (): void {
    config()->set('invitations.resend_cooldown_seconds', 60);

    $owner = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->create();
    $invitation = Invitation::factory()
        ->forTenant($tenant, $owner)
        ->create([
            'last_sent_at' => now()->subSeconds(60),
        ]);
    $connection = DB::connection();

    $connection->commit();

    $resend = (static function () use ($tenant, $owner, $invitation): array {
        config()->set('invitations.resend_cooldown_seconds', 60);
        Date::setTestNow(now());
        Notification::fake();
        tenancy()->initialize($tenant->id);
        Auth::loginUsingId($owner->id);

        try {
            request()->setUserResolver(fn () => Auth::user());
            app(InvitationController::class)->resend(request(), $invitation->id);

            return [
                'outcome' => 'sent',
                'notifications' => collect(Notification::sentNotifications())->flatten(3)->count(),
            ];
        } catch (ValidationException) {
            return [
                'outcome' => 'blocked',
                'notifications' => collect(Notification::sentNotifications())->flatten(3)->count(),
            ];
        } finally {
            tenancy()->end();
        }
    })->bindTo(null, null);

    try {
        $results = Concurrency::driver('process')->run([$resend, $resend]);

        expect(collect($results)->sortBy('outcome')->values()->all())->toBe([
            ['outcome' => 'blocked', 'notifications' => 0],
            ['outcome' => 'sent', 'notifications' => 1],
        ]);
    } finally {
        DB::table('invitations')->where('tenant_id', $tenant->id)->delete();
        DB::table('tenant_user')->where('tenant_id', $tenant->id)->delete();
        DB::table('domains')->where('tenant_id', $tenant->id)->delete();
        DB::table('tenants')->where('id', $tenant->id)->delete();
        DB::table('users')->where('id', $owner->id)->delete();
        $connection->beginTransaction();
    }
});

it('lists a pending invitation whose inviter was deleted', function (): void {
    $owner = User::factory()->create();
    $tenant = Tenant::factory()
        ->withDomain()
        ->withMember($owner, TenantRole::Owner)
        ->create();
    $invitation = Invitation::factory()
        ->for($tenant)
        ->create(['invited_by' => null]);

    $this->actingAs($owner)
        ->get(tenantRoute($tenant, 'memberships.index'))
        ->assertOk()
        ->assertInertia(
            fn (Assert $page) => $page
                ->component('settings/members')
                ->has('pendingInvitations', 1)
                ->where('pendingInvitations.0.id', $invitation->id)
                ->where('pendingInvitations.0.invitedBy', null)
        );
});
