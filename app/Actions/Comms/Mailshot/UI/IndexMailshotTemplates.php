<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Created: Tuesday, 20 Jan 2026 11:12:48 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\Mailshot\UI;

use App\Actions\OrgAction;
use App\Http\Resources\Comms\MailshotTemplatesInDashboardResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Comms\EmailTemplate;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Closure;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;
use App\Services\QueryBuilder;

class IndexMailshotTemplates extends OrgAction
{
    use HasUIMailshots;

    public Shop $parent;

    public function handle(Shop $parent, $prefix = null): LengthAwarePaginator
    {

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereWith('email_templates.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(EmailTemplate::class)
            ->where('shop_id', $parent->id);


        return $queryBuilder
            ->defaultSort('-email_templates.created_at')
            ->select([
                'email_templates.id',
                'email_templates.slug',
                'email_templates.name',
                'email_templates.state',
                'email_templates.created_at',
                'email_templates.updated_at',
            ])
            ->allowedSorts(['state', 'name', 'created_at'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix . 'Page');
            }

            $table
                ->withGlobalSearch()
                ->column(key: 'state', label: '', type: 'icon')
                ->column(key: 'name', label: __('name'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'created_at', label: __('Date'), canBeHidden: false, sortable: true, searchable: true, align: 'right');
        };
    }


    public function htmlResponse(LengthAwarePaginator $mailshots, ActionRequest $request): Response
    {
        $actions = [];
        if ($this->parent instanceof Shop) {
            $actions = [
                [
                    'type'  => 'button',
                    'style' => 'create',
                    'label' => __('New Template'),
                    'route' => [
                        'name'       => 'grp.org.shops.show.marketing.templates.create',
                        'parameters' => array_values($request->route()->originalParameters())
                    ]
                ]
            ];
        }

        $title = __('Templates');

        return Inertia::render(
            'Comms/Templates',
            [
                'breadcrumbs' => $this->getBreadcrumbs(
                    $request->route()->getName(),
                    $request->route()->originalParameters(),
                    $this->parent
                ),
                'title'       => $title,
                'pageHead'    => array_filter([
                    'title'   => $title,
                    'icon'    => ['fal', 'fa-newspaper'],
                    'actions' => $actions,
                ]),
                'data'        => MailshotTemplatesInDashboardResource::collection($mailshots),
            ]
        )->table($this->tableStructure($this->parent));
    }

    public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    {
        $this->parent = $shop;
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }
}
