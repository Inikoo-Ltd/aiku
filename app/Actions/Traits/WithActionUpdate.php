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

    /**
     * Like Arr::dot but list arrays stay whole leaf values, so a shrinking
     * list replaces the stored list instead of overwriting only its first
     * indexes with arrow updates.
     *
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    protected function dotJsonLeaves(array $values, string $prefix = ''): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $fullKey = $prefix === '' ? (string) $key : $prefix.'.'.$key;
            if (is_array($value) && $value !== [] && !array_is_list($value)) {
                $result += $this->dotJsonLeaves($value, $fullKey);
            } else {
                $result[$fullKey] = $value;
            }
        }

        return $result;
    }

    protected function extractJson($modelData, $field = ['data']): array
    {
        $data = [];
        foreach ($this->dotJsonLeaves(Arr::only($modelData, $field)) as $key => $value) {
            if (is_array($value) && count($value) == 0) {
                $value = null;
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
        foreach ($this->dotJsonLeaves(Arr::only($modelData, $jsonFields)) as $dottedKey => $newValue) {
            $oldValue = data_get($model, $dottedKey);
            if (json_encode($oldValue) !== json_encode($newValue)) {
                if (preg_match(PasswordRedactor::SECRET_KEY_PATTERN, $dottedKey)) {
                    continue;
                }
                $auditOld[$dottedKey] = $oldValue;
                $auditNew[$dottedKey] = $newValue;
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
            $model->isCustomEvent  = false;
            $model->auditCustomOld = [];
            $model->auditCustomNew = [];
        }

        return $model;
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
