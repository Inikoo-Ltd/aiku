<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp\Templates;

use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappCredentials;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaMessageTemplate;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteWhatsappMessageTemplate extends OrgAction
{
    use WithWhatsappCredentials;

    /**
     * Meta deletes by name, which removes every language variant of the template. The
     * local rows follow only once Meta confirms, so a failed call never leaves Aiku
     * claiming a template is gone while it still exists upstream.
     *
     * @return array{ok: bool, message?: string}
     */
    public function handle(Shop $shop, MetaMessageTemplate $metaMessageTemplate): array
    {
        // A draft never reached Meta, so there is nothing upstream to delete.
        if (blank($metaMessageTemplate->template_id)) {
            $metaMessageTemplate->delete();

            return ['ok' => true];
        }

        $wabaId = (string) Arr::get($shop->settings, 'whatsapp.waba_id');

        ['access_token' => $accessToken] = $this->whatsappCredentials($shop);

        if ($wabaId === '' || $accessToken === '') {
            return ['ok' => false, 'message' => __('WhatsApp is not configured for this shop.')];
        }

        $response = Http::withToken($accessToken)->delete($this->whatsappEndpoint($wabaId.'/message_templates'), [
            'hsm_id' => $metaMessageTemplate->template_id,
            'name'   => $metaMessageTemplate->name,
        ]);

        if ($response->failed()) {
            return [
                'ok'      => false,
                'message' => Arr::get($response->json(), 'error.message') ?: __('Meta refused to delete the template.'),
            ];
        }

        MetaMessageTemplate::where('shop_id', $shop->id)
            ->where('name', $metaMessageTemplate->name)
            ->delete();

        return ['ok' => true];
    }

    public function asController(Organisation $organisation, Shop $shop, MetaMessageTemplate $metaMessageTemplate, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        $result = $this->handle($shop, $metaMessageTemplate);

        return Redirect::back()->with('notification', $result['ok']
            ? [
                'status'      => 'success',
                'title'       => __('Template deleted'),
                'description' => __(':name has been removed from WhatsApp.', ['name' => $metaMessageTemplate->name]),
            ]
            : [
                'status'      => 'error',
                'title'       => __('Template not deleted'),
                'description' => $result['message'],
            ]);
    }
}
