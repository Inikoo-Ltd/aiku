<?php

namespace App\Actions\Goods\TradeUnit;

use App\Actions\GrpAction;
use App\Models\Goods\TradeUnit;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateTradeUnitTranslationsFromUpdate extends GrpAction
{
    use asAction;

    public function handle(TradeUnit $tradeUnit, array $modelData): TradeUnit
    {
        $name_i8n = [];
        $description_i8n = [];
        $description_title_i8n = [];
        $description_extra_i8n = [];

        if (Arr::has($modelData, 'translations.name')) {
            foreach ($modelData['translations']['name'] as $locale => $translation) {
                $name_i8n[$locale] = $translation;
                $tradeUnit->name_i8n = $name_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description_title')) {
            foreach ($modelData['translations']['description_title'] as $locale => $translation) {
                $description_title_i8n[$locale] = $translation;
                $tradeUnit->description_title_i8n = $description_title_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description')) {
            foreach ($modelData['translations']['description'] as $locale => $translation) {
                $description_i8n[$locale] = $translation;
                $tradeUnit->description_i8n = $description_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description_extra')) {
            foreach ($modelData['translations']['description_extra'] as $locale => $translation) {
                $description_extra_i8n[$locale] = $translation;
                $tradeUnit->description_extra_i8n = $description_extra_i8n;
            }
        }

        $tradeUnit->save();


        return $tradeUnit;


    }

    public function rules(): array
    {
        return [
            'translations' => ['required', 'array'],
        ];
    }

    public function action(TradeUnit $tradeUnit, array $modelData): void
    {
        $this->initialisation($tradeUnit->group, $modelData);
        $this->handle($tradeUnit, $this->validatedData);
    }

}
