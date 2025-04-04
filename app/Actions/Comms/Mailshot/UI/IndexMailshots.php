<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithCatalogueAuthorisation;
use App\Http\Resources\Mail\MailshotResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Comms\Mailshot;
use App\Models\Comms\OrgPostRoom;
use App\Models\Comms\Outbox;
use App\Models\Comms\PostRoom;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexMailshots extends OrgAction
{
    use HasUIMailshots;
    use WithCatalogueAuthorisation;

    public Outbox|PostRoom|OrgPostRoom|Organisation $parent;

    public function handle(Outbox|PostRoom|OrgPostRoom|Organisation $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('mailshots.subject', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Mailshot::class);

        if ($parent instanceof Outbox) {
            $queryBuilder->where('mailshots.outbox_id', $parent->id);
        } else {
            $queryBuilder->where('mailshots.organisation_id', $parent->id);
        }

        return $queryBuilder
            ->defaultSort('mailshots.state')
            ->select([
                'mailshots.slug',
                'mailshots.subject',
                'mailshots.date',
                'mailshots.state',
                'mailshots.id',
                'mailshots.data',
                'outboxes.slug as outboxes_slug',
                'post_rooms.id as post_room_id'
            ])
            ->leftJoin('outboxes', 'mailshots.outbox_id', 'outboxes.id')
            ->leftJoin('post_rooms', 'outboxes.post_room_id', 'post_rooms.id')
            ->when($parent, function ($query) use ($parent) {
                if (class_basename($parent) == 'Comms') {
                    $query->where('mailshots.post_room_id', $parent->id);
                }
            })
            ->allowedSorts(['mailshots.state', 'mailshots.data'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($parent, ?array $modelOperations = null, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $modelOperations, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withModelOperations($modelOperations)
                ->column(key: 'state', label: __('state'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'subject', label: __('subject'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'date', label: __('date'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function jsonResponse(LengthAwarePaginator $mailshots): AnonymousResourceCollection
    {
        return MailshotResource::collection($mailshots);
    }


    public function htmlResponse(LengthAwarePaginator $mailshots, ActionRequest $request): Response
    {
        return Inertia::render(
            'Comms/Mailshots',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                    $this->parent
                ),
                'title'       => __('mailshots'),
                'pageHead'    => [
                    'title'   => __('mailshots'),
                    'actions' => [
                        [
                            'type'  => 'button',
                            'style' => 'create',
                            'label' => __('mailshot'),
                            'route' => [
                                'name'       => 'grp.org.shops.show.marketing.mailshots.create',
                                'parameters' => array_values($request->route()->originalParameters())
                            ]
                        ]
                    ],
                ],
                'data'        => MailshotResource::collection($mailshots),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);

        return $this->handle($organisation);
    }

    public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($organisation);
    }

}
