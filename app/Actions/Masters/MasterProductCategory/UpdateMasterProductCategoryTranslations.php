<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 05 Aug 2025 11:42:45 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory;

use App\Actions\OrgAction;
use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateMasterProductCategoryTranslations extends OrgAction
{
    use asAction;

    public function handle(MasterProductCategory $masterProductCategory, array $modelData): MasterProductCategory
    {
        UpdateMasterProductCategory::run($masterProductCategory, $modelData['master']);


        $name_i8n = [];
        $description_i8n = [];
        $description_title_i8n = [];
        $description_extra_i8n = [];

        foreach ($modelData['translations'] as $locale => $translation) {
            $name_i8n[$locale] = $translation['name'];
            $description_i8n[$locale] = $translation['description'];
            $description_title_i8n[$locale] = $translation['description_title'];
            $description_extra_i8n[$locale] = $translation['description_extra'];
        }
        $masterProductCategory->name_i8n = $name_i8n;
        $masterProductCategory->description_i8n = $description_i8n;
        $masterProductCategory->description_title_i8n = $description_title_i8n;
        $masterProductCategory->description_extra_i8n = $description_extra_i8n;
        $masterProductCategory->save();


        return $masterProductCategory;


    }

    public function rules(): array
    {
        return [
            'master' => ['required', 'array'],
            'master.name' => 'required|string',
            'master.description' => ['present','nullable','string','max:10000'],
            'master.description_title' => ['present','nullable','string','max:1000'],
            'master.description_extra' => ['present','nullable','string','max:20000'],
            'translations' => ['required', 'array'],
        ];
    }

    public function asController(MasterProductCategory $masterProductCategory, ActionRequest $request): void
    {

        $this->initialisationFromGroup(group(), $request);
        $this->handle($masterProductCategory, $this->validatedData);
    }

}
