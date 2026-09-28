<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Tenant;
use App\Models\User;
use App\TenantRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array{name?: string, email?: string, password?: string, password_confirmation?: string, organization?: string, subdomain?: mixed}  $input
     */
    public function create(array $input): User
    {
        if (is_string($input['subdomain'] ?? null)) {
            $input['subdomain'] = Str::lower(trim($input['subdomain']));
        }

        if (is_string($input['email'] ?? null)) {
            $input['email'] = Str::lower(trim($input['email']));
        }

        $validated = Validator::make(
            $input,
            [
                ...$this->profileRules(),
                'password' => $this->passwordRules(),
                'organization' => ['required', 'max:255', 'string'],
                'subdomain' => [
                    'required',
                    'string',
                    'max:63',
                    'regex:/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/',
                    Rule::notIn(config('tenancy.reserved_subdomains')),
                    Rule::unique('tenants', 'slug'),
                    Rule::unique('domains', 'domain'),
                ],
            ],
            [
                'email.unique' => 'An account with this email already exists. Log in or accept your invitation instead.',
            ]
        )->validate();

        return DB::transaction(function () use ($validated): User {

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            $tenant = Tenant::create([
                'name' => $validated['organization'],
                'slug' => $validated['subdomain'],
            ]);

            $tenant->domains()->create([
                'domain' => $validated['subdomain'],
            ]);

            $tenant->users()->attach($user, [
                'role' => TenantRole::Owner->value,
            ]);

            return $user;
        });
    }
}
