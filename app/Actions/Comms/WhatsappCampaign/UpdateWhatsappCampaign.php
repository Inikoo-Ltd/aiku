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
    use WithWhatsappCampaignSendable;

    private WhatsappCampaign $campaign;

    /**
     * recipients_recipe holds the audience settings the picker was last run with. It is not
     * in the json merge list: a saved recipe must replace the stored one wholesale, or
     * dropped channels would survive as leftover arrow updates.
     *
     * The chosen contacts themselves are rows in whatsapp_recipients, written by
     * StoreWhatsappCampaignRecipients, and recipients_count is derived there. Readiness is
     * still re-evaluated here because a template change is the other half of the gate.
     */
    public function handle(WhatsappCampaign $campaign, array $modelData): WhatsappCampaign
    {
        $this->assertEditable($campaign);

        $campaign = $this->update($campaign, $modelData, ['data']);

        $this->syncReadyState($campaign);

        return $campaign;
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
            /* Swapping the template is fine, clearing it is not: the recipients were picked
               against its merge tags, and a campaign with an audience but no template is a
               state neither the workshop nor the send path has anything to say about. */
            'meta_message_template_id' => [
                'sometimes',
                $this->campaign->meta_message_template_id ? 'required' : 'nullable',
                'integer',
                Rule::exists('meta_message_templates', 'id')->where('shop_id', $this->shop->id),
            ],
            'recipients_recipe'                  => ['sometimes', 'array'],
            'recipients_recipe.type'             => ['sometimes', 'string'],
            'recipients_recipe.channels'         => ['sometimes', 'array'],
            'recipients_recipe.channels.*'       => ['sometimes', 'boolean'],
            'recipients_recipe.customer_filters' => ['sometimes', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'meta_message_template_id.required' => __('A campaign keeps a template once it has one. Choose a different template instead of clearing it.'),
        ];
    }

    public function action(WhatsappCampaign $campaign, array $modelData): WhatsappCampaign
    {
        $this->asAction = true;
        $this->campaign = $campaign;
        $this->initialisationFromShop($campaign->shop, $modelData);

        return $this->handle($campaign, $this->validatedData);
    }

    public function asController(Organisation $organisation, Shop $shop, WhatsappCampaign $whatsappCampaign, ActionRequest $request): WhatsappCampaign
    {
        $this->campaign = $whatsappCampaign;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($whatsappCampaign, $this->validatedData);
    }
}
