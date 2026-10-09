<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Models\Web\SeoCompetitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreSeoCompetitor extends OrgAction
{
    use WithSeoEditAuthorisation;

    public function handle(Shop $shop, array $modelData): SeoCompetitor
    {
        /** @var SeoCompetitor $competitor */
        $competitor = $shop->seoCompetitors()->create($modelData);

        return $competitor;
    }

    public function prepareForValidation(ActionRequest $request): void
    {
        if ($this->has('domain')) {
            $domain = Str::lower(trim((string) $this->get('domain')));
            $domain = parse_url(str_contains($domain, '://') ? $domain : 'https://'.$domain, PHP_URL_HOST) ?: $domain;

            $this->set('domain', Str::after($domain, 'www.'));
        }
    }

    public function rules(): array
    {
        return [
            'domain' => [
                'required',
                'string',
                'max:255',
                'regex:/^([a-z0-9-]+\.)+[a-z]{2,}$/',
                Rule::unique(SeoCompetitor::class, 'domain')->where('shop_id', $this->shop->id),
            ],
            'label'  => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function getValidationMessages(): array
    {
        return [
            'domain.regex'  => __('Enter a domain such as example.com.'),
            'domain.unique' => __('This competitor is already on the list.'),
        ];
    }

    public function asController(Shop $shop, ActionRequest $request): SeoCompetitor
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
