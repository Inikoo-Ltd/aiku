<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Whatsapp\Templates;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaChannel;
use App\Models\Chat\MetaMessageTemplate;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class SyncWhatsappMessageTemplates extends OrgAction
{
    public function handle(Shop $shop): int
    {
        $metaChannel = MetaChannel::where('code', 'whatsapp')->firstOrFail();

        $templates = GetWhatsappMessageTemplate::run($shop);

        foreach ($templates as $template) {
            MetaMessageTemplate::updateOrCreate(
                [
                    'template_id' => (string) Arr::get($template, 'id'),
                ],
                [
                    'group_id'         => $shop->group_id,
                    'organisation_id'  => $shop->organisation_id,
                    'shop_id'          => $shop->id,
                    'meta_channel_id'  => $metaChannel->id,
                    'name'             => Arr::get($template, 'name', ''),
                    'parameter_format' => Arr::get($template, 'parameter_format'),
                    'language'         => Arr::get($template, 'language'),
                    'status'           => Arr::get($template, 'status'),
                    'category'         => Arr::get($template, 'category'),
                    'data'             => $template,
                    'synchronize_at'   => now(),
                ]
            );
        }

        return count($templates);
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        if (blank(Arr::get($shop->settings, 'whatsapp.waba_id'))) {
            return Redirect::back()->with('notification', [
                'status'      => 'error',
                'title'       => __('WhatsApp not connected'),
                'description' => __('Set the WhatsApp WABA ID in the shop settings before synchronizing templates.'),
            ]);
        }

        $count = $this->handle($shop);

        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('WhatsApp templates synchronized'),
            'description' => __(':count templates fetched from Meta.', ['count' => $count]),
        ]);
    }
}
