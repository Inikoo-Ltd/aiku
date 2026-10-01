<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 30 Sep 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage\Json;

use App\Actions\OrgAction;
use App\Http\Resources\Web\BlogAuthorsResource;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetBlogAuthors extends OrgAction
{
    public function handle(Shop $shop): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('users.contact_name', $value)
                    ->orWhereStartWith('users.username', $value);
            });
        });

        return QueryBuilder::for(User::class)
            ->where('users.group_id', $shop->group_id)
            ->where('users.status', true)
            ->select([
                'users.id',
                'users.username',
                DB::raw("coalesce(nullif(users.contact_name, ''), users.username) as name"),
            ])
            ->defaultSort('name')
            ->allowedFilters([$globalSearch])
            ->withPaginator(null)
            ->withQueryString();
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo([
            "web.{$this->shop->id}.edit",
            "group-webmaster.edit",
        ]);
    }

    public function asController(Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function jsonResponse(LengthAwarePaginator $users): AnonymousResourceCollection
    {
        return BlogAuthorsResource::collection($users);
    }
}
