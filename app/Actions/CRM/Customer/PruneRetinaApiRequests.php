<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\Customer;

use App\Models\CRM\RetinaApiRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class PruneRetinaApiRequests
{
    use AsAction;

    public const int RETENTION_DAYS = 30;

    public const int CAP_PER_CUSTOMER = 20000;

    public function handle(): void
    {
        RetinaApiRequest::where('created_at', '<', now()->subDays(self::RETENTION_DAYS))->delete();

        $this->capPerCustomer();
    }

    /**
     * A client stuck in a retry loop can write far more rows than its own history is worth,
     * so no customer keeps more than the cap regardless of the retention window.
     */
    protected function capPerCustomer(): void
    {
        // The ids are read in full first: deleting rows changes which customers are over the cap,
        // so paging through the grouped query would step over every second batch.
        $customerIds = DB::table('retina_api_requests')
            ->select('customer_id')
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) > ?', [self::CAP_PER_CUSTOMER])
            ->pluck('customer_id');

        foreach ($customerIds as $customerId) {
            $cutoffId = DB::table('retina_api_requests')
                ->where('customer_id', $customerId)
                ->orderByDesc('id')
                ->skip(self::CAP_PER_CUSTOMER)
                ->take(1)
                ->value('id');

            if ($cutoffId) {
                DB::table('retina_api_requests')
                    ->where('customer_id', $customerId)
                    ->where('id', '<=', $cutoffId)
                    ->delete();
            }
        }
    }

    public function getCommandSignature(): string
    {
        return 'maintenance:prune_retina_api_requests';
    }

    public function asCommand(Command $command): int
    {
        try {
            $this->handle();
        } catch (Throwable $e) {
            $command->error($e->getMessage());

            return 1;
        }

        return 0;
    }
}
