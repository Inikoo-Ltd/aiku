<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Jun 2025 14:01:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions;

use App\Actions\Search\StoreWebsiteSearchLog;
use App\Actions\Traits\WithTab;
use App\Actions\Web\WebsiteVisitor\UI\GetBrowserInfo;
use App\Enums\Search\WebsiteSearchSourceEnum;
use Illuminate\Support\Arr;
use App\Models\Catalogue\Shop;
use App\Models\CRM\WebUser;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Models\Web\Website;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class IrisAction
{
    use AsAction;
    use WithAttributes;
    use WithTab;

    protected Group $group;
    protected Organisation $organisation;
    protected Shop $shop;
    protected Website $website;


    protected bool $asAction = false;

    public int $hydratorsDelay = 0;


    protected array $validatedData;


    public function initialisation(ActionRequest|array $request): static
    {

        $this->website = $request->input('website');
        $this->shop = $this->website->shop;

        $this->organisation = $this->website->organisation;
        $this->group        =  $this->website->group;
        if (is_array($request)) {
            $this->setRawAttributes($request);
        } else {
            $this->fillFromRequest($request);
        }
        $this->validatedData = $this->validateAttributes();

        return $this;
    }

    /**
     * Check logged in/logget out user. Return null or id.
     */
    protected function signedInCustomerId(): ?int
    {
        /** @var WebUser|null $webUser */
        $webUser = request()->user();

        if (!$webUser || $webUser->website_id !== $this->website->id) {
            return null;
        }

        return $webUser->customer_id;
    }

    /**
     * $armCounts splits the hits by which arm of a hybrid search found them. The gap
     * reporting keys off the keyword count, not what the customer ended up seeing.
     *
     * @param array{keyword: int, vector: int}|null $armCounts
     */
    protected function recordWebsiteSearchLog(
        ActionRequest $request,
        string $scope,
        string $query,
        int $resultsCount,
        ?array $armCounts = null
    ): string {
        $ulid = (string)Str::ulid();

        $browserInfo = $request->userAgent() ? GetBrowserInfo::run($request->userAgent()) : [];

        StoreWebsiteSearchLog::dispatchAfterResponse([
            'ulid'            => $ulid,
            'group_id'        => $this->group->id,
            'organisation_id' => $this->organisation->id,
            'shop_id'         => $this->shop->id,
            'website_id'      => $this->website->id,
            'web_user_id'     => $request->user()?->id,
            'customer_id'     => $request->user()?->customer_id,
            'scope'           => $scope,
            'source'          => $this->searchSource(),
            'query'           => mb_substr($query, 0, 255),
            'session_id'      => $request->hasSession() ? $request->session()->getId() : null,
            'results_count'         => $resultsCount,
            'keyword_results_count' => Arr::get($armCounts, 'keyword', $resultsCount),
            'vector_results_count'  => Arr::get($armCounts, 'vector', 0),
            'device'          => Arr::get($browserInfo, 'device'),
            'browser'         => Arr::get($browserInfo, 'browser'),
            'os'              => Arr::get($browserInfo, 'os'),
        ]);

        return $ulid;
    }

    /**
     * The storefront tells us which control opened the search; anything the enum does not
     * know about is discarded rather than stored, so the breakdown cannot be polluted
     * by a crafted request.
     */
    protected function searchSource(): ?string
    {
        $source = Arr::get($this->validatedData, 'source');

        return WebsiteSearchSourceEnum::tryFrom((string)$source)?->value;
    }

}
