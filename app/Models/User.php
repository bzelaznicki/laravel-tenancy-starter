<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use App\TenantRole;
use Carbon\CarbonInterface;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Override;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $first_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'email_lower'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUlids, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'first_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Store emails in canonical form so exact-match lookups are reliable.
     *
     * @return Attribute<string, string>
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => Str::lower(trim($value)),
        );
    }

    /**
     * Mark the email as verified and keep the first verification time, which
     * survives the reset that an email change causes.
     */
    #[Override]
    public function markEmailAsVerified(): bool
    {
        return $this->forceFill([
            'email_verified_at' => $this->freshTimestamp(),
            'first_verified_at' => $this->first_verified_at ?? $this->freshTimestamp(),
        ])->save();
    }

    /**
     * Users who signed up before the cutoff, never verified an email, and are the
     * only member (as owner) of their single tenant, which has no invitations.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function abandonedSignup(Builder $query, CarbonInterface $cutoff): void
    {
        $query->whereNull('email_verified_at')
            ->whereNull('first_verified_at')
            ->where('created_at', '<=', $cutoff)
            ->has('tenants', '=', 1)
            ->whereHas('tenants', function (Builder $tenants): void {
                $tenants->where('tenant_user.role', TenantRole::Owner->value)
                    ->has('users', '=', 1)
                    ->doesntHave('invitations');
            });
    }

    /**
     * @return BelongsToMany<Tenant, $this, Membership>
     */
    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class)
            ->using(Membership::class)
            ->withPivot('role')
            ->withTimestamps();
    }

    public function roleFor(Tenant $tenant): ?TenantRole
    {
        return Membership::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('user_id', $this->getKey())
            ->first()
            ?->role;
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function sentInvitations(): HasMany
    {
        return $this->hasMany(Invitation::class, 'invited_by');
    }
}
