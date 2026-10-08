<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\OrgAction;
use App\Models\Web\SeoCompetitor;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

class DeleteSeoCompetitor extends OrgAction
{
    use WithSeoEditAuthorisation;

    public function handle(SeoCompetitor $competitor): void
    {
        $competitor->delete();
    }

    public function asController(SeoCompetitor $seoCompetitor, ActionRequest $request): void
    {
        $this->initialisationFromShop($seoCompetitor->shop, $request);

        $this->handle($seoCompetitor);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
