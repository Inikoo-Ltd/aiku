<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\GoodsIn\UnidentifiedReturn;

use App\Actions\Helpers\Media\SaveModelImage;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Inventory\WithWarehouseEditAuthorisation;
use App\Models\GoodsIn\UnidentifiedReturn;
use App\Models\Inventory\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class StoreUnidentifiedReturn extends OrgAction
{
    use WithWarehouseEditAuthorisation;

    public function handle(Warehouse $warehouse, array $modelData): UnidentifiedReturn
    {
        $image = Arr::pull($modelData, 'image');

        $unidentifiedReturn = UnidentifiedReturn::create([
            ...$modelData,
            'group_id'        => $warehouse->group_id,
            'organisation_id' => $warehouse->organisation_id,
            'warehouse_id'    => $warehouse->id,
        ]);

        if ($image) {
            $unidentifiedReturn = SaveModelImage::run(
                model: $unidentifiedReturn,
                imageData: [
                    'path'         => $image->getPathName(),
                    'originalName' => $image->getClientOriginalName(),
                    'extension'    => $image->getClientOriginalExtension(),
                ]
            );
        }

        return $unidentifiedReturn;
    }

    public function rules(): array
    {
        return [
            'notes' => ['required_without:image', 'nullable', 'string', 'max:10000'],
            'image' => ['required_without:notes', 'nullable', File::image()->max(20 * 1024)],
        ];
    }

    public function asController(Warehouse $warehouse, ActionRequest $request): UnidentifiedReturn
    {
        $this->initialisationFromWarehouse($warehouse, $request);

        return $this->handle($warehouse, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back()->with('notification', [
            'status'      => 'success',
            'title'       => __('Return saved to identify'),
            'description' => __('The box has been logged. The office can identify it from the photo and notes.'),
        ]);
    }
}
