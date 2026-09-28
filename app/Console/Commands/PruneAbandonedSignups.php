<?php

namespace App\Console\Commands;

use App\Actions\DeleteTenant;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('signups:prune-abandoned')]
#[Description('Delete unverified signups older than the configured age, with their tenant, and free their subdomain')]
class PruneAbandonedSignups extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DeleteTenant $deleteTenant): int
    {
        $cutoff = now()->subDays((int) config('signups.prune_unverified_after_days'));
        $pruned = 0;

        User::query()->abandonedSignup($cutoff)->eachById(function (User $candidate) use ($cutoff, $deleteTenant, &$pruned): void {
            if ($this->prune($candidate, $cutoff, $deleteTenant)) {
                $pruned++;
            }
        });

        $this->info("Pruned {$pruned} abandoned signup(s).");

        return self::SUCCESS;
    }

    /**
     * Delete the signup tenant, then the user. Domains, memberships, and invitations
     * cascade. The user row is locked and checked again, so a user who verifies
     * while the command runs is kept.
     */
    private function prune(User $candidate, CarbonInterface $cutoff, DeleteTenant $deleteTenant): bool
    {
        return DB::transaction(function () use ($candidate, $cutoff, $deleteTenant): bool {
            $user = User::query()
                ->abandonedSignup($cutoff)
                ->whereKey($candidate->getKey())
                ->lockForUpdate()
                ->first();

            if ($user === null) {
                return false;
            }

            $deleteTenant->handle($user->tenants()->sole());
            $user->delete();

            return true;
        });
    }
}
