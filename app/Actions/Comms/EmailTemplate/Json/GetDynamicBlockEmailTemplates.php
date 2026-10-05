<?php

/*
 * Author: eka yudinata <ekayudinatha@gmail.com>
 * Created: Mon, 05 Oct 2026 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\EmailTemplate\Json;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithMarketingEditAuthorisation;
use App\Enums\Comms\EmailTemplate\EmailTemplateStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Comms\EmailTemplate;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Lorisleiva\Actions\ActionRequest;

class GetDynamicBlockEmailTemplates extends OrgAction
{
    use WithMarketingEditAuthorisation;

    public function handle(Shop $shop, ?string $search = null, bool $fromOtherShops = false): LengthAwarePaginator
    {
        $queryBuilder = QueryBuilder::for(EmailTemplate::class)
            ->join('shops', 'shops.id', '=', 'email_templates.shop_id')
            ->join('organisations', 'organisations.id', '=', 'shops.organisation_id')
            ->where('email_templates.state', EmailTemplateStateEnum::ACTIVE->value)
            ->whereRaw("coalesce(email_templates.data->>'dynamic_block', 'false') = 'true'")
            ->whereNotNull('email_templates.compiled_layout')
            ->where('email_templates.compiled_layout', '!=', '');

        if ($fromOtherShops) {
            $queryBuilder->where('email_templates.group_id', $shop->group_id)
                ->where('email_templates.shop_id', '!=', $shop->id);
        } else {
            $queryBuilder->where('email_templates.shop_id', $shop->id);
        }

        if ($search) {
            $queryBuilder->whereWith('email_templates.name', $search);
        }

        return $queryBuilder
            ->select([
                'email_templates.id',
                'email_templates.slug',
                'email_templates.name',
                'email_templates.compiled_layout',
                'shops.slug as shop_slug',
                'shops.name as shop_name',
                'organisations.slug as organisation_slug',
            ])
            ->defaultSort('-email_templates.updated_at')
            ->withPaginator(null, queryName: 'per_page')
            ->withQueryString();
    }

    public function rules(): array
    {
        return [
            'search'      => ['nullable', 'string', 'max:255'],
            'other_shops' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle(
            $shop,
            $this->validatedData['search'] ?? null,
            $this->validatedData['other_shops'] ?? false
        );
    }

    public function jsonResponse(LengthAwarePaginator $emailTemplates): AnonymousResourceCollection
    {
        return JsonResource::collection($emailTemplates->through(fn (EmailTemplate $emailTemplate) => [
            'id'              => $emailTemplate->id,
            'name'            => $emailTemplate->name,
            'compiled_layout' => $emailTemplate->compiled_layout,
            'shop_name'       => $emailTemplate->shop_name,
            'workshop_url'    => route('grp.org.shops.show.dashboard.comms.templates.workshop', [
                $emailTemplate->organisation_slug,
                $emailTemplate->shop_slug,
                $emailTemplate->slug,
            ]),
        ]));
    }
}
