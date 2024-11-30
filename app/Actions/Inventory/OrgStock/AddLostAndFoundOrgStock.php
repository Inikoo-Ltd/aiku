<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 15 May 2023 16:45:46 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Enums\Inventory\OrgStock\LostAndFoundOrgStockStateEnum;
use App\Models\Inventory\Location;
use App\Models\Inventory\LostAndFoundStock;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class AddLostAndFoundOrgStock
{
    use AsAction;
    use WithAttributes;

    public function handle(Location $location, array $modelData = []): LostAndFoundStock
    {
        /** @var LostAndFoundStock $lostAndFound */
        $lostAndFound = $location->lostAndFoundStocks()->updateOrCreate($modelData);

        return $lostAndFound;
    }

    public function rules(): array
    {
        return [
            'code'  => ['required', 'string'],
            'type'  => ['required', Rule::in([LostAndFoundOrgStockStateEnum::LOST->value, LostAndFoundOrgStockStateEnum::FOUND->value])],
        ];
    }

    public function action(Location $location, array $modelData = []): LostAndFoundStock
    {
        $this->setRawAttributes($modelData);
        $this->validateAttributes();

        return $this->handle($location, $modelData);
    }
}
