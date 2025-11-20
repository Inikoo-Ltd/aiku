<?php

/*
 * author Arya Permana - Kirin
 * created on 09-06-2025-11h-47m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Dropshipping\Ebay;

use App\Actions\Dropshipping\Ebay\Traits\WithEbayApiRequest;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\EbayUser;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Mockery\Exception;

class CheckEbayUserAuthorized extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithEbayApiRequest;

    public function handle(EbayUser $ebayUser): void
    {
        try {
            $result = $ebayUser->getUser();
            if (! Arr::has($result, 'username')) {
                throw new Exception('Ebay username not found');
            }
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['message' => __('You are not authenticated yet, please click auth store button')]);
        }
    }

    public function asController(EbayUser $ebayUser, ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($ebayUser);
    }
}
