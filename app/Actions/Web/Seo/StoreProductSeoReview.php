<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Created: Fri, 16 Oct 2026
 * Copyright (c) 2026
 */

namespace App\Actions\Web\Seo;

use App\Actions\OrgAction;
use App\Enums\Web\Seo\SeoContentSuggestionStateEnum;
use App\Enums\Web\Seo\SeoKeywordFrequencyEnum;
use App\Models\Catalogue\Product;
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
 * The SEO team's review of a product webpage, the product counterpart of StoreFamilySeoReview:
 * the keywords it targets and a suggestion per value the webmaster should change. Sending again
 * replaces the suggestions still waiting.
 */
class StoreProductSeoReview extends OrgAction
{
    use WithSeoEditAuthorisation;

    private Product $product;

    /**
     * The values a suggestion can be written for, keyed by the suggestion field, which the
     * suggestions table caps at 16 characters.
     *
     * @return array<string, ?string>
     */
    public static function currentValues(Product $product, Webpage $webpage): array
    {
        return [
            'title'            => $webpage->title,
            'description'      => $webpage->description,
            'url'              => $webpage->url,
            'breadcrumb_label' => $webpage->breadcrumb_label,
            'product_name'     => $product->name,
            'top_desc'         => $product->description,
            'extra_desc'       => $product->description_extra,
        ];
    }

    public function handle(Product $product, array $modelData, ?User $user): int
    {
        $webpage = $product->webpage;

        $suggestionsCount = DB::transaction(function () use ($product, $webpage, $modelData, $user) {
            if (Arr::has($modelData, 'keywords')) {
                $this->targetKeywords($product, $webpage, (string)$modelData['keywords']);
            }

            SeoContentSuggestion::where('webpage_id', $webpage->id)
                ->where('reason', SeoContentSuggestion::REASON_SEO_REVIEW)
                ->where('state', SeoContentSuggestionStateEnum::PENDING)
                ->get()
                ->each->delete();

            $currentValues = self::currentValues($product, $webpage);
            $suggestions   = collect(Arr::get($modelData, 'suggestions', []))
                ->map(fn ($suggestion) => trim((string)$suggestion))
                ->filter(fn (string $suggestion) => trim(html_entity_decode(strip_tags($suggestion))) !== '')
                ->map(fn (string $suggestion, string $field) => [$field, $currentValues[$field], $suggestion]);

            StoreFamilySeoReview::addStructuredData($webpage, Arr::get($modelData, 'structured_data', []));

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
            AskWebmastersToImplementSeoReview::run($webpage, $user, $suggestionsCount, route('grp.org.shops.show.catalogue.products.all_products.setting', [
                'organisation' => $product->organisation->slug,
                'shop'         => $product->shop->slug,
                'product'      => $product->slug,
                'tab'          => 'validation',
            ]), ['model_type' => 'Product', 'model_id' => $product->id]);
        }

        return $suggestionsCount;
    }

    /**
     * Same as the family review: keywords are tracked on request only, so targeting one never
     * starts paid rank checks.
     */
    private function targetKeywords(Product $product, Webpage $webpage, string $keywords): void
    {
        $shop     = $product->shop;
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

    public function rules(): array
    {
        return [
            'keywords'        => ['sometimes', 'nullable', 'string', 'max:1000'],
            'suggestions'     => ['sometimes', 'array:'.implode(',', array_keys(self::currentValues($this->product, $this->product->webpage)))],
            'suggestions.*'   => ['nullable', 'string', 'max:65500'],
            'structured_data'           => ['sometimes', 'array'],
            'structured_data.*.json_ld' => ['nullable', 'json'],
        ];
    }

    public function asController(Product $product, ActionRequest $request): int
    {
        abort_unless($product->webpage, 404);

        $this->product = $product;
        $this->initialisationFromShop($product->shop, $request);

        return $this->handle($product, $this->validatedData, $request->user());
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
