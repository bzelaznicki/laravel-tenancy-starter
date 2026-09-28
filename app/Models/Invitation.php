<?php

namespace App\Models;

use App\TenantRole;
use Carbon\CarbonInterface;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $invited_by
 * @property string $email
 * @property TenantRole $role
 * @property string|null $message
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 * @property Carbon|null $expired_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant $tenant
 * @property-read User|null $inviter
 * @property Carbon|null $last_sent_at
 */
#[Fillable([
    'tenant_id',
    'invited_by',
    'email',
    'role',
    'message',
    'token_hash',
    'expires_at',
    'accepted_at',
    'revoked_at',
    'expired_at',
    'last_sent_at',
])]
#[Hidden(['token_hash'])]
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory, HasUlids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected function casts(): array
    {
        return [
            'role' => TenantRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'expired_at' => 'datetime',
            'last_sent_at' => 'datetime',
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

    public function resendAvailableAt(): ?CarbonInterface
    {
        return $this->last_sent_at?->copy()->addSeconds(
            (int) config('invitations.resend_cooldown_seconds'),
        );
    }

    /**
     * An invitation is pending while none of its closing timestamps are set.
     * Expiry is deliberately not checked here: an overdue-but-unmarked row is
     * still open as far as the one-invitation-per-tenant-email index cares.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->whereNull('expired_at');
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function forTenant(Builder $query, string $tenantId): void
    {
        $query->where('tenant_id', $tenantId);
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
