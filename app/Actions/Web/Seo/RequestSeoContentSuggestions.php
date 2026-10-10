<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\OrgAction;
use App\Models\Web\SeoContentSuggestion;
use App\Models\Web\Webpage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * A suggested title and/or description asked for from the webpage's SEO panel.
 */
class RequestSeoContentSuggestions extends OrgAction
{
    use WithSeoEditAuthorisation;

    /**
     * @throws ValidationException
     */
    public function handle(Webpage $webpage, array $fields, ActionRequest $request): Collection
    {
        return GenerateSeoContentSuggestions::run($webpage, $fields, SeoContentSuggestion::REASON_REQUESTED, $request->user());
    }

    public function rules(): array
    {
        return [
            'fields'   => ['required', 'array', 'min:1'],
            'fields.*' => ['string', Rule::in([SeoContentSuggestion::FIELD_TITLE, SeoContentSuggestion::FIELD_DESCRIPTION])],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function asController(Webpage $webpage, ActionRequest $request): Collection
    {
        $this->initialisationFromShop($webpage->shop, $request);

        return $this->handle($webpage, $this->validatedData['fields'], $request);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
