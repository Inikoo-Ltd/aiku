<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Jul 2023 13:31:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Audits\Redactors\PasswordRedactor;
use App\Transfers\AuroraCatalogueGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use OwenIt\Auditing\Contracts\Auditable;
use OwenIt\Auditing\Events\AuditCustom;

trait WithActionUpdate
{
    use AsAction;
    use WithAttributes;

    protected function extractJson($modelData, $field = ['data']): array
    {
        $data = [];
        foreach (Arr::dot(Arr::only($modelData, $field)) as $key => $value) {
            if (is_array($value)) {
                if (count($value) == 0) {
                    $value = null;
                } else {
                    $value = json_encode($value);
                }
            }

            if (str_contains($key, '.') || $value) {
                $data[str_replace('.', '->', $key)] = $value;
            }

        }

        return $data;
    }

    protected function update($model, $modelData, $jsonFields = [])
    {
        if (AuroraCatalogueGuard::blocksUpdate($model)) {
            return $model;
        }

        $auditOld = [];
        $auditNew = [];
        foreach (Arr::dot(Arr::only($modelData, $jsonFields)) as $dottedKey => $newValue) {
            $oldValue = data_get($model, $dottedKey);
            if (json_encode($oldValue) !== json_encode($newValue)) {
                if (preg_match('/password|secret|token|api_key|access_key|private_key|pin/i', $dottedKey)) {
                    $auditOld[$dottedKey] = $oldValue === null ? null : PasswordRedactor::redact($oldValue);
                    $auditNew[$dottedKey] = $newValue === null ? null : PasswordRedactor::redact($newValue);
                } else {
                    $auditOld[$dottedKey] = $oldValue;
                    $auditNew[$dottedKey] = $newValue;
                }
            }
        }

        $model->update(
            Arr::except($modelData, $jsonFields)
        );
        $model->update($this->extractJson($modelData, $jsonFields));

        if ($auditOld !== [] && $model instanceof Auditable) {
            $model->auditEvent     = 'updated';
            $model->isCustomEvent  = true;
            $model->auditCustomOld = $auditOld;
            $model->auditCustomNew = $auditNew;
            Event::dispatch(new AuditCustom($model));
        }

        return $model;
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
