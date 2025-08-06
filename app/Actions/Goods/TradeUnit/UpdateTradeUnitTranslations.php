<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 05 Aug 2025 11:42:45 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit;

use App\Actions\OrgAction;
use App\Models\Goods\TradeUnit;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateTradeUnitTranslations extends OrgAction
{
    use asAction;

    public function handle(TradeUnit $tradeUnit, array $modelData): TradeUnit
    {
        UpdateTradeUnit::run($tradeUnit, $modelData['master']);


        $name_i8n=[];
        $description_i8n=[];
        $description_title_i8n=[];
        $description_extra_i8n=[];

        foreach ($modelData['translations'] as $locale => $translation) {
            $name_i8n[$locale] = $translation['name'];
            $description_i8n[$locale] = $translation['description'];
            $description_title_i8n[$locale] = $translation['description_title'];
            $description_extra_i8n[$locale] = $translation['description_extra'];
        }
        $tradeUnit->name_i8n = $name_i8n;
        $tradeUnit->description_i8n = $description_i8n;
        $tradeUnit->description_title_i8n = $description_title_i8n;
        $tradeUnit->description_extra_i8n = $description_extra_i8n;
        $tradeUnit->save();


        return $tradeUnit;


    }

    public function rules(): array
    {
        return [
            'master' => ['required', 'array'],
            'master.name' => 'required|string',
            'master.description' => ['present','nullable','string','max:10000'],
            'master.description_title' => ['present','nullable','string','max:1000'],
            'master.description_extra' => ['present','nullable','string','max:20000'],
            'translations'=> ['required', 'array'],
        ];
    }

    public function asController(TradeUnit $tradeUnit, ActionRequest $request): void
    {

        $this->initialisationFromGroup(group(), $request);
        $this->handle($tradeUnit, $this->validatedData);
    }

}
