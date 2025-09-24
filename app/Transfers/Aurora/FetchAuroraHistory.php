<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Oct 2024 11:58:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Models\Catalogue\Product;
use App\Models\CRM\Customer;
use App\Models\CRM\Prospect;
use App\Models\Helpers\Upload;
use App\Models\Inventory\Location;
use App\Models\Inventory\WarehouseArea;
use Illuminate\Support\Facades\DB;

class FetchAuroraHistory extends FetchAurora
{
    use WithParseUpdateHistory;
    use WithParseCreatedHistory;

    protected function parseModel(): void
    {

        //enum('sold_since','last_sold','first_sold','placed','wrote','deleted','edited','cancelled','charged','merged','created','associated','disassociate','register','login','logout','fail_login','password_request','password_reset','search')
        $event = match ($this->auroraModelData->{'Action'}) {
            'edited' => 'updated',
            default => $this->auroraModelData->{'Action'}
        };


        if ($event == 'created' and $this->auroraModelData->{'Indirect Object'} != '') {
            print "*** Error: Created with indirect object\n";
            print_r($this->auroraModelData);

            return;
        }


        $auditable = $this->parseAuditableFromHistory();
        if (!$auditable) {
            return;
        }

        $skip = $this->checkIfSkip($auditable, $event);
        if ($skip) {
            return;
        }


        $tags = $auditable->generateTags();

        $user = $this->parseUserFromHistory();

        $newValues = $this->parseHistoryNewValues($auditable, $event);
        $oldValues = $this->parseHistoryOldValues($auditable, $event);
        $data      = $this->parseHistoryData($auditable, $event);


        $upload = $this->parseHistoryUpload();
        if ($upload) {
            data_set($data, 'upload_id', $upload->id);
        }


        if ($event == 'updated' and
            (
                count($oldValues) == 0 and
                count($newValues) == 1
            )

        ) {
            // todo, later on check if really the old values are empty or is an parsing error
            $oldValues = $newValues;
            foreach ($oldValues as $key => $value) {
                $oldValues[$key] = '';
            }
        }

        if ($event == 'updated' and
            (
                count($oldValues) == 1 and
                count($newValues) == 0
            )

        ) {
            // todo, later on check if really the old values are empty or is an parsing error
            $newValues = $oldValues;
            foreach ($newValues as $key => $value) {
                $newValues[$key] = '';
            }
        }


        if ($event == 'updated' and
            (
                count($oldValues) == 0 or
                count($newValues) == 0
            )

        ) {
            print_r($oldValues);
            print_r($newValues);
            print_r($this->auroraModelData);
        }


        $this->parsedData['auditable'] = $auditable;
        $this->parsedData['history']   =
            [

                'created_at'      => $this->auroraModelData->{'History Date'},
                'source_id'       => $this->organisation->id.':'.$this->auroraModelData->{'History Key'},
                'fetched_at'      => now(),
                'last_fetched_at' => now(),
                'event'           => $event,
                'tags'            => $tags,
                'new_values'      => $newValues,
                'old_values'      => $oldValues,
                'data'            => $data,
            ];


        if ($user) {
            $this->parsedData['history']['user_type'] = class_basename($user);
            $this->parsedData['history']['user_id']   = $user->id;
        }

        //        print_r($this->parsedData['history']);
        //
        //        if ($this->parsedData['history']['event'] == 'updated') {
        //            print "=======. ".$this->parsedData['history']['event']."=============\n";
        //            print_r($this->parsedData['history']['old_values']);
        //            print_r($this->parsedData['history']['new_values']);
        //        }
    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('History Dimension')
            ->where('History Key', $id)->first();
    }


    protected function parseAuditableFromHistory(): Customer|Location|Product|WarehouseArea|Prospect|null
    {
        return match ($this->auroraModelData->{'Direct Object'}) {
            'Customer' => $this->parseCustomer($this->organisation->id.':'.$this->auroraModelData->{'Direct Object Key'}),
            'Location' => $this->parseLocation($this->organisation->id.':'.$this->auroraModelData->{'Direct Object Key'}, $this->organisationSource),
            'Product' => $this->parseProduct($this->organisation->id.':'.$this->auroraModelData->{'Direct Object Key'}),
            'Warehouse Area' => $this->parseWarehouseArea($this->organisation->id.':'.$this->auroraModelData->{'Direct Object Key'}),
            'Prospect' => $this->parseProspect($this->organisation->id.':'.$this->auroraModelData->{'Direct Object Key'}),
            default => null,
        };
    }


    protected function checkIfSkip($auditable, $event): bool
    {
        $skip = false;


        if ($event == 'updated') {
            $skip = true;
            switch ($auditable) {
                case $auditable instanceof Customer:
                    break;
                case $auditable instanceof Location:
                    $skip = !in_array($this->auroraModelData->{'Indirect Object'}, ['Location Code', 'Location Max Weight', 'Location Max Volume']);
                    break;
                case $auditable instanceof Product:
                    $skip = !in_array($this->auroraModelData->{'Indirect Object'}, ['Product Code', 'Product Price', 'Product Name', 'Product Status']);
                    break;
                case $auditable instanceof WarehouseArea:
                    $skip = !in_array($this->auroraModelData->{'Indirect Object'}, ['Warehouse Area Code', 'Warehouse Area Name']);
                    break;
                case $auditable instanceof Prospect:

                    if ($this->auroraModelData->{'Indirect Object'} == '') {
                        return true;
                    }


                    $skip = !in_array($this->auroraModelData->{'Indirect Object'}, [
                        'Prospect Website',
                        'Prospect Main Plain Email',
                        'Prospect Main Contact Name',
                        'Prospect Company Name',
                        'Prospect Contact Address',
                        'Prospect Preferred Contact Number Formatted Number'
                    ]);

                    if ($skip and

                        !in_array(
                            $this->auroraModelData->{'Indirect Object'},
                            [
                                'Prospect Preferred Contact Number',
                                'Prospect Sticky Note'
                            ]
                        )


                    ) {
                        print "Error unknown indirect object for skipping\n";
                        print_r($this->auroraModelData);

                    }


                    break;
            }
        }


        return $skip;
    }

    protected function parseHistoryOldValues($auditable, string $event): array
    {
        if ($event == 'created') {
            return [];
        }

        return $this->parseHistoryUpdatedOldValues($auditable);
    }


    protected function parseHistoryUpload(): ?Upload
    {
        $upload   = null;
        $abstract = $this->auroraModelData->{'History Abstract'};
        if (preg_match('/change_view\(\'upload\/(\d+)/', $abstract, $matches)) {
            $uploadSourceId = $matches[1];
            $upload         = $this->parseUpload($this->organisation->id.':'.$uploadSourceId);
        }

        return $upload;
    }


    protected function parseHistoryNewValues($auditable, string $event): array
    {
        if ($event == 'created') {
            return $this->parseHistoryCreatedNewValues($auditable);
        } elseif ($event == 'updated') {
            return $this->parseHistoryUpdatedNewValues($auditable);
        }

        return [];
    }

    protected function parseHistoryData($auditable, string $event): array
    {
        if ($event == 'created') {
            return $this->parseHistoryCreatedData($auditable);
        }

        return [];
    }


    private function getField()
    {
        return match ($this->auroraModelData->{'Indirect Object'}) {
            'Location Code', 'Product Code', 'Warehouse Area Code' => 'code',
            'Location Max Weight' => 'max_weight',
            'Location Max Volume' => 'max_volume',
            'Product Price' => 'price',
            'Product Status' => 'state',
            'Product Name', 'Warehouse Area Name' => 'name',
            'Prospect Website' => 'contact_website',
            'Prospect Main Plain Email' => 'email',
            'Prospect Main Contact Name' => 'contact_name',
            'Prospect Company Name' => 'company_name',
            'Prospect Contact Address' => 'address',
            'Prospect Preferred Contact Number Formatted Number' => 'phone',
            default => $this->auroraModelData->{'Indirect Object'}
        };
    }


}
