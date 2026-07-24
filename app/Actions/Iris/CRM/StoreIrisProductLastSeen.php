<?php

namespace App\Actions\Iris\CRM;

use App\Actions\IrisAction;
use App\Http\Resources\Catalogue\IrisProductLastSeenResource;
use App\Models\CRM\Customer;
use App\Models\CRM\ProductLastSeen;
use App\Models\Web\Webpage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

class StoreIrisProductLastSeen extends IrisAction
{
    public const string COOKIE_NAME = 'aiku_pls';
    private Webpage $webpage;

    public function handle(Webpage $webpage, ?Customer $customer, ?string $cookieId): array
    {
        $lastSeenAt = now()->startOfSecond();

        HandleIrisProductLastSeen::dispatch($webpage, $customer, $cookieId, $lastSeenAt);

        if ($cookieId) {
            Cookie::queue(self::COOKIE_NAME, $cookieId, 0);
        }

        $lastSeenAt = now()->startOfSecond();

        return [
            'id'              => $customer?->id ?? $cookieId,
            'cookie_name'     => self::COOKIE_NAME,
            'cookie_id'       => $cookieId,
            'group_id'        => $webpage->group_id,
            'organisation_id' => $webpage->organisation_id,
            'shop_id'         => $webpage->shop_id,
            'webpage_id'      => $webpage->id,
            'customer_id'     => $customer?->id,
            'last_seen_at'    => $lastSeenAt,
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return $this->webpage->website_id !== $this->website->id;
    }

    public function asController(Webpage $webpage, ActionRequest $request): array
    {
        $this->webpage = $webpage;
        $this->initialisation($request);

        $customer = optional($request->user())->customer;

        $cookieId = null;

        if (!$customer) {
            $cookieId = $request->cookie(self::COOKIE_NAME) ?: Str::uuid()->toString();
        }

        return $this->handle($webpage, $customer, $cookieId);
    }

    public function jsonResponse(array $data): array
    {
        return IrisProductLastSeenResource::make($data);
    }
}
