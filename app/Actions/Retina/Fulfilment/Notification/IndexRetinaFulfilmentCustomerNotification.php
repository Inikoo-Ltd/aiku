<?php
/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-08h-45m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Fulfilment\Notification;

use App\Actions\Retina\UI\Dashboard\ShowRetinaDashboard;
use App\Actions\RetinaAction;
use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Http\Resources\SysAdmin\NotificationResource;
use App\InertiaTable\InertiaTable;
use App\Models\Fulfilment\FulfilmentCustomer;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\AllowedFilter;

// use App\Models\CRM\WebUser;

class IndexRetinaFulfilmentCustomerNotification extends RetinaAction
{
    use AsAction;
    use WithInertia;

    public function handle(FulfilmentCustomer $fulfilmentCustomer, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('filter', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                if ($value == 'unread') {
                    $query->whereNull('notifications.read_at');
                }

                if ($value == 'read') {
                    $query->whereNotNull('notifications.read_at');
                }
            });
        });


        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = QueryBuilder::for($fulfilmentCustomer->notifications());

        return $query->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($request);
        return $this->handle($this->fulfilmentCustomer, 'notifications');
    }

    public function jsonResponse(LengthAwarePaginator $notifications): AnonymousResourceCollection
    {
        return NotificationResource::collection($notifications);
    }

    public function getBreadcrumbs(string $routeName): array
    {
        $headCrumb = function (array $routeParameters = []) {
            return [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('notifications'),
                        'icon'  => 'fal fa-bars'
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.notifications' =>
            array_merge(
                ShowRetinaDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name' => 'retina.fulfilment.notifications',
                        null
                    ]
                ),
            ),

            default => []
        };
    }
}
