<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Ebay;

use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\CustomerSalesChannel;
use App\Models\Dropshipping\EbayUser;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * HELP-4933: the handling time of the postage policy we create on a seller's eBay account follows our warehouse
 * (e.g. longer over Christmas). Only our own "Shipping-<channel>" policy is changed, and only while it still has the
 * days we last gave it: a seller who picked their own policy, or changed the days on ours in eBay, keeps theirs.
 */
class SetEbayHandlingDays
{
    use AsAction;

    public string $commandSignature = 'ebay:handling_days {shop} {days} {--channel= : only this channel slug} {--dry-run}';

    /**
     * @param  int  $shopDaysBefore  the shop's default before this run; a policy created without stored days has it, or 1 from before HELP-4933
     */
    public function handle(EbayUser $ebayUser, int $days, int $shopDaysBefore, bool $dryRun = false): string
    {
        $policyId = $ebayUser->fulfillment_policy_id;
        $policy   = $ebayUser->getFulfilmentPolicy($policyId);

        if ($error = $ebayUser::ebayResponseError($policy)) {
            return 'failed: '.$error;
        }

        if (!$this->isOurPolicy((string) Arr::get($policy, 'name'), $ebayUser)) {
            return 'seller_policy';
        }

        $current = (int) Arr::get($policy, 'handlingTime.value');
        if ($current === $days) {
            return 'already';
        }

        $storedDays = Arr::get($ebayUser->settings, 'shipping.max_dispatch_time');
        $daysWeGave = filled($storedDays) ? [(int) $storedDays] : [1, $shopDaysBefore];
        if (!in_array($current, $daysWeGave, true)) {
            return 'seller_changed';
        }

        if ($dryRun) {
            return 'would_update';
        }

        data_set($policy, 'handlingTime', ['unit' => 'DAY', 'value' => $days]);
        $updated = $ebayUser->replaceFulfilmentPolicy($policyId, $this->withoutEmptyBlocks(Arr::except($policy, ['fulfillmentPolicyId', 'warnings'])));

        if ($error = $ebayUser::ebayResponseError($updated)) {
            return 'failed: '.$error;
        }

        $settings = $ebayUser->settings;
        data_set($settings, 'shipping.max_dispatch_time', (string) $days);
        UpdateEbayUser::run($ebayUser, ['settings' => $settings]);

        return 'updated';
    }

    /**
     * A seller who reconnects the same eBay account keeps the policy we created for their earlier channel, named after its slug.
     */
    private function isOurPolicy(string $name, EbayUser $ebayUser): bool
    {
        if ($name === $ebayUser->ownFulfilmentPolicyName()) {
            return true;
        }

        return str_starts_with($name, 'Shipping-') && CustomerSalesChannel::where('slug', substr($name, strlen('Shipping-')))->exists();
    }

    /**
     * eBay's {} comes back from json() as [] and is refused when sent back (PRE-102).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withoutEmptyBlocks(array $data): array
    {
        $data = array_map(fn ($value) => is_array($value) ? $this->withoutEmptyBlocks($value) : $value, $data);

        $filtered = array_filter($data, fn ($value) => $value !== []);

        return array_is_list($data) ? array_values($filtered) : $filtered;
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $shop = Shop::where('slug', $command->argument('shop'))->firstOrFail();
        $days = (int) $command->argument('days');

        if ($days < 1 || $days > 30) {
            $command->error('days must be between 1 and 30');

            return 1;
        }

        $dryRun         = (bool) $command->option('dry-run');
        $shopDaysBefore = (int) Arr::get($shop->settings, 'ebay.handling_days', EbayUser::DEFAULT_HANDLING_DAYS);

        if (!$dryRun && !$command->option('channel')) {
            $settings = $shop->settings;
            data_set($settings, 'ebay.handling_days', $days);
            $shop->update(['settings' => $settings]);
        }

        $ebayUsers = EbayUser::whereNotNull('fulfillment_policy_id')
            ->whereHas('customerSalesChannel', fn ($query) => $query->where('shop_id', $shop->id)->where('status', CustomerSalesChannelStatusEnum::OPEN)
                ->when($command->option('channel'), fn ($query, $slug) => $query->where('slug', $slug)))
            ->get();

        $counts = [];
        foreach ($ebayUsers as $ebayUser) {
            $outcome = $this->handle($ebayUser, $days, $shopDaysBefore, $dryRun);
            $key     = str_starts_with($outcome, 'failed') ? 'failed' : $outcome;
            $counts[$key] = ($counts[$key] ?? 0) + 1;

            if ($key === 'failed') {
                $command->warn($ebayUser->customerSalesChannel->slug.' '.$outcome);
            }
        }

        $command->info(collect($counts)->map(fn ($count, $outcome) => "$outcome: $count")->implode(', ') ?: 'no channels');

        return 0;
    }
}
