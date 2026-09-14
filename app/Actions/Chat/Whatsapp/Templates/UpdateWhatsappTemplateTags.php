<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp\Templates;

use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\WhatsappTemplateTagEnum;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaMessageTemplate;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * Says what each `{{n}}` in an existing template means.
 *
 * Templates synced from Meta, or written before merge tags existed, carry bare numbered
 * slots and nothing records that `{{1}}` is the customer's name. Mapping them once here
 * lets the composer fill them from the conversation instead of asking the agent to type
 * the values on every send. The template text itself is untouched, so no re-approval is
 * needed.
 */
class UpdateWhatsappTemplateTags extends OrgAction
{
    public function rules(): array
    {
        return [
            'merge_tags'   => ['present', 'array', 'max:10'],
            'merge_tags.*' => ['nullable', Rule::in(array_column(WhatsappTemplateTagEnum::cases(), 'value'))],
        ];
    }

    public function handle(MetaMessageTemplate $metaMessageTemplate, array $modelData): MetaMessageTemplate
    {
        $tags = array_values(array_filter($modelData['merge_tags'] ?? []));

        $data = $metaMessageTemplate->data ?? [];

        // An incomplete mapping is worse than none: a partial list would shift every
        // later slot onto the wrong value.
        if (count($tags) === count($modelData['merge_tags'] ?? [])) {
            $data['merge_tags'] = ['body' => $tags];
        } else {
            unset($data['merge_tags']);
        }

        $metaMessageTemplate->update(['data' => $data]);

        return $metaMessageTemplate->fresh();
    }

    public function asController(Organisation $organisation, Shop $shop, MetaMessageTemplate $metaMessageTemplate, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        $this->handle($metaMessageTemplate, $this->validatedData);

        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Variables saved'),
            'description' => __('Aiku will fill :name from the conversation from now on.', [
                'name' => $metaMessageTemplate->name,
            ]),
        ]);
    }
}
