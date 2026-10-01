<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 30 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Dispatching\DeliveryNote\UI;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Procurement\OrgPartner;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\QueryBuilder;

trait WithDeliveryNotesChannel
{
    public const string PARTNERS_CHANNEL = 'partners';

    protected function channelShopType(string $channel): string
    {
        return $channel === self::PARTNERS_CHANNEL ? ShopTypeEnum::B2B->value : $channel;
    }

    protected function whereDeliveryNotesChannel(Builder|QueryBuilder $queryWithShopsJoined, string $channel): void
    {
        if ($channel === 'all') {
            return;
        }

        $queryWithShopsJoined->where('shops.type', $this->channelShopType($channel));
        $this->whereDeliveryNotesPartnership($queryWithShopsJoined, $channel);
    }

    protected function whereDeliveryNotesPartnership(Builder|QueryBuilder $query, string $channel): void
    {
        $partnerCustomers = fn ($subQuery) => $subQuery->select(DB::raw(1))
            ->from('org_partners')
            ->whereColumn('org_partners.customer_id', 'delivery_notes.customer_id');

        if ($channel === self::PARTNERS_CHANNEL) {
            $query->whereExists($partnerCustomers);
        } elseif ($channel === ShopTypeEnum::B2B->value) {
            $query->whereNotExists($partnerCustomers);
        }
    }

    protected function hasPartnerCustomers(Organisation $organisation): bool
    {
        return OrgPartner::where('partner_id', $organisation->id)
            ->whereNotNull('customer_id')
            ->exists();
    }

    /**
     * @return array<string, int>
     */
    protected function partnerDeliveryNotesStateCounts(Organisation $organisation): array
    {
        $partnerCustomerIds = OrgPartner::where('partner_id', $organisation->id)
            ->whereNotNull('customer_id')
            ->pluck('customer_id');

        if ($partnerCustomerIds->isEmpty()) {
            return [];
        }

        return DeliveryNote::query()
            ->join('shops', 'delivery_notes.shop_id', '=', 'shops.id')
            ->where('delivery_notes.organisation_id', $organisation->id)
            ->where('shops.type', ShopTypeEnum::B2B)
            ->where('shops.is_aiku', true)
            ->where('delivery_notes.handled_in_aurora', false)
            ->whereIn('delivery_notes.customer_id', $partnerCustomerIds)
            ->toBase()
            ->groupBy('delivery_notes.state')
            ->selectRaw('delivery_notes.state, count(*) as number_delivery_notes')
            ->pluck('number_delivery_notes', 'state')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * @param  array<string, int>|null  $partnerStateCounts
     */
    protected function channelDeliveryNotesCount(Organisation $organisation, string $channel, ?string $state = null, ?array $partnerStateCounts = null): int
    {
        $statsSuffix = $state ? '_state_'.$state : '';

        if ($channel === 'all') {
            return (int) $organisation->orderingStats->{'number_delivery_notes'.$statsSuffix};
        }

        $shopTypeCount = (int) $organisation->orderingStats->{'number_'.$this->channelShopType($channel).'_shop_delivery_notes'.$statsSuffix};

        if ($channel !== self::PARTNERS_CHANNEL && $channel !== ShopTypeEnum::B2B->value) {
            return $shopTypeCount;
        }

        $partnerStateCounts ??= $this->partnerDeliveryNotesStateCounts($organisation);
        $partnerCount       = $state ? ($partnerStateCounts[$state] ?? 0) : array_sum($partnerStateCounts);

        return $channel === self::PARTNERS_CHANNEL ? $partnerCount : max(0, $shopTypeCount - $partnerCount);
    }
}
