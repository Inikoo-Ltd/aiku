<?php

namespace App\Actions\Audits;

use Lorisleiva\Actions\Concerns\AsAction;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Events\AuditCustom;
use Illuminate\Support\Facades\Event;

class DispatchSimpleAudit
{
    use AsAction;

    /**
     * @param Auditable $auditableModel Model that implements Auditable contract
     * @param string $logKey Key to identify the log entry in the audit
     * @param mixed $oldValue Old value before the change 
     * @param mixed $newValue New value after the change
     * @param string $eventName Event name to categorize the audit log (default: 'updated')
     */
    public function handle(
        Auditable $auditableModel, 
        string $logKey, 
        $oldValue, 
        $newValue, 
        string $eventName = 'updated'
    ): void {
        if ($oldValue === $newValue) {
            return;
        }

        $auditableModel->auditEvent = $eventName;
        $auditableModel->isCustomEvent = true;

        $auditableModel->auditCustomOld = [
            $logKey => $oldValue ?: 'None'
        ];

        $auditableModel->auditCustomNew = [
            $logKey => $newValue
        ];
        
        Event::dispatch(new AuditCustom($auditableModel));
    }
}