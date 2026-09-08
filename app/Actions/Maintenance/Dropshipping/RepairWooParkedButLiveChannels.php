<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 07 Sep 2026 00:30:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\WooCommerce\PingActiveWooChannel;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\WooCommerceUser;
use Illuminate\Console\Command;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Until the channel check learned to reset the ping counter, a channel revived by hand after
 * being parked kept its parked label and was never pinged again. This asks each such channel
 * whether the store still answers and clears the label when it does; a channel that does not
 * answer is left exactly as it is, it stays on the nightly report for a person to look at.
 */
class RepairWooParkedButLiveChannels
{
    use AsAction;

    /**
     * @return array{cleared: int, still_down: int}
     */
    public function handle(bool $dryRun = false): array
    {
        $platform = Platform::where('type', PlatformTypeEnum::WOOCOMMERCE)->firstOrFail();
        $result   = ['cleared' => 0, 'still_down' => 0];

        CustomerSalesChannel::where('platform_id', $platform->id)
            ->where('status', CustomerSalesChannelStatusEnum::OPEN)
            ->where('platform_status', true)
            ->where('ping_error_count', '>=', PingActiveWooChannel::PARKED_AFTER_FAILURES)
            ->whereHasMorph('user', WooCommerceUser::class)
            ->orderBy('id')
            ->each(function (CustomerSalesChannel $customerSalesChannel) use (&$result, $dryRun) {
                if (!$customerSalesChannel->user->checkConnection()) {
                    $result['still_down']++;

                    return;
                }

                $result['cleared']++;

                if (!$dryRun) {
                    $customerSalesChannel->update(['ping_error_count' => 0]);
                }
            });

        return $result;
    }

    public function getCommandSignature(): string
    {
        return 'repair:woo_parked_but_live {--dry-run}';
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $dryRun = (bool) $command->option('dry-run');
        $result = $this->handle($dryRun);

        $command->info(($dryRun ? 'Would clear' : 'Cleared')." the parked label on {$result['cleared']} channels whose store answers, {$result['still_down']} did not answer and were left alone");

        return 0;
    }
}
