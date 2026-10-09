<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Actions\OrgAction;
use App\Models\Catalogue\Shop;
use App\Services\DataForSeo\DataForSeoException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Runs the AI visibility jobs for one shop at once instead of waiting for the scheduler, so the flow
 * can be tried locally: asks up to `PROMPTS` of the prompts that are due (about 30 seconds each) and
 * fetches the LLM Mentions figures if this month has none yet. Only routed in the local environment.
 */
class RunSeoAiVisibility extends OrgAction
{
    use WithSeoEditAuthorisation;

    private const int PROMPTS = 5;

    /**
     * @return array{asked: int, mentions: int}
     * @throws ValidationException
     */
    public function handle(Shop $shop): array
    {
        try {
            return [
                'asked'    => AskSeoAiPrompts::run($shop, self::PROMPTS),
                'mentions' => FetchSeoAiMentions::run($shop, true),
            ];
        } catch (DataForSeoException $e) {
            throw ValidationException::withMessages(['checks' => $e->getMessage()]);
        }
    }

    /**
     * @throws ValidationException
     */
    public function asController(Shop $shop, ActionRequest $request): array
    {
        abort_unless(app()->environment('local'), 404);

        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
