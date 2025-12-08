<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FetchAuroraSupplier extends FetchAurora
{
    use WithAuroraImages;

    protected function parseModel(): void
    {

        if ($this->auroraModelData->{'aiku_ignore'} == 'Yes') {
            return;
        }

        $agentData = Db::connection('aurora')->table('Agent Supplier Bridge')
            ->leftJoin('Agent Dimension', 'Agent Supplier Agent Key', '=', 'Agent Key')
            ->select('Agent Code', 'Agent Key')
            ->where('Agent Supplier Supplier Key', $this->auroraModelData->{'Supplier Key'})->first();

        $agent = null;
        if ($agentData) {
            $agent = $this->parseAgent(
                $this->organisation->id.':'.$agentData->{'Agent Key'}
            );
            if (! $agent) {
                echo 'agent not found '.$agentData->{'Agent Supplier Agent Key'}." \n";

                return;
            }
        }

        if ($agent) {
            $this->parsedData['parent'] = $agent;
        } else {
            $this->parsedData['parent'] = $this->organisation->group;
        }

        $status = true;

        $archivedAt = $this->parseDatetime($this->auroraModelData->{'Supplier Valid To'});
        if ($this->auroraModelData->{'Supplier Type'} == 'Archived') {
            $status = false;
            $archivedAt = null;
        }
        $phone = $this->auroraModelData->{'Supplier Main Plain Mobile'};
        if ($phone == '') {
            $phone = $this->auroraModelData->{'Supplier Main Plain Telephone'};
        }

        $name = $this->auroraModelData->{'Supplier Nickname'};
        if (! $name) {
            $name = $this->auroraModelData->{'Supplier Name'};
        }

        $sourceSlug = Str::kebab(strtolower($this->auroraModelData->{'Supplier Code'}));

        $code = preg_replace('/\s/', '-', $this->auroraModelData->{'Supplier Code'});
        $code = preg_replace('/&/', 'and', $code);
        $code = preg_replace('/\s|\?|\.|\'/', '', $code);
        $code = preg_replace('/-?\(.+\)/', '', $code);

        $scopeType = 'Group';
        $scopeId = $this->organisation->group_id;

        $this->parsedData['supplier'] =
            [
                'name' => $name,
                'code' => $code,
                'company_name' => $this->auroraModelData->{'Supplier Company Name'},
                'contact_name' => $this->auroraModelData->{'Supplier Main Contact Name'},
                'email' => $this->auroraModelData->{'Supplier Main Plain Email'},
                'phone' => $phone,
                'currency_id' => $this->parseCurrencyID($this->auroraModelData->{'Supplier Default Currency Code'}),
                'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Supplier Key'},
                'source_slug' => $sourceSlug,
                'created_at' => $this->parseDatetime($this->auroraModelData->{'Supplier Valid From'}),
                'deleted_at' => $archivedAt,
                'address' => $this->parseAddress(prefix: 'Supplier Contact', auAddressData: $this->auroraModelData),
                'archived_at' => $archivedAt,
                'status' => $status,
                'fetched_at' => now(),
                'last_fetched_at' => now(),
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
            ];

        $this->parsePhoto();
    }

    private function parsePhoto(): void
    {
        $profile_images = $this->getModelImagesCollection(
            'Supplier',
            $this->auroraModelData->{'Supplier Key'}
        )->map(function ($auroraImage) {
            return $this->fetchImage($auroraImage);
        });
        $this->parsedData['photo'] = $profile_images->toArray();
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Supplier Dimension')
            ->where('Supplier Key', $id)->first();
    }
}
