<?php

namespace App\Actions\Iris\CRM;

use App\Actions\IrisAction;
use App\Models\CRM\Customer;
use App\Models\CRM\ProductLastSeen;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

class StoreIrisProductLastSeen extends IrisAction
{
    public const COOKIE_NAME = 'aiku_pls';

    public function handle(Webpage $webpage, ?Customer $customer, ?string $cookieId): ProductLastSeen
    {
        return ProductLastSeen::updateOrCreate(
            [
                'webpage_id'  => $webpage->id,
                'customer_id' => $customer?->id,
                'cookie_id'   => $customer ? null : $cookieId,
            ],
            [
                'group_id'        => $webpage->group_id,
                'organisation_id' => $webpage->organisation_id,
                'shop_id'         => $webpage->shop_id,
                'last_seen_at'    => now(),
            ]
        );
    }

    public function asController(Webpage $webpage, ActionRequest $request): void
    {
        $this->initialisation($request);

        if ($webpage->website_id !== $this->website->id) {
            abort(404);
        }

        $customer = $request->user()?->customer;

        $cookieId = null;
        if (!$customer) {
            $cookieId = $request->cookie(self::COOKIE_NAME) ?: Str::uuid()->toString();
        }

        if ($cookieId) {
            Cookie::queue(self::COOKIE_NAME, $cookieId, 0);
        }

        StoreIrisProductLastSeen::dispatch($webpage, $customer, $cookieId);
    }
}
