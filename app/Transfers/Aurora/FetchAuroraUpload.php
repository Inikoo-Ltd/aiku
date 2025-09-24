<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Oct 2024 02:22:07 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use Illuminate\Support\Facades\DB;

class FetchAuroraUpload extends FetchAurora
{
    protected function parseModel(): void
    {

        if ($this->auroraModelData->{'Upload Object'} == 'production_part') {
            return;
        }

        // uploads in agent organisation
        if (
            $this->organisation->id.':'.$this->auroraModelData->{'Upload User Key'} == '1:238202'
            || $this->organisation->id.':'.$this->auroraModelData->{'Upload User Key'} == '1:298700'
        ) {
            return;
        }

        $this->parsedData['parent'] = $this->organisation;

        $model = match ($this->auroraModelData->{'Upload Object'}) {
            'supplier_part', 'supplier_parts' => 'SupplierProduct',
            'part' => 'Stock',
            'product' => 'Product',
            'location' => 'Location',
            'prospect' => 'Prospect',
            'warehouse_area' => 'WarehouseArea',
            'supplier' => 'Supplier',
            'fulfilment_asset' => 'Pallet',
            default => null
        };


        $user = $this->parseUser($this->organisation->id.':'.$this->auroraModelData->{'Upload User Key'});
        if (!$user) {
            dd($this->auroraModelData);
        }


        $this->parsedData['upload'] = [
            'model'             => $model,
            'original_filename' => $this->auroraModelData->{'Upload File Name'},
            'filename'          => $this->auroraModelData->{'Upload File Name'},
            'filesize'          => $this->auroraModelData->{'Upload File Size'},
            'source_id'         => $this->organisation->id.':'.$this->auroraModelData->{'Upload Key'},
            'created_at'        => $this->parseDatetime($this->auroraModelData->{'Upload Created'}),
            'updated_at'        => $this->parseDatetime($this->auroraModelData->{'Upload Created'}),
            'number_rows'       => $this->auroraModelData->{'Upload Records'},
            'number_success'    => $this->auroraModelData->{'Upload OK'},
            'number_fails'      => $this->auroraModelData->{'Upload Errors'},
            'fetched_at'        => now(),
            'last_fetched_at'   => now(),
            'user_id'           => $user->id,
        ];
    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Upload Dimension')
            ->leftJoin('Upload File Dimension', 'Upload Dimension.Upload Key', '=', 'Upload File Dimension.Upload File Upload Key')
            ->where('Upload Key', $id)->first();
    }
}
