<?php

/*
 * author Louis Perez
 * created on 22-12-2025-13h-50m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Actions\Ordering\Order\UI;

use App\Actions\OrgAction;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\Event;
use OwenIt\Auditing\Events\AuditCustom;

class AuditOrderCustom extends OrgAction
{
    public function handle(Order $order, array $oldOrderData, array $changedOrderData, $action = 'update')
    {
        $order->auditEvent    = $action;
        $order->isCustomEvent = true;

        $order->auditCustomOld = array_intersect_key($oldOrderData, $changedOrderData);
        $order->auditCustomNew = $changedOrderData;

        // Checks if action done by user. If it's from a job, log update is from System
        // Should this still be logged though?
        if(auth()->user()){
            $order->auditActor = auth()->user();
        }

        Event::dispatch(new AuditCustom($order));
    }
}
