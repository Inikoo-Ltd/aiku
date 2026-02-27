<?php

namespace App\Actions\UI\Notification;

use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\InertiaTable\InertiaTable;
use App\Models\Notifications\UserNotificationSetting;
use App\Models\SysAdmin\User;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class IndexUsersNotificationSettings
{
    use AsAction;
    use WithNotificationSubNavigation;

    public function handle(User $authUser, array $filters = [], int $perPage = 50, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $usersQuery = User::query()
            ->where('group_id', $authUser->group_id)
            ->whereHas('notificationSettings')
            ->with([
                'notificationSettings.notificationType',
                'notificationSettings.scope',
            ])
            ->when(isset($filters['user_id']), fn ($q) => $q->where('id', $filters['user_id']))
            ->latest();

        $paginator = $usersQuery->paginate($perPage)->withQueryString();

        $paginator->setCollection(
            $paginator->getCollection()->map(function (User $user) {
                $settings = $user->notificationSettings->map(function (UserNotificationSetting $setting) {
                    $scopeName = __('Global');
                    if ($setting->scope_type) {
                        $scopeLabel = null;
                        if ($setting->scope) {
                            $scopeLabel = $setting->scope->name
                                ?? $setting->scope->code
                                ?? $setting->scope->contact_name
                                ?? $setting->scope->username
                                ?? $setting->scope->slug
                                ?? (string) $setting->scope->getKey();
                        }
                        $scopeName = class_basename($setting->scope_type) . ($scopeLabel ? ": {$scopeLabel}" : '');
                    }

                    $filters = [];
                    if (!empty($setting->filters) && is_array($setting->filters)) {
                        foreach ($setting->filters as $key => $value) {
                            if ($value === null || $value === '' || (is_array($value) && count($value) === 0)) {
                                continue;
                            }
                            // Just return the raw values for the frontend to format
                            $filters[$key] = $value;
                        }
                    }

                    return [
                        'type' => $setting->notificationType?->name ?? __('Unknown Type'),
                        'scope' => $scopeName,
                        'filters' => $filters,
                    ];
                })->sortBy(['type', 'scope'])->values();

                return [
                    'id'          => $user->id,
                    'user_name'   => $user->contact_name ?: $user->username,
                    'user_settings' => $settings,
                ];
            })
        );

        return $paginator;
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
                    key: 'user_name',
                    label: __('User'),
                    sortable: true,
                    searchable: true
                )
                ->column(
                    key: 'types',
                    label: __('Types'),
                    sortable: false
                )
                ->column(
                    key: 'scopes',
                    label: __('Scopes'),
                    sortable: false
                )
                ->column(
                    key: 'filters',
                    label: __('Filters'),
                    sortable: false
                );
        };
    }

    public function asController(ActionRequest $request): Response
    {
        $filters = $request->only(['user_id', 'notification_type_id', 'is_enabled', 'scope_type', 'scope_id']);
        $settings = $this->handle($request->user(), $filters, 50);

        return Inertia::render('SysAdmin/Notifications/Settings/UsersNotification', [
            'title'    => __('Notification settings'),
            'breadcrumbs' => $this->getBreadcrumbs(),
            'settings' => $settings,
            'pageHead' => [
                'title'         => __('Notification settings'),
                'icon'          => ['fal', 'fa-bell'],
                'actions'       => [
                    [
                        'type'  => 'button',
                        'style' => 'create',
                        'key'   => 'new',
                        'label' => __('Add User Notification'),
                        'icon'  => ['fal', 'fa-plus'],
                    ],
                ],
                'subNavigation' => $this->getNotificationSubNavigation($request),
            ]
        ])->table($this->tableStructure('userNotificationSettings'));
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
                            'name' => 'grp.sysadmin.notification-settings.users',
                        ],
                        'label' => __('Notification settings'),
                    ]
                ]
            ]
        );
    }


}
