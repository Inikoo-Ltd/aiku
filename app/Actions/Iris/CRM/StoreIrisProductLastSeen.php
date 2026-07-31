<?php

namespace App\Actions\Iris\CRM;

use App\Actions\IrisAction;
use App\Http\Resources\Catalogue\IrisProductLastSeenResource;
use App\Models\CRM\Customer;
use App\Models\Web\Webpage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

class StoreIrisProductLastSeen extends IrisAction
{
    public const string COOKIE_NAME = 'aiku_pls';
    public const int COOKIE_MINUTES = 60 * 24 * 365;

    private Webpage $webpage;

    public function handle(Webpage $webpage, ?Customer $customer, ?string $cookieId): Collection
    {
        HandleIrisProductLastSeen::dispatchAfterResponse($webpage, $customer, $cookieId, now()->startOfSecond());

        if ($cookieId) {
            Cookie::queue(self::COOKIE_NAME, $cookieId, self::COOKIE_MINUTES);
        }

        return GetIrisProductLastSeen::run($webpage, $customer, $cookieId);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->webpage->website_id === $this->website->id;
    }

    public function asController(Webpage $webpage, ActionRequest $request): Collection
    {
        $this->webpage = $webpage;
        $this->initialisation($request);

        $customer = $request->user()?->customer;

        $cookieId = null;

        if (!$customer) {
            $cookieId = $request->cookie(self::COOKIE_NAME) ?: Str::uuid()->toString();
        }

        return $this->handle($webpage, $customer, $cookieId);
    }

    public function jsonResponse(Collection $products): AnonymousResourceCollection
    {
        return IrisProductLastSeenResource::collection($products);
    }
}
