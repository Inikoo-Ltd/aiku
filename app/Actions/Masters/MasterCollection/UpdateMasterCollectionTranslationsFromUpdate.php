<?php

/*
 * author Louis Perez
 * created on 25-02-2026-17h-06m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Masters\MasterCollection;

use App\Actions\GrpAction;
use App\Models\Masters\MasterCollection;
use App\Models\Catalogue\Collection;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateMasterCollectionTranslationsFromUpdate extends GrpAction
{
    use asAction;

    public function handle(MasterCollection $masterCollection, array $modelData): MasterCollection
    {
        $name_i8n = [];
        $description_i8n = [];
        $description_title_i8n = [];
        $description_extra_i8n = [];

        if (Arr::has($modelData, 'translations.name')) {
            foreach ($modelData['translations']['name'] as $locale => $translation) {
                $name_i8n[$locale] = $translation;
                $masterCollection->name_i8n = $name_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description_title')) {
            foreach ($modelData['translations']['description_title'] as $locale => $translation) {
                $description_title_i8n[$locale] = $translation;
                $masterCollection->description_title_i8n = $description_title_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description')) {
            foreach ($modelData['translations']['description'] as $locale => $translation) {
                $description_i8n[$locale] = $translation;
                $masterCollection->description_i8n = $description_i8n;
            }
        }
        if (Arr::has($modelData, 'translations.description_extra')) {
            foreach ($modelData['translations']['description_extra'] as $locale => $translation) {
                $description_extra_i8n[$locale] = $translation;
                $masterCollection->description_extra_i8n = $description_extra_i8n;
            }
        }

        $masterCollection->save();

        if ($masterCollection->childrenCollections) {
            foreach ($masterCollection->childrenCollections as $collection) {
                $this->updateChildren($collection, $name_i8n, $description_i8n, $description_title_i8n, $description_extra_i8n);
            }
        }

        return $masterCollection;
    }

    public function updateChildren(Collection $collection, array $name_i8n, array $description_i8n, array $description_title_i8n, array $description_extra_i8n)
    {
        $childNameI8n = $collection->getTranslations('name_i8n');
        $childDescriptionI8n = $collection->getTranslations('description_i8n');
        $childDescriptionTitleI8n = $collection->getTranslations('description_title_i8n');
        $childDescriptionExtraI8n =  $collection->getTranslations('description_extra_i8n');
        $childLanguage = $collection->shop->language->code;

        $updateChild = false;
        if (!empty($name_i8n)) {
            foreach ($name_i8n as $locale => $translation) {
                $childNameI8n[$locale] = $translation;
                if ($locale === $childLanguage) {
                    $collection->name = $translation;
                }
            }
            $collection->name_i8n = $childNameI8n;
            $updateChild = true;
        }

        if (!empty($description_i8n)) {
            foreach ($description_i8n as $locale => $translation) {
                $childDescriptionI8n[$locale] = $translation;
                if ($locale === $childLanguage) {
                    $collection->description = $translation;
                }
            }
            $collection->description_i8n = $childDescriptionI8n;
            $updateChild = true;
        }

        if (!empty($description_title_i8n)) {
            foreach ($description_title_i8n as $locale => $translation) {
                $childDescriptionTitleI8n[$locale] = $translation;
                if ($locale === $childLanguage) {
                    $collection->description_title = $translation;
                }
            }
            $collection->description_title_i8n = $childDescriptionTitleI8n;
            $updateChild = true;
        }

        if (!empty($description_extra_i8n)) {
            foreach ($description_extra_i8n as $locale => $translation) {
                $childDescriptionExtraI8n[$locale] = $translation;
                if ($locale === $childLanguage) {
                    $collection->description_extra = $translation;
                }
            }
            $collection->description_extra_i8n = $childDescriptionExtraI8n;
            $updateChild = true;
        }

        if ($updateChild) {
            $collection->save();
        }
    }

    public function rules(): array
    {
        return [
            'translations' => ['required', 'array'],
        ];
    }

    public function action(MasterCollection $masterCollection, array $modelData): void
    {
        $this->initialisation($masterCollection->group, $modelData);
        $this->handle($masterCollection, $this->validatedData);
    }

}
