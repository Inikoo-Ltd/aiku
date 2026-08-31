<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMarketingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Shop;
use App\Models\Comms\WhatsappCampaign;
use App\Models\SysAdmin\Organisation;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateWhatsappCampaign extends OrgAction
{
    use WithActionUpdate;
    use WithMarketingEditAuthorisation;

    private WhatsappCampaign $campaign;

    public function handle(WhatsappCampaign $campaign, array $modelData): WhatsappCampaign
    {
        return $this->update($campaign, $modelData, ['data']);
    }

    public function rules(): array
    {
        return [
            'name'                     => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('whatsapp_campaigns', 'name')
                    ->where('shop_id', $this->shop->id)
                    ->whereNull('deleted_at')
                    ->ignore($this->campaign->id),
            ],
            'meta_message_template_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('meta_message_templates', 'id')->where('shop_id', $this->shop->id),
            ],
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, WhatsappCampaign $whatsappCampaign, ActionRequest $request): WhatsappCampaign
    {
        $this->campaign = $whatsappCampaign;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($whatsappCampaign, $this->validatedData);
    }
}
