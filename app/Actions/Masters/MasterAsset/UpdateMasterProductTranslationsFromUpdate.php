<?php

namespace App\Actions\Masters\MasterAsset;

use App\Actions\OrgAction;
use App\Models\Masters\MasterAsset;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateMasterProductTranslationsFromUpdate extends OrgAction
{
    use asAction;

    public function handle(MasterAsset $masterAsset, array $modelData): MasterAsset
    {
        $name_i8n = [];
        $description_i8n = [];
        $description_title_i8n = [];
        $description_extra_i8n = [];

        if (Arr::has($modelData, 'translations.name')) {
            foreach ($modelData['translations']['name'] as $locale => $translation) {
                $name_i8n[$locale] = $translation;
                $masterAsset->name_i8n = $name_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description_title')) {
            foreach ($modelData['translations']['description_title'] as $locale => $translation) {
                $description_title_i8n[$locale] = $translation;
                $masterAsset->description_title_i8n = $description_title_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description')) {
            foreach ($modelData['translations']['description'] as $locale => $translation) {
                $description_i8n[$locale] = $translation;
                $masterAsset->description_i8n = $description_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description_extra')) {
            foreach ($modelData['translations']['description_extra'] as $locale => $translation) {
                $description_extra_i8n[$locale] = $translation;
                $masterAsset->description_extra_i8n = $description_extra_i8n;
            }
        }

        $masterAsset->save();

        PropagateMasterContentToProducts::run($masterAsset, array_keys(array_filter([
            'name'              => $name_i8n,
            'description_title' => $description_title_i8n,
            'description'       => $description_i8n,
            'description_extra' => $description_extra_i8n,
        ])));

        return $masterAsset;
    }

    public function rules(): array
    {
        return [
            'translations' => ['required', 'array'],
        ];
    }

    public function action(MasterAsset $masterAsset, array $modelData): void
    {
        $this->initialisationFromGroup($masterAsset->group, $modelData);
        $this->handle($masterAsset, $this->validatedData);
    }

}
