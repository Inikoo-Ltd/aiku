<?php

/*
 * Author Louis Perez
 * Created on 17-11-2026-10h-05m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Fulfilment;

use App\Actions\Fulfilment\StoredItemAuditDelta\StoreStoredItemAuditDelta;
use App\Actions\Fulfilment\StoredItemAuditDelta\UpdateStoredItemAuditDelta;
use App\Models\Fulfilment\Pallet;
use App\Models\Fulfilment\StoredItem;
use App\Models\Fulfilment\StoredItemAudit;
use App\Models\Fulfilment\StoredItemAuditDelta;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Throwable;

class CountMobileStoredItemAudit
{
    use AsController;
    use WithMobileStoredItemAudits;

    /**
     * @throws Throwable
     */
    public function asController(Warehouse $warehouse, StoredItemAudit $storedItemAudit, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanAuditStoredItems($user, $warehouse);
        $pallet = $this->palletOfAudit($warehouse, $storedItemAudit);

        $data = Validator::make($request->all(), [
            'stored_item_id'    => ['required_without:barcode', 'prohibits:barcode', 'integer', 'min:1', 'max:'.self::MAX_ID],
            'barcode'           => ['required_without:stored_item_id', 'string', 'max:255'],
            'audited_quantity'  => ['required_without:add', 'prohibits:add', 'integer', 'min:0', 'max:1000000'],
            'add'               => ['required_without:audited_quantity', 'integer', 'min:1', 'max:1000000'],
            'client_request_id' => ['required_with:add', 'uuid'],
        ])->validate();

        return $this->oncePerRequest(
            $user,
            isset($data['add']) ? $data['client_request_id'] : null,
            fn () => $this->respondWithAudit($storedItemAudit, $pallet, 'done', $this->changeAudit(
                $storedItemAudit,
                fn (StoredItemAudit $storedItemAudit) => $this->countItem($storedItemAudit, $pallet, $user, $data)
            )),
            fn () => $this->respondWithAudit($storedItemAudit, $pallet, 'nothing_changed', __('Already counted.'))
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @throws ValidationException
     */
    private function countItem(StoredItemAudit $storedItemAudit, Pallet $pallet, User $user, array $data): string
    {
        $field      = isset($data['stored_item_id']) ? 'stored_item_id' : 'barcode';
        $storedItem = $field === 'stored_item_id'
            ? $this->storedItemOfCustomer($pallet, (int) $data['stored_item_id'])
            : $this->storedItemByCode($pallet, trim($data['barcode']));

        /** @var StoredItemAuditDelta|null $delta */
        $delta = $storedItemAudit->deltas()->where('pallet_id', $pallet->id)->where('stored_item_id', $storedItem->id)->first();

        $auditedQuantity = isset($data['add'])
            ? (int) ($delta?->audited_quantity ?? 0) + (int) $data['add']
            : (int) $data['audited_quantity'];

        try {
            if ($delta) {
                UpdateStoredItemAuditDelta::make()->handle($delta, ['audited_quantity' => $auditedQuantity, 'user_id' => $user->id]);
            } else {
                StoreStoredItemAuditDelta::make()->action($storedItemAudit, [
                    'pallet_id'        => $pallet->id,
                    'stored_item_id'   => $storedItem->id,
                    'audited_quantity' => $auditedQuantity,
                    'user_id'          => $user->id,
                ]);
            }
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([$field => collect($exception->errors())->flatten()->first()]);
        }

        return __('Counted :quantity × :reference.', ['quantity' => $auditedQuantity, 'reference' => $storedItem->reference]);
    }

    /**
     * @throws ValidationException
     */
    private function storedItemOfCustomer(Pallet $pallet, int $storedItemId): StoredItem
    {
        $storedItem = StoredItem::where('fulfilment_customer_id', $pallet->fulfilment_customer_id)->find($storedItemId);

        if (!$storedItem) {
            throw ValidationException::withMessages(['stored_item_id' => __('That item does not belong to the customer of this pallet.')]);
        }

        return $storedItem;
    }

    /**
     * @throws ValidationException
     */
    private function storedItemByCode(Pallet $pallet, string $code): StoredItem
    {
        $matches = StoredItem::where('fulfilment_customer_id', $pallet->fulfilment_customer_id)
            ->where(fn ($query) => $query->whereRaw('LOWER(reference) = LOWER(?)', [$code])->orWhereRaw('LOWER(barcode) = LOWER(?)', [$code]))
            ->limit(2)
            ->get();

        if ($matches->isEmpty()) {
            throw ValidationException::withMessages(['barcode' => __('No item of this customer has the code :code.', ['code' => $code])]);
        }

        if ($matches->count() > 1) {
            throw ValidationException::withMessages(['barcode' => __('Several items of this customer have the code :code; pick the item from the list.', ['code' => $code])]);
        }

        return $matches->first();
    }
}
