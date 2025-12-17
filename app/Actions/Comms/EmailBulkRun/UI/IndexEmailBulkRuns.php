<?php

/*
 * author Arya Permana - Kirin
 * created on 12-12-2024-16h-41m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Comms\EmailBulkRun\UI;

use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Http\Resources\Mail\EmailBulkRunsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Comms\EmailBulkRun;
use App\Models\Comms\Outbox;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexEmailBulkRuns extends OrgAction
{
    private Group|Organisation|Shop|Outbox $parent;

    public function handle(Group|Organisation|Shop|Outbox $parent, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('email_bulk_runs.subject', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(EmailBulkRun::class);
        $queryBuilder->leftJoin('organisations', 'email_bulk_runs.organisation_id', '=', 'organisations.id')
            ->leftJoin('shops', 'email_bulk_runs.shop_id', '=', 'shops.id');
        if ($parent instanceof Outbox) {
            $queryBuilder->where('email_bulk_runs.outbox_id', $parent->id);
        } elseif ($parent instanceof Shop) {
            $queryBuilder->where('email_bulk_runs.shop_id', $parent->id);
        } elseif ($parent instanceof Group) {
            $queryBuilder->where('email_bulk_runs.group_id', $parent->id);
        } else {
            $queryBuilder->where('email_bulk_runs.organisation_id', $parent->id);
        }

        return $queryBuilder
            ->defaultSort('email_bulk_runs.id')
            ->select([
                'email_bulk_runs.id',
                'email_bulk_runs.subject',
                'email_bulk_runs.state',
                'shops.name as shop_name',
                'shops.slug as shop_slug',
                'organisations.name as organisation_name',
                'organisations.slug as organisation_slug',
            ])
            ->allowedSorts(['email_bulk_runs.subject', 'email_bulk_runs.state', 'shop_name', 'organisation_name'])
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
                ->column(key: 'state', label: '', type: 'icon')
                ->column(key: 'subject', label: __('subject'), canBeHidden: false, sortable: true, searchable: true);
            if ($parent instanceof Group) {
                $table->column(key: 'shop_name', label: __('Shop'), canBeHidden: false, sortable: true, searchable: true)
                    ->column(key: 'organisation_name', label: __('organisation'), canBeHidden: false, sortable: true, searchable: true);
            }
        };
    }

    public function jsonResponse(LengthAwarePaginator $emailBulkRuns): AnonymousResourceCollection
    {
        return EmailBulkRunsResource::collection($emailBulkRuns);
    }


    public function htmlResponse(LengthAwarePaginator $mailshots, ActionRequest $request): Response
    {
        return Inertia::render(
            'Comms/Mailshots',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                ),
                'title'       => __('Email Bulk Runs'),
                'pageHead'    => [
                    'title' => __('Email Bulk Runs'),
                    'icon'  => ['fal', 'fa-raygun'],
                ],
                'data'        => EmailBulkRunsResource::collection($mailshots),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function inGroup(ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = group();
        $this->initialisationFromGroup($this->parent, $request);

        return $this->handle(parent: $this->parent);
    }

    // public function inOrganisation(Organisation $organisation, ActionRequest $request): LengthAwarePaginator
    // {
    //     $this->parent = $organisation;
    //     $this->initialisation($organisation, $request);
    //     return $this->handle($organisation);
    // }

    // public function inShop(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    // {
    //     $this->parent = $organisation;
    //     $this->initialisationFromShop($shop, $request);

    //     return $this->handle($organisation);
    // }

    // /** @noinspection PhpUnused */
    // public function inOutbox(Outbox $outbox, ActionRequest $request): LengthAwarePaginator
    // {
    //     $this->initialisation($request);
    //     return $this->handle($outbox);
    // }

    // /** @noinspection PhpUnused */
    // public function inPostRoomInShop(PostRoom $postRoom, Outbox $outbox, ActionRequest $request): LengthAwarePaginator
    // {
    //     $this->initialisation($request);
    //     return $this->handle($outbox);
    // }


    // /** @noinspection PhpUnused */
    // public function inOutboxInShop(Outbox $outbox, ActionRequest $request): LengthAwarePaginator
    // {
    //     $this->initialisation($request);
    //     return $this->handle($outbox);
    // }

    public function getBreadcrumbs(string $routeName, array $routeParameters, string $suffix = null): array
    {
        $headCrumb = function (array $routeParameters, ?string $suffix) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('Email Bulk Runs'),
                        'icon'  => 'fal fa-bars'
                    ],
                    'suffix' => $suffix
                ]
            ];
        };


        return match ($routeName) {
            'grp.overview.comms-marketing.email-bulk-runs.index', =>
            array_merge(
                ShowGroupOverviewHub::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name'       => $routeName,
                        'parameters' => $routeParameters
                    ],
                    $suffix
                )
            ),
            default => []
        };
    }
}
