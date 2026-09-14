<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp\Templates;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaMessageTemplate;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

/**
 * Only the internal label is editable.
 *
 * An approved template is frozen at Meta: its name identifies it when sending and its
 * wording was reviewed as-is, so neither can change without submitting a new template.
 * The label lives in `data` purely so a team can call it something friendlier than
 * `birthday_30_percent` without touching anything Meta knows about.
 */
class UpdateWhatsappMessageTemplate extends OrgAction
{
    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function handle(MetaMessageTemplate $metaMessageTemplate, array $modelData): MetaMessageTemplate
    {
        $data  = $metaMessageTemplate->data ?? [];
        $label = trim((string) ($modelData['label'] ?? ''));

        if ($label === '') {
            unset($data['label']);
        } else {
            $data['label'] = $label;
        }

        $metaMessageTemplate->update(['data' => $data]);

        return $metaMessageTemplate->fresh();
    }

    public function asController(Organisation $organisation, Shop $shop, MetaMessageTemplate $metaMessageTemplate, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        $this->handle($metaMessageTemplate, $this->validatedData);

        return Redirect::route('grp.org.shops.show.chat.whatsapp_templates.index', [
            'organisation' => $organisation->slug,
            'shop'         => $shop->slug,
        ])->with('notification', [
            'status'      => 'success',
            'title'       => __('Template updated'),
            'description' => __('The label has been saved.'),
        ]);
    }
}
