<?php

/*
 * author Louis Perez
 * created on 25-02-2026-17h-15m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\Collection;

use App\Actions\OrgAction;
use App\Models\Catalogue\Collection;
use App\Models\Masters\MasterCollection;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateCollectionAndMasterTranslations extends OrgAction
{
    use asAction;

    public function handle(Collection $collection, array $modelData): Collection
    {
        $name_i8n = [];
        $description_i8n = [];
        $description_title_i8n = [];
        $description_extra_i8n = [];

        if (Arr::has($modelData, 'translations.name')) {
            foreach ($modelData['translations']['name'] as $locale => $translation) {
                $name_i8n[$locale] = $translation;
                $collection->name_i8n = $name_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description_title')) {
            foreach ($modelData['translations']['description_title'] as $locale => $translation) {
                $description_title_i8n[$locale] = $translation;
                $collection->description_title_i8n = $description_title_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description')) {
            foreach ($modelData['translations']['description'] as $locale => $translation) {
                $description_i8n[$locale] = $translation;
                $collection->description_i8n = $description_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description_extra')) {
            foreach ($modelData['translations']['description_extra'] as $locale => $translation) {
                $description_extra_i8n[$locale] = $translation;
                $collection->description_extra_i8n = $description_extra_i8n;
            }
        }

        $collection->save();

        if ($collection->masterCollection) {
            $this->updateMaster($collection->masterCollection, $name_i8n, $description_i8n, $description_title_i8n, $description_extra_i8n);
        }


        return $collection;
    }

    public function updateMaster(MasterCollection $masterCollection, array $name_i8n, array $description_i8n, array $description_title_i8n, array $description_extra_i8n)
    {
        $masterNameI8n = $masterCollection->getTranslations('name_i8n');
        $masterDescriptionI8n =  $masterCollection->getTranslations('description_i8n');
        $masterDescriptionTitleI8n =  $masterCollection->getTranslations('description_title_i8n');
        $masterDescriptionExtraI8n = $masterCollection->getTranslations('description_extra_i8n');

        $updateMaster = false;

        if (!empty($name_i8n)) {
            foreach ($name_i8n as $locale => $translation) {
                $masterNameI8n[$locale] = $translation;
            }
            $masterCollection->name_i8n = $masterNameI8n;
            $updateMaster = true;
        }

        if (!empty($description_i8n)) {
            foreach ($description_i8n as $locale => $translation) {
                $masterDescriptionI8n[$locale] = $translation;
            }
            $masterCollection->description_i8n = $masterDescriptionI8n;
            $updateMaster = true;
        }

        if (!empty($description_title_i8n)) {
            foreach ($description_title_i8n as $locale => $translation) {
                $masterDescriptionTitleI8n[$locale] = $translation;
            }
            $masterCollection->description_title_i8n = $masterDescriptionTitleI8n;
            $updateMaster = true;
        }

        if (!empty($description_extra_i8n)) {
            foreach ($description_extra_i8n as $locale => $translation) {
                $masterDescriptionExtraI8n[$locale] = $translation;
            }
            $masterCollection->description_extra_i8n = $masterDescriptionExtraI8n;
            $updateMaster = true;
        }

        if ($updateMaster) {
            $masterCollection->save();
        }
    }

    public function rules(): array
    {
        return [
            'translations' => ['required', 'array'],
        ];
    }

    public function action(Collection $collection, array $modelData): void
    {
        $this->initialisationFromShop($collection->shop, $modelData);
        $this->handle($collection, $this->validatedData);
    }

}
