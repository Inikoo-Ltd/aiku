<?php

/*
 * author Louis Perez
 * created on 19-11-2025-13h-31m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Discounts\Offer\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Catalogue\OfferResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\SysAdmin\Organisation;
use Exception;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use App\Models\Discounts\OfferCampaign;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Http\Request;

class FinishOffer extends OrgAction
{
    use AsAction;

    public function handle(array $data)
    {
        // create offer logic
    }

    public function asController(Request $request)
    {
        $data = $request->all();

        dd([
            'payload' => $data
        ]);
    }
}
