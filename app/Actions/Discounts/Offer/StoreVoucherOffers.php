<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 09 Jun 2026 16:05:27 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreVoucherOffers extends OrgAction
{
    use AsAction;

    public function handle(Shop $shop, array $modelData)
    {
        dd($modelData);
    }

    public function rules(): array
    {
        return [
            'voucher'             => ['required', 'string', 'max:16'],
            'name'                => ['required', 'string', 'max:255'],
            'type'                => ['required', 'string', 'in:amount'],
            'duration'            => ['required', 'string', 'in:interval,permanent'],
            'offer_amount'        => ['nullable', 'required_if:type,amount', 'numeric', 'min:0'],
            'reuse_customer'      => ['required', 'boolean'],
            'start_at'            => [
                'required',
                'date',
                Rule::when(
                    request('duration') === 'interval',
                    ['before_or_equal:end_at']
                )
            ],
            'end_at'              => ['nullable', 'required_if:duration,interval', 'date'],
            'discount_percentage' => ['required', 'numeric', 'gt:0', 'lt:100'],
            'target_type'         => ['required', 'string', 'in:shop,department,sub_department,family,collection,product'],
            'target_id'           => ['required', 'integer'],
        ];
    }

    public function asController(Shop $shop, ActionRequest $request)
    {
        dd($request->all());
        $this->initialisationFromShop($shop, $request);
        $this->handle($shop, $this->validatedData);
    }
}
