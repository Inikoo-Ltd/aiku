<?php

namespace App\Audits\Transformer;

use Illuminate\Support\Arr;
class RelationTransformer
{
    public static function execute($auditable, array $data, string $relationName, string $relationModel, array $attributes): array
    {
        $foreignKey = $relationName . '_id';

        if (Arr::has($data, 'new_values.' . $foreignKey)) {
            $oldRelated = $relationModel::find($auditable->getOriginal($foreignKey));
            $newRelated = $relationModel::find($auditable->getAttribute($foreignKey));

            $extractValue = function ($model) use ($attributes) {
                if (!$model) return null;

                $values = [];
                foreach ($attributes as $attribute) {
                    $values[] = $model->{$attribute};
                }
                return implode(', ', array_filter($values));
            };

            $data['old_values'][$relationName] = $extractValue($oldRelated);
            $data['new_values'][$relationName] = $extractValue($newRelated);

            unset($data['new_values'][$foreignKey]);
            unset($data['old_values'][$foreignKey]);
        }
        return $data;

    }
}