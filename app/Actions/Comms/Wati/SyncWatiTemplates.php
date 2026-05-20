<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Wednesday, 20 May 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Wati;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Comms\WatiTemplate;
use App\Models\SysAdmin\Organisation;
use App\Services\Wati\WatiClient;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class SyncWatiTemplates extends OrgAction
{
    public function handle(Shop $shop): void
    {
        $client = WatiClient::make();
        $pageNumber = 1;

        $pageSize = 100;

        do {
            $response  = $client->getTemplates(pageSize: $pageSize, pageNumber: $pageNumber);
            $templates = $response['templates'] ?? [];

            foreach ($templates as $template) {
                WatiTemplate::updateOrCreate(
                    [
                        'shop_id' => $shop->id,
                        'waba_id' => $template['id'] ?? null,
                    ],
                    [
                        'element_name'    => $template['name'] ?? null,
                        'category'        => $template['category'] ?? null,
                        'sub_category'    => $template['sub_category'] ?? null,
                        'status'          => $template['status'] ?? null,
                        'type'            => $template['type'] ?? null,
                        'language'        => $template['language_option'] ?? null,
                        'header'          => $template['header'] ?? null,
                        'body'            => $template['body'] ?? null,
                        'body_original'   => $template['body_original'] ?? null,
                        'footer'          => $template['footer'] ?? null,
                        'buttons'         => $template['buttons'] ?? null,
                        'buttons_type'    => $template['buttons_type'] ?? null,
                        'quality'         => $template['quality'] ?? null,
                        'creation_method' => $template['creation_method'] ?? null,
                        'last_modified'   => $template['last_modified'] ?? null,
                        'client_name'     => $template['client_name'] ?? null,
                        'custom_params'   => $template['custom_params'] ?? null,
                        'hsm'             => $template['hsm'] ?? null,
                        'catalog_info'    => $template['catalog_info'] ?? null,
                    ]
                );
            }

            $pageNumber++;
        } while (count($templates) === $pageSize);
    }

    public function htmlResponse(): RedirectResponse
    {
        return redirect()->back();
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): void
    {
        $this->initialisationFromShop($shop, $request);
        $this->handle($shop);
    }
}
