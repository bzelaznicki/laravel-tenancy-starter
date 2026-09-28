<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function (): void {
    $this->skipUnlessFortifyHas(Features::emailVerification());
});

it('sends verification notification', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $this->actingAs($user)
        ->post(tenantRoute($tenant, 'verification.send'))
        ->assertRedirect(tenantRoute($tenant, 'home'));

    Notification::assertSentTo($user, VerifyEmail::class);
});

it('does not send verification notification if email is verified', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $this->actingAs($user)
        ->post(tenantRoute($tenant, 'verification.send'))
        ->assertRedirect(route('dashboard', absolute: false));

    Notification::assertNothingSent();
});

it('sets verification notification link to the tenant host subdomain', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $tenantHost = tenantHost($tenant);

    $this->actingAs($user)
        ->post(tenantRoute($tenant, 'verification.send'));

    Notification::assertSentTo(
        $user,
        VerifyEmail::class,
        function (VerifyEmail $notification) use ($user, $tenantHost): bool {
            $url = $notification->toMail($user)->actionUrl;

            expect(parse_url($url, PHP_URL_HOST))->toBe($tenantHost);

            return true;
        }
    );
});

it('renders the verification email with its link', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $this->actingAs($user)->post(tenantRoute($tenant, 'verification.send'));

    Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user): bool {
        $mail = $notification->toMail($user);

        expect($mail->subject)->toBe('Confirm your email address')
            ->and((string) $mail->render())->toContain('Confirm address')->toContain(e($mail->actionUrl))
            ->and(view($mail->view['text'], $mail->viewData)->render())->toContain($mail->actionUrl);

        return true;
    });
});
