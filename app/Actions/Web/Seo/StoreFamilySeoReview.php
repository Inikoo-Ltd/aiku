<?php

/*
 * Created: Fri, 16 Oct 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Seo;

use App\Actions\OrgAction;
use App\Actions\Web\Webpage\UpdateWebpage;
use App\Enums\UI\Catalogue\FamilyTabsEnum;
use App\Enums\Web\Seo\SeoContentSuggestionStateEnum;
use App\Enums\Web\Seo\SeoKeywordFrequencyEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\User;
use App\Models\Web\SeoContentSuggestion;
use App\Models\Web\SeoTrackedKeyword;
use App\Models\Web\Webpage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\ActionRequest;

/**
 * The SEO team's review of a family webpage: the keywords it targets, and a suggestion per value
 * the webmaster should change. Sending again replaces the suggestions still waiting; sending only
 * structured data saves it to the page and leaves the suggestions as they are.
 */
class StoreFamilySeoReview extends OrgAction
{
    use WithSeoEditAuthorisation;

    public const string FIELD_FAQ = 'faq';

    public const string FIELD_STRUCTURED_DATA = 'structured_data';

    private ProductCategory $family;

    /**
     * The values a suggestion can be written for, keyed by the suggestion field, which the
     * suggestions table caps at 16 characters.
     *
     * @return array<string, ?string>
     */
    public static function currentValues(ProductCategory $family, Webpage $webpage): array
    {
        return [
            'title'            => $webpage->title,
            'description'      => $webpage->description,
            'url'              => $webpage->url,
            'breadcrumb_label' => $webpage->breadcrumb_label,
            'desc_title'       => $family->description_title,
            'top_desc'         => $family->description,
            'extra_desc'       => $family->description_extra,
        ];
    }

    public function handle(ProductCategory $family, array $modelData, ?User $user): int
    {
        $webpage = $family->webpage;

        if (!Arr::hasAny($modelData, ['keywords', 'suggestions', 'faq'])) {
            self::addStructuredData($webpage, Arr::get($modelData, 'structured_data', []));

            return 0;
        }

        $suggestionsCount = DB::transaction(function () use ($family, $webpage, $modelData, $user) {
            if (Arr::has($modelData, 'keywords')) {
                $this->targetKeywords($family->shop, $webpage, (string) $modelData['keywords']);
            }

            SeoContentSuggestion::where('webpage_id', $webpage->id)
                ->where('reason', SeoContentSuggestion::REASON_SEO_REVIEW)
                ->where('state', SeoContentSuggestionStateEnum::PENDING)
                ->get()
                ->each->delete();

            $currentValues = self::currentValues($family, $webpage);
            $suggestions   = collect(Arr::get($modelData, 'suggestions', []))
                ->map(fn ($suggestion) => trim((string) $suggestion))
                ->filter(fn (string $suggestion) => trim(html_entity_decode(strip_tags($suggestion))) !== '')
                ->map(fn (string $suggestion, string $field) => [$field, $currentValues[$field], $suggestion]);

            if ($faq = $this->faqSuggestion(Arr::get($modelData, 'faq', []))) {
                $suggestions->push([self::FIELD_FAQ, json_encode($family->faq), json_encode($faq)]);
            }

            self::addStructuredData($webpage, Arr::get($modelData, 'structured_data', []));

            $suggestions->each(fn (array $suggestion) => SeoContentSuggestion::create([
                'website_id'           => $webpage->website_id,
                'webpage_id'           => $webpage->id,
                'field'                => $suggestion[0],
                'current_value'        => $suggestion[1],
                'suggestion'           => $suggestion[2],
                'reason'               => SeoContentSuggestion::REASON_SEO_REVIEW,
                'state'                => SeoContentSuggestionStateEnum::PENDING,
                'requested_by_user_id' => $user?->id,
            ]));

            return $suggestions->count();
        });

        if ($user) {
            AskWebmastersToImplementSeoReview::run($webpage, $user, $suggestionsCount, route('grp.org.shops.show.catalogue.families.show', [
                'organisation' => $family->organisation->slug,
                'shop'         => $family->shop->slug,
                'family'       => $family->slug,
                'tab'          => FamilyTabsEnum::EDIT_PAGE->value,
            ]));
        }

        return $suggestionsCount;
    }

    /**
     * Keywords are tracked on request only, so targeting one never starts paid rank checks. A
     * keyword taken off the page keeps its ranking history and stops targeting this page.
     */
    private function targetKeywords(Shop $shop, Webpage $webpage, string $keywords): void
    {
        $keywords = collect(explode(',', $keywords))
            ->map(fn (string $keyword) => Str::lower(Str::squish($keyword)))
            ->filter()
            ->unique()
            ->values();

        SeoTrackedKeyword::where('target_webpage_id', $webpage->id)
            ->whereNotIn('keyword', $keywords)
            ->update(['target_webpage_id' => null]);

        foreach ($keywords as $keyword) {
            StoreSeoTrackedKeyword::run($shop, [
                'keyword'       => $keyword,
                'country_codes' => [$shop->country->code],
                'language_code' => $shop->language->code,
                'frequency'     => SeoKeywordFrequencyEnum::ON_REQUEST,
            ])->each(fn (SeoTrackedKeyword $trackedKeyword) => $trackedKeyword->update(['target_webpage_id' => $webpage->id]));
        }
    }

    /**
     * The SEO team's JSON-LD goes on the page straight away, without waiting for the webmaster.
     */
    public static function addStructuredData(Webpage $webpage, array $structuredData): void
    {
        $jsonLds = collect($structuredData)
            ->map(fn ($item) => trim((string) Arr::get($item, 'json_ld')))
            ->filter()
            ->all();

        if ($jsonLds === []) {
            return;
        }

        $structuredDataJson = ImplementFamilySeoSuggestion::structuredDataJsonWith($webpage, ...$jsonLds);
        if ($structuredDataJson !== $webpage->structuredDataJson()) {
            UpdateWebpage::make()->action($webpage, ['structured_data' => $structuredDataJson]);
        }
    }

    /**
     * Items line up with the family FAQ by position; the FAQ as it was is kept as the current value.
     *
     * @return null|array{items: array<int, array{question: string, answer: string, remove: bool}>, new: array<int, array{question: string, answer: string}>}
     */
    /**
     * The JSON-LD items of a structured data suggestion; one written before a review could
     * suggest several is read as a single item.
     *
     * @return array<int, array{json_ld: string, why: ?string, implemented?: bool}>
     */
    public static function structuredDataItems(?string $suggestion): array
    {
        $value = json_decode((string) $suggestion, true) ?? [];

        return $value['items'] ?? (filled($value['json_ld'] ?? null) ? [['json_ld' => $value['json_ld'], 'why' => $value['why'] ?? null]] : []);
    }

    private function faqSuggestion(array $faq): ?array
    {
        $items = collect(Arr::get($faq, 'items', []))->map(fn ($item) => [
            'question' => trim((string) Arr::get($item, 'question')),
            'answer'   => trim((string) Arr::get($item, 'answer')),
            'remove'   => (bool) Arr::get($item, 'remove'),
        ]);

        $newItems = collect(Arr::get($faq, 'new', []))
            ->map(fn ($item) => [
                'question' => trim((string) Arr::get($item, 'question')),
                'answer'   => trim((string) Arr::get($item, 'answer')),
            ])
            ->filter(fn (array $item) => $item['question'] !== '')
            ->values();

        $hasChange = $items->contains(fn (array $item) => $item['remove'] || $item['question'] !== '' || $item['answer'] !== '');

        return $hasChange || $newItems->isNotEmpty() ? ['items' => $items->all(), 'new' => $newItems->all()] : null;
    }

    public function rules(): array
    {
        return [
            'keywords'             => ['sometimes', 'nullable', 'string', 'max:1000'],
            'suggestions'          => ['sometimes', 'array:'.implode(',', array_keys(self::currentValues($this->family, $this->family->webpage)))],
            'suggestions.*'        => ['nullable', 'string', 'max:65500'],
            'faq'                  => ['sometimes', 'array'],
            'faq.items'            => ['sometimes', 'array'],
            'faq.items.*.question' => ['nullable', 'string', 'max:65500'],
            'faq.items.*.answer'   => ['nullable', 'string', 'max:65500'],
            'faq.items.*.remove'   => ['sometimes', 'boolean'],
            'faq.new'              => ['sometimes', 'array'],
            'faq.new.*.question'   => ['nullable', 'string', 'max:65500'],
            'faq.new.*.answer'     => ['nullable', 'string', 'max:65500'],
            'structured_data'           => ['sometimes', 'array'],
            'structured_data.*.json_ld' => ['nullable', 'json'],
        ];
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): int
    {
        abort_unless($productCategory->webpage, 404);

        $this->family = $productCategory;
        $this->initialisationFromShop($productCategory->shop, $request);

        return $this->handle($productCategory, $this->validatedData, $request->user());
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
