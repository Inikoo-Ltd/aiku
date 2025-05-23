<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Actions\Utils\Abbreviate;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Web\Website\WebsiteStateEnum;
use App\Models\Web\Website;
use Illuminate\Support\Facades\DB;

class FetchAuroraWebsite extends FetchAurora
{
    public function fetch(int $id): ?array
    {
        $this->auroraModelData = $this->fetchData($id);


        $code = strtolower($this->auroraModelData->{'Website Code'});
        $code = preg_replace('/\.com$/', '', $code);
        $code = preg_replace('/\.eu$/', '', $code);
        $code = preg_replace('/\.biz$/', '', $code);


        $sourceId = $this->organisation->id.':'.$this->auroraModelData->{'Website Key'};
        if (Website::where('code', $code)->whereNot('source_id', $sourceId)->exists()) {
            $code = $code.strtolower(Abbreviate::run(string: $this->organisation->slug, maximumLength: 2));
        }
        $this->auroraModelData->code = $code;
        $this->parseModel();

        return $this->parsedData;
    }

    protected function parseModel(): void
    {
        $shop = $this->parseShop($this->organisation->id.':'.$this->auroraModelData->{'Website Store Key'});

        if ($shop->type == ShopTypeEnum::FULFILMENT || $shop->type == ShopTypeEnum::DROPSHIPPING) {
            return;
        }

        $this->parsedData['shop'] = $shop;

        $state = match ($this->auroraModelData->{'Website Status'}) {
            'Active' => WebsiteStateEnum::LIVE,
            'Closed' => WebsiteStateEnum::CLOSED,
            default => WebsiteStateEnum::IN_PROCESS,
        };


        $domain = preg_replace('/^www\./', '', strtolower($this->auroraModelData->{'Website URL'}));


        $this->parsedData['website'] =
            [
                'name'            => $this->auroraModelData->{'Website Name'},
                'code'            => $this->auroraModelData->code,
                'domain'          => $domain,
                'source_id'       => $this->organisation->id.':'.$this->auroraModelData->{'Website Key'},
                'fetched_at'      => now(),
                'last_fetched_at' => now()
            ];

        $this->parsedData['website']['state']  = $state;
        $this->parsedData['website']['status'] = $this->auroraModelData->{'Website Status'} === 'Active';

        if ($launchedAt = $this->parseDatetime($this->auroraModelData->{'Website Launched'})) {
            $this->parsedData['website']['launched_at'] = $launchedAt;
        }

        if ($state !== WebsiteStateEnum::LIVE) {
            $this->parsedData['launch'] = false;
        } else {
            $this->parsedData['launch'] = true;
        }


        if ($createdAt = $this->parseDatetime($this->auroraModelData->{'Website From'})) {
            $this->parsedData['website']['created_at'] = $createdAt;
        }
    }


    protected function fetchData($id): object|null
    {
        return DB::connection('aurora')
            ->table('Website Dimension')
            ->where('Website Key', $id)->first();
    }
}
