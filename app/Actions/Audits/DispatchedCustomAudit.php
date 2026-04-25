<?php

namespace App\Actions\Audits;

use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Concerns\AsAction;
use OwenIt\Auditing\Events\AuditCustom;
use OwenIt\Auditing\Contracts\Auditable;

class DispatchedCustomAudit
{
    use AsAction;

    /**
     * @param Auditable $auditableModel The model instance that implements the Auditable contract and is being audited
     * @param string $relationModelClass The class name of the related model to fetch values from
     * @param string $logKey The key to use in the audit log for the changed value
     * @param mixed $oldId The old ID of the related model before the change
     * @param mixed $newId The new ID of the related model after the change
     * @param array $attributes The attributes to extract from the related model for the audit log
     * @param string $eventName The name of the audit event (default: 'updated')
     */

    public function handle(
        Auditable $auditableModel,
        string $relationModelClass,
        $oldId,
        $newId,
        array $attributes,
        string $logKey,
        string $eventName = 'updated'
    ): void {
        if ($oldId === $newId) {
            return;
        }

        $oldValue = $this->extractValue($relationModelClass, $oldId, $attributes);
        $newValue = $this->extractValue($relationModelClass, $newId, $attributes);

        $auditableModel->auditEvent = $eventName;
        $auditableModel->isCustomEvent = true;

        $auditableModel->auditCustomOld = [
            $logKey => $oldValue
        ];

        $auditableModel->auditCustomNew = [
            $logKey => $newValue
        ];

        Event::dispatch(new AuditCustom($auditableModel));
    }

    private function extractValue($modelClass, $id, array $attributes): ?string
    {
        if (!$id) {
            return null;
        }

        $model = $modelClass::find($id);

        if (!$model) {
            return null;
        }

        foreach ($attributes as $attribute) {
            if (!empty($model->{$attribute})) {
                return $model->{$attribute};
            }
        }

        return null;
    }
}
