<?php

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Run migration assertions against legacy email data before the lowercase index exists.
 * Schema changes commit transactions on MySQL, so restore the index and test transaction.
 *
 * @param  list<string>  $emails
 * @param  Closure(Migration): void  $assertions
 */
function withLegacyUserEmails(array $emails, Closure $assertions): void
{
    $migration = require database_path('migrations/2026_09_23_214809_add_case_insensitive_unique_email_index_to_users_table.php');
    $connection = DB::connection();
    $connection->commit();
    $userIds = [];

    try {
        $migration->down();

        foreach ($emails as $index => $email) {
            $user = User::factory()->create(['email' => "user-{$index}@email-normalization.test"]);
            $userIds[] = $user->id;
            DB::table('users')->where('id', $user->id)->update(['email' => $email]);
        }

        $assertions($migration);
    } finally {
        DB::table('users')->whereIn('id', $userIds)->delete();

        if (! Schema::hasIndex('users', 'users_email_lower_unique')) {
            $migration->up();
        }

        $connection->beginTransaction();
    }
}

it('stores user emails lowercased and trimmed', function (): void {
    $user = User::factory()->create(['email' => ' RICHARD@PiedPiper.CoM ']);

    expect($user->fresh()->email)->toBe('richard@piedpiper.com');
});

it('stores invitation emails lowercased and trimmed', function (): void {
    $invitation = Invitation::factory()->create(['email' => ' RICHARD@PiedPiper.CoM ']);

    expect($invitation->fresh()->email)->toBe('richard@piedpiper.com');
});

it('rejects a user whose email differs from an existing one only by case at the database level', function (): void {
    User::factory()->create(['email' => 'richard@piedpiper.com']);

    expect(fn (): bool => DB::table('users')->insert(
        [
            'id' => (string) Str::ulid(),
            'email' => 'Richard@PiedPiper.com',
            'name' => 'Richard Hendricks',
            'password' => 'SecurePassword123!',
        ]
    ))->toThrow(UniqueConstraintViolationException::class);
});

it('rejects an email update that differs from another user only by case at the database level', function (): void {
    User::factory()->create(['email' => 'richard@piedpiper.com']);
    $user = User::factory()->create(['email' => 'jared@piedpiper.com']);

    expect(fn (): int => DB::table('users')->where('id', $user->id)->update([
        'email' => 'RICHARD@PiedPiper.com',
    ]))->toThrow(UniqueConstraintViolationException::class);
});

it('keeps accented email addresses distinct', function (): void {
    User::factory()->create(['email' => 'rene@piedpiper.com']);

    $user = User::factory()->create(['email' => 'rené@piedpiper.com']);

    $this->assertModelExists($user);
    $this->assertDatabaseCount('users', 2);
});

it('hides generated email index columns when serializing users', function (): void {
    $user = User::factory()->create()->refresh();

    expect($user->toArray())->not->toHaveKey('email_lower');
});

it('reports every colliding normalized email without changing existing emails', function (): void {
    $emails = [
        'Richard@email-normalization.test',
        'RICHARD@email-normalization.test',
        'Gilfoyle@email-normalization.test',
        'GILFOYLE@email-normalization.test',
        'Monica@email-normalization.test',
    ];

    withLegacyUserEmails($emails, function (Migration $migration) use ($emails): void {
        expect(fn () => $migration->up())->toThrow(
            RuntimeException::class,
            'Cannot lowercase users.email; these values collide: gilfoyle@email-normalization.test, richard@email-normalization.test',
        );

        foreach ($emails as $email) {
            $this->assertDatabaseHas('users', ['email' => $email]);
        }

        $this->assertDatabaseCount('users', 5);
    });
});

it('lowercases noncolliding legacy emails and enforces case-insensitive uniqueness', function (): void {
    withLegacyUserEmails([
        'Richard@email-normalization.test',
        'MONICA@email-normalization.test',
    ], function (Migration $migration): void {
        $migration->up();

        $this->assertDatabaseHas('users', ['email' => 'richard@email-normalization.test']);
        $this->assertDatabaseHas('users', ['email' => 'monica@email-normalization.test']);

        expect(fn (): bool => DB::table('users')->insert([
            'id' => (string) Str::ulid(),
            'email' => 'RICHARD@email-normalization.test',
            'name' => 'Richard Hendricks',
            'password' => 'SecurePassword123!',
        ]))->toThrow(UniqueConstraintViolationException::class);
    });
});
