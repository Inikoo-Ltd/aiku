<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-14h-39m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\GoodsOut;

use App\Actions\Dispatching\DeliveryNoteLeaflet\WithPrintDeliveryNoteLeaflet;
use App\Models\Dispatching\DeliveryNote;
use App\Models\SysAdmin\User;
use Illuminate\Support\Arr;

trait WithMobilePrinters
{
    use WithPrintDeliveryNoteLeaflet;

    protected function printsByPrintNode(User $user): bool
    {
        return (bool) Arr::get($user->group->settings, 'printnode.print_by_printnode', false);
    }

    protected function canPrintLabels(User $user): bool
    {
        return $this->printsByPrintNode($user) && Arr::get($user->settings, 'preferred_printer_id');
    }

    protected function insertPrinterId(User $user): ?int
    {
        return $this->printsByPrintNode($user) ? $this->leafletPrinterId($user) : null;
    }

    protected function canPrintAllInserts(DeliveryNote $deliveryNote, User $user): bool
    {
        return $this->insertPrinterId($user) !== null && $deliveryNote->hasUnprintedLeaflets();
    }
}
