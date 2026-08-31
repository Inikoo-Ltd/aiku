<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMarketingEditAuthorisation;
use App\Enums\Comms\WhatsappCampaign\WhatsappCampaignTypeEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\WhatsappCampaign;
use App\Models\SysAdmin\Organisation;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Lorisleiva\Actions\ActionRequest;
use Symfony\Component\HttpFoundation\Response;

class StoreWhatsappCampaign extends OrgAction
{
    use WithMarketingEditAuthorisation;

    public function prepareForValidation(ActionRequest $request): void
    {
        if (!$this->has('type')) {
            $this->set('type', WhatsappCampaignTypeEnum::NEWSLETTER->value);
        }

        if (blank($this->get('name'))) {
            $this->set('name', $this->uniqueDefaultName($request));
        }
    }

    /**
     * The name is unique per shop, so a second campaign created on the same day by the
     * same user gets a counter rather than failing validation.
     */
    protected function uniqueDefaultName(ActionRequest $request): string
    {
        $base = __('New campaign by :user (:date)', [
            'user' => $request->user()->contact_name ?: $request->user()->username,
            'date' => now()->format('d/m/Y'),
        ]);

        // prepareForValidation runs before initialisationFromShop, so the shop comes from
        // the route rather than $this->shop.
        $shop = $request->route('shop');

        $name   = $base;
        $suffix = 1;

        while (WhatsappCampaign::where('shop_id', $shop->id)->where('name', $name)->exists()) {
            $suffix++;
            $name = $base.' #'.$suffix;
        }

        return $name;
    }

    public function rules(): array
    {
        return [
            'name'                     => [
                'required',
                'string',
                'max:255',
                Rule::unique('whatsapp_campaigns', 'name')
                    ->where('shop_id', $this->shop->id)
                    ->whereNull('deleted_at'),
            ],
            'type'                     => ['required', Rule::enum(WhatsappCampaignTypeEnum::class)],
            'meta_message_template_id' => [
                'nullable',
                'integer',
                Rule::exists('meta_message_templates', 'id')->where('shop_id', $this->shop->id),
            ],
        ];
    }

    public function handle(Shop $shop, array $modelData): WhatsappCampaign
    {
        $modelData['group_id']        = $shop->group_id;
        $modelData['organisation_id'] = $shop->organisation_id;
        $modelData['shop_id']         = $shop->id;

        return WhatsappCampaign::create($modelData);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): WhatsappCampaign
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function htmlResponse(WhatsappCampaign $campaign): Response
    {
        return Inertia::location(route('grp.org.shops.show.marketing.whatsapp_campaigns.workshop', [
            'organisation'     => $campaign->organisation->slug,
            'shop'             => $campaign->shop->slug,
            'whatsappCampaign' => $campaign->slug,
        ]));
    }
}
