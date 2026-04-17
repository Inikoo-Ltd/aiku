<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 10 April 2026 16:40:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\EmailTemplate;

use App\Actions\OrgAction;
use App\Enums\Comms\EmailTemplate\EmailTemplateBuilderEnum;
use App\Enums\Comms\EmailTemplate\EmailTemplateStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\EmailTemplate;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOtherShopEmailTemplates extends OrgAction
{
    use AsObject;

    public function handle(Organisation $organisation, Shop $currentShop): array
    {
        $queryBuilder = QueryBuilder::for(EmailTemplate::class);

        $queryBuilder->where('email_templates.shop_id', '!=', $currentShop->id);
        $queryBuilder->whereNotNull('email_templates.compiled_layout');
        $queryBuilder->where('email_templates.compiled_layout', '!=', '');
        $queryBuilder->where('email_templates.is_seeded', false);
        $queryBuilder->where('email_templates.state', EmailTemplateStateEnum::ACTIVE->value);
        $queryBuilder->where('email_templates.builder', EmailTemplateBuilderEnum::BEEFREE->value);


        return $queryBuilder
            ->leftJoin('shops', 'email_templates.shop_id', '=', 'shops.id')
            ->select([
                'email_templates.id',
                'email_templates.slug',
                'email_templates.name',
                'email_templates.compiled_layout',
                'email_templates.created_at',
                'shops.name as shop_name'
            ])
            ->orderBy('email_templates.created_at', 'desc')
            ->get()
            ->toArray();
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): array
    {
        $this->initialisationFromShop($shop, $request);
        return $this->handle($organisation, $shop);
    }

    public function jsonResponse(array $emailTemplates): array
    {
        return $emailTemplates;
    }

    public function action(Shop $currentShop): array
    {
        $this->initialisationFromShop($currentShop, []);
        return $this->handle($this->organisation, $currentShop);
    }
}
