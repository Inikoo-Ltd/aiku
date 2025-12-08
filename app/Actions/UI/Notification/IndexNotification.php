<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 08 Apr 2024 15:23:05 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Notification;

use App\Actions\UI\Dashboards\ShowGroupDashboard;
use App\Actions\UI\WithInertia;
use App\Http\Resources\SysAdmin\NotificationResource;
use App\InertiaTable\InertiaTable;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\QueryBuilder\AllowedFilter;

// use App\Models\CRM\WebUser;

class IndexNotification
{
    use AsAction;
    use WithInertia;
    // private User $parent;

    public function handle(User $user, $prefix = null): LengthAwarePaginator
    {
        // dd($user);
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

        $query = QueryBuilder::for($user->notifications());

        return $query->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function asController(ActionRequest $request): LengthAwarePaginator
    {
        return $this->handle($request->user(), 'notifications');
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
                    'type' => 'simple',
                    'simple' => [
                        'route' => $routeParameters,
                        'label' => __('notifications'),
                        'icon' => 'fal fa-bars',
                    ],
                ],
            ];
        };

        return match ($routeName) {
            'grp.notifications' => array_merge(
                ShowGroupDashboard::make()->getBreadcrumbs(),
                $headCrumb(
                    [
                        'name' => 'grp.notifications',
                        null,
                    ]
                ),
            ),

            default => []
        };
    }
}
