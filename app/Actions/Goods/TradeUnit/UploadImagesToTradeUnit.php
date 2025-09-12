<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Sept 2025 13:09:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Goods\TradeUnit;

use App\Actions\GrpAction;
use App\Actions\Traits\WithUploadModelImages;
use App\Models\Goods\TradeUnit;
use Lorisleiva\Actions\ActionRequest;

class UploadImagesToTradeUnit extends GrpAction
{
    use WithUploadModelImages;

    public function handle(TradeUnit $model, string $scope, array $modelData): array
    {
        return $this->uploadImages($model, $scope, $modelData);
    }

    public function rules(): array
    {
        return $this->imageUploadRules();
    }

    public function asController(TradeUnit $tradeUnit, ActionRequest $request): void
    {
        $this->initialisation($tradeUnit->group, $request);

        $this->handle($tradeUnit, 'image', $this->validatedData);
    }
}
