<?php

namespace App\Actions\Discounts\OfferCampaign\Json;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Discounts\Offer;
use App\Models\Discounts\OfferCampaign;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class CheckVoucherCodeExistence extends OrgAction
{
    public function handle(OfferCampaign $offerCampaign, array $modelData): bool
    {
        $code = trim((string) Arr::get($modelData, 'code'));

        if ($code === '') {
            return false;
        }

        return Offer::where('shop_id', $offerCampaign->shop_id)
            ->whereRaw('LOWER(voucher) = ?', [strtolower($code)])
            ->exists();
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:60'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo("discounts.{$this->shop->id}.view");
    }

    public function asController(Organisation $organisation, Shop $shop, OfferCampaign $offerCampaign, ActionRequest $request): bool
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($offerCampaign, $this->validatedData);
    }

    /**
     * @return array{exists: bool}
     */
    public function jsonResponse(bool $result): array
    {
        return ['exists' => $result];
    }
}
