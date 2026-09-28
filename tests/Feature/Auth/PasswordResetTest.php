<?php

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function (): void {
    $this->skipUnlessFortifyHas(Features::resetPasswords());
});

test('reset password link screen can be rendered', function (): void {
    $tenant = Tenant::factory()->withDomain()->create();

    $response = $this->get(tenantRoute($tenant, 'password.request'));

    $response->assertOk();
});

test('reset password link can be requested', function (): void {
    Notification::fake();
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $this->post(tenantRoute($tenant, 'password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('reset password screen can be rendered', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $this->post(tenantRoute($tenant, 'password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) {
        $response = $this->get(route('password.reset', $notification->token));

        $response->assertOk();

        return true;
    });
});

test('password can be reset with valid token', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $this->post(tenantRoute($tenant, 'password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user, $tenant) {
        $response = $this->post(tenantRoute($tenant, 'password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        return true;
    });
});

test('password cannot be reset with invalid token', function (): void {
    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $response = $this->post(tenantRoute($tenant, 'password.update'), [
        'token' => 'invalid-token',
        'email' => $user->email,
        'password' => 'newpassword123',
        'password_confirmation' => 'newpassword123',
    ]);

    $response->assertSessionHasErrors('email');
});

it('uses the tenant host domain for password reset links', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $tenantHost = tenantHost($tenant);

    $this->post(tenantRoute($tenant, 'password.email'), ['email' => $user->email]);
    Notification::assertSentTo(
        $user,
        ResetPassword::class,
        function (ResetPassword $notification) use ($user, $tenantHost): bool {
            $url = $notification->toMail($user)->actionUrl;

            expect(parse_url($url, PHP_URL_HOST))->toBe($tenantHost);

            return true;
        },
    );
});

it('renders the password reset email with its link and expiry', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $tenant = Tenant::factory()->withDomain()->withMember($user)->create();

    $this->post(tenantRoute($tenant, 'password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
        $mail = $notification->toMail($user);

        expect($mail->subject)->toBe('Reset your '.config('app.name').' password');

        foreach ([(string) $mail->render(), view($mail->view['text'], $mail->viewData)->render()] as $body) {
            expect($body)
                ->toContain($user->email)
                ->toContain('expires in 60 minutes');
        }

        expect((string) $mail->render())->toContain('href="'.e($mail->actionUrl).'"')
            ->and(view($mail->view['text'], $mail->viewData)->render())->toContain($mail->actionUrl);

        return true;
    });
});
