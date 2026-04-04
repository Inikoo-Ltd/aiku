<?php

namespace App\Actions\UI\Notification;

use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Enums\Notification\NotificationChannelEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Notifications\NotificationType;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class IndexNotificationTypes
{
    use AsAction;
    use WithNotificationSubNavigation;

    public function handle(array $filters = [], int $perPage = 50, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $query = NotificationType::query()
            ->when(request('notificationTypes_filter.global'), function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        ->orWhere('category', 'like', "%{$search}%");
                });
            })
            ->orderBy('category')
            ->orderBy('name');

        return $query->paginate($perPage)->withQueryString();
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->column(
                    key: 'name',
                    label: __('Name'),
                    sortable: true,
                    searchable: true
                )
                ->column(
                    key: 'slug',
                    label: __('Slug'),
                    sortable: true,
                    searchable: true
                )
                ->column(
                    key: 'category',
                    label: __('Category'),
                    sortable: true,
                    searchable: true
                )
                ->column(
                    key: 'description',
                    label: __('Description'),
                    sortable: false
                )
                ->column(
                    key: 'available_channels',
                    label: __('Available Channels'),
                    sortable: false
                )
                ->column(
                    key: 'default_channels',
                    label: __('Default Channels'),
                    sortable: false
                )
                ->column(
                    key: 'actions',
                    label: __('Actions'),
                    sortable: false
                );
        };
    }

    public function asController(ActionRequest $request): Response
    {
        $types = $this->handle([], 50, 'notificationTypes');

        return Inertia::render('SysAdmin/Notifications/Settings/NotificationTypes', [
            'title'    => __('Notification Types'),
            'breadcrumbs' => $this->getBreadcrumbs(),
            'types' => $types,
            'pageHead' => [
                'title'         => __('Notification Types'),
                'icon'          => ['fal', 'fa-bell'],
                'actions'       => [
                    [
                        'type'  => 'button',
                        'style' => 'create',
                        'key'   => 'new',
                        'label' => __('Add Type'),
                        'icon'  => ['fal', 'fa-plus'],
                    ],
                ],
                'subNavigation' => $this->getNotificationSubNavigation($request),
            ],
            'channelOptions' => NotificationChannelEnum::options(),
        ])->table($this->tableStructure('notificationTypes'));
    }

    public function getBreadcrumbs(): array
    {
        return array_merge(
            ShowGroupDashboard::make()->getBreadcrumbs(),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name' => 'grp.sysadmin.notification-settings.types',
                        ],
                        'label' => __('Notification Types'),
                    ]
                ]
            ]
        );
    }
}
