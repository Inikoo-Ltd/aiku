<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 21-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Comms\EmailTrackingEvent\UI;

use App\Actions\Comms\PostRoom\UI\ShowPostRoom;
use App\Actions\OrgAction;
use App\Actions\Overview\ShowGroupOverviewHub;
use App\Actions\UI\Marketing\MarketingHub;
use App\Http\Resources\Mail\DispatchedEmailsResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Comms\DispatchedEmail;
use App\Models\Comms\EmailTrackingEvent;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class IndexEmailTrackingEvents extends OrgAction
{
    private DispatchedEmail $parent;

    public function handle(DispatchedEmail $parent, $prefix = null): LengthAwarePaginator
    {
        // $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
        //     $query->where(function ($query) use ($value) {
        //         $query->orWhereWith('email_addresses.email', $value);
        //     });
        // });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(EmailTrackingEvent::class);

        if ($parent instanceof DispatchedEmail) {
            $queryBuilder->where('dispatched_email_id', $parent->id);
        }

        // if (is_array($this->elementGroups) || is_object($this->elementGroups) && !($parent instanceof Group)) {
        //     foreach ($this->elementGroups as $key => $elementGroup) {
        //         $queryBuilder->whereElementGroup(
        //             key: $key,
        //             allowedElements: array_keys($elementGroup['elements']),
        //             engine: $elementGroup['engine'],
        //             prefix: $prefix
        //         );
        //     }
        // }

        return $queryBuilder
            ->defaultSort('-date')
            ->select([
                'email_tracking_events.type',
                'email_tracking_events.data',
                'email_tracking_events.ip',
                'email_tracking_events.device',
                'email_tracking_events.created_at as date',
            ])
            ->allowedSorts(['type', 'data', 'device', 'ip', 'date'])
            // ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure($parent, $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($parent, $prefix) {

            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            // $table->withGlobalSearch()

            $table
                ->column(key: 'type', label: '', type: 'icon', canBeHidden: false);
            $table->column(key: 'ip', label: __('Ip Address'), canBeHidden: false, sortable: true);
            $table->column(key: 'device', label: __('Device'), canBeHidden: false, sortable: true);
            $table->column(key: 'date', label: __('Date'), canBeHidden: false, sortable: true);
            $table->defaultSort('-date');
        };
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->parent instanceof Group) {
            return $request->user()->authTo("group-overview");
        }
        $this->canEdit = $request->user()->authTo('mail.edit');
        return
            (
                $request->user()->tokenCan('root') or
                $request->user()->authTo('mail.view')
            );
    }


    // public function htmlResponse(LengthAwarePaginator $dispatched_emails, ActionRequest $request): Response
    // {
    //     return Inertia::render(
    //         'Comms/DispatchedEmails',
    //         [
    //             'breadcrumbs' => $this->getBreadcrumbs(
    //                 $request->route()->getName(),
    //                 $request->route()->originalParameters()
    //             ),
    //             'title'       => __('dispatched emails'),
    //             'pageHead'    => [
    //                 'title' => __('dispatched emails'),
    //                 'icon'  => ['fal', 'fa-paper-plane'],
    //             ],
    //             ...array_merge(
    //                 ($this->parent instanceof Group) ?
    //                 ['data' => DispatchedEmailsResource::collection($dispatched_emails)] :
    //                 ['dispatched_emails' => DispatchedEmailsResource::collection($dispatched_emails)]
    //             ),
    //         ]
    //     )->table($this->tableStructure($this->parent));
    // }


    // public function asController(Organisation $organisation, Shop $shop, ActionRequest $request): LengthAwarePaginator
    // {
    //     $this->parent = $shop;
    //     $this->initialisationFromShop($shop, $request);
    //     return $this->handle($shop);
    // }


    // public function getBreadcrumbs(string $routeName, array $routeParameters): array
    // {
    //     $headCrumb = function (array $routeParameters = []) use ($routeName) {
    //         return [
    //             [
    //                 'type'   => 'simple',
    //                 'simple' => [
    //                     'route' => $routeParameters,
    //                     'label' => __('Dispatched Emails'),
    //                     'icon'  => 'fal fa-bars'
    //                 ],
    //             ],
    //         ];
    //     };

    //     return match ($routeName) {
    //         'mail.dispatched-emails.index' =>
    //         array_merge(
    //             (new MarketingHub())->getBreadcrumbs(
    //                 $routeName,
    //                 $routeParameters
    //             ),
    //             $headCrumb()
    //         ),
    //         'mail.post_rooms.show.dispatched-emails.index' =>
    //         array_merge(
    //             (new ShowPostRoom())->getBreadcrumbs(),
    //             $headCrumb([])
    //         ),
    //         'grp.overview.comms-marketing.dispatched-emails.index' =>
    //         array_merge(
    //             ShowGroupOverviewHub::make()->getBreadcrumbs(),
    //             $headCrumb(
    //                 [
    //                     'name'       => $routeName,
    //                     'parameters' => $routeParameters
    //                 ]
    //             )
    //         ),
    //         default => []
    //     };
    // }
}
