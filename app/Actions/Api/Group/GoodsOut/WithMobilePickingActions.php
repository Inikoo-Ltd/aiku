<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-13h-56m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNote\UI\ShowDeliveryNote;
use App\Actions\Dispatching\DeliveryNote\WithDeliveryNoteHandler;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;

trait WithMobilePickingActions
{
    use WithDeliveryNoteHandler;
    use WithMobilePackingActions;

    /**
     * @return array{allow_scan_to_pick: bool, allow_waiting: bool, allow_picker_set_not_picked: bool, allow_picker_choose_location: bool, allow_scan_to_pack: bool}
     */
    protected function pickingSettings(Organisation $organisation): array
    {
        return [
            'allow_scan_to_pick'           => (bool) data_get($organisation->settings, 'orders.allow_scan_to_pick', false),
            'allow_waiting'                => (bool) data_get($organisation->settings, 'orders.allow_waiting', false),
            'allow_picker_set_not_picked'  => ShowDeliveryNote::allowsPickerToSetNotPicked($organisation),
            'allow_picker_choose_location' => (bool) data_get($organisation->settings, 'orders.allow_picker_choose_location', false),
            'allow_scan_to_pack'           => (bool) data_get($organisation->settings, 'orders.allow_scan_to_pack', false),
        ];
    }

    protected function isDropshipping(DeliveryNote $deliveryNote): bool
    {
        return $deliveryNote->shop->type == ShopTypeEnum::DROPSHIPPING;
    }

    /**
     * @return array<int, array{key: string, label: string, disabled_reason?: string}>
     */
    protected function availableActions(DeliveryNote $deliveryNote, User $user, bool $isEditable, bool $canHandle): array
    {
        if ($deliveryNote->isLockedInAurora()) {
            return [];
        }

        $canShip            = $deliveryNote->canBeShippedBy($user);
        $isInPickingSession = $deliveryNote->pickingSessions()->exists();

        $actions = match ($deliveryNote->state) {
            DeliveryNoteStateEnum::UNASSIGNED       => $isEditable ? array_merge(
                ['start_picking' => __('Start picking')],
                $isInPickingSession ? [] : ['assign_picker' => __('Assign picker')]
            ) : [],
            DeliveryNoteStateEnum::QUEUED           => $isEditable ? array_merge(
                ['start_picking' => $deliveryNote->picker_user_id == $user->id ? __('Start picking') : __('I will pick this')],
                $isInPickingSession ? [] : ['assign_picker' => __('Change Picker'), 'remove_picker' => __('Remove Picker')]
            ) : [],
            DeliveryNoteStateEnum::HANDLING         => $isEditable ? $this->handlingActions($deliveryNote, $canHandle) : [],
            DeliveryNoteStateEnum::HANDLING_BLOCKED => $isEditable ? $this->handlingBlockedActions($deliveryNote, $user, $canHandle) : [],
            DeliveryNoteStateEnum::PICKED           => $isEditable ? $this->pickedActions($deliveryNote, $canHandle) : [],
            DeliveryNoteStateEnum::PACKING          => $isEditable ? $this->packingActions($deliveryNote, $canHandle) : [],
            DeliveryNoteStateEnum::PACKED           => $this->packedActions($deliveryNote, $isEditable, $canShip),
            DeliveryNoteStateEnum::FINALISED        => $this->finalisedActions($deliveryNote, $isEditable, $canShip),
            default                                 => [],
        };

        return $this->listedActions($deliveryNote, $actions + $this->editingActions($deliveryNote, $user, $isEditable, $canShip, $isInPickingSession));
    }

    /**
     * @return array<string, string>
     */
    private function editingActions(DeliveryNote $deliveryNote, User $user, bool $isEditable, bool $canShip, bool $isInPickingSession): array
    {
        $actions = [];
        if ($isEditable && !$isInPickingSession && in_array($deliveryNote->state, [DeliveryNoteStateEnum::PACKING, DeliveryNoteStateEnum::PACKED], true)) {
            $actions['change_packer'] = __('Change packer');
        }

        if ($canShip && $deliveryNote->isDeliveryAddressEditable()) {
            $actions['edit_delivery_address'] = __('Edit delivery address');
        }

        if (ShowDeliveryNote::canBeCancelledInWarehouse($deliveryNote, $user)) {
            $actions['cancel'] = __('Cancel delivery note');
        }

        return $actions;
    }

    /**
     * @return array<string, string>
     */
    private function pickedActions(DeliveryNote $deliveryNote, bool $canHandle): array
    {
        if ($this->isDropshipping($deliveryNote)) {
            return array_merge(
                $canHandle ? ['set_as_packed' => __('Set as packed')] : [],
                ['undo_set_as_picked' => __('Undo set as picked')]
            );
        }

        return array_merge(
            ['start_packing' => __('Start packing'), 'undo_set_as_picked' => __('Undo set as picked')],
            $canHandle ? ['change_trolley' => __('Change trolley'), 'change_picked_bay' => __('Change bay')] : []
        );
    }

    /**
     * @return array<string, string>
     */
    private function handlingActions(DeliveryNote $deliveryNote, bool $canHandle): array
    {
        if (!$canHandle) {
            return ['take_over' => __('Unlock to pick')];
        }

        $actions = [];
        if ($deliveryNote->hasIncompleteSets()) {
            $actions['put_back_incomplete_sets'] = ShowDeliveryNote::make()->getPutBackIncompleteSetsAction($deliveryNote)['label'];
        } elseif (!ShowDeliveryNote::hasUnhandledItems($deliveryNote)) {
            if ($this->isDropshipping($deliveryNote)) {
                $actions['set_as_packed'] = __('Set as packed');
            } else {
                $actions['set_as_picked'] = __('Finish picking');
            }
        }

        if (!$this->isDropshipping($deliveryNote)) {
            $actions['change_trolley'] = __('Change trolley');
        }

        return $actions;
    }

    /**
     * @return array<string, string>
     */
    private function handlingBlockedActions(DeliveryNote $deliveryNote, User $user, bool $canHandle): array
    {
        $actions = [];
        if ($deliveryNote->hasIncompleteSets()) {
            $actions['put_back_incomplete_sets'] = ShowDeliveryNote::make()->getPutBackIncompleteSetsAction($deliveryNote)['label'];
        } elseif (!$deliveryNote->hasBlockingItems()) {
            $actions['resume_picking'] = __('Resume picking');
        }

        if (!$canHandle) {
            $actions['take_over'] = __('Unlock to pick');
        }

        $actions['auto_finish_waiting'] = __('Auto Finish Waiting');

        if (ShowDeliveryNote::canGoBackToPicking($deliveryNote, $user)) {
            $actions['back_to_picking'] = __('Back to picking');
        }

        if ($canHandle) {
            if (!$this->isDropshipping($deliveryNote)) {
                $actions['change_trolley'] = __('Change trolley');
            }
            $actions['change_picked_bay'] = __('Change bay');
        }

        return $actions;
    }
}
