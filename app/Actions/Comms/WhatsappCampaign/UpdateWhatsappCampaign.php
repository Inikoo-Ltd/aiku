<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMarketingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\WhatsappCampaign\WhatsappCampaignStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\WhatsappCampaign;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UpdateWhatsappCampaign extends OrgAction
{
    use WithActionUpdate;
    use WithMarketingEditAuthorisation;
    use WithWhatsappCampaignSendable;

    private WhatsappCampaign $campaign;

    /**
     * Reduces the selected recipients to the same digits only form the audience query
     * groups by, dropping blanks, duplicates and anything WhatsApp cannot deliver to.
     * Normalising here rather than in the page means every write path gets it, not just
     * the one the recipients table posts from.
     *
     * Unsendable numbers are dropped rather than rejected: selecting a whole page should
     * not fail on one bad row, and recipients_count is derived from what survives.
     */
    public function prepareForValidation(ActionRequest $request): void
    {
        $recipientsList = $this->get('recipients_list');

        if (!is_array($recipientsList)) {
            return;
        }

        $keys = array_filter(array_map(
            function ($recipient) {
                $phoneNumber = Arr::get($recipient, 'phone_number');

                if (!is_scalar($phoneNumber) || !GetWhatsappRecipientsQuery::isSendablePhone((string) $phoneNumber)) {
                    return '';
                }

                return GetWhatsappRecipientsQuery::normalisePhoneKey((string) $phoneNumber);
            },
            $recipientsList
        ));

        $this->set('recipients_list', array_map(
            fn (string $key) => ['phone_number' => $key],
            array_values(array_unique($keys))
        ));
    }

    /**
     * recipients_recipe holds the audience settings, recipients_list the contacts chosen
     * from them. Neither is in the json merge list: a recipient selection must replace the
     * stored list wholesale, or deselected contacts would survive as leftover arrow updates.
     *
     * recipients_count is derived rather than accepted: it is the audience half of the
     * READY and sendable gates, so a client must not be able to declare an audience it
     * did not select. It is only touched when a selection is part of this update, so a
     * rename or a template change leaves the stored count alone.
     */
    public function handle(WhatsappCampaign $campaign, array $modelData): WhatsappCampaign
    {
        $this->assertEditable($campaign);

        if (array_key_exists('recipients_list', $modelData)) {
            $modelData['recipients_count'] = count($modelData['recipients_list']);
        }

        $campaign = $this->update($campaign, $modelData, ['data']);

        $this->syncReadyState($campaign);

        return $campaign;
    }

    /**
     * Composing is only allowed while the campaign is still being put together. Once it is
     * scheduled, sending, sent, cancelled or stopped its content is frozen, a scheduled
     * campaign must be cancelled first so the audience it goes out to is the one chosen.
     *
     * @throws ValidationException
     */
    protected function assertEditable(WhatsappCampaign $campaign): void
    {
        if ($campaign->isUnsent()) {
            return;
        }

        throw ValidationException::withMessages([
            'campaign' => __('This campaign can no longer be edited.'),
        ]);
    }

    /**
     * A campaign is publishable once it is composed and has an audience, so every update
     * re-evaluates readiness: the template and the recipients are saved by separate
     * requests and either one can be the last piece to arrive.
     *
     * Only the draft/ready pair is touched. Once a campaign is scheduled, sending or
     * finished it is left alone, an in flight send must never be pulled backwards.
     */
    protected function syncReadyState(WhatsappCampaign $campaign): void
    {
        $isReady = $this->isCampaignReady($campaign);

        if ($isReady && $campaign->state == WhatsappCampaignStateEnum::IN_PROCESS) {
            $this->update($campaign, [
                'state'    => WhatsappCampaignStateEnum::READY,
                'ready_at' => now(),
            ]);

            return;
        }

        if (!$isReady && $campaign->state == WhatsappCampaignStateEnum::READY) {
            $this->update($campaign, [
                'state'    => WhatsappCampaignStateEnum::IN_PROCESS,
                'ready_at' => null,
            ]);
        }
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
            'recipients_recipe'                  => ['sometimes', 'array'],
            'recipients_recipe.type'             => ['sometimes', 'string'],
            'recipients_recipe.channels'         => ['sometimes', 'array'],
            'recipients_recipe.channels.*'       => ['sometimes', 'boolean'],
            'recipients_recipe.customer_filters' => ['sometimes', 'array'],
            'recipients_list'                    => ['sometimes', 'array'],
            'recipients_list.*'                  => ['array'],
            'recipients_list.*.phone_number'     => ['required', 'string', 'regex:/^[1-9][0-9]{3,14}$/'],
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
