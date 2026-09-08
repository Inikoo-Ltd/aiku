<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Sep 2026 12:50:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\Dropshipping;

use App\Actions\Dropshipping\Ebay\MergeEbayChannelInto;
use App\Actions\Dropshipping\Ebay\ReconnectEbayChannel;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Enums\Dropshipping\EbayUserStepEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use App\Models\Dropshipping\Platform;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairEbayDuplicateChannels
{
    use AsAction;

    /**
     * Groups a customer's open eBay channels by the eBay account behind them and folds every extra channel
     * into the one that holds the most, leaving channels with orders untouched for a human to look at.
     *
     * @param  Collection<int, CustomerSalesChannel>  $channels  open eBay channels of one customer, identity already stored
     * @return array<int, array{keep: string, extra: string, action: string, portfolios: int, clients: int}>
     */
    public function handle(Collection $channels, bool $dryRun = false): array
    {
        $rows = [];

        foreach ($channels as $channel) {
            if (!($channel->user instanceof EbayUser) || !Arr::get($channel->user->data, 'ebay_user.userId')) {
                $rows[] = ['keep' => '', 'extra' => $channel->slug, 'action' => 'skipped: no ebay identity, token dead?', 'portfolios' => 0, 'clients' => 0];
            }
        }

        $groups = $channels
            ->filter(fn (CustomerSalesChannel $channel) => $channel->user instanceof EbayUser && Arr::get($channel->user->data, 'ebay_user.userId'))
            ->groupBy(fn (CustomerSalesChannel $channel) => Arr::get($channel->user->data, 'ebay_user.userId'))
            ->filter(fn (Collection $group) => $group->count() > 1);

        foreach ($groups as $group) {
            $ranked = $group->sortBy([
                fn (CustomerSalesChannel $a, CustomerSalesChannel $b) => $b->number_orders <=> $a->number_orders,
                fn (CustomerSalesChannel $a, CustomerSalesChannel $b) => $b->number_portfolios <=> $a->number_portfolios,
                fn (CustomerSalesChannel $a, CustomerSalesChannel $b) => $a->id <=> $b->id,
            ])->values();

            $keep = $ranked->first();

            foreach ($ranked->slice(1) as $extra) {
                if ($extra->number_orders > 0) {
                    $rows[] = ['keep' => $keep->slug, 'extra' => $extra->slug, 'action' => 'skipped: has orders', 'portfolios' => 0, 'clients' => 0];

                    continue;
                }

                $moved  = MergeEbayChannelInto::run($extra, $keep, $dryRun);
                $rows[] = ['keep' => $keep->slug, 'extra' => $extra->slug, 'action' => $dryRun ? 'would merge' : 'merged', ...$moved];
            }
        }

        return $rows;
    }

    public function getCommandSignature(): string
    {
        return 'repair:ebay_duplicate_channels {customer? : customer slug; omitted = every customer with more than one open eBay channel} {--dry-run}';
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        $dryRun = (bool) $command->option('dry-run');
        $ebay   = Platform::where('type', PlatformTypeEnum::EBAY)->firstOrFail();

        $query = CustomerSalesChannel::where('platform_id', $ebay->id)
            ->where('status', CustomerSalesChannelStatusEnum::OPEN)
            ->whereHasMorph('user', EbayUser::class, fn ($q) => $q->where('step', EbayUserStepEnum::COMPLETED));

        if ($slug = $command->argument('customer')) {
            $query->whereHas('customer', fn ($q) => $q->where('slug', $slug));
        } else {
            $query->whereIn('customer_id', function ($sub) use ($ebay) {
                $sub->from('customer_sales_channels')
                    ->select('customer_id')
                    ->where('platform_id', $ebay->id)
                    ->where('status', CustomerSalesChannelStatusEnum::OPEN->value)
                    ->whereNull('deleted_at')
                    ->groupBy('customer_id')
                    ->havingRaw('count(*) > 1');
            });
        }

        $channels = $query->orderBy('customer_id')->orderBy('id')->get();

        $command->info("Checking {$channels->count()} open eBay channels");

        $rows = [];
        foreach ($channels->groupBy('customer_id') as $customerChannels) {
            foreach ($customerChannels as $channel) {
                if (!Arr::get($channel->user->data, 'ebay_user.userId')) {
                    ReconnectEbayChannel::storeIdentity($channel->user);
                    $channel->user->refresh();
                }
            }

            $rows = array_merge($rows, $this->handle($customerChannels, $dryRun));
        }

        $command->table(['keep', 'extra', 'action', 'portfolios', 'clients'], $rows);

        $merged  = count(array_filter($rows, fn ($row) => !str_starts_with($row['action'], 'skipped')));
        $skipped = count($rows) - $merged;
        $command->info(($dryRun ? 'Would merge ' : 'Merged ')."{$merged} extra channels, {$skipped} skipped (orders or no identity)");

        return 0;
    }
}
