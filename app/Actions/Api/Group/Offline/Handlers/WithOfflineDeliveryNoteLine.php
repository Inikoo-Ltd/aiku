<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-10h-20m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline\Handlers;

use App\Actions\Api\Group\GoodsOut\MobileClientRequest;
use App\Actions\Api\Group\Offline\Contracts\MobileOfflineContext;
use App\Actions\Api\Group\Offline\MobileOfflineOutcome;
use App\Actions\Dispatching\DeliveryNote\UI\ShowDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\WithDeliveryNoteHandler;
use App\Actions\Dispatching\DeliveryNoteItem\WithScannedDeliveryNoteItemMatching;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Dispatching\DeliveryNoteItem;
use App\Models\SysAdmin\MobileOfflineOperation;
use App\Models\SysAdmin\MobileOfflineOperationEffect;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

trait WithOfflineDeliveryNoteLine
{
    use WithDeliveryNoteHandler;
    use WithScannedDeliveryNoteItemMatching;

    public function kind(): string
    {
        return 'rules';
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{subject_type: string, subject_id: int, parent_type: ?string, parent_id: ?int}
     */
    public function subject(array $payload): array
    {
        return [
            'subject_type' => 'DeliveryNoteItem',
            'subject_id'   => (int) $payload['delivery_note_item_id'],
            'parent_type'  => 'DeliveryNote',
            'parent_id'    => (int) $payload['delivery_note_id'],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array{t: string, id: int, v?: int}>
     */
    public function touchedRows(array $payload): array
    {
        return [['t' => 'DeliveryNoteItem', 'id' => (int) $payload['delivery_note_item_id']]];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $rules
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validateLinePayload(array $payload, array $rules): array
    {
        return Validator::make($payload, [
            'delivery_note_id'      => ['required', 'integer', 'min:1'],
            'delivery_note_item_id' => ['required', 'integer', 'min:1'],
            'client_request_id'     => ['required', 'uuid'],
            ...$rules,
        ])->validate();
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function alreadyDone(MobileOfflineContext $context, array $payload): ?MobileOfflineOutcome
    {
        if (!MobileClientRequest::isDone($context->user(), $payload['client_request_id'] ?? null)) {
            return null;
        }

        return MobileOfflineOutcome::applied((float) $payload['quantity'], (float) $payload['quantity'], false, __('Already done.'));
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function markDone(MobileOfflineContext $context, array $payload): void
    {
        MobileClientRequest::markDoneAfterCommit($context->user(), $payload['client_request_id'] ?? null);
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function lockedLine(MobileOfflineContext $context, array $payload): ?DeliveryNoteItem
    {
        /** @var DeliveryNoteItem|null $deliveryNoteItem */
        $deliveryNoteItem = DeliveryNoteItem::whereKey((int) $payload['delivery_note_item_id'])->lockForUpdate()->first();

        if (!$deliveryNoteItem || $deliveryNoteItem->delivery_note_id !== (int) $payload['delivery_note_id']) {
            return null;
        }

        $deliveryNote = $deliveryNoteItem->deliveryNote;
        if (!$deliveryNote || $deliveryNote->warehouse_id !== $context->warehouse()->id) {
            return null;
        }

        return $deliveryNoteItem;
    }

    /**
     * @param array<int, DeliveryNoteStateEnum> $states
     */
    protected function lineRefusal(MobileOfflineContext $context, ?DeliveryNoteItem $deliveryNoteItem, array $states, string $instruction, float $requestedQuantity, ?string $setting = null): ?MobileOfflineOutcome
    {
        if (!$deliveryNoteItem) {
            return MobileOfflineOutcome::refused('not_found', $instruction, __('This line is no longer on the delivery note.'), $requestedQuantity);
        }

        $user         = $context->user();
        $deliveryNote = $deliveryNoteItem->deliveryNote;

        if (!$user->authorisedWarehouses()->whereKey($deliveryNote->warehouse_id)->exists()
            || !$deliveryNote->canBeViewedBy($user)
            || !$deliveryNote->canBeWorkedOnBy($user)) {
            return MobileOfflineOutcome::refused('no_right', $instruction, __('Only warehouse staff can work on this delivery note.'), $requestedQuantity);
        }

        if ($deliveryNote->state === DeliveryNoteStateEnum::CANCELLED) {
            return MobileOfflineOutcome::refused('note_cancelled', $instruction, __('Delivery note :reference was cancelled.', ['reference' => $deliveryNote->reference]), $requestedQuantity, null, $this->lineState($deliveryNoteItem));
        }

        if ($deliveryNote->isLockedInAurora() || !in_array($deliveryNote->state, $states, true)) {
            return MobileOfflineOutcome::refused('wrong_state', $instruction, __('Delivery note :reference is :state now.', [
                'reference' => $deliveryNote->reference,
                'state'     => DeliveryNoteStateEnum::labels()[$deliveryNote->state->value],
            ]), $requestedQuantity, null, $this->lineState($deliveryNoteItem));
        }

        if ($deliveryNote->pickingSessions()->exists()) {
            return MobileOfflineOutcome::refused('in_picking_session', $instruction, __('Delivery note :reference is in a picking session: do this line online.', ['reference' => $deliveryNote->reference]), $requestedQuantity);
        }

        if (!$this->isHandledBy($deliveryNote, $user)) {
            $handler = User::find(self::deliveryNoteHandlerId($deliveryNote));

            return MobileOfflineOutcome::refused('not_handler', $instruction, __('Somebody else is handling delivery note :reference.', ['reference' => $deliveryNote->reference]), $requestedQuantity, $handler?->id);
        }

        if ($setting && !$this->settingAllows($deliveryNote->organisation, $setting)) {
            return MobileOfflineOutcome::refused('setting_off', $instruction, $setting === 'allow_waiting'
                ? __('Waiting is not enabled for this organisation.')
                : __('Pickers can not mark lines as not picked in this organisation.'), $requestedQuantity);
        }

        return null;
    }

    protected function isHandledBy(DeliveryNote $deliveryNote, User $user): bool
    {
        $handlerId = self::deliveryNoteHandlerId($deliveryNote);

        return ($handlerId && $handlerId === $user->id) || self::useMobileDeliveryNoteClaim($deliveryNote, $user);
    }

    protected function settingAllows(Organisation $organisation, string $setting): bool
    {
        return match ($setting) {
            'allow_picker_set_not_picked' => ShowDeliveryNote::allowsPickerToSetNotPicked($organisation),
            default                       => (bool) data_get($organisation->settings, 'orders.'.$setting, false),
        };
    }

    protected function refusalFromException(Throwable $exception, string $instruction, float $requestedQuantity): MobileOfflineOutcome
    {
        if ($exception instanceof HttpException && $exception->getStatusCode() === 403) {
            return MobileOfflineOutcome::refused('no_right', $instruction, $exception->getMessage(), $requestedQuantity);
        }

        $message = $exception instanceof ValidationException
            ? (string) collect($exception->errors())->flatten()->first()
            : $exception->getMessage();

        return MobileOfflineOutcome::refused('wrong_state', $instruction, $message, $requestedQuantity);
    }

    protected function isKnownRefusal(Throwable $exception): bool
    {
        return $exception instanceof ValidationException
            || ($exception instanceof HttpException && in_array($exception->getStatusCode(), [403, 422], true));
    }

    /**
     * @return array<string, mixed>
     */
    protected function lineState(DeliveryNoteItem $deliveryNoteItem): array
    {
        $deliveryNoteItem = DeliveryNoteItem::findOrFail($deliveryNoteItem->id);

        return [
            'state'                      => $deliveryNoteItem->state->value,
            'quantity_required'          => (float) $deliveryNoteItem->quantity_required,
            'quantity_picked'            => (float) $deliveryNoteItem->quantity_picked,
            'quantity_not_picked'        => (float) $deliveryNoteItem->quantity_not_picked,
            'quantity_packed'            => (float) $deliveryNoteItem->quantity_packed,
            'quantity_waiting_warehouse' => (float) $deliveryNoteItem->quantity_waiting_warehouse,
            'quantity_waiting_crm'       => (float) $deliveryNoteItem->quantity_waiting_crm,
        ];
    }

    protected function createdEffect(MobileOfflineOperation $operation, string $modelType): ?MobileOfflineOperationEffect
    {
        return $operation->effects()
            ->where('effect', 'created')
            ->orderBy('id')
            ->get()
            ->first(fn (MobileOfflineOperationEffect $effect) => class_basename($effect->model_type) === $modelType);
    }

    protected function updatedEffect(MobileOfflineOperation $operation, string $modelType): ?MobileOfflineOperationEffect
    {
        return $operation->effects()
            ->where('effect', 'updated')
            ->orderBy('id')
            ->get()
            ->first(fn (MobileOfflineOperationEffect $effect) => class_basename($effect->model_type) === $modelType);
    }

    protected function operationLine(MobileOfflineOperation $operation): ?DeliveryNoteItem
    {
        return DeliveryNoteItem::find((int) data_get($operation->payload, 'delivery_note_item_id'));
    }

    protected function localTime(CarbonInterface $time, Organisation $organisation): string
    {
        return $time->copy()->timezone($organisation->timezone?->name ?? config('app.timezone'))->format('H:i');
    }

    protected function quantityLabel(DeliveryNoteItem $deliveryNoteItem, float $quantity): string
    {
        return $this->formatScanQuantity($deliveryNoteItem, $quantity);
    }

    /**
     * @throws ValidationException
     */
    protected function refuseRollback(string $message): never
    {
        throw ValidationException::withMessages(['rollback' => $message]);
    }
}
