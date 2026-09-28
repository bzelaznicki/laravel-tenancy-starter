<?php

namespace App\Http\Middleware;

use App\Policies\MembershipPermissions;
use App\TenantRole;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return list<string>
     */
    private function assignableMembershipRoles(Request $request): array
    {
        $actorRoleValue = $request->attributes->get('tenant_role');
        $actorRole = is_string($actorRoleValue)
            ? TenantRole::tryFrom($actorRoleValue)
            : null;

        if ($actorRole === null) {
            return [];
        }

        return array_values(array_map(
            fn (TenantRole $role): string => $role->value,
            array_filter(
                TenantRole::cases(),
                fn (TenantRole $role): bool => MembershipPermissions::canInvite(
                    $actorRole,
                    $role,
                ),
            ),
        ));
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'appDomain' => config('app.domain'),
            'auth' => [
                'user' => $request->user()
                    ? [
                        'id' => $request->user()->id,
                        'name' => $request->user()->name,
                        'email' => $request->user()->email,
                        'emailVerified' => $request->user()->hasVerifiedEmail(),
                    ]
                    : null,
                'tenant' => fn () => tenant()
                    ? ['name' => tenant()->name]
                    : null,
                'role' => fn () => $request->attributes->get('tenant_role'),
                'membershipCapabilities' => [
                    'assignableRoles' => fn (): array => $this->assignableMembershipRoles($request),
                ],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
