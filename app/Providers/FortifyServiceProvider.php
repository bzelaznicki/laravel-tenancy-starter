<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\RegisterResponse;
use App\Models\Invitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Routing\UrlGenerator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(
            RegisterResponseContract::class,
            RegisterResponse::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
        $this->configureAuthentication();
        $this->configureEmailVerification();
        $this->configurePasswordResetMail();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
            'workspaceHost' => $request->getHost(),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn () => Inertia::render('auth/register', [
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Configure subdomain authentication
     */
    private function configureAuthentication(): void
    {
        Fortify::authenticateUsing(function (Request $request): ?User {
            $user = User::query()
                ->where('email', $request->string('email'))
                ->first();

            if ($user === null || ! Hash::check($request->string('password'), $user->password)) {
                return null;
            }

            return $this->canAuthenticateForTenant($user) ? $user : null;
        });
        Passkeys::authorizeLoginUsing(
            function (Request $request, PasskeyUser $user, Passkey $passkey): bool {
                return $user instanceof User
                    && $this->canAuthenticateForTenant($user);
            },
        );
    }

    private function canAuthenticateForTenant(User $user): bool
    {
        $tenant = tenant();

        if (! $tenant instanceof Tenant) {
            return false;
        }

        if ($user->roleFor($tenant) !== null) {
            return true;
        }

        return Invitation::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('email', $user->email)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->whereNull('expired_at')
            ->where('expires_at', '>', now())
            ->exists();
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }

    private function configureEmailVerification(): void
    {
        VerifyEmail::createUrlUsing(function (User $user): string {
            $currentTenant = tenant();

            $tenant = $currentTenant instanceof Tenant && $user->roleFor($currentTenant) !== null
                ? $currentTenant
                : $user->tenants()->orderBy('tenants.id')->firstOrFail();

            $url = clone app(UrlGenerator::class);

            $url->useOrigin($tenant->origin());

            return $url->temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(config('auth.verification.expire', 60)),
                [
                    'id' => $user->getKey(),
                    'hash' => sha1($user->getEmailForVerification()),
                ],
            );
        });

        VerifyEmail::toMailUsing(function (User $user, string $url): MailMessage {
            return (new MailMessage)
                ->subject('Confirm your email address')
                ->action('Confirm address', $url)
                ->view(['html' => 'mail.verify-email', 'text' => 'mail.verify-email-text'], [
                    'url' => $url,
                    'email' => $user->getEmailForVerification(),
                    'expiresInMinutes' => config('auth.verification.expire', 60),
                ]);
        });
    }

    private function configurePasswordResetMail(): void
    {
        ResetPassword::toMailUsing(function (User $user, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $user->getEmailForPasswordReset(),
            ], false));

            return (new MailMessage)
                ->subject('Reset your '.config('app.name').' password')
                ->action('Set a new password', $url)
                ->view(['html' => 'mail.reset-password', 'text' => 'mail.reset-password-text'], [
                    'url' => $url,
                    'email' => $user->getEmailForPasswordReset(),
                    'expiresInMinutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
                ]);
        });
    }
}
