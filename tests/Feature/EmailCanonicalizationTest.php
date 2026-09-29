<?php

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
