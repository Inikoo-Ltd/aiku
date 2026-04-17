<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 17 April 2026 11:20:00 Central Indonesia Time, Sanur, Bali, Indonesia
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

class GetEmailTemplates extends OrgAction
{
    use AsObject;

    public function handle(Organisation $organisation, Shop $shop, string $type): array
    {
        $queryBuilder = QueryBuilder::for(EmailTemplate::class);

        if ($type === 'own') {
            $queryBuilder->where('email_templates.shop_id', $shop->id);
        } elseif ($type === 'other') {
            $queryBuilder->where('email_templates.shop_id', '!=', $shop->id);
            $queryBuilder->where('email_templates.is_seeded', false);
            $queryBuilder->where('email_templates.state', EmailTemplateStateEnum::ACTIVE->value);
            $queryBuilder->where('email_templates.builder', EmailTemplateBuilderEnum::BEEFREE->value);
        }

        $queryBuilder->whereNotNull('email_templates.compiled_layout');
        $queryBuilder->where('email_templates.compiled_layout', '!=', '');

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
        $type = $request->get('type', 'own');
        return $this->handle($organisation, $shop, $type);
    }

    public function jsonResponse(array $emailTemplates): array
    {
        return $emailTemplates;
    }

    public function action(Shop $shop, string $type = 'own'): array
    {
        $this->initialisationFromShop($shop, []);
        return $this->handle($this->organisation, $shop, $type);
    }
}
