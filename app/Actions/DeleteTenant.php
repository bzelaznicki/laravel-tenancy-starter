<?php

namespace App\Actions;

use App\Models\Tenant;

class DeleteTenant
{
    /**
     * Delete the tenant. Its domains, memberships, and invitations go with it
     * through the `tenant_id` foreign keys.
     */
    public function handle(Tenant $tenant): void
    {
        $tenant->delete();
    }
}
