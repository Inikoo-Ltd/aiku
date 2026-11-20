<?php

/*
 * Author Louis Perez
 * Created on 20-11-2026-11h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Offline\Handlers;

use App\Actions\Api\Group\Offline\Contracts\MobileOfflineContext;
use App\Actions\Api\Group\Offline\Contracts\MobileOfflineHandler;
use App\Actions\Api\Group\Offline\MobileOfflineOutcome;
use App\Models\SysAdmin\MobileOfflineOperation;
use App\Models\SysAdmin\User;

class SetWaitingCrmOfflineHandler implements MobileOfflineHandler
{
    use WithOfflineLineWaiting;

    public function operation(): string
    {
        return 'set_waiting_crm';
    }

    public function apply(MobileOfflineContext $context, array $payload): MobileOfflineOutcome
    {
        return $this->applyWaiting($context, $payload, false);
    }

    public function canRollback(MobileOfflineOperation $operation): bool
    {
        return false;
    }

    public function rollback(MobileOfflineOperation $operation, User $user): void
    {
        $this->refuseRollback(__('Passing a line to customer services can not be undone here: tell customer services.'));
    }
}
