<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Services\DataForSeo\DataForSeoException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Runs the rank tracking jobs for one shop at once instead of waiting for the scheduler, so the
 * flow can be tried locally. Only routed in the local environment: on a server every press would
 * spend DataForSEO credit outside the planned schedule.
 */
class RunSeoRankChecks extends OrgAction
{
    use WithSeoEditAuthorisation;

    /**
     * @return array{refreshed: int, posted: int, collected: int}
     * @throws ValidationException
     */
    public function handle(Shop $shop): array
    {
        try {
            return [
                'refreshed' => RefreshTrackedKeywordVolumes::run($shop),
                'posted'    => PostSerpTasks::run($shop),
                'collected' => CollectSerpTasks::run(),
            ];
        } catch (DataForSeoException $e) {
            throw ValidationException::withMessages(['checks' => $e->getMessage()]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function asController(Shop $shop, ActionRequest $request): array
    {
        abort_unless(app()->environment('local'), 404);

        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
