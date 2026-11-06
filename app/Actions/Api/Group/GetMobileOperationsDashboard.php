<?php

/*
 * Author Louis Perez
 * Created on 06-11-2026-10h-14m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group;

use App\Actions\UI\Dashboards\GetOperationsDashboardData;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMobileOperationsDashboard
{
    use AsAction;

    /**
     * Delivery notes per stage, as the web's Operations dashboard counts them, for one warehouse
     * or for every warehouse the user works in.
     */
    public function handle(User $user, ?Warehouse $warehouse = null): array
    {
        $data  = GetOperationsDashboardData::make()->handle($user, ['warehouse' => $warehouse?->id]);
        $slugs = Warehouse::whereIn('id', collect($data['warehouses'])->pluck('id'))->pluck('slug', 'id');

        return [
            'generated_at' => $data['generated_at'],
            'warehouses'   => collect($data['warehouses'])->map(fn (array $row) => [
                'slug'         => $slugs[$row['id']] ?? null,
                'name'         => $row['name'],
                'organisation' => $row['organisation'],
            ])->values()->all(),
            'stages'       => collect($this->stageLabels())->map(fn (string $label, string $key) => [
                'key'   => $key,
                'label' => $label,
                'count' => (int) Arr::get($data, "pipeline.$key.count", 0),
            ])->values()->all(),
        ];
    }

    /**
     * The web dashboard's names for its stages (OperationsDashboard.vue), in its order.
     */
    private function stageLabels(): array
    {
        return [
            'unassigned' => __('To assign'),
            'queued'     => __('Queued'),
            'picking'    => __('Picking'),
            'blocked'    => __('Blocked'),
            'packing'    => __('Packing'),
            'packed'     => __('Packed'),
            'finalised'  => __('Waiting for dispatch'),
            'dispatched' => __('Dispatched today'),
        ];
    }

    /**
     * Checked in each entry point rather than in authorize(): Laravel Actions skips authorize()
     * on a route that names a method, such as inWarehouse.
     */
    private function abortUnlessAllowed(User $user, ?Warehouse $warehouse): void
    {
        $warehouses = GetOperationsDashboardData::warehousesFor($user);

        abort_unless(
            $warehouse ? $warehouses->contains('id', $warehouse->id) : $warehouses->isNotEmpty(),
            403
        );
    }

    public function asController(ActionRequest $request): array
    {
        $this->abortUnlessAllowed($request->user(), null);

        return $this->handle($request->user());
    }

    public function inWarehouse(Warehouse $warehouse, ActionRequest $request): array
    {
        $this->abortUnlessAllowed($request->user(), $warehouse);

        return $this->handle($request->user(), $warehouse);
    }
}
