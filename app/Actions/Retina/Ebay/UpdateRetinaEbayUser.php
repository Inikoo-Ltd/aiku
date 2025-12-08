<?php

/*
 * author Arya Permana - Kirin
 * created on 09-06-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Ebay;

use App\Actions\Dropshipping\Ebay\UpdateEbayUser;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dropshipping\EbayUserStepEnum;
use App\Models\Dropshipping\EbayUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateRetinaEbayUser extends RetinaAction
{
    use AsAction;
    use WithActionUpdate;
    use WithAttributes;

    public function handle(EbayUser $ebayUser, array $modelData): EbayUser
    {
        if ($marketplace = Arr::get($modelData, 'marketplace')) {
            data_set($modelData, 'marketplace', $marketplace);
            data_set($modelData, 'step', EbayUserStepEnum::MARKETPLACE->value);
        }

        return UpdateEbayUser::run($ebayUser, $modelData);
    }

    public function rules(): array
    {
        return [
            'marketplace' => ['sometimes', 'required', 'string'],
        ];
    }

    public function asController(EbayUser $ebayUser, ActionRequest $request): EbayUser
    {
        $this->initialisation($request);

        return $this->handle($ebayUser, $this->validatedData);
    }
}
