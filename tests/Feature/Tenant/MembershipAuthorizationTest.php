<?php

use App\Models\Membership;
use App\Models\Tenant;
use App\Models\User;
use App\TenantRole;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

describe('membership authorization matrix', function (): void {
    it('allows owners to invite every tenant role', function (TenantRole $intendedRole): void {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->withMember($actor)->create();

        expect($actor->can('invite', [Membership::class, $tenant, $intendedRole]))->toBeTrue();
    })->with(TenantRole::cases());

    it('allows owners to change every tenant role', function (TenantRole $currentRole, TenantRole $intendedRole): void {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $tenant = Tenant::factory()
            ->withMember($actor)
            ->withMember($target, $currentRole)
            ->create();
        $membership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        expect($actor->can('update', [$membership, $intendedRole]))->toBeTrue();
    })->with(TenantRole::cases(), TenantRole::cases());

    it('allows owners to remove every tenant role', function (TenantRole $currentRole): void {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $tenant = Tenant::factory()
            ->withMember($actor)
            ->withMember($target, $currentRole)
            ->create();
        $membership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        expect($actor->can('delete', $membership))->toBeTrue();
    })->with(TenantRole::cases());

    it('allows admins to invite member and viewer roles', function (TenantRole $intendedRole): void {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->withMember($actor, TenantRole::Admin)->create();

        expect($actor->can('invite', [Membership::class, $tenant, $intendedRole]))->toBeTrue();
    })->with([
        TenantRole::Member,
        TenantRole::Viewer,
    ]);

    it('allows admins to change member and viewer memberships', function (TenantRole $currentRole, TenantRole $intendedRole): void {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $tenant = Tenant::factory()
            ->withMember($actor, TenantRole::Admin)
            ->withMember($target, $currentRole)
            ->create();
        $membership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        expect($actor->can('update', [$membership, $intendedRole]))->toBeTrue();
    })->with([
        TenantRole::Member,
        TenantRole::Viewer,
    ], [
        TenantRole::Member,
        TenantRole::Viewer,
    ]);

    it('allows admins to remove member and viewer memberships', function (TenantRole $currentRole): void {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $tenant = Tenant::factory()
            ->withMember($actor, TenantRole::Admin)
            ->withMember($target, $currentRole)
            ->create();
        $membership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        expect($actor->can('delete', $membership))->toBeTrue();
    })->with([
        TenantRole::Member,
        TenantRole::Viewer,
    ]);

    it('prevents admins from assigning owner and admin roles', function (TenantRole $intendedRole): void {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->withMember($actor, TenantRole::Admin)->create();

        expect($actor->can('invite', [Membership::class, $tenant, $intendedRole]))->toBeFalse();
    })->with([
        TenantRole::Owner,
        TenantRole::Admin,
    ]);

    it('prevents admins from changing owner and admin memberships', function (TenantRole $currentRole, TenantRole $intendedRole): void {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $tenant = Tenant::factory()
            ->withMember($actor, TenantRole::Admin)
            ->withMember($target, $currentRole)
            ->create();
        $membership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        expect($actor->can('update', [$membership, $intendedRole]))->toBeFalse();
    })->with([
        TenantRole::Owner,
        TenantRole::Admin,
    ], TenantRole::cases());

    it('prevents admins from promoting member and viewer memberships', function (TenantRole $currentRole, TenantRole $intendedRole): void {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $tenant = Tenant::factory()
            ->withMember($actor, TenantRole::Admin)
            ->withMember($target, $currentRole)
            ->create();
        $membership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        expect($actor->can('update', [$membership, $intendedRole]))->toBeFalse();
    })->with([
        TenantRole::Member,
        TenantRole::Viewer,
    ], [
        TenantRole::Owner,
        TenantRole::Admin,
    ]);

    it('prevents admins from removing owner and admin memberships', function (TenantRole $currentRole): void {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $tenant = Tenant::factory()
            ->withMember($actor, TenantRole::Admin)
            ->withMember($target, $currentRole)
            ->create();
        $membership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        expect($actor->can('delete', $membership))->toBeFalse();
    })->with([
        TenantRole::Owner,
        TenantRole::Admin,
    ]);

    it('prevents members from managing memberships', function (TenantRole $currentRole, TenantRole $intendedRole): void {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $tenant = Tenant::factory()
            ->withMember($actor, TenantRole::Member)
            ->withMember($target, $currentRole)
            ->create();
        $membership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        expect($actor->can('invite', [Membership::class, $tenant, $intendedRole]))->toBeFalse()
            ->and($actor->can('update', [$membership, $intendedRole]))->toBeFalse()
            ->and($actor->can('delete', $membership))->toBeFalse();
    })->with(TenantRole::cases(), TenantRole::cases());

    it('prevents viewers from managing memberships', function (TenantRole $currentRole, TenantRole $intendedRole): void {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $tenant = Tenant::factory()
            ->withMember($actor, TenantRole::Viewer)
            ->withMember($target, $currentRole)
            ->create();
        $membership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        expect($actor->can('invite', [Membership::class, $tenant, $intendedRole]))->toBeFalse()
            ->and($actor->can('update', [$membership, $intendedRole]))->toBeFalse()
            ->and($actor->can('delete', $membership))->toBeFalse();
    })->with(TenantRole::cases(), TenantRole::cases());
});

describe('final owner protection', function (): void {
    it('prevents removing the final owner', function (): void {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->withMember($actor, TenantRole::Owner)->create();

        $membership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $actor->id)
            ->firstOrFail();

        expect($actor->can('delete', $membership))->toBeFalse();
    });
    it('prevents demoting the final owner', function (TenantRole $targetRole): void {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->withMember($actor, TenantRole::Owner)->create();

        $membership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $actor->id)
            ->firstOrFail();

        expect($actor->can('update', [$membership, $targetRole]))->toBeFalse();
    })->with([
        TenantRole::Admin,
        TenantRole::Member,
        TenantRole::Viewer,
    ]);
    it('allows removing an owner when another owner remains', function (): void {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $tenant = Tenant::factory()->withMember($actor, TenantRole::Owner)->withMember($target, TenantRole::Owner)->create();

        $targetMembership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        expect($actor->can('delete', $targetMembership))->toBeTrue();
    });
    it('allows demoting an owner when another owner remains', function (TenantRole $targetRole): void {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $tenant = Tenant::factory()->withMember($actor, TenantRole::Owner)->withMember($target, TenantRole::Owner)->create();

        $targetMembership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        expect($actor->can('update', [$targetMembership, $targetRole]))->toBeTrue();
    })->with([
        TenantRole::Admin,
        TenantRole::Member,
        TenantRole::Viewer,
    ]);

    it('allows the final owner to retain the owner role', function (): void {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->withMember($actor, TenantRole::Owner)->create();

        $membership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $actor->id)
            ->firstOrFail();

        expect($actor->can('update', [$membership, TenantRole::Owner]))
            ->toBeTrue();
    });
});

describe('membership role changes', function (): void {
    it('allows an owner to change a membership role', function (TenantRole $targetRole): void {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $tenant = Tenant::factory()->withDomain()->withMember($actor, TenantRole::Owner)->withMember($target, TenantRole::Member)->create();

        $this->actingAs($actor);

        $targetMembership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        $response = $this->patch(
            tenantRoute($tenant, 'memberships.update', [
                'membership' => $targetMembership,
            ]),
            ['role' => $targetRole->value],
        );

        $response->assertRedirect();

        $currentMembership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        expect($currentMembership->role)->toBe($targetRole);
    })->with(TenantRole::cases());
});

describe('member removal', function (): void {
    it('allows an owner to remove a member', function (): void {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $tenant = Tenant::factory()->withDomain()->withMember($actor, TenantRole::Owner)->withMember($target, TenantRole::Member)->create();

        $this->actingAs($actor);

        $targetMembership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        $response = $this->delete(tenantRoute($tenant, 'memberships.destroy', ['membership' => $targetMembership]));

        $response->assertRedirect();

        assertDatabaseMissing('tenant_user', ['user_id' => $target->id, 'tenant_id' => $tenant->id]);
        assertDatabaseHas('tenant_user', ['user_id' => $actor->id, 'tenant_id' => $tenant->id]);
    });
});

describe('members page', function (): void {
    it('returns tenant memberships with policy-derived capabilities', function (): void {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->withDomain()->withMember($actor, TenantRole::Owner)->create();

        $membership = Membership::query()
            ->where('user_id', $actor->id)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $this->actingAs($actor);

        $response = $this->get(tenantRoute($tenant, 'memberships.index'));

        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('settings/members')
                ->has('memberships', 1)
                ->has('pendingInvitations', 0)
                ->where(
                    'memberships.0',
                    function (Collection $membership): bool {
                        return $membership->keys()->sort()->values()->all() === [
                            'capabilities',
                            'id',
                            'joinedAt',
                            'role',
                            'user',
                        ];
                    },
                )
                ->where(
                    'memberships.0.user',
                    function (Collection $user): bool {
                        return $user->keys()->sort()->values()->all() === [
                            'email',
                            'id',
                            'name',
                        ];
                    },
                )
                ->where(
                    'memberships.0.capabilities',
                    function (Collection $capabilities): bool {
                        return $capabilities->keys()->sort()->values()->all() === [
                            'assignableRoles',
                            'canDelete',
                        ];
                    },
                )
                ->where('memberships.0.id', $membership->id)
                ->where('memberships.0.role', TenantRole::Owner->value)
                ->where('memberships.0.user.id', $actor->id)
                ->where(
                    'memberships.0.capabilities.assignableRoles',
                    [TenantRole::Owner->value],
                )
                ->where('memberships.0.capabilities.canDelete', false)
        );
    });

    it('exposes full per-membership capabilities to owners', function (): void {
        $actor = User::factory()->create();
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $regularMember = User::factory()->create();
        $viewer = User::factory()->create();

        $tenant = Tenant::factory()
            ->withDomain()
            ->withMember($actor, TenantRole::Owner)
            ->withMember($owner, TenantRole::Owner)
            ->withMember($admin, TenantRole::Admin)
            ->withMember($regularMember, TenantRole::Member)
            ->withMember($viewer, TenantRole::Viewer)
            ->create();

        $this->actingAs($actor);

        $expectedRoles = array_map(
            fn (TenantRole $role): string => $role->value,
            TenantRole::cases()
        );

        $response = $this->get(
            tenantRoute($tenant, 'memberships.index'),
        );

        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('settings/members')
                ->has('memberships', 5)
                ->where(
                    'memberships',
                    function (Collection $memberships) use ($expectedRoles): bool {
                        return $memberships->every(
                            fn (array $membership): bool => data_get(
                                $membership,
                                'capabilities.assignableRoles',
                            ) === $expectedRoles
                                && data_get(
                                    $membership,
                                    'capabilities.canDelete',
                                ) === true,
                        );
                    },
                )
        );
    });

    it('exposes limited per-membership capabilities to admins', function (): void {
        $actor = User::factory()->create();
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $regularMember = User::factory()->create();
        $viewer = User::factory()->create();

        $tenant = Tenant::factory()
            ->withDomain()
            ->withMember($actor, TenantRole::Admin)
            ->withMember($owner, TenantRole::Owner)
            ->withMember($admin, TenantRole::Admin)
            ->withMember($regularMember, TenantRole::Member)
            ->withMember($viewer, TenantRole::Viewer)
            ->create();

        $this->actingAs($actor);

        $expectedRoles = array_values(
            array_map(
                fn (TenantRole $role): string => $role->value,
                array_filter(
                    TenantRole::cases(),
                    fn (TenantRole $role): bool => $role->level() < TenantRole::Admin->level(),
                ),
            ),
        );

        $response = $this->get(
            tenantRoute($tenant, 'memberships.index'),
        );

        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('settings/members')
                ->has('memberships', 5)
                ->where(
                    'memberships',
                    function (Collection $memberships) use ($expectedRoles): bool {
                        return $memberships->every(
                            function (array $membership) use ($expectedRoles): bool {
                                $isManageable = in_array(
                                    data_get($membership, 'role'),
                                    [
                                        TenantRole::Member->value,
                                        TenantRole::Viewer->value,
                                    ],
                                    true,
                                );

                                return data_get(
                                    $membership,
                                    'capabilities.assignableRoles',
                                ) === ($isManageable ? $expectedRoles : [])
                                    && data_get(
                                        $membership,
                                        'capabilities.canDelete',
                                    ) === $isManageable;
                            },
                        );
                    },
                )
        );
    });

    it(
        'does not expose per-membership capabilities to non-managers',
        function (TenantRole $actorRole): void {
            $actor = User::factory()->create();
            $owner = User::factory()->create();
            $admin = User::factory()->create();
            $regularMember = User::factory()->create();
            $viewer = User::factory()->create();

            $tenant = Tenant::factory()
                ->withDomain()
                ->withMember($actor, $actorRole)
                ->withMember($owner, TenantRole::Owner)
                ->withMember($admin, TenantRole::Admin)
                ->withMember($regularMember, TenantRole::Member)
                ->withMember($viewer, TenantRole::Viewer)
                ->create();

            $this->actingAs($actor);
            $response = $this->get(
                tenantRoute($tenant, 'memberships.index'),
            );

            $response->assertInertia(
                fn (Assert $page) => $page
                    ->component('settings/members')
                    ->has('memberships', 5)
                    ->where(
                        'memberships',
                        function (Collection $memberships): bool {
                            return $memberships->every(
                                fn (array $membership): bool => data_get(
                                    $membership,
                                    'capabilities.assignableRoles',
                                ) === []
                                    && data_get(
                                        $membership,
                                        'capabilities.canDelete',
                                    ) === false,
                            );
                        },
                    )
            );
        },
    )->with([
        TenantRole::Member,
        TenantRole::Viewer,
    ]);
});

describe('tenant isolation', function (): void {
    it('does not expose memberships belonging to another tenant', function (): void {
        $actor = User::factory()->create();
        $actorNeighbor = User::factory()->create();
        $target = User::factory()->create();
        $actorTenant = Tenant::factory()
            ->withDomain()
            ->withMember($actor, TenantRole::Owner)
            ->withMember($actorNeighbor, TenantRole::Member)
            ->create();
        Tenant::factory()
            ->withMember($target)
            ->create();

        $this->actingAs($actor);

        $response = $this->get(tenantRoute($actorTenant, 'memberships.index'));

        $response->assertInertia(
            fn (Assert $page) => $page
                ->component('settings/members')
                ->has('memberships', 2)
                ->where(
                    'memberships',
                    function (Collection $memberships) use (
                        $actor,
                        $actorNeighbor,
                        $target,
                    ): bool {
                        $userIds = $memberships->pluck('user.id');

                        return $userIds->contains($actor->id)
                            && $userIds->contains($actorNeighbor->id)
                            && ! $userIds->contains($target->id);
                    },
                ),
        );
    });
    it('does not change the role of a membership belonging to another tenant', function (TenantRole $targetRole): void {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $actorTenant = Tenant::factory()->withDomain()->withMember($actor, TenantRole::Owner)->create();
        $targetTenant = Tenant::factory()->withMember($target, TenantRole::Member)->create();

        $this->actingAs($actor);

        $targetMembership = Membership::query()
            ->where('tenant_id', $targetTenant->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        $response = $this->patch(
            tenantRoute($actorTenant, 'memberships.update', [
                'membership' => $targetMembership,
            ]),
            ['role' => $targetRole->value],
        );

        $response->assertNotFound();

        $currentMembership = Membership::query()
            ->where('tenant_id', $targetTenant->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        expect($currentMembership->role)->toBe(TenantRole::Member);
    })->with(TenantRole::cases());
    it('does not remove a membership belonging to another tenant', function (): void {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $actorTenant = Tenant::factory()->withDomain()->withMember($actor, TenantRole::Owner)->create();
        $targetTenant = Tenant::factory()->withMember($target, TenantRole::Member)->create();

        $this->actingAs($actor);

        $targetMembership = Membership::query()
            ->where('tenant_id', $targetTenant->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        $response = $this->delete(tenantRoute($actorTenant, 'memberships.destroy', ['membership' => $targetMembership]));

        $response->assertNotFound();
        $currentMembership = Membership::query()
            ->where('tenant_id', $targetTenant->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        expect($currentMembership->role)->toBe(TenantRole::Member);
    });
});

describe('Inertia authorization capabilities', function (): void {
    it('exposes membership management capabilities to owners', function (): void {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->withDomain()->withMember($actor)->create();
        $this->actingAs($actor);

        $response = $this->get(tenantRoute($tenant, 'dashboard'));

        $response->assertInertia(
            fn (Assert $page) => $page->where(
                'auth.membershipCapabilities.assignableRoles',
                array_map(
                    fn (TenantRole $role): string => $role->value,
                    TenantRole::cases()
                )
            )
        );
    });
    it('exposes limited membership management capabilities to admins', function (): void {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->withDomain()->withMember($actor, TenantRole::Admin)->create();
        $this->actingAs($actor);

        $response = $this->get(tenantRoute($tenant, 'dashboard'));

        $response->assertInertia(
            fn (Assert $page) => $page->where(
                'auth.membershipCapabilities.assignableRoles',
                array_values(array_map(
                    fn (TenantRole $role): string => $role->value,
                    array_filter(
                        TenantRole::cases(),
                        fn (TenantRole $role): bool => $role->level() < TenantRole::Admin->level(),
                    ),
                ))
            )
        );
    });
    it('does not expose membership management capabilities to members', function (): void {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->withDomain()->withMember($actor, TenantRole::Member)->create();
        $this->actingAs($actor);

        $response = $this->get(tenantRoute($tenant, 'dashboard'));

        $response->assertInertia(
            fn (Assert $page) => $page->where(
                'auth.membershipCapabilities.assignableRoles',
                []
            )
        );
    });
    it('does not expose membership management capabilities to viewers', function (): void {
        $actor = User::factory()->create();
        $tenant = Tenant::factory()->withDomain()->withMember($actor, TenantRole::Viewer)->create();
        $this->actingAs($actor);

        $response = $this->get(tenantRoute($tenant, 'dashboard'));

        $response->assertInertia(
            fn (Assert $page) => $page->where(
                'auth.membershipCapabilities.assignableRoles',
                []
            )
        );
    });
});

describe('direct HTTP authorization', function (): void {
    it('rejects an unauthorized role change request', function (TenantRole $actorRole, TenantRole $currentRole, TenantRole $targetRole): void {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $tenant = Tenant::factory()->withDomain()->withMember($actor, $actorRole)->withMember($target, $currentRole)->create();

        $this->actingAs($actor);

        $targetMembership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        $response = $this->patch(
            tenantRoute(
                $tenant,
                'memberships.update',
                ['membership' => $targetMembership]
            ),
            ['role' => $targetRole->value]
        );
        $response->assertForbidden();

        $currentMembership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        expect($currentMembership->role)->toBe($currentRole);
    })->with([TenantRole::Member, TenantRole::Viewer], TenantRole::cases(), TenantRole::cases());
    it('rejects an unauthorized member removal request', function (TenantRole $actorRole, TenantRole $targetRole): void {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $tenant = Tenant::factory()->withDomain()->withMember($actor, $actorRole)->withMember($target, $targetRole)->create();

        $this->actingAs($actor);

        $targetMembership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        $response = $this->delete(
            tenantRoute(
                $tenant,
                'memberships.destroy',
                ['membership' => $targetMembership],
            )
        );

        $response->assertForbidden();

        $currentMembership = Membership::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $target->id)
            ->firstOrFail();

        expect($currentMembership->role)->toBe($targetRole);
    })->with([TenantRole::Member, TenantRole::Viewer], TenantRole::cases());
});
