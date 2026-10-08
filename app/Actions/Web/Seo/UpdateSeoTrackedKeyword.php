<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\OrgAction;
use App\Enums\Web\Seo\SeoKeywordDeviceEnum;
use App\Enums\Web\Seo\SeoKeywordFrequencyEnum;
use App\Models\Web\SeoTrackedKeyword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateSeoTrackedKeyword extends OrgAction
{
    use WithSeoEditAuthorisation;

    public function handle(SeoTrackedKeyword $trackedKeyword, array $modelData): SeoTrackedKeyword
    {
        $trackedKeyword->update($modelData);

        return $trackedKeyword;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['sometimes', 'boolean'],
            'device'    => ['sometimes', Rule::enum(SeoKeywordDeviceEnum::class)],
            'frequency' => ['sometimes', Rule::enum(SeoKeywordFrequencyEnum::class)],
        ];
    }

    public function asController(SeoTrackedKeyword $seoTrackedKeyword, ActionRequest $request): SeoTrackedKeyword
    {
        $this->initialisationFromShop($seoTrackedKeyword->shop, $request);

        return $this->handle($seoTrackedKeyword, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
