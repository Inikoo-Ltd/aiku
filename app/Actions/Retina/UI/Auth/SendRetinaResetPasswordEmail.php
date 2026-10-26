<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Thu, 21 Mar 2024 15:44:04 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Auth;

use App\Actions\Comms\Email\SendResetPasswordEmail;
use App\Actions\CRM\WebUser\StoreWebUser;
use App\Actions\CRM\WebUserPasswordReset\StoreWebUserPasswordReset;
use App\Actions\RetinaAction;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use App\Models\CRM\Customer;
use App\Models\CRM\WebUser;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class SendRetinaResetPasswordEmail extends RetinaAction
{
    use AsController;


    public function handle(array $modelData): void
    {
        $webUser = WebUser::where('website_id', $this->website->id)
            ->where('status', true)
            ->whereRaw('lower(email) = ?', [strtolower($modelData['email'])])
            ->first();

        if (!$webUser) {
            $customer = $this->customerWithoutLogin($modelData['email']);
            if (!$customer) {
                return;
            }
            $webUser  = StoreWebUser::make()->action($customer, [
                'contact_name' => $customer->contact_name,
                'username'     => $customer->email,
                'email'        => $customer->email,
                'is_root'      => true,
            ], sendRegistrationEmail: false);
        }

        $token = Str::random(rand(24, 28));
        $webUserPasswordReset = StoreWebUserPasswordReset::run($webUser, $token);

        $url = route('retina.reset-password.show', [
            'token' => $token,
            'id' => $webUserPasswordReset->id
        ]);


        SendResetPasswordEmail::run($webUser, [
            'url' => $url
        ]);


    }

    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }


    /**
     * Customers brought over from Aurora (and some created in Grp) have no web user, so they can neither log in nor
     * register again with their email. Asking for a reset link claims the account: the login is only usable after the
     * link sent to the customer's own address is followed.
     */
    private function customerWithoutLogin(string $email): ?Customer
    {
        $email = strtolower(trim($email));

        $loginExists = WebUser::withTrashed()
            ->where('website_id', $this->website->id)
            ->where(fn ($query) => $query->whereRaw('lower(email) = ?', [$email])->orWhereRaw('lower(username) = ?', [$email]))
            ->exists();

        if ($loginExists) {
            return null;
        }

        return Customer::where('shop_id', $this->shop->id)
            ->where('status', CustomerStatusEnum::APPROVED)
            ->whereRaw('lower(email) = ?', [$email])
            ->whereDoesntHave('webUsers', fn ($query) => $query->withTrashed())
            ->first();
    }

    public function asController(ActionRequest $request): void
    {
        $this->logoutInitialisation($request);

        $this->handle($this->validatedData);
    }
}
