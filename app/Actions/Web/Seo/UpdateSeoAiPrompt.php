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

class UpdateSeoAiPrompt extends OrgAction
{
    use WithSeoEditAuthorisation;

    public function handle(SeoAiPrompt $prompt, array $modelData): SeoAiPrompt
    {
        $prompt->update($modelData);

        return $prompt;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['required', 'boolean'],
        ];
    }

    public function asController(SeoAiPrompt $seoAiPrompt, ActionRequest $request): SeoAiPrompt
    {
        $this->initialisationFromShop($seoAiPrompt->shop, $request);

        return $this->handle($seoAiPrompt, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
