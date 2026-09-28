<?php

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class TenantOwnedRecord extends Model
{
    use BelongsToTenant, HasUlids;

    protected $table = 'tenant_owned_records';

    protected $fillable = [
        'name',
    ];
}

beforeEach(function (): void {
    Schema::create('tenant_owned_records', function (Blueprint $table): void {
        $table->ulid('id')->primary();

        $table->foreignUlid('tenant_id')->constrained()->cascadeOnDelete();
        $table->string('name');
        $table->timestamps();
    });
});

afterEach(function (): void {
    if (tenancy()->initialized) {
        tenancy()->end();
    }
});

it('automatically assigns the current tenant when creating a record', function (): void {
    $tenant = Tenant::factory()->create();

    tenancy()->initialize($tenant);

    $record = TenantOwnedRecord::create([
        'name' => 'Tenant record',
    ]);

    expect($record->tenant_id)->toBe($tenant->getKey());
});

it('only returns records belonging to the current tenant', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    tenancy()->initialize($tenantA);

    TenantOwnedRecord::create([
        'name' => 'Tenant A record',
    ]);

    tenancy()->initialize($tenantB);

    TenantOwnedRecord::create([
        'name' => 'Tenant B record',
    ]);

    expect(TenantOwnedRecord::query()->pluck('name')->all())->toBe(['Tenant B record']);
});

it("cannot find another tenant's record through a scoped query", function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    tenancy()->initialize($tenantB);

    $tenantBRecord = TenantOwnedRecord::create([
        'name' => 'Tenant B record',
    ]);

    tenancy()->initialize($tenantA);

    expect(TenantOwnedRecord::find($tenantBRecord->getKey()))->toBeNull();
});

it('changes the query scope when the current tenant changes', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    tenancy()->initialize($tenantA);

    TenantOwnedRecord::create([
        'name' => 'Tenant A record',
    ]);

    tenancy()->initialize($tenantB);

    TenantOwnedRecord::create([
        'name' => 'Tenant B record',
    ]);

    tenancy()->initialize($tenantA);
    expect(TenantOwnedRecord::query()->sole()->name)->toBe('Tenant A record');

    tenancy()->initialize($tenantB);
    expect(TenantOwnedRecord::query()->sole()->name)->toBe('Tenant B record');
});

it('provides a relationship to the owning tenant', function (): void {
    $tenant = Tenant::factory()->create();

    tenancy()->initialize($tenant);

    $record = TenantOwnedRecord::create([
        'name' => 'Tenant record',
    ]);

    expect($record->tenant->is($tenant))->toBeTrue();
});

it('allows the tenant scope to be removed explicitly', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    tenancy()->initialize($tenantA);

    TenantOwnedRecord::create([
        'name' => 'Tenant A record',
    ]);

    tenancy()->initialize($tenantB);

    TenantOwnedRecord::create([
        'name' => 'Tenant B record',
    ]);

    $recordNames = TenantOwnedRecord::query()->withoutTenancy()->orderBy('name')->pluck('name')->all();

    expect($recordNames)->toBe([
        'Tenant A record',
        'Tenant B record',
    ]);
});
