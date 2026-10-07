<?php

/*
 * Author Louis Perez
 * Created on 23-09-2026-11h-34m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Inventory\OrgStock\Json;

use App\Actions\Inventory\OrgStock\UI\GetOrgStockLabelOptions;
use App\Actions\OrgAction;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\Warehouse;
use Lorisleiva\Actions\ActionRequest;

/**
 * The label modal's options for one org stock, fetched when the printer icon is clicked.
 *
 * They are not carried in the table payload because working them out means reading the trade units,
 * the organisation and its address once per barcode level, which is wasted on the fifty rows of a
 * delivery note nobody is going to print a label for.
 */
class FetchOrgStockLabelOptions extends OrgAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Warehouse $warehouse, OrgStock $orgStock): array
    {
        return [
            'options'     => GetOrgStockLabelOptions::run($orgStock),
            'label_route' => [
                'name'       => 'grp.org.warehouses.show.inventory.org_stocks.label',
                'parameters' => [
                    'organisation' => $warehouse->organisation->slug,
                    'warehouse'    => $warehouse->slug,
                    'orgStock'     => $orgStock->slug,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $labelOptions
     * @return array<string, mixed>
     */
    public function jsonResponse(array $labelOptions): array
    {
        return $labelOptions;
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(Warehouse $warehouse, OrgStock $orgStock, ActionRequest $request): array
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, $orgStock);
    }
}
