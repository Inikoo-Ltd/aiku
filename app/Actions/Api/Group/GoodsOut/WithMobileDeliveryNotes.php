<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-10h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\UI\IndexDeliveryNotes;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Http\Resources\Dispatching\DeliveryNotesResource;
use App\Models\Catalogue\Shop;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Illuminate\Support\Carbon;

trait WithMobileDeliveryNotes
{
    public const array BUCKETS = [
        'unassigned'       => 'unassigned',
        'queued'           => 'queued',
        'handling'         => 'handling',
        'handling_blocked' => 'handling_blocked',
        'picked'           => 'picked',
        'packing'          => 'packing',
        'packed'           => 'packed',
        'finalised'        => 'finalised',
        'dispatched'       => 'dispatched',
        'in_warehouse'     => 'inWarehouse',
        'all'              => 'all',
    ];

    public const array CHANNELS = ['all', 'b2b', 'b2c', 'dropshipping', 'external', 'fulfilment', 'partners'];

    public const array PICKER_FILTERS = ['me', 'none', 'others'];

    protected function deliveryNotesQuery(Warehouse $warehouse, string $bucket, string $channel, ?string $picker, User $user): QueryBuilder
    {
        $query = IndexDeliveryNotes::make()->deliveryNotesQuery(parent: $warehouse, bucket: self::BUCKETS[$bucket], shopType: $channel);

        match ($picker) {
            'me'     => $query->where('delivery_notes.picker_user_id', $user->id),
            'none'   => $query->whereNull('delivery_notes.picker_user_id'),
            'others' => $query->whereNotNull('delivery_notes.picker_user_id')->where('delivery_notes.picker_user_id', '!=', $user->id),
            default  => null,
        };

        return $query;
    }

    protected function withRowColumns(QueryBuilder $query): QueryBuilder
    {
        return $query
            ->addSelect(['delivery_notes.shop_id', 'shops.type as list_shop_type'])
            ->selectRaw(Shop::dispatchingChannelSql().' as dispatching_channel')
            ->selectRaw('exists (select 1 from org_partners where org_partners.customer_id = delivery_notes.customer_id) as is_partner_customer');
    }

    protected function dispatchingChannel(string $shopChannel, bool $isPartnerCustomer): string
    {
        return $shopChannel === ShopTypeEnum::B2B->value && $isPartnerCustomer ? IndexDeliveryNotes::PARTNERS_CHANNEL : $shopChannel;
    }

    protected function isPickingSessionCandidate(DeliveryNote $row, bool $checkChannel = true): bool
    {
        return in_array($row->state, [DeliveryNoteStateEnum::UNASSIGNED, DeliveryNoteStateEnum::QUEUED], true)
            && (int) $row->picking_sessions_count === 0
            && !$row->isLockedInAurora()
            && (!$checkChannel || !in_array(
                $this->dispatchingChannel((string) $row->dispatching_channel, (bool) $row->is_partner_customer),
                IndexDeliveryNotes::CHANNELS_WITHOUT_PICKING_SESSIONS,
                true
            ));
    }

    /**
     * @return array<string, mixed>
     */
    protected function deliveryNoteRow(DeliveryNote $deliveryNote): array
    {
        $row = DeliveryNotesResource::make($deliveryNote)->resolve();

        return [
            'id'                          => $deliveryNote->id,
            'slug'                        => $deliveryNote->slug,
            'reference'                   => $deliveryNote->reference,
            'type'                        => $deliveryNote->type->value,
            'state'                       => $deliveryNote->state->value,
            'state_label'                 => DeliveryNoteStateEnum::labels()[$deliveryNote->state->value],
            'date'                        => $deliveryNote->date?->toIso8601String(),
            'last_modified_at'            => $this->isoDate($deliveryNote->last_modified_date),
            'customer_name'               => $row['customer_name'],
            'is_customer_vip'             => (bool) $row['is_customer_vip'],
            'shop'                        => [
                'id'   => $deliveryNote->shop_id,
                'name' => $row['shop_name'],
                'type' => $deliveryNote->list_shop_type,
            ],
            'channel'                     => $this->dispatchingChannel((string) $deliveryNote->dispatching_channel, (bool) $deliveryNote->is_partner_customer),
            'number_items'                => (int) $row['number_items'],
            'estimated_weight_kg'         => (float) $row['estimated_weight'],
            'parcels'                     => (int) $row['parcels'],
            'is_premium_dispatch'         => (bool) $row['is_premium_dispatch'],
            'is_priority'                 => (bool) $deliveryNote->is_priority,
            'is_collection'               => (bool) $row['is_collection'],
            'is_export'                   => (bool) $row['is_export'],
            'has_extra_packing'           => (bool) $row['has_extra_packing'],
            'picker'                      => $this->idAndName($row['picker']),
            'packer'                      => $this->idAndName($row['packer']),
            'trolleys'                    => $this->idsAndNames($row['trolleys']),
            'picked_bays'                 => $this->idsAndNames($row['picked_bays']),
            'waiting_warehouse_count'     => (int) $row['waiting_warehouse_count'],
            'waiting_crm_count'           => (int) $row['waiting_crm_count'],
            'number_of_days_in_warehouse' => (int) $row['number_of_days_in_warehouse'],
        ];
    }

    /**
     * @return array{id: int, name: string|null}|null
     */
    protected function idAndName(mixed $model): ?array
    {
        if (!is_array($model) || !isset($model['id'])) {
            return null;
        }

        return [
            'id'   => (int) $model['id'],
            'name' => $model['name'] ?? null,
        ];
    }

    /**
     * @return array<int, array{id: int, name: string|null}>
     */
    protected function idsAndNames(mixed $models): array
    {
        return collect(is_array($models) ? $models : [])
            ->map(fn ($model) => $this->idAndName($model))
            ->filter()
            ->values()
            ->all();
    }

    protected function isoDate(mixed $date): ?string
    {
        if (blank($date)) {
            return null;
        }

        return Carbon::parse($date)->toIso8601String();
    }
}
