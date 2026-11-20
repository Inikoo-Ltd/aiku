<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-09h-54m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline\Handlers;

use App\Actions\Api\Group\GoodsIn\WithMobileReturns;
use App\Actions\Api\Group\Offline\Contracts\MobileOfflineContext;
use App\Actions\Api\Group\Offline\Contracts\MobileOfflineHandler;
use App\Actions\Api\Group\Offline\MobileOfflineOutcome;
use App\Actions\GoodsIn\ReturnDeliveryNoteItem\CalculateReturnDeliveryNoteItemTotalSowed;
use App\Actions\GoodsIn\Sowing\DeleteSowing;
use App\Actions\GoodsIn\Sowing\StoreSowing;
use App\Enums\GoodsIn\ReturnDeliveryNote\ReturnDeliveryNoteStateEnum;
use App\Enums\GoodsIn\Sowing\SowingTypeEnum;
use App\Models\GoodsIn\ReturnDeliveryNote;
use App\Models\GoodsIn\ReturnDeliveryNoteItem;
use App\Models\GoodsIn\Sowing;
use App\Models\Inventory\LocationOrgStock;
use App\Models\SysAdmin\MobileOfflineOperation;
use App\Models\SysAdmin\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

abstract class ReturnPutOfflineHandler implements MobileOfflineHandler
{
    use WithMobileOfflineStockHandling;
    use WithMobileReturns;

    public const float TOLERANCE = 0.00005;

    abstract protected function sowingType(): SowingTypeEnum;

    public function kind(): string
    {
        return self::KIND_RULES;
    }

    protected function putsBackToStock(): bool
    {
        return $this->sowingType() === SowingTypeEnum::SOW;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validatePayload(array $payload): array
    {
        return Validator::make($payload, array_filter([
            'return_delivery_note_id'      => ['required', 'integer', 'min:1', 'max:'.self::MAX_ID],
            'return_delivery_note_item_id' => ['required', 'integer', 'min:1', 'max:'.self::MAX_ID],
            'quantity'                     => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'location_org_stock_id'        => $this->putsBackToStock() ? ['required', 'integer', 'min:1', 'max:'.self::MAX_ID] : null,
            'client_request_id'            => ['required', 'uuid'],
        ]))->validate();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{subject_type: string, subject_id: int, parent_type: ?string, parent_id: ?int}
     */
    public function subject(array $payload): array
    {
        return [
            'subject_type' => 'ReturnDeliveryNoteItem',
            'subject_id'   => (int) $payload['return_delivery_note_item_id'],
            'parent_type'  => 'ReturnDeliveryNote',
            'parent_id'    => (int) $payload['return_delivery_note_id'],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, array{t: string, id: int}>
     */
    public function touchedRows(array $payload): array
    {
        $rows = [['t' => 'ReturnDeliveryNoteItem', 'id' => (int) $payload['return_delivery_note_item_id']]];

        if ($this->putsBackToStock()) {
            $rows[] = ['t' => 'LocationOrgStock', 'id' => (int) $payload['location_org_stock_id']];
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws \Throwable
     */
    public function apply(MobileOfflineContext $context, array $payload): MobileOfflineOutcome
    {
        $requested = (float) $payload['quantity'];

        if ($alreadyDone = $this->alreadyDoneOnline($context, $payload, $requested)) {
            return $alreadyDone;
        }

        $user      = $context->user();
        $warehouse = $context->warehouse();

        $returnDeliveryNote = ReturnDeliveryNote::find((int) $payload['return_delivery_note_id']);
        $item               = ReturnDeliveryNoteItem::find((int) $payload['return_delivery_note_item_id']);
        if (!$returnDeliveryNote || !$item || $item->return_delivery_note_id !== $returnDeliveryNote->id || $returnDeliveryNote->warehouse_id !== $warehouse->id) {
            return MobileOfflineOutcome::refused(self::REASON_NOT_FOUND, MobileOfflineOutcome::INSTRUCTION_NONE, __('This return item is not in this warehouse any more.'), $requested);
        }

        if (!self::canViewReturns($user, $warehouse) || !self::canHandleReturns($user, $warehouse)) {
            return MobileOfflineOutcome::refused(self::REASON_NO_RIGHT, $this->putsBackToStock() ? MobileOfflineOutcome::INSTRUCTION_CHECK_SHELF : MobileOfflineOutcome::INSTRUCTION_NONE, __('You can not work on returns.'), $requested);
        }

        $row = null;
        if ($this->putsBackToStock()) {
            $row = LocationOrgStock::with('location')->find((int) $payload['location_org_stock_id']);
            if (!$row || $row->warehouse_id !== $warehouse->id || $row->org_stock_id !== $item->org_stock_id) {
                return MobileOfflineOutcome::refused(self::REASON_NOT_FOUND, MobileOfflineOutcome::INSTRUCTION_NONE, $this->locationRefusalMessage(self::REASON_NOT_FOUND), $requested);
            }
        }

        $returnDeliveryNote = ReturnDeliveryNote::lockForUpdate()->findOrFail($returnDeliveryNote->id);
        $item               = ReturnDeliveryNoteItem::lockForUpdate()->findOrFail($item->id);
        if ($row) {
            $location = $row->location;
            $row      = $this->lockLocationOrgStocks([$row->id])->get($row->id)->setRelation('location', $location);
        }
        $countedSince = $row !== null && $context->countedSince($row);

        if ($returnDeliveryNote->state !== ReturnDeliveryNoteStateEnum::RETURNING) {
            $description = __('Return :reference: :quantity of :sko was :done offline after the return was closed, so it was not recorded. Customer services must check the return before any refund.', [
                'reference' => $returnDeliveryNote->reference,
                'quantity'  => $this->quantityLabel($requested),
                'sko'       => $item->orgStock?->code,
                'done'      => $this->doneLabel(),
            ]);
            if ($row && !$countedSince) {
                $description .= ' '.$this->stockCheckDescription($item, $returnDeliveryNote, $requested, $row);
            }
            $context->requestStockCheck($returnDeliveryNote, $description);

            return MobileOfflineOutcome::refused('return_closed', $this->extraInstruction(), __('This return is no longer being processed, so this was not recorded. Customer services were asked to check it before any refund.'), $requested);
        }

        if ($row && $reason = $this->locationRefusalReason($row->location, $warehouse->id)) {
            return $this->refusedOnShelf($context, $row, $countedSince, $reason, $this->locationRefusalMessage($reason), $requested, $item, $returnDeliveryNote);
        }

        $remaining = $this->remainingOf($item);
        if ($remaining <= self::TOLERANCE) {
            return $this->refusedOnShelf($context, $row, $countedSince, 'over_expected', __('Nothing was left to handle on this item, so this was not recorded.'), $requested, $item, $returnDeliveryNote);
        }

        $applied        = min($requested, $remaining);
        $before         = $item->only(['total_item_returned', 'total_item_damaged', 'total_item_not_returned']);
        $lastSowingId   = (int) Sowing::max('id');
        $lastMovementId = $this->lastMovementId();

        $result = $this->inSavepoint(function () use ($item, $user, $applied, $row, $countedSince) {
            StoreSowing::make()->action($item, $user, array_filter([
                'quantity'              => $applied,
                'type'                  => $this->sowingType(),
                'sower_user_id'         => $user->id,
                'location_org_stock_id' => $row?->id,
            ]), $countedSince);

            return CalculateReturnDeliveryNoteItemTotalSowed::make()->action($item);
        }, $requested);

        if ($result instanceof MobileOfflineOutcome) {
            return $result;
        }

        Sowing::where('return_item_id', $item->id)->where('id', '>', $lastSowingId)->orderBy('id')->get()->each(fn (Sowing $sowing) => $context->recordCreated($sowing));
        $context->recordUpdated($result, $before, $result->only(array_keys($before)));
        if ($row) {
            $this->recordMovementsSince($context, $item->org_stock_id, [$row->location_id], $lastMovementId);
        }
        $this->rememberClientRequest($context, $payload);

        $message = $this->doneMessage($applied, $row);

        if ($requested - $applied > self::TOLERANCE) {
            $extra = $requested - $applied;
            if ($row && !$countedSince) {
                $context->requestStockCheck($row, $this->stockCheckDescription($item, $returnDeliveryNote, $extra, $row));
            }

            return MobileOfflineOutcome::partlyApplied($requested, $applied, 'over_expected', $this->extraInstruction(), $message.' '.__('Only :applied was left on this item, so :extra more are not recorded.', [
                'applied' => $this->quantityLabel($applied),
                'extra'   => $this->quantityLabel($extra),
            ]), $countedSince);
        }

        return MobileOfflineOutcome::applied($requested, $applied, $countedSince, $message);
    }

    private function extraInstruction(): string
    {
        return $this->putsBackToStock() ? MobileOfflineOutcome::INSTRUCTION_CHECK_SHELF : MobileOfflineOutcome::INSTRUCTION_NONE;
    }

    private function refusedOnShelf(MobileOfflineContext $context, ?LocationOrgStock $row, bool $countedSince, string $reason, string $message, float $requested, ReturnDeliveryNoteItem $item, ReturnDeliveryNote $returnDeliveryNote): MobileOfflineOutcome
    {
        if ($row && !$countedSince) {
            $context->requestStockCheck($row, $this->stockCheckDescription($item, $returnDeliveryNote, $requested, $row));
        }

        return MobileOfflineOutcome::refused($reason, $this->extraInstruction(), $message, $requested);
    }

    private function stockCheckDescription(ReturnDeliveryNoteItem $item, ReturnDeliveryNote $returnDeliveryNote, float $quantity, LocationOrgStock $row): string
    {
        return __('Offline put back of :quantity of :sko from return :reference in :location was not recorded. Count the location.', [
            'quantity'  => $this->quantityLabel($quantity),
            'sko'       => $item->orgStock?->code,
            'reference' => $returnDeliveryNote->reference,
            'location'  => $row->location?->code,
        ]);
    }

    private function doneLabel(): string
    {
        return match ($this->sowingType()) {
            SowingTypeEnum::SOW     => __('put back to stock'),
            SowingTypeEnum::DAMAGED => __('marked as damaged'),
            SowingTypeEnum::NOT_SOW => __('marked as not returned'),
        };
    }

    private function doneMessage(float $quantity, ?LocationOrgStock $row): string
    {
        return match ($this->sowingType()) {
            SowingTypeEnum::SOW     => __('Put :quantity back in :location.', ['quantity' => $this->quantityLabel($quantity), 'location' => $row?->location?->code]),
            SowingTypeEnum::DAMAGED => __(':quantity marked as damaged; it does not go back to stock.', ['quantity' => $this->quantityLabel($quantity)]),
            SowingTypeEnum::NOT_SOW => __(':quantity marked as not returned.', ['quantity' => $this->quantityLabel($quantity)]),
        };
    }

    public function canRollback(MobileOfflineOperation $operation): bool
    {
        return $this->rollbackRefusal($operation) === null;
    }

    /**
     * @throws ValidationException
     * @throws \Throwable
     */
    public function rollback(MobileOfflineOperation $operation, User $user): void
    {
        DB::transaction(function () use ($operation, $user) {
            ReturnDeliveryNote::lockForUpdate()->find((int) ($operation->payload['return_delivery_note_id'] ?? 0));

            if ($refusal = $this->rollbackRefusal($operation)) {
                $this->refusedRollback($refusal);
            }

            foreach ($this->createdSowings($operation) as $sowing) {
                DeleteSowing::make()->action(Sowing::lockForUpdate()->findOrFail($sowing->id), $user);
            }
        });
    }

    private function rollbackRefusal(MobileOfflineOperation $operation): ?string
    {
        if ($this->createdSowings($operation)->isEmpty()) {
            return __('This was already undone.');
        }

        $returnDeliveryNote = ReturnDeliveryNote::find((int) ($operation->payload['return_delivery_note_id'] ?? 0));
        if (!$returnDeliveryNote || $returnDeliveryNote->state !== ReturnDeliveryNoteStateEnum::RETURNING) {
            return __('A put-back can only be undone while the return is being returned to locations');
        }

        return null;
    }

    /**
     * @return Collection<int, Sowing>
     */
    private function createdSowings(MobileOfflineOperation $operation): Collection
    {
        return Sowing::whereIn('id', $this->createdEffectIds($operation, new Sowing()))->orderBy('id')->get();
    }
}
