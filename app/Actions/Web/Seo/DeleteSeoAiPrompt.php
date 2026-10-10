<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\OrgAction;
use App\Models\Web\SeoAiPrompt;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * Deletes a prompt with its answers. To keep the history, switch the prompt off instead.
 */
class DeleteSeoAiPrompt extends OrgAction
{
    use WithSeoEditAuthorisation;

    public function handle(SeoAiPrompt $prompt): void
    {
        $prompt->delete();
    }

    public function asController(SeoAiPrompt $seoAiPrompt, ActionRequest $request): void
    {
        $this->initialisationFromShop($seoAiPrompt->shop, $request);

        $this->handle($seoAiPrompt);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
