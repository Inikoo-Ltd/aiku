<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 10 April 2026 16:40:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\EmailTemplate;

use App\Actions\InertiaAction;
use App\Models\Catalogue\Shop;
use App\Models\Comms\EmailTemplate;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOtherShopEmailTemplates extends InertiaAction
{
    use AsObject;

    public function handle(Organisation $organisation, Shop $currentShop): array
    {
        $templates = EmailTemplate::where('email_templates.organisation_id', $organisation->id)
            ->leftJoin('shops', 'email_templates.shop_id', '=', 'shops.id')
            ->where('email_templates.shop_id', '!=', $currentShop->id)
            ->whereNotNull('email_templates.compiled_layout')
            ->where('email_templates.compiled_layout', '!=', '')
            ->select('email_templates.id', 'email_templates.name', 'email_templates.compiled_layout', 'shops.name as shop_name')
            ->orderBy('email_templates.created_at', 'desc')
            ->get();

        return [
            'templates' => $templates->toArray(),
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): array
    {
        return $this->handle($organisation, $shop);
    }
}
