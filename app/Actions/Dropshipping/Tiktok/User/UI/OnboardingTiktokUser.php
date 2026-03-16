<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 10 Mar 2025 16:53:20 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Tiktok\User\UI;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Dropshipping\TiktokUser;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class OnboardingTiktokUser extends OrgAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(array $modelData): TiktokUser|null|array|string
    {
        $tiktokUser = TiktokUser::find($modelData['tiktok_user_id']);

        if (! $tiktokUser) {
            return Arr::get($modelData, 'message', 'Something went wrong.');
        }

        return $tiktokUser;
    }

    public function htmlResponse(TiktokUser|array|string|null $model = null): Response
    {
        $tiktokUser = null;
        $message = null;
        $website = null;
        $status = false;
        $needCreateCustomer = false;
        if ($model instanceof TiktokUser) {
            $tiktokUser = $model;
            $message = __('Your account is now connected to your TikTok seller account.');
            $status = true;
            if ($tiktokUser->customerSalesChannel?->shop?->website) {
                $website = $tiktokUser->customerSalesChannel->shop->website;
            }
            if ($tiktokUser->customer_id == null) {
                $needCreateCustomer = true;
            }
        } elseif (is_string($model)) {
            $message = $model;
        }

        return Inertia::render('Tiktok/TiktokOnboarding', [
            'success' => $status,
            'name' => $tiktokUser?->name,
            'message' => $message,
            'website' => $website,
            'is_need_create_customer' => $needCreateCustomer, // @Vika If true, need to show those three websites to register page.
            //If false, show "close this page and continue your registration in the previous website (without bringing the code)"
            // the url to register page should bring the code on the parameter
        ]);
    }

    public function rules(): array
    {
        return [
            'tiktok_user_id' => ['nullable', 'integer'],
            'message' => ['nullable', 'string']
        ];
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        $decodedData = json_decode(base64_decode($request->get('tiktok_code')), true);

        $this->set('tiktok_user_id', Arr::get($decodedData, 'tiktok_user_id'));
        $this->set('status', Arr::get($decodedData, 'status'));
        $this->set('message', Arr::get($decodedData, 'message'));
    }

    public function asController(ActionRequest $request): TiktokUser|null|array|string
    {
        $this->fillFromRequest($request);

        return $this->handle($this->validateAttributes());
    }
}
