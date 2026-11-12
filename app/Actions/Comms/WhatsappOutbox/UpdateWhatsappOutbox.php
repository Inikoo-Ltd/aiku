<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappOutbox;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCommsEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Comms\Outbox\OutboxCodeEnum;
use App\Enums\Comms\Outbox\OutboxStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaMessageTemplate;
use App\Models\Comms\WhatsappOutbox;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateWhatsappOutbox extends OrgAction
{
    use WithActionUpdate;
    use WithCommsEditAuthorisation;

    private WhatsappOutbox $whatsappOutbox;

    public function handle(WhatsappOutbox $whatsappOutbox, array $modelData): WhatsappOutbox
    {
        return $this->update($whatsappOutbox, $modelData);
    }

    public function rules(): array
    {
        return [
            'state'                    => ['sometimes', 'required', Rule::enum(OutboxStateEnum::class)],
            'interval'                 => ['sometimes', 'nullable', 'integer', 'min:1', 'max:720'],
            'meta_message_template_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('meta_message_templates', 'id')
                    ->where('shop_id', $this->whatsappOutbox->shop_id)
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /** The page shows the state as an on/off toggle */
    public function prepareForValidation(): void
    {
        $state = $this->get('state');
        if (is_bool($state) || in_array($state, ['true', 'false', '1', '0', 1, 0], true)) {
            $this->set('state', filter_var($state, FILTER_VALIDATE_BOOLEAN) ? OutboxStateEnum::ACTIVE->value : OutboxStateEnum::SUSPENDED->value);
        }
    }

    /**
     * An active WhatsApp outbox needs an approved UTILITY template: Meta bills and delivers a
     * MARKETING one as marketing, which a transactional notification must never be.
     */
    public function afterValidator(Validator $validator): void
    {
        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $state = $this->has('state') ? OutboxStateEnum::tryFrom((string) $this->get('state')) : $this->whatsappOutbox->state;

        if ($state !== OutboxStateEnum::ACTIVE) {
            return;
        }

        $interval = $this->has('interval') ? $this->get('interval') : $this->whatsappOutbox->interval;

        if ($this->whatsappOutbox->code === OutboxCodeEnum::OOS_IN_ORDER_NOTIFICATION && blank($interval)) {
            $validator->errors()->add('interval', __('Set how many hours to wait between runs before activating this outbox, otherwise it will never send.'));

            return;
        }

        $templateId = $this->has('meta_message_template_id')
            ? $this->get('meta_message_template_id')
            : $this->whatsappOutbox->meta_message_template_id;

        $template = $templateId ? MetaMessageTemplate::find($templateId) : null;

        if (!$template) {
            $validator->errors()->add('meta_message_template_id', __('Choose a WhatsApp template before activating this outbox.'));

            return;
        }

        if ($template->status !== 'APPROVED') {
            $validator->errors()->add('meta_message_template_id', __('This template is not approved by WhatsApp yet.'));

            return;
        }

        if (!SendCustomerNotificationWhatsapp::make()->isUsableTemplate($template)) {
            $validator->errors()->add('meta_message_template_id', __('Only UTILITY templates can be used for customer notifications. This one is :category.', [
                'category' => strtoupper((string) $template->category),
            ]));
        }
    }

    /** @noinspection PhpUnusedParameterInspection */
    public function inShop(Shop $shop, WhatsappOutbox $whatsappOutbox, ActionRequest $request): WhatsappOutbox
    {
        abort_unless($whatsappOutbox->shop_id === $shop->id, 404);

        $this->whatsappOutbox = $whatsappOutbox;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($whatsappOutbox, $this->validatedData);
    }

    public function action(WhatsappOutbox $whatsappOutbox, array $modelData): WhatsappOutbox
    {
        $this->asAction       = true;
        $this->whatsappOutbox = $whatsappOutbox;
        $this->initialisationFromShop($whatsappOutbox->shop, $modelData);

        return $this->handle($whatsappOutbox, Arr::only($this->validatedData, ['state', 'meta_message_template_id', 'interval']));
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
