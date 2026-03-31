<?php

namespace App\Actions\CRM\Customer;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class PruneCustomerWebActivities
{
    use AsAction;

    public const int CAP = 500;

    public function handle(): void
    {
        DB::table('customer_web_activities')
            ->select('customer_id')
            ->groupBy('customer_id')
            ->havingRaw('COUNT(*) > ?', [self::CAP])
            ->orderBy('customer_id')
            ->chunk(200, function ($customers) {
                foreach ($customers as $customer) {
                    $cutoffId = DB::table('customer_web_activities')
                        ->where('customer_id', $customer->customer_id)
                        ->orderByDesc('id')
                        ->skip(self::CAP)
                        ->take(1)
                        ->value('id');

                    if ($cutoffId) {
                        DB::table('customer_web_activities')
                            ->where('customer_id', $customer->customer_id)
                            ->where('id', '<=', $cutoffId)
                            ->delete();
                    }
                }
            });
    }

    public function getCommandSignature(): string
    {
        return 'maintenance:prune_customer_web_activities';
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
