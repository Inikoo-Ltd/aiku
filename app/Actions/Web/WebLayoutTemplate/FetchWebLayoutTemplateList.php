<?php

/*
 * author Louis Perez
 * created on 25-02-2026-13h-30m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebLayoutTemplate;

use App\Actions\OrgAction;
use App\Actions\Web\Webpage\WithStoreWebpage;
use App\Enums\Web\WebLayoutTemplate\WebLayoutTemplateType;
use App\Http\Resources\Web\WebLayoutTemplateResource;
use App\Models\Web\WebBlock;
use App\Models\Web\WebLayoutTemplate;
use App\Models\Web\Webpage;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class FetchWebLayoutTemplateList extends OrgAction
{
    use WithStoreWebpage;

    public function handle(Webpage|WebBlock $parent, $prefix = null): LengthAwarePaginator
    {
        $webLayoutTemplate = QueryBuilder::for(WebLayoutTemplate::class);
        if ($parent instanceof Webpage) {
            $webLayoutTemplate
                ->where('type', WebLayoutTemplateType::WEBPAGE)
                ->where('scope', $parent->sub_type)
                ->get();
        }

        return $webLayoutTemplate
                ->leftJoin('users', 'web_layout_templates.author_id', 'users.id')
                ->select([
                    'web_layout_templates.id',
                    'web_layout_templates.label',
                    'users.contact_name as author_name',
                    'web_layout_templates.data',
                    'web_layout_templates.created_at',
                    'web_layout_templates.updated_at',
                ])
                ->withPaginator($prefix, tableName: request()->route()->getName())
                ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $webLayoutTemplate): AnonymousResourceCollection
    {
        return WebLayoutTemplateResource::collection($webLayoutTemplate);
    }

    public function asController(Webpage $webpage, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($webpage->shop, $request);

        return $this->handle($webpage);
    }
}
