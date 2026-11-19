<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\DeliveryNote;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * HELP-4810. How many boxes a wholesale delivery note will need: the larger of what its weight needs
 * (packing materials added, the warehouse's maximum per box) and what its volume needs (each SKO's
 * packed litres learnt by LearnOrgStocksPackedVolume, or its weight times the warehouse's litres per kg
 * when not learnt yet). Worked out again while the note is being picked; the estimate made before
 * picking started is kept apart as the first one, to compare with what was packed.
 */
class EstimateDeliveryNoteBoxes
{
    use AsAction;

    public string $commandSignature = 'delivery_notes:estimate_boxes {organisations?*}';

    public const array DEFAULT_BOX = ['length' => 38, 'width' => 38, 'height' => 38, 'max_weight' => 20];
    private const float DEFAULT_LITRES_PER_KG = 6.0;
    private const float BOX_WALL_CM = 1.0;
    private const float DEFAULT_WEIGHT_UPLIFT = 1.1;
    private const float MIN_WEIGHT_UPLIFT = 1.0;
    private const float MAX_WEIGHT_UPLIFT = 1.3;

    private const array SHOP_TYPES = [ShopTypeEnum::B2B, ShopTypeEnum::EXTERNAL];
    private const array OPEN_STATES = [
        DeliveryNoteStateEnum::UNASSIGNED,
        DeliveryNoteStateEnum::QUEUED,
        DeliveryNoteStateEnum::HANDLING,
        DeliveryNoteStateEnum::HANDLING_BLOCKED,
        DeliveryNoteStateEnum::PICKED,
        DeliveryNoteStateEnum::PACKING,
    ];
    private const array BEFORE_PICKING_STATES = [DeliveryNoteStateEnum::UNASSIGNED, DeliveryNoteStateEnum::QUEUED];
    private const array PICKED_ITEM_STATES = ['picked', 'packing', 'packed', 'finalised', 'dispatched'];
    private const array GONE_ITEM_STATES = ['cancelled', 'out_of_stock', 'no_dispatched'];

    public function handle(DeliveryNote $deliveryNote): ?int
    {
        if (!in_array($deliveryNote->shop_type, self::SHOP_TYPES, true) || !in_array($deliveryNote->state, self::OPEN_STATES, true)) {
            return $deliveryNote->estimated_boxes;
        }

        $warehouse = $deliveryNote->warehouse;
        $box       = self::box($warehouse);
        $packing   = Arr::get($warehouse?->data ?? [], 'packing', []);
        $litresPerKg  = (float) Arr::get($packing, 'litres_per_kg', self::DEFAULT_LITRES_PER_KG) ?: self::DEFAULT_LITRES_PER_KG;
        $weightUplift = min(self::MAX_WEIGHT_UPLIFT, max(self::MIN_WEIGHT_UPLIFT, (float) Arr::get($packing, 'weight_uplift', self::DEFAULT_WEIGHT_UPLIFT)));

        $lines = DB::table('delivery_note_items')
            ->leftJoin('org_stocks', 'org_stocks.id', 'delivery_note_items.org_stock_id')
            ->where('delivery_note_items.delivery_note_id', $deliveryNote->id)
            ->whereNotIn('delivery_note_items.state', self::GONE_ITEM_STATES)
            ->select([
                'delivery_note_items.state',
                'delivery_note_items.quantity_required',
                'delivery_note_items.quantity_picked',
                'delivery_note_items.estimated_required_weight',
                'delivery_note_items.estimated_picked_weight',
                'org_stocks.code',
                'org_stocks.packed_volume',
            ])
            ->get();

        $boxLitres       = ($box['length'] + self::BOX_WALL_CM) * ($box['width'] + self::BOX_WALL_CM) * ($box['height'] + self::BOX_WALL_CM) / 1000;
        $weight          = 0.0;
        $litres          = 0.0;
        $notLearntWeight = 0.0;
        $heavyItems      = [];
        $bulkyItems      = [];
        $unknownItems    = [];

        foreach ($lines as $line) {
            $isPicked = in_array($line->state, self::PICKED_ITEM_STATES, true) && $line->quantity_picked !== null;
            $quantity = (float) ($isPicked ? $line->quantity_picked : $line->quantity_required);
            if ($quantity <= 0) {
                continue;
            }

            $lineWeight = ($isPicked ? (int) $line->estimated_picked_weight : (int) $line->estimated_required_weight) / 1000;
            $weight     += $lineWeight;

            if ($lineWeight <= 0 && $line->packed_volume === null) {
                $unknownItems[] = $line->code ?? '?';
                continue;
            }

            if ($line->packed_volume !== null) {
                $litres += $quantity * (float) $line->packed_volume;
                if ((float) $line->packed_volume > $boxLitres && $line->code) {
                    $bulkyItems[] = $line->code;
                }
            } else {
                $litres          += $lineWeight * $litresPerKg;
                $notLearntWeight += $lineWeight;
            }

            if ($line->code && $lineWeight / $quantity * $weightUplift > $box['max_weight']) {
                $heavyItems[] = $line->code;
            }
        }

        if ($weight <= 0 && $litres <= 0) {
            $deliveryNote->update(['estimated_boxes' => null, 'boxes_estimate' => []]);

            return null;
        }

        $boxes           = (int) max(1, ceil($weight * $weightUplift / $box['max_weight']), ceil(round($litres / $boxLitres, 3)));
        $notLearntShare  = $weight > 0 ? $notLearntWeight / $weight : 0;

        $estimate = [
            'boxes'             => $boxes,
            'weight'            => round($weight * $weightUplift, 1),
            'volume'            => round($litres, 1),
            'box'               => $box,
            'not_learnt_share'  => round($notLearntShare, 2),
            'heavy_items'       => array_values(array_unique($heavyItems)),
            'bulky_items'       => array_values(array_unique($bulkyItems)),
            'unknown_items'     => array_values(array_unique($unknownItems)),
        ];

        $previous = $deliveryNote->boxes_estimate ?? [];
        $first    = Arr::get($previous, 'first');
        if (!$first || in_array($deliveryNote->state, self::BEFORE_PICKING_STATES, true)) {
            $first = Arr::only($estimate, ['boxes', 'weight', 'volume', 'not_learnt_share']);
        }
        $estimate['first'] = Arr::except($first, ['estimated_at']);

        if ($deliveryNote->estimated_boxes === $boxes && $this->sameEstimate($previous, $estimate)) {
            return $boxes;
        }

        $estimate['first']['estimated_at'] = $this->sameEstimate(Arr::get($previous, 'first', []), $estimate['first'])
            ? Arr::get($previous, 'first.estimated_at', now()->toIso8601String())
            : now()->toIso8601String();
        $estimate['estimated_at'] = now()->toIso8601String();

        $deliveryNote->update(['estimated_boxes' => $boxes, 'boxes_estimate' => $estimate]);

        return $boxes;
    }

    /**
     * @param array<string, mixed> $stored
     * @param array<string, mixed> $estimate
     */
    private function sameEstimate(array $stored, array $estimate): bool
    {
        $comparable = function (array $values) use (&$comparable): array {
            $values = Arr::except($values, ['estimated_at']);
            ksort($values);

            return array_map(fn ($value) => is_array($value) && !array_is_list($value) ? $comparable($value) : (is_numeric($value) ? (float) $value : $value), $values);
        };

        return $comparable($stored) == $comparable($estimate);
    }

    /**
     * @return array{length: float, width: float, height: float, max_weight: float}
     */
    public static function box(?Warehouse $warehouse): array
    {
        $settings = Arr::get($warehouse?->settings ?? [], 'packing_box', []);

        return collect(self::DEFAULT_BOX)
            ->map(fn ($default, $key) => (float) Arr::get($settings, $key) > 0 ? (float) Arr::get($settings, $key) : (float) $default)
            ->all();
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $organisations = Organisation::where('type', OrganisationTypeEnum::SHOP)
            ->when($command->argument('organisations'), fn ($query, $slugs) => $query->whereIn('slug', $slugs))
            ->pluck('id');

        $count = 0;
        DeliveryNote::whereIn('organisation_id', $organisations)
            ->whereIn('shop_type', array_map(fn (ShopTypeEnum $type) => $type->value, self::SHOP_TYPES))
            ->whereIn('state', array_map(fn (DeliveryNoteStateEnum $state) => $state->value, self::OPEN_STATES))
            ->with('warehouse')
            ->chunkById(200, function ($deliveryNotes) use (&$count) {
                foreach ($deliveryNotes as $deliveryNote) {
                    $this->handle($deliveryNote);
                    $count++;
                }
            });

        $command->info("$count delivery notes estimated");

        return 0;
    }
}
