<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 15 Jul 2026 08:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Search;

use App\Models\Billables\Charge;
use App\Models\Billables\Service;
use App\Models\Billables\ShippingZone;
use App\Models\Billables\ShippingZoneSchema;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SearchBillables
{
    use AsAction;
    use WithRawSearchResults;

    public function handle(string $query, array $options): array
    {
        $shopId = Arr::get($options, 'shop_id');

        $mapCodeNameState = static fn (array $document) => [
            'id'    => (int)$document['id'],
            'code'  => $document['code'] ?? null,
            'name'  => $document['name'] ?? null,
            'state' => $document['state'] ?? null,
        ];

        $scoped = function (string $model) use ($query, $shopId) {
            $builder = $model::search($query);
            if ($shopId) {
                $builder->where('shop_id', $shopId);
            }

            return $builder;
        };

        return [
            'scope'   => 'billables',
            'results' => [
                'charges'               => array_map($mapCodeNameState, $this->rawDocuments($scoped(Charge::class))),
                'services'              => array_map($mapCodeNameState, $this->rawDocuments($scoped(Service::class))),
                'shipping_zone_schemas' => array_map($mapCodeNameState, $this->rawDocuments($scoped(ShippingZoneSchema::class))),
                'shipping_zones'        => array_map($mapCodeNameState, $this->rawDocuments($scoped(ShippingZone::class))),
            ],
        ];
    }


}
