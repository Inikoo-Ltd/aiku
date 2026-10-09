<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\OrgAction;
use App\Actions\Web\Webpage\UpdateWebpage;
use App\Enums\Web\Seo\SeoContentSuggestionStateEnum;
use App\Models\SysAdmin\User;
use App\Models\Web\SeoContentSuggestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Writes a suggestion, as suggested or as edited by the person accepting it, to the webpage's title
 * or meta description through UpdateWebpage, so the change lands in the webpage history like any
 * other edit.
 */
class AcceptSeoContentSuggestion extends OrgAction
{
    use WithSeoEditAuthorisation;

    /**
     * @throws ValidationException
     */
    public function handle(SeoContentSuggestion $suggestion, ?string $value, ?User $user): SeoContentSuggestion
    {
        if ($suggestion->state !== SeoContentSuggestionStateEnum::PENDING) {
            throw ValidationException::withMessages(['value' => __('This suggestion has already been decided.')]);
        }

        $value = trim($value ?? $suggestion->suggestion);

        UpdateWebpage::make()->action($suggestion->webpage, [$suggestion->field => $value]);

        $suggestion->update([
            'suggestion'         => $value,
            'state'              => SeoContentSuggestionStateEnum::ACCEPTED,
            'decided_by_user_id' => $user?->id,
            'decided_at'         => now(),
        ]);

        return $suggestion;
    }

    public function rules(): array
    {
        return [
            'value' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function asController(SeoContentSuggestion $seoContentSuggestion, ActionRequest $request): SeoContentSuggestion
    {
        $this->initialisationFromShop($seoContentSuggestion->webpage->shop, $request);

        return $this->handle($seoContentSuggestion, $this->validatedData['value'] ?? null, $request->user());
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
