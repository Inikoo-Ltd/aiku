<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Actions\Helpers\ClearCacheByWildcard;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithWebEditAuthorisation;
use App\Enums\Web\Crawl\CrawlTriggerEnum;
use App\Helpers\WebsiteTexts;
use App\Models\Web\Website;
use App\Models\Web\WebsiteTextTranslation;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * Staff correct a website text in another language (INI-510). The row becomes human, and TranslateWebsiteTexts only
 * inserts rows that are missing, so the AI never replaces it. Links, images and placeholders must stay as in the shop's
 * own text, as for the AI. The text can be in any page, header or footer, so the whole website cache is broken.
 */
class UpdateWebsiteTextTranslation extends OrgAction
{
    use WithWebEditAuthorisation;

    public function handle(WebsiteTextTranslation $websiteTextTranslation, array $modelData): WebsiteTextTranslation
    {
        $source = $websiteTextTranslation->source_text;
        if (!TranslateWebsiteTexts::make()->keepsMarkup($source, $modelData['text']) || !WebsiteTexts::nestsLike($source, $modelData['text'])) {
            throw ValidationException::withMessages([
                'text' => __('Keep every tag, link, image and placeholder of the original text as it is; change only the words.'),
            ]);
        }

        $websiteTextTranslation->update([
            'text'   => $modelData['text'],
            'origin' => WebsiteTextTranslation::HUMAN,
        ]);

        $website = $websiteTextTranslation->website;
        ClearCacheByWildcard::run("irisData:website:$website->id:*");
        BreakWebsiteCache::run($website, CrawlTriggerEnum::WEBSITE_UPDATE);

        return $websiteTextTranslation;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:100000'],
        ];
    }

    public function asController(Website $website, WebsiteTextTranslation $websiteTextTranslation, ActionRequest $request): void
    {
        abort_unless($websiteTextTranslation->website_id === $website->id, 404);
        $this->initialisationFromShop($website->shop, $request);

        $this->handle($websiteTextTranslation, $this->validatedData);
    }
}
