<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\OrgAction;
use App\Models\Web\SeoTrackedKeyword;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class DeleteSeoTrackedKeyword extends OrgAction
{
    use WithSeoEditAuthorisation;

    public function handle(SeoTrackedKeyword $trackedKeyword): void
    {
        $trackedKeyword->delete();
    }

    public function asController(SeoTrackedKeyword $seoTrackedKeyword, ActionRequest $request): void
    {
        $this->initialisationFromShop($seoTrackedKeyword->shop, $request);

        $this->handle($seoTrackedKeyword);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
