<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\PalletReturnItem\PickPalletReturnItemByScan;
use App\Enums\Fulfilment\PalletReturn\PalletReturnItemStateEnum;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\PalletReturn;
use App\Models\Fulfilment\PalletReturnItem;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Throwable;

class PickMobilePalletReturnByScan
{
    use AsController;
    use WithMobilePalletReturns;

    /**
     * @throws Throwable
     */
    public function asController(Warehouse $warehouse, PalletReturn $palletReturn, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanViewPalletReturn($user, $warehouse, $palletReturn);
        abort_unless($this->scanToPickEnabled($warehouse), 403, __('Scan to pick is not enabled for this organisation.'));
        abort_unless(self::canEditFulfilmentWarehouse($user, $warehouse), 403, __('You can not work on the returns of this warehouse.'));

        $data = Validator::make($request->all(), [
            'barcode'           => ['required', 'string', 'max:255'],
            'quantity'          => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000000'],
            'client_request_id' => ['required', 'uuid'],
        ])->validate();

        return $this->oncePerRequest(
            $user,
            $data['client_request_id'],
            function () use ($warehouse, $palletReturn, $user, $data) {
                $outcome = $this->changePalletReturn($palletReturn, fn (PalletReturn $palletReturn) => $this->scan($palletReturn, $user, trim($data['barcode']), (int) ($data['quantity'] ?? 1)));

                return $this->respondWithPalletReturn($warehouse, $palletReturn, $user, $outcome['status'], $outcome['message'], ['line_id' => $outcome['line_id']]);
            },
            fn () => $this->respondWithPalletReturn($warehouse, $palletReturn, $user, 'nothing_changed', __('This scan was already handled.'), ['line_id' => null])
        );
    }

    /**
     * @return array{status: string, message: string, line_id: int|null}
     *
     * @throws Throwable
     */
    private function scan(PalletReturn $palletReturn, User $user, string $barcode, int $quantity): array
    {
        $scanPicker = PickPalletReturnItemByScan::make();
        $lines      = $scanPicker->loadItems($palletReturn);
        $matched    = $scanPicker->matchItems($lines, $barcode);
        $blocked    = $matched->filter(fn (PalletReturnItem $line) => $this->scanBlockedReason($line, $scanPicker) !== null);

        if ($palletReturn->state === PalletReturnStateEnum::PICKING && $matched->isNotEmpty() && $blocked->count() === $matched->count()) {
            $line = $blocked->first();

            return ['status' => 'not_found', 'message' => $this->scanBlockedReason($line, $scanPicker), 'line_id' => $line->id];
        }

        $outcome = $scanPicker->handle(
            $palletReturn,
            $user,
            ['barcode' => $barcode, 'quantity' => $quantity],
            $lines->reject(fn (PalletReturnItem $line) => $blocked->contains('id', $line->id))->values()
        );

        return ['status' => $outcome['status'], 'message' => $outcome['message'], 'line_id' => $outcome['item']['id'] ?? null];
    }

    private function scanBlockedReason(PalletReturnItem $line, PickPalletReturnItemByScan $scanPicker): ?string
    {
        $reference = $scanPicker->itemReference($line);

        return match (true) {
            (bool) $line->has_waiting_crm => __(':reference is waiting for customer service, it can not be picked.', ['reference' => $reference]),
            $line->state === PalletReturnItemStateEnum::NOT_PICKED,
            $line->type === 'StoredItem' && $line->getQuantityLeftToPick() <= 0 && (float) $line->quantity_picked <= 0 && (float) $line->quantity_not_picked > 0 => __(':reference was set as not picked.', ['reference' => $reference]),
            default => null,
        };
    }
}
