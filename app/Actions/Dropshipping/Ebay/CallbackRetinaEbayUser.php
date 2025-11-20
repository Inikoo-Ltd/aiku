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
use App\Enums\Dropshipping\EbayUserStepEnum;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\EbayUser;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Symfony\Component\HttpFoundation\Response;

class CallbackRetinaEbayUser extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;
    use WithEbayApiRequest;

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    public function handle(Customer $customer, array $modelData): string
    {

        $config = $this->getEbayConfig();

        try {
            $response = Http::asForm()->withHeaders([
                'Authorization' => 'Basic ' . base64_encode($config['client_id'] . ':' . $config['client_secret'])
            ])->post($this->getEbayTokenUrl(), [
                'grant_type' => 'authorization_code',
                'code' => Arr::get($modelData, 'code'),
                'redirect_uri' => $config['redirect_uri']
            ]);

            if ($response->successful()) {
                $tokenData = $response->json();

                /** @var EbayUser $ebayUser */
                $ebayUser = EbayUser::where('customer_id', $customer->id)
                    ->where('step', EbayUserStepEnum::MARKETPLACE)
                    ->orderBy('updated_at', 'desc')
                    ->first();

                $ebayUser->refresh();
                $userData = $ebayUser->getUser();

                $registrationMarketplaceId = Arr::get($userData, 'registrationMarketplaceId');
                if ($registrationMarketplaceId === "EBAY_US") {
                    $registrationMarketplaceId = "EBAY_GB";
                }

                $ebayUser = UpdateEbayUser::run($ebayUser, [
                        'step' => EbayUserStepEnum::AUTH,
                        'settings' => [
                            'marketplace_id' => $registrationMarketplaceId,
                            'credentials' => [
                                'ebay_access_token' => $tokenData['access_token'],
                                'ebay_refresh_token' => $tokenData['refresh_token'],
                                'ebay_token_expires_at' => now()->addSeconds($tokenData['expires_in'])
                            ]
                        ]
                    ]);

                // UpdateEbayUserData::dispatch($ebayUser);
                // CheckEbayChannel::run($ebayUser);

                $routeName = match ($ebayUser->customer->is_fulfilment) {
                    true => 'retina.fulfilment.dropshipping.customer_sales_channels.show',
                    default => 'retina.dropshipping.platform.ebay_callback.success'
                };

                return route($routeName);
            }

            throw new Exception('Failed to exchange code for token: ' . $response->body());
        } catch (Exception $e) {
            Log::error('eBay OAuth Token Exchange Error: ' . $e->getMessage());
            throw $e;
        }
    }

    public function htmlResponse(string $url): Response
    {
        return redirect($url);
    }

    public function asController(ActionRequest $request): string
    {
        $this->initialisation($request);

        return $this->handle($request->user()->customer, $request->all());
    }
}
