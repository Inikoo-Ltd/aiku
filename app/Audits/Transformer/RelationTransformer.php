<?php

namespace App\Audits\Transformer;

use Illuminate\Support\Arr;

class RelationTransformer
{
    public static function execute($auditable, array $auditData, string $relationName, string $relationModel, array $attributes): array
    {
        $foreignKey = $relationName . '_id';

        if (Arr::has($auditData, 'new_values.' . $foreignKey)) {
            $oldRelated = $relationModel::find($auditable->getOriginal($foreignKey));
            $newRelated = $relationModel::find($auditable->getAttribute($foreignKey));

            $extractValue = function ($model) use ($attributes) {
                if (!$model) {
                    return null;
                }

                $values = [];
                foreach ($attributes as $attribute) {
                    $values[] = $model->{$attribute};
                }
                return implode(', ', array_filter($values));
            };

            $auditData['old_values'][$relationName] = $extractValue($oldRelated);
            $auditData['new_values'][$relationName] = $extractValue($newRelated);

            unset($auditData['new_values'][$foreignKey]);
            unset($auditData['old_values'][$foreignKey]);
        }
        return $auditData;

    }
}
