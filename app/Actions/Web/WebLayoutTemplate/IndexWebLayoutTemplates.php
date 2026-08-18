<?php

/*
 * Author Louis Perez
 * Created on 10-08-2026-14h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Web\WebLayoutTemplate;

use App\Actions\OrgAction;
use App\Http\Resources\Web\WebLayoutTemplatesResource;
use App\Models\Web\WebLayoutTemplate;
use App\Models\Web\Webpage;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWebLayoutTemplates extends OrgAction
{
    public function handle(Webpage $webpage, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('web_layout_templates.name', $value)
                    ->orWhereStartWith('user.username', $value);
            });
        });

        $query = QueryBuilder::for(WebLayoutTemplate::class)
            ->where('scope', class_basename($webpage))
            ->where('type', $webpage->type->value)
            ->where('sub_type', $webpage->sub_type)
            ->leftJoin('users', 'users.id', 'web_layout_templates.author_id')
            ->orderBy('name');

        return $query
            ->select([
                'web_layout_templates.id',
                'web_layout_templates.name',
                'web_layout_templates.type',
                'web_layout_templates.sub_type',
                'web_layout_templates.scope',
                'web_layout_templates.blocks',
                'web_layout_templates.created_at',
                'users.username'
            ])
            ->allowedSorts([
                'name',
                'username',
            ])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $layoutTemplates): AnonymousResourceCollection
    {
        return WebLayoutTemplatesResource::collection($layoutTemplates);
    }

    public function asController(Webpage $webpage, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromGroup($webpage->group, $request);

        return $this->handle($webpage);
    }
}
