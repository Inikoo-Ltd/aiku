<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit\UI;

use App\Enums\Goods\TradeUnit\TradeAttachmentScopeEnum;
use App\Models\Goods\TradeUnit;
use App\Models\Goods\TradeUnitFamily;
use App\Models\Helpers\Media;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class GetTradeUnitDocuments
{
    use AsObject;

    /**
     * @param  Collection<int, TradeUnit>  $tradeUnits
     * @return list<array{scope: string, scope_label: string, name: string, download_route: array, source: array}>
     */
    public function handle(Collection $tradeUnits): array
    {
        $tradeUnits = EloquentCollection::make($tradeUnits->unique('id')->values());
        $tradeUnits->loadMissing(['attachments', 'tradeUnitFamily.attachments']);

        $publicScopes = TradeAttachmentScopeEnum::publicScopes();
        $labels       = TradeAttachmentScopeEnum::labels();
        $documents    = [];

        foreach ($tradeUnits as $tradeUnit) {
            $tradeUnitChecksums = [];
            foreach ($tradeUnit->attachments as $attachment) {
                if (!in_array($attachment->pivot->scope, $publicScopes)) {
                    continue;
                }
                $tradeUnitChecksums[$attachment->checksum] = true;
                $documents['tu-'.$tradeUnit->id.'-'.$attachment->id] = $this->document($attachment, $labels, $this->tradeUnitSource($tradeUnit));
            }

            $tradeUnitFamily = $tradeUnit->tradeUnitFamily;
            if (!$tradeUnitFamily) {
                continue;
            }
            foreach ($tradeUnitFamily->attachments as $attachment) {
                if (!in_array($attachment->pivot->scope, $publicScopes) || isset($tradeUnitChecksums[$attachment->checksum])) {
                    continue;
                }
                $documents['tuf-'.$tradeUnitFamily->id.'-'.$attachment->id] = $this->document($attachment, $labels, $this->tradeUnitFamilySource($tradeUnitFamily));
            }
        }

        return array_values($documents);
    }

    /**
     * @param  Collection<int, Media>  $productAttachments
     * @param  Collection<int, TradeUnit>  $tradeUnits
     */
    public function forProduct(Collection $productAttachments, Collection $tradeUnits): array
    {
        $expected = collect($this->handle($tradeUnits))->keyBy('media_id');
        $labels   = TradeAttachmentScopeEnum::labels();

        return $productAttachments
            ->filter(fn (Media $attachment) => in_array($attachment->pivot->scope, TradeAttachmentScopeEnum::publicScopes()))
            ->map(fn (Media $attachment) => $this->document($attachment, $labels, Arr::get($expected->get($attachment->id), 'source')))
            ->values()
            ->all();
    }

    public function forTradeUnitFamilyOf(TradeUnit $tradeUnit): array
    {
        return collect($this->handle(collect([$tradeUnit])))
            ->where('source.type', 'trade_unit_family')
            ->values()
            ->all();
    }

    private function document(Media $attachment, array $labels, ?array $source): array
    {
        $scope = $attachment->pivot->scope;

        return [
            'media_id'       => $attachment->id,
            'scope'          => $scope,
            'scope_label'    => Arr::get($labels, $scope, $scope),
            'name'           => $attachment->name,
            'download_route' => [
                'name'       => 'grp.media.download',
                'parameters' => ['media' => $attachment->ulid],
            ],
            'source'         => $source,
        ];
    }

    private function tradeUnitSource(TradeUnit $tradeUnit): array
    {
        return [
            'type'  => 'trade_unit',
            'label' => __('Trade unit').' '.$tradeUnit->code,
            'route' => [
                'name'       => 'grp.trade_units.units.show',
                'parameters' => ['tradeUnit' => $tradeUnit->slug, 'tab' => 'attachments'],
            ],
        ];
    }

    private function tradeUnitFamilySource(TradeUnitFamily $tradeUnitFamily): array
    {
        return [
            'type'  => 'trade_unit_family',
            'label' => __('Trade unit family').' '.$tradeUnitFamily->code,
            'route' => [
                'name'       => 'grp.trade_units.families.show',
                'parameters' => ['tradeUnitFamily' => $tradeUnitFamily->slug, 'tab' => 'attachments'],
            ],
        ];
    }
}
