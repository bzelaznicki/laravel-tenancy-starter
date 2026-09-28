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
