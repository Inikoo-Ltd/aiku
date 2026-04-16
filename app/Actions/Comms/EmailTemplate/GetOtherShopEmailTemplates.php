<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Thursday, 10 April 2026 16:40:00 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\EmailTemplate;

use App\Actions\OrgAction;
use App\Http\Resources\Mail\EmailTemplateResource;
use App\Models\Catalogue\Shop;
use App\Models\Comms\EmailTemplate;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOtherShopEmailTemplates extends OrgAction
{
    use AsObject;

    public function handle(Organisation $organisation, Shop $currentShop, $prefix = null, ?int $numberOfRecords = 4): LengthAwarePaginator
    {
        $queryBuilder = QueryBuilder::for(EmailTemplate::class);

        $queryBuilder->where('email_templates.shop_id', '!=', $currentShop->id);
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
            ->withPaginator($prefix, $numberOfRecords)
            ->withQueryString();
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);
        return $this->handle($organisation, $shop);
    }

    public function jsonResponse(LengthAwarePaginator $emailTemplates): AnonymousResourceCollection
    {
        return EmailTemplateResource::collection($emailTemplates);
    }
}
