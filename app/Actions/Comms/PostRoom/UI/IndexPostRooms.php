<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Nov 2024 11:09:35 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\PostRoom\UI;

use App\Actions\Comms\DispatchedEmail\UI\IndexDispatchedEmails;
use App\Actions\Comms\Mailshot\UI\IndexMailshots;
use App\Actions\Comms\Outbox\UI\IndexOutboxes;
use App\Actions\OrgAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Comms\PostRoom\PostRoomsTabsEnum;
use App\Http\Resources\Mail\DispatchedEmailResource;
use App\Http\Resources\Mail\MailshotResource;
use App\Http\Resources\Mail\OutboxesResource;
use App\Http\Resources\Mail\PostRoomResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
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

class IndexPostRooms extends OrgAction
{
    // private Organisation|Shop $parent;

    public function handle($prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->where('post_rooms.code', '~*', "\y$value\y")
                    ->orWhere('post_rooms.data', '=', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(PostRoom::class);
        foreach ($this->elementGroups as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: $elementGroup['engine'],
                prefix: $prefix
            );
        }

        return $queryBuilder
            ->defaultSort('post_rooms.code')
            ->select(['code', 'number_outboxes', 'number_mailshots', 'number_dispatched_emails'])
            ->leftJoin('post_room_stats', 'post_rooms.id', 'post_room_stats.post_room_id')
            ->allowedSorts(['code', 'number_outboxes', 'number_mailshots', 'number_dispatched_emails'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function asController(ActionRequest $request): ActionRequest
    {
        $this->initialisation(app('group'), $request);

        return $request;
    }

    public function tableStructure($prefix): Closure
    {
        return function (InertiaTable $table) use ($prefix) {

            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->defaultSort('code')
                ->column(key: 'code', label: __('code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_outboxes', label: __('outboxes'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_mailshots', label: __('mailshots'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'number_dispatched_emails', label: __('dispatched emails'), canBeHidden: false, sortable: true, searchable: true);
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        $this->canEdit = $request->user()->hasPermissionTo('mail.edit');
        return
            (
                $request->user()->tokenCan('root') or
                $request->user()->hasPermissionTo('mail.view')
            );
    }


    public function jsonResponse(): AnonymousResourceCollection
    {
        return PostRoomResource::collection($this->handle());
    }


    public function htmlResponse(LengthAwarePaginator $postRoom, ActionRequest $request): Response
    {

        return Inertia::render(
            'Mail/PostRooms',
            [
                'breadcrumbs' => $this->getBreadcrumbs(),
                'title'       => __('post room'),
                'pageHead'    => [
                    'title'   => __('post room'),
                    'create'  => $this->canEdit && $request->route()->getName() == 'mail.post_rooms.index' ? [
                        'route' => [
                            'name'       => 'shops.create',
                            'parameters' => array_values($request->route()->originalParameters())
                        ],
                        'label'    => __('post room'),
                    ] : false,
                ],
                'tabs' => [
                    'current'    => $this->tab,
                    'navigation' => PostRoomsTabsEnum::navigation(),
                ],


                PostRoomsTabsEnum::POST_ROOMS->value => $this->tab == PostRoomsTabsEnum::POST_ROOMS->value ?
                    fn () => PostRoomResource::collection($postRoom)
                    : Inertia::lazy(fn () => PostRoomResource::collection($postRoom)),

                PostRoomsTabsEnum::OUTBOXES->value => $this->tab == PostRoomsTabsEnum::OUTBOXES->value ?
                    fn () => OutboxesResource::collection(IndexOutboxes::run($this->parent, PostRoomsTabsEnum::OUTBOXES->value))
                    : Inertia::lazy(fn () => OutboxesResource::collection(IndexOutboxes::run($this->parent, PostRoomsTabsEnum::OUTBOXES->value))),

                PostRoomsTabsEnum::MAILSHOTS->value => $this->tab == PostRoomsTabsEnum::MAILSHOTS->value ?
                    fn () => MailshotResource::collection(IndexMailshots::run($this->parent))
                    : Inertia::lazy(fn () => MailshotResource::collection(IndexMailshots::run($this->parent))),

                PostRoomsTabsEnum::DISPATCHED_EMAILS->value => $this->tab == PostRoomsTabsEnum::DISPATCHED_EMAILS->value ?
                    fn () => DispatchedEmailResource::collection(IndexDispatchedEmails::run($this->parent))
                    : Inertia::lazy(fn () => DispatchedEmailResource::collection(IndexDispatchedEmails::run($this->parent))),
            ]
        )->table($this->tableStructure(prefix: 'post_rooms'))
            ->table(IndexOutboxes::make()->tableStructure(parent:$this->parent, prefix: 'outboxes'))
            ->table(IndexMailshots::make()->tableStructure(parent:$this->parent, prefix: 'mailshots'))
            ->table(IndexDispatchedEmails::make()->tableStructure(parent:$this->parent, prefix: 'dispatched_emails'));
    }


    public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $organisation;
        $this->initialisation($organisation, $request);
        return $this->handle();
    }

    public function getBreadcrumbs($suffix = null): array
    {
        return array_merge(
            (new ShowGroupDashboard())->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'mail.post_rooms.index'
                        ],
                        'label' => __('post rooms'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix

                ]
            ]
        );
    }
}
