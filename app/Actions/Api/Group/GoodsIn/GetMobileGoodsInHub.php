<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-11h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsIn;

use App\Actions\Api\Group\GetMobileWarehouses;
use App\Actions\UI\Incoming\GetIncomingHubPalletDeliveryWidget;
use App\Actions\UI\Incoming\GetIncomingHubReturnDeliveryNoteWidget;
use App\Actions\UI\Incoming\GetIncomingHubStockDeliveryWidget;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class GetMobileGoodsInHub
{
    use AsController;
    use WithMobileGoodsIn;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function handle(Warehouse $warehouse, User $user): array
    {
        $can  = app(GetMobileWarehouses::class)->abilities($user, $warehouse);
        $rows = [
            $this->row('stock_deliveries', __('Stock Deliveries'), GetIncomingHubStockDeliveryWidget::run($warehouse, ['OrgSupplier', 'Production'])),
            $this->row('partners', __('Partners'), GetIncomingHubStockDeliveryWidget::run($warehouse, ['OrgPartner'])),
            $this->row('agents', __('Agents'), GetIncomingHubStockDeliveryWidget::run($warehouse, ['OrgAgent'])),
        ];

        if ($can['view_fulfilment']) {
            $rows[] = $this->row('fulfilment', __('Fulfilment Deliveries'), GetIncomingHubPalletDeliveryWidget::run($warehouse));
        }

        if ($can['view_returns']) {
            $rows[] = $this->row('returns', __('Returns'), GetIncomingHubReturnDeliveryNoteWidget::run($warehouse));
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $widget
     * @return array<string, mixed>
     */
    private function row(string $key, string $label, array $widget): array
    {
        $figures = Arr::get($widget, 'data._global', []);
        $figure  = fn (string $metric, string $part = 'value') => Arr::has($figures, "$metric.$part") ? (int) Arr::get($figures, "$metric.$part") : null;

        return [
            'key'          => $key,
            'label'        => $label,
            'arriving'     => $figure('arriving'),
            'received'     => $figure('received'),
            'checked'      => $figure('checked'),
            'booking_in'   => $figure('booking_in'),
            'booked_in_7d' => $figure('booked_in'),
            'placed_7d'    => $figure('booked_in', 'suffix.value'),
        ];
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureWarehouseAccess($user, $warehouse, self::canViewGoodsIn($user, $warehouse));

        return response()->json(['data' => $this->handle($warehouse, $user)]);
    }
}
