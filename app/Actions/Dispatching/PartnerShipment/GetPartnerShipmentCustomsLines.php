<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PartnerShipment;

use App\Actions\Dispatching\DeliveryNote\UI\Traits\WithDeliveryNoteTariffCodesQuery;
use App\Models\Procurement\OrgPartner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Customs invoice lines of the goods packed for a partner (HELP-4482): grouped by tariff code and origin
 * like a delivery note's tariff codes tab, over several notes, counting packed quantities, and split the
 * way the partner's customs want it: cosmetics apart (Spain), GB origin apart (Slovakia, duty free).
 */
class GetPartnerShipmentCustomsLines
{
    use AsObject;
    use WithDeliveryNoteTariffCodesQuery;

    public const array SPLIT_ORDER = ['main', 'cosmetic', 'gb'];

    /**
     * @param array<int, int> $deliveryNoteIds
     *
     * @return Collection<string, Collection<int, object>> lines keyed by split, in SPLIT_ORDER
     */
    public function handle(OrgPartner $orgPartner, array $deliveryNoteIds): Collection
    {
        $query = $this->getTariffCodesQueryForDeliveryNotes($deliveryNoteIds, $orgPartner->organisation_id, 'quantity_packed');
        $split = self::splitSql($orgPartner);

        if ($split !== null) {
            $query->leftJoin('stocks as st', 'st.id', '=', 'os.stock_id')
                ->groupBy(DB::raw($split))
                ->addSelect(DB::raw("$split as split"));
        } else {
            $query->addSelect(DB::raw("'main' as split"));
        }

        $lines = DB::query()->fromSub($query, 'lines')
            ->orderByDesc('is_incomplete')
            ->orderBy('tariff_code')
            ->orderBy('origin')
            ->get()
            ->groupBy('split');

        return collect(self::SPLIT_ORDER)
            ->filter(fn (string $key) => $lines->has($key))
            ->mapWithKeys(fn (string $key) => [$key => $lines->get($key)]);
    }

    /**
     * Uses the stock's own origin, not OrgStock::isOnGbPallet(): customs follow origin, while a family
     * may ride on the normal pallet for warehouse reasons.
     */
    public static function splitSql(OrgPartner $orgPartner): ?string
    {
        $cases = [];
        if ($orgPartner->split_cosmetics) {
            $cases[] = "WHEN COALESCE(st.is_cosmetic, false) THEN 'cosmetic'";
        }
        if ($orgPartner->split_gb_origin) {
            $cases[] = "WHEN COALESCE(st.is_gb_origin, false) THEN 'gb'";
        }

        return $cases ? 'CASE '.implode(' ', $cases)." ELSE 'main' END" : null;
    }

    public static function splitLabel(OrgPartner $orgPartner, string $split): string
    {
        return match ($split) {
            'cosmetic' => __('Cosmetics'),
            'gb'       => __('GB origin (preferential)'),
            default    => match (true) {
                (bool) $orgPartner->split_cosmetics => __('Non cosmetics'),
                (bool) $orgPartner->split_gb_origin => __('Non preferential origin'),
                default                             => __('Customs invoice'),
            },
        };
    }
}
