<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Mon, 05 Sept 2022 01:11:27 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Goods\Ingredient\StoreIngredient;
use App\Actions\Goods\Ingredient\UpdateIngredient;
use App\Models\Goods\Ingredient;
use App\Transfers\SourceOrganisationService;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchAuroraIngredients extends FetchAuroraAction
{
    public string $commandSignature = 'fetch:ingredients {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Ingredient
    {
        $ingredientData = $organisationSource->fetchIngredient($organisationSourceId);

        if (! $ingredientData) {
            return null;
        }

        $ingredient = Ingredient::whereRaw('LOWER(name)=? ', [trim(strtolower($ingredientData['ingredient']['name']))])->first();

        if (! $ingredient) {
            if ($ingredient = Ingredient::where('source_id', $ingredientData['ingredient']['source_id'])
                ->first()) {
                try {
                    $ingredient = UpdateIngredient::make()->action(
                        ingredient: $ingredient,
                        modelData: $ingredientData['ingredient'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );

                    $this->recordChange($organisationSource, $ingredient->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $ingredientData['ingredient'], 'Ingredient', 'update');

                    return null;
                }
            }
        }

        if (! $ingredient) {
            $ingredient = Ingredient::whereJsonContains('sources->ingredients', $ingredientData['ingredient']['source_id'])->first();
        }

        if (! $ingredient) {
            try {
                $ingredient = StoreIngredient::make()->action(
                    group: group(),
                    modelData: $ingredientData['ingredient'],
                    hydratorsDelay: $this->hydratorsDelay,
                    strict: false,
                    audit: false
                );

                Ingredient::enableAuditing();
                $this->saveMigrationHistory(
                    $ingredient,
                    Arr::except($ingredientData['ingredient'], ['fetched_at', 'last_fetched_at', 'source_id'])
                );

                $this->recordNew($organisationSource);

                $sourceData = explode(':', $ingredient->source_id);
                DB::connection('aurora')->table('Material Dimension')
                    ->where('Material Key', $sourceData[1])
                    ->update(['aiku_id' => $ingredient->id]);
            } catch (Exception|Throwable $e) {
                $this->recordError($organisationSource, $e, $ingredientData['ingredient'], 'Ingredient', 'store');

                return null;
            }
        }

        if ($ingredient) {
            $this->updateIngredientSources($ingredient, $ingredientData['ingredient']['source_id']);

            if (isset($ingredientData['trade_unit_args'])) {
                $this->updateTradeUnitArgs($ingredient, $ingredientData['ingredient']['source_id'], $ingredientData['trade_unit_args']);
            }

            $this->updateExtraIngredients($ingredient, $ingredientData['ingredient']['source_id'], $ingredientData['extra_ingredients']);
        }

        return $ingredient;
    }

    public function updateExtraIngredients(Ingredient $ingredient, string $source, array $data): void
    {
        $extraIngredients = $ingredient->source_extra_ingredients;
        $extraIngredients[$source] = $data;

        $ingredient->updateQuietly([
            'source_extra_ingredients' => $extraIngredients,
        ]);
    }

    public function updateTradeUnitArgs(Ingredient $ingredient, string $source, array $data): void
    {
        $tradeUnitArgs = Arr::get($ingredient->source_data, 'trade_unt_args', []);
        $tradeUnitArgs[$source] = $data;

        $ingredient->updateQuietly([
            'source_data' => [
                'trade_unt_args' => $tradeUnitArgs,
            ],
        ]);
    }

    public function updateIngredientSources(Ingredient $ingredient, string $source): void
    {
        $sources = Arr::get($ingredient->sources, 'ingredients', []);
        $sources[] = $source;
        $sources = array_unique($sources);

        $ingredient->updateQuietly([
            'sources' => [
                'ingredients' => $sources,
            ],
        ]);
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Material Dimension')
            ->select('Material Key as source_id')
            ->orderBy('source_id');
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Material Dimension')->count();
    }
}
