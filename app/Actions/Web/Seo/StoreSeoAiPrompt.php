<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Helpers\Country;
use App\Models\Helpers\Language;
use App\Models\Web\SeoAiPrompt;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreSeoAiPrompt extends OrgAction
{
    use WithSeoEditAuthorisation;

    public function handle(Shop $shop, array $modelData): SeoAiPrompt
    {
        /** @var SeoAiPrompt $prompt */
        $prompt = $shop->seoAiPrompts()->create($modelData);

        return $prompt;
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if ($this->has('prompt')) {
            $this->set('prompt', trim(preg_replace('/\s+/u', ' ', (string) $this->get('prompt'))));
        }

        if ($this->has('country_code')) {
            $this->set('country_code', strtoupper((string) $this->get('country_code')));
        }

        if ($this->has('language_code')) {
            $this->set('language_code', strtolower((string) $this->get('language_code')));
        }
    }

    public function rules(): array
    {
        return [
            'prompt'        => [
                'required',
                'string',
                'min:10',
                'max:500',
                Rule::unique(SeoAiPrompt::class, 'prompt')->where(fn ($query) => $query
                    ->where('shop_id', $this->shop->id)
                    ->where('country_code', $this->get('country_code'))
                    ->where('language_code', $this->get('language_code'))),
            ],
            'country_code'  => ['required', 'string', Rule::exists(Country::class, 'code')],
            'language_code' => ['required', 'string', Rule::exists(Language::class, 'code')],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'prompt.unique' => __('This prompt is already asked for that country and language.'),
        ];
    }

    public function asController(Shop $shop, ActionRequest $request): SeoAiPrompt
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
