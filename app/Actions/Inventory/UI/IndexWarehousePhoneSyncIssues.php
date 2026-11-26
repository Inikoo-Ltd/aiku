<?php

/*
 * Author Louis Perez
 * Created on 26-11-2026-10h-53m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Inventory\UI;

use App\Actions\Api\Group\Offline\MobileOfflineOperationPresenter;
use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\MobileOfflineOperation;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;
use Lorisleiva\Actions\ActionRequest;

/**
 * What the staff app did offline in this warehouse that could not be recorded exactly as done, read only, for the
 * stock supervisors who may resolve them: who, on which phone, what, on which record, why, and the stock check
 * task it raised. The rows are the same the app's Resolve conflicts screen shows.
 */
class IndexWarehousePhoneSyncIssues extends OrgAction
{
    public const array CLOSED_STATES = [
        MobileOfflineOperation::STATE_RESOLVED,
        MobileOfflineOperation::STATE_ROLLED_BACK,
        MobileOfflineOperation::STATE_EXPIRED,
    ];

    public const array REASON_CODES = [
        'over_required'      => 'AIKU-SYNC-001',
        'location_empty'     => 'AIKU-SYNC-002',
        'not_enough_stock'   => 'AIKU-SYNC-003',
        'other_change'       => 'AIKU-SYNC-004',
        'note_cancelled'     => 'AIKU-SYNC-005',
        'delivery_closed'    => 'AIKU-SYNC-006',
        'return_closed'      => 'AIKU-SYNC-007',
        'waiting_cleared'    => 'AIKU-SYNC-008',
        'in_picking_session' => 'AIKU-SYNC-009',
        'not_handler'        => 'AIKU-SYNC-010',
        'over_packed'        => 'AIKU-SYNC-011',
        'over_checked'       => 'AIKU-SYNC-012',
        'over_expected'      => 'AIKU-SYNC-013',
        'setting_off'        => 'AIKU-SYNC-014',
        'wrong_state'        => 'AIKU-SYNC-015',
        'no_right'           => 'AIKU-SYNC-016',
        'not_found'          => 'AIKU-SYNC-017',
        'invalid'            => 'AIKU-SYNC-018',
    ];

    private const string DOCS_URL = 'https://aiku.io/docs/';

    public static function canView(User $user, Warehouse $warehouse): bool
    {
        return $user->authTo("supervisor-stocks.$warehouse->id");
    }

    public function authorize(ActionRequest $request): bool
    {
        return self::canView($request->user(), $this->warehouse);
    }

    public static function issues(Warehouse $warehouse): Builder
    {
        return MobileOfflineOperation::query()
            ->where('mobile_offline_operations.warehouse_id', $warehouse->id)
            ->whereIn('mobile_offline_operations.state', [...MobileOfflineOperation::OPEN_STATES, ...self::CLOSED_STATES]);
    }

    /**
     * @return array{open: int, closed_last_30_days: int}
     */
    public static function summary(Warehouse $warehouse): array
    {
        return [
            'open'                => self::issues($warehouse)->whereIn('state', MobileOfflineOperation::OPEN_STATES)->count(),
            'closed_last_30_days' => self::issues($warehouse)->whereIn('state', self::CLOSED_STATES)->where('resolved_at', '>=', now()->subDays(30))->count(),
        ];
    }

    public function asController(Organisation $organisation, Warehouse $warehouse, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse);
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    protected function statusElements(Warehouse $warehouse): array
    {
        $counts = self::issues($warehouse)->toBase()->selectRaw('state, count(*) as total')->groupBy('state')->pluck('total', 'state');

        return [
            'open'   => [__('Needs someone'), (int) $counts->only(MobileOfflineOperation::OPEN_STATES)->sum()],
            'closed' => [__('Done'), (int) $counts->only(self::CLOSED_STATES)->sum()],
        ];
    }

    public function handle(Warehouse $warehouse, string $prefix = 'phone_sync_issues'): LengthAwarePaginator
    {
        InertiaTable::updateQueryBuilderParameters($prefix);

        $queryBuilder = QueryBuilder::for(self::issues($warehouse))
            ->with(['user', 'staffTask']);

        $queryBuilder->whereElementGroup(
            key: 'status',
            allowedElements: array_keys($this->statusElements($warehouse)),
            engine: fn ($query, $elements) => $query->whereIn('mobile_offline_operations.state', array_merge(
                in_array('open', $elements, true) ? MobileOfflineOperation::OPEN_STATES : [],
                in_array('closed', $elements, true) ? self::CLOSED_STATES : [],
            )),
            prefix: $prefix,
            default: 'open'
        );

        return $queryBuilder
            ->defaultSort('-synced_at')
            ->allowedSorts(['synced_at', 'performed_at', 'operation', 'state'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(LengthAwarePaginator $issues, User $viewer): array
    {
        $operations = $issues->getCollection();
        $devices    = $this->devices($operations->pluck('personal_access_token_id')->filter()->unique()->all());
        $items      = collect(app(MobileOfflineOperationPresenter::class)->items($operations, $viewer))->keyBy('operation_id');

        return $operations->map(function (MobileOfflineOperation $operation) use ($items, $devices) {
            $item = $items->get($operation->id, []);
            $code = self::REASON_CODES[$operation->outcome_reason] ?? null;

            return [
                'id'           => $operation->id,
                'since'        => $operation->synced_at?->toIso8601String(),
                'performed_at' => $operation->performed_at?->toIso8601String(),
                'staff'        => $operation->user?->contact_name ?: $operation->user?->username,
                'device'       => trim(implode(' · ', array_filter([
                    $devices[$operation->personal_access_token_id] ?? null,
                    $operation->platform,
                    $operation->app_version,
                ]))) ?: null,
                'action'       => self::operationLabel($operation->operation),
                'record'       => Arr::get($item, 'labels.title'),
                'record_sub'   => Arr::get($item, 'labels.subtitle'),
                'reason'       => Arr::get($item, 'message') ?: $operation->outcome_reason,
                'code'         => $code,
                'code_url'     => $code ? self::DOCS_URL.Str::lower($code) : null,
                'task'         => $operation->staffTask?->reference,
                'task_url'     => $operation->staffTask ? route('grp.tasks.index', ['task' => $operation->staffTask->reference]) : null,
                'state'        => $operation->state,
                'state_label'  => self::stateLabel($operation->state),
                'is_open'      => in_array($operation->state, MobileOfflineOperation::OPEN_STATES, true),
            ];
        })->values()->all();
    }

    /**
     * @param array<int, int> $tokenIds
     * @return array<int, string>
     */
    private function devices(array $tokenIds): array
    {
        return $tokenIds === [] ? [] : PersonalAccessToken::whereIn('id', $tokenIds)->pluck('name', 'id')
            ->map(fn (string $name) => Str::after($name, 'mobile|') ?: null)
            ->filter()
            ->all();
    }

    public static function operationLabel(string $operation): string
    {
        return match ($operation) {
            'pick'                      => __('Pick'),
            'not_picked'                => __('Not picked'),
            'set_waiting_warehouse'     => __('Waiting for the warehouse'),
            'set_waiting_crm'           => __('Waiting for customer service'),
            'pack'                      => __('Pack'),
            'move_stock'                => __('Move stock'),
            'place_stock_delivery_item' => __('Place a stock delivery item'),
            'return_put_back'           => __('Return put back'),
            'return_damaged'            => __('Return damaged'),
            'return_not_returned'       => __('Return not returned'),
            'audit_location'            => __('Location audit'),
            'audit_org_stock'           => __('SKO audit'),
            default                     => Str::ucfirst(str_replace('_', ' ', $operation)),
        };
    }

    public static function stateLabel(string $state): string
    {
        return match ($state) {
            MobileOfflineOperation::STATE_REFUSED        => __('Refused'),
            MobileOfflineOperation::STATE_PARTLY_APPLIED => __('Partly recorded'),
            MobileOfflineOperation::STATE_CONFLICT       => __('Conflict'),
            MobileOfflineOperation::STATE_BLOCKED        => __('Waiting for an earlier action'),
            MobileOfflineOperation::STATE_RESOLVED       => __('Resolved'),
            MobileOfflineOperation::STATE_ROLLED_BACK    => __('Rolled back'),
            MobileOfflineOperation::STATE_EXPIRED        => __('Expired'),
            default                                      => Str::ucfirst(str_replace('_', ' ', $state)),
        };
    }

    public function tableStructure(Warehouse $warehouse, string $prefix = 'phone_sync_issues'): Closure
    {
        return function (InertiaTable $table) use ($warehouse, $prefix) {
            $table->name($prefix)->pageName($prefix.'Page');

            $table->elementGroup(key: 'status', label: __('Status'), elements: $this->statusElements($warehouse), default: 'open');

            $table
                ->withLabelRecord([__('issue'), __('issues')])
                ->defaultSort('-synced_at')
                ->column(key: 'since', label: __('Since'), canBeHidden: false, sortable: true, type: 'date', className: 'whitespace-nowrap w-px')
                ->column(key: 'staff', label: __('Staff'), canBeHidden: false, className: 'whitespace-nowrap w-px')
                ->column(key: 'device', label: __('Phone'), canBeHidden: false, className: 'whitespace-nowrap w-px')
                ->column(key: 'action', label: __('Action'), canBeHidden: false, sortable: true, className: 'whitespace-nowrap w-px')
                ->column(key: 'record', label: __('Record'), canBeHidden: false, className: 'whitespace-nowrap w-px')
                ->column(key: 'reason', label: __('What differs'), canBeHidden: false, className: 'w-full max-w-0')
                ->column(key: 'task', label: __('Stock check task'), canBeHidden: false, className: 'whitespace-nowrap w-px')
                ->column(key: 'state', label: __('Status'), canBeHidden: false, sortable: true, className: 'whitespace-nowrap w-px');
        };
    }

    public function htmlResponse(LengthAwarePaginator $issues, ActionRequest $request): Response
    {
        $summary = self::summary($this->warehouse);
        $issues->setCollection(collect($this->rows($issues, $request->user())));

        return Inertia::render(
            'Org/Inventory/WarehousePhoneSyncIssues',
            [
                'breadcrumbs' => $this->getBreadcrumbs($request->route()->originalParameters()),
                'title'       => __('Phone sync issues'),
                'pageHead'    => [
                    'title'      => __('Phone sync issues'),
                    'icon'       => [
                        'icon'  => ['fal', 'fa-mobile-alt'],
                        'title' => __('Phone sync issues'),
                    ],
                    'afterTitle' => [
                        'label' => $summary['open']
                            ? trans_choice(':count needs someone|:count need someone', $summary['open'], ['count' => $summary['open']])
                            : __('Nothing needs anyone'),
                    ],
                ],
                'issues'      => JsonResource::collection($issues),
            ]
        )->table($this->tableStructure($this->warehouse));
    }

    public function getBreadcrumbs(array $routeParameters): array
    {
        return array_merge(
            ShowInventoryDashboard::make()->getBreadcrumbs($routeParameters),
            [
                [
                    'type'   => 'simple',
                    'simple' => [
                        'route' => [
                            'name'       => 'grp.org.warehouses.show.inventory.phone_sync_issues.index',
                            'parameters' => $routeParameters,
                        ],
                        'label' => __('Phone sync issues'),
                        'icon'  => 'fal fa-mobile-alt',
                    ],
                ],
            ]
        );
    }
}
