<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 31 Jul 2026 06:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Events;

use App\Models\HumanResources\Employee;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BroadcastEmployeeClockingUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public Employee $employee;

    public function __construct(Employee $employee)
    {
        $this->employee = $employee;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('grp.employee.'.$this->employee->id.'.clocking')];
    }

    public function broadcastAs(): string
    {
        return 'clocking-updated';
    }

    public function broadcastWith(): array
    {
        return ['employee_id' => $this->employee->id];
    }
}
