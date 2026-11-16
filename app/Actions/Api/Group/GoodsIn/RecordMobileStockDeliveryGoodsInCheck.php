<?php

/*
 * Author Louis Perez
 * Created on 16-11-2026-11h-33m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsIn;

use App\Actions\GoodsIn\StockDelivery\RecordStockDeliveryGoodsInCheck;
use App\Actions\GoodsIn\StockDelivery\UI\GetStockDeliveryGoodsInChecklist;
use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use App\Enums\Procurement\ProcurementNote\ProcurementNoteIncidentTypeEnum;
use App\Models\GoodsIn\StockDelivery;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class RecordMobileStockDeliveryGoodsInCheck
{
    use AsController;
    use WithMobileStockDeliveries;

    public function asController(Warehouse $warehouse, StockDelivery $stockDelivery, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanBookIn($user, $warehouse, $stockDelivery);

        $data = Validator::make($request->all(), [
            'key'               => ['required', 'string', 'max:100'],
            'result'            => ['required', Rule::in(['passed', 'failed'])],
            'body'              => ['required_if:result,failed', 'nullable', 'string', 'max:10000'],
            'incident_type'     => ['sometimes', 'nullable', Rule::enum(ProcurementNoteIncidentTypeEnum::class)],
            'incident_quantity' => ['sometimes', 'nullable', 'numeric', 'min:0'],
        ])->validate();

        if (!in_array($stockDelivery->state, GetStockDeliveryGoodsInChecklist::STATES, true)) {
            $this->refuseState(__('The checklist can not be filled while the delivery is :state.', ['state' => StockDeliveryStateEnum::labels()[$stockDelivery->state->value]]));
        }

        RecordStockDeliveryGoodsInCheck::make()->action($stockDelivery, Arr::only($data, ['key', 'result', 'body', 'incident_type', 'incident_quantity']), $user);

        return $this->respondWithStockDelivery($warehouse, $stockDelivery, $user, 'done', $data['result'] === 'failed'
            ? __('Check failed; an incident was opened for procurement.')
            : __('Check passed.'));
    }
}
