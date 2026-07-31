<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 16 Jun 2025 14:01:42 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions;

use App\Actions\Search\StoreWebsiteSearchLog;
use App\Actions\Traits\WithTab;
use App\Models\Catalogue\Shop;
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

    protected function recordWebsiteSearchLog(ActionRequest $request, string $scope, string $query, int $resultsCount): string
    {
        $ulid = (string)Str::ulid();

        StoreWebsiteSearchLog::dispatchAfterResponse([
            'ulid'            => $ulid,
            'group_id'        => $this->group->id,
            'organisation_id' => $this->organisation->id,
            'shop_id'         => $this->shop->id,
            'website_id'      => $this->website->id,
            'web_user_id'     => $request->user()?->id,
            'scope'           => $scope,
            'query'           => mb_substr($query, 0, 255),
            'session_id'      => $request->hasSession() ? $request->session()->getId() : null,
            'results_count'   => $resultsCount,
        ]);

        return $ulid;
    }

}
