<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-14h-45m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNoteItem\UpdateDeliveryNoteItemPacking;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PackMobileDeliveryNoteItem
{
    use AsController;
    use WithMobilePickingGuards;
    use WithMobilePackingMessages;

    /**
     * @throws \Throwable
     */
    public function asController(DeliveryNote $deliveryNote, DeliveryNoteItem $deliveryNoteItem, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->ensureCanWork($deliveryNote, $user);
        $this->ensureState($deliveryNote, [DeliveryNoteStateEnum::PACKING]);
        $this->ensureCanHandle($deliveryNote);
        $this->ensureLineOfNote($deliveryNote, $deliveryNoteItem);

        $data = Validator::make($request->all(), [
            'quantity' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
        ])->validate();

        $packedBefore = $this->packedQuantity($deliveryNoteItem);
        $warning      = null;

        try {
            UpdateDeliveryNoteItemPacking::make()->action($deliveryNoteItem, $user, isset($data['quantity']) ? (float) $data['quantity'] : null);
        } catch (ValidationException|HttpException $exception) {
            $warning = $this->promotionRefusal($exception, $deliveryNoteItem, $packedBefore);
        }

        $deliveryNoteItem = DeliveryNoteItem::with('orgStock')->findOrFail($deliveryNoteItem->id);
        [, $message]      = $this->packedMessage($deliveryNoteItem, $this->packedQuantity($deliveryNoteItem) - $packedBefore, (string) $deliveryNoteItem->orgStock?->code);

        return $this->respondWith($deliveryNote, $user, 'done', $message, [
            'warning' => $warning ?? $this->packedButNotPromotedReason($deliveryNote),
        ]);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    private function promotionRefusal(ValidationException|HttpException $exception, DeliveryNoteItem $deliveryNoteItem, float $packedBefore): string
    {
        $isRefusal = $exception instanceof ValidationException || $exception->getStatusCode() === 422;

        if (!$isRefusal || $this->packedQuantity($deliveryNoteItem) === $packedBefore) {
            if ($exception instanceof HttpException && $isRefusal) {
                throw ValidationException::withMessages(['state' => $exception->getMessage()]);
            }

            throw $exception;
        }

        return $exception instanceof ValidationException
            ? collect($exception->errors())->flatten()->first()
            : $exception->getMessage();
    }
}
