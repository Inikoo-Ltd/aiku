<?php

/*
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright (c) 2026, Andi Ferdiawan
 */

namespace App\Actions\Chat\Whatsapp\Templates;

use App\Actions\Chat\Whatsapp\Concerns\WithWhatsappCredentials;
use App\Actions\Chat\Whatsapp\Templates\Concerns\WithWhatsappTemplateLocalData;
use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Chat\MetaMessageTemplate;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

/**
 * Reads one template straight from Graph, so an agent waiting on an approval can check
 * that template alone instead of pulling the whole WABA catalogue.
 */
class RefreshWhatsappMessageTemplate extends OrgAction
{
    use WithWhatsappCredentials;
    use WithWhatsappTemplateLocalData;

    /**
     * @return array{ok: bool, message?: string, status?: string, changed?: bool}
     */
    public function handle(Shop $shop, MetaMessageTemplate $metaMessageTemplate): array
    {
        $accessToken = (string) Arr::get($shop->organisation->settings, 'meta.access_key');

        if ($accessToken === '' || blank($metaMessageTemplate->template_id)) {
            return [
                'ok'      => false,
                'message' => __('This template is not linked to Meta yet.'),
            ];
        }

        $response = Http::withToken($accessToken)->get(
            $this->whatsappEndpoint($metaMessageTemplate->template_id),
            ['fields' => 'id,name,language,status,category,parameter_format,components,rejected_reason']
        );

        if ($response->failed()) {
            return [
                'ok'      => false,
                'message' => Arr::get($response->json(), 'error.message') ?: __('Meta did not return this template.'),
            ];
        }

        $previousStatus = $metaMessageTemplate->status;
        $template       = $response->json();

        $metaMessageTemplate->update([
            'name'             => Arr::get($template, 'name', $metaMessageTemplate->name),
            'parameter_format' => Arr::get($template, 'parameter_format', $metaMessageTemplate->parameter_format),
            'language'         => Arr::get($template, 'language', $metaMessageTemplate->language),
            'status'           => Arr::get($template, 'status', $metaMessageTemplate->status),
            'category'         => Arr::get($template, 'category', $metaMessageTemplate->category),
            'data'             => $this->mergeLocalData($template, $metaMessageTemplate),
            'synchronize_at'   => now(),
        ]);

        return [
            'ok'      => true,
            'status'  => $metaMessageTemplate->status,
            'changed' => $previousStatus !== $metaMessageTemplate->status,
        ];
    }

    public function asController(Organisation $organisation, Shop $shop, MetaMessageTemplate $metaMessageTemplate, ActionRequest $request): RedirectResponse
    {
        $this->initialisationFromShop($shop, $request);

        $result = $this->handle($shop, $metaMessageTemplate);

        if (!$result['ok']) {
            return Redirect::back()->with('notification', [
                'status'      => 'error',
                'title'       => __('Status not refreshed'),
                'description' => $result['message'],
            ]);
        }

        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => $result['changed'] ? __('Status updated') : __('Status unchanged'),
            'description' => __(':name is :status on WhatsApp.', [
                'name'   => $metaMessageTemplate->name,
                'status' => $result['status'],
            ]),
        ]);
    }
}
