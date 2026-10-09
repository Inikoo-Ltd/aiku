<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\OrgAction;
use App\Enums\Web\Seo\SeoContentSuggestionStateEnum;
use App\Models\SysAdmin\User;
use App\Models\Web\SeoContentSuggestion;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * Keeps the webpage as it is. The page is not suggested again for that field for 90 days.
 */
class DismissSeoContentSuggestion extends OrgAction
{
    use WithSeoEditAuthorisation;

    public function handle(SeoContentSuggestion $suggestion, ?User $user): SeoContentSuggestion
    {
        if ($suggestion->state === SeoContentSuggestionStateEnum::PENDING) {
            $suggestion->update([
                'state'              => SeoContentSuggestionStateEnum::DISMISSED,
                'decided_by_user_id' => $user?->id,
                'decided_at'         => now(),
            ]);
        }

        return $suggestion;
    }

    public function asController(SeoContentSuggestion $seoContentSuggestion, ActionRequest $request): SeoContentSuggestion
    {
        $this->initialisationFromShop($seoContentSuggestion->webpage->shop, $request);

        return $this->handle($seoContentSuggestion, $request->user());
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
