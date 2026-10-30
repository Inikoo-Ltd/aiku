<?php

/*
 * Created: Fri, 16 Oct 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\ProductCategory\UI;

use App\Actions\Traits\Authorisations\WithShopPpcPermissions;
use App\Actions\Web\Seo\ImplementFamilySeoSuggestion;
use App\Actions\Web\Seo\StoreFamilySeoReview;
use App\Actions\Web\Webpage\GetWebpageStructuredDataSource;
use App\Actions\Web\Webpage\UI\WithWebpageSeoImageField;
use App\Actions\Web\Webpage\WithWebpageSeoData;
use App\Enums\Web\Seo\SeoContentSuggestionStateEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Http\Resources\Helpers\LanguageResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\SysAdmin\User;
use App\Models\Web\SeoContentSuggestion;
use App\Models\Web\SeoTrackedKeyword;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;

class GetFamilyEditPage
{
    use AsObject;
    use WithShopPpcPermissions;
    use WithWebpageSeoImageField;
    use WithWebpageSeoData;

    private const array EDITOR_TOOLBAR = ['heading1', 'heading2', 'heading3', 'bold', 'italic', 'fontSize', 'underline', 'bulletList', 'customLink', 'undo', 'redo', 'highlight', 'color', 'clear'];

    public function handle(ProductCategory $family, bool $canEdit, User $user): array
    {
        $webpage        = $family->webpage;
        $canEditWebpage = ImplementFamilySeoSuggestion::isWebmaster($user, $family);

        $keywords = $webpage
            ? SeoTrackedKeyword::where('target_webpage_id', $webpage->id)->where('is_active', true)->distinct()->orderBy('keyword')->pluck('keyword')->all()
            : [];
        $faqCount = collect($family->faq)->filter(fn ($item) => filled(Arr::get($item, 'question')))->count();

        $titleCheck       = $this->keywordCheck($webpage?->title, $keywords);
        $descriptionCheck = $this->keywordCheck($webpage?->description, $keywords);
        $topCheck         = $this->keywordCheck($family->description, $keywords);
        $urlCheck         = $this->check($webpage && !str_contains($webpage->url, '_'), __('No underscores'), __('Contains underscore'));
        $crumbCheck       = $this->check(filled($webpage?->breadcrumb_label), __('Filled in'), __('Empty'));
        $extraCheck       = $this->check(filled(strip_tags((string) $family->description_extra)), __('Filled in'), __('Empty'));
        $faqCheck         = $this->check($faqCount > 0, trans_choice(':count question|:count questions', $faqCount), __('No questions yet'));
        $keywordsCheck    = $this->check(count($keywords) > 0, trans_choice(':count keyword set|:count keywords set', count($keywords)), __('Not set yet'));

        $byWebmaster    = __('Filled in by webmaster');
        $familyRoute    = ['name' => 'grp.models.product_category.update', 'parameters' => ['productCategory' => $family->id]];
        $webpageRoute   = $webpage ? ['name' => 'grp.models.webpage.update', 'parameters' => ['webpage' => $webpage->id]] : null;
        $languages      = [$family->shop->language_id => LanguageResource::make($family->shop->language)->resolve()];
        $showsIndexing  = $webpage && !$webpage->sub_type?->isHiddenFromSearchEngines();
        $followsPricing = data_get($family->shop->settings, 'catalog.follow_master_pricing', true);

        $sections = [];

        if ($webpage) {
            $sections[] = $this->section(__('Targeted keywords'), __('Owned by the SEO team'), [
                [
                    'label'    => __('Targeted keywords'),
                    'owner'    => __('Set by SEO team'),
                    'check'    => $keywordsCheck,
                    'keywords' => $keywords,
                    'fields'   => [],
                ],
            ]);
        }

        $sections[] = $this->section(__('Page content'), __('Filled in by the webmaster when the page is created'), array_values(array_filter([
            $webpage ? $this->card(__('Meta title (Browser title)'), $byWebmaster, $webpageRoute, [
                'title' => $this->bare(['type' => 'input', 'options' => ['counter' => true], 'value' => $webpage->title]),
            ], !$canEditWebpage, $titleCheck, 'title') : null,

            $webpage ? $this->card(__('Title extras'), $byWebmaster, $webpageRoute, [
                'use_title_prefix_suffix' => [
                    'type'        => 'toggle',
                    'label'       => __('Add extra words to the title'),
                    'information' => __('Adds your prefix and suffix around this page title. Switch it off if you want the title to show exactly as you typed it.'),
                    'value'       => (bool) Arr::get($webpage->seo_data, 'use_title_prefix_suffix', true),
                ],
                'webpage_title_prefix'    => [
                    'type'        => 'input',
                    'label'       => __('Title Prefix'),
                    'information' => __('Words that go in front of this page title. Leave it empty and we will use the one from your website settings.'),
                    'value'       => data_get($webpage->settings, 'webpage.title_prefix'),
                ],
                'webpage_title_suffix'    => [
                    'type'        => 'input',
                    'label'       => __('Title Suffix'),
                    'information' => __('Words that go after this page title, like your shop name. Leave it empty and we will use the one from your website settings.'),
                    'value'       => data_get($webpage->settings, 'webpage.title_suffix'),
                ],
            ], !$canEditWebpage) : null,

            $webpage ? $this->card(__('Meta description'), $byWebmaster, $webpageRoute, [
                'description' => $this->metaDescriptionField($family, $webpage, $languages),
            ], !$canEditWebpage, $descriptionCheck, 'description') : null,

            $webpage ? $this->card(__('Share image'), $byWebmaster, $webpageRoute, [
                'seo_image' => $this->bare(Arr::except($this->webpageSeoImageField($webpage), 'label')),
            ], !$canEditWebpage, key: 'seo_image') : null,

            $webpage ? $this->card(__('URL'), $byWebmaster, $webpageRoute, [
                'url' => $this->bare([
                    'type'      => 'inputWithAddOn',
                    'leftAddOn' => ['label' => 'https://'.$webpage->website->domain.'/'],
                    'required'  => true,
                    'value'     => $webpage->url,
                ]),
            ], !$canEditWebpage, $urlCheck, 'url') : null,

            $webpage ? $this->card(__('Breadcrumb label'), $byWebmaster, $webpageRoute, [
                'breadcrumb_label' => $this->bare(['type' => 'input', 'options' => ['counter' => true], 'value' => $webpage->breadcrumb_label]),
            ], !$canEditWebpage, $crumbCheck, 'breadcrumb_label') : null,

            $this->card(__('Description title'), $byWebmaster, $familyRoute, [
                'description_title' => $this->translatedField($family, 'description_title', false, $languages),
            ], !$canEdit, key: 'desc_title'),

            $this->card(__('Top description'), $byWebmaster, $familyRoute, [
                'description' => $this->translatedField($family, 'description', true, $languages),
            ], !$canEdit, $webpage ? $topCheck : null, 'top_desc'),

            $this->card(__('Extra description'), $byWebmaster, $familyRoute, [
                'description_extra' => $this->translatedField($family, 'description_extra', true, $languages),
            ], !$canEdit, $extraCheck, 'extra_desc'),

            $followsPricing ? $this->card(__('Price'), $byWebmaster, $familyRoute, [
                'not_follow_master_prices' => [
                    'type'        => 'toggle',
                    'label'       => __('Do not follow master prices'),
                    'value'       => $family->not_follow_master_prices,
                    'information' => __('Enabling this would allow all of the products under this family to have their price editable and it will stop following master'),
                    'warningText' => __('Modifying this setting would cause those products to either diverge/follow master').'. '.__('Are you sure you want to do this?'),
                ],
            ], !$canEdit) : null,

            $showsIndexing ? $this->card(__('Indexing'), $byWebmaster, $webpageRoute, [
                'index_page'  => [
                    'type'        => 'toggle',
                    'label'       => __('Index Page'),
                    'information' => __('On: search engines may index this page (index). Off: noindex.'),
                    'value'       => $webpage->index_page ?? true,
                ],
                'follow_link' => [
                    'type'        => 'toggle',
                    'label'       => __('Follow Link'),
                    'information' => __('On: search engines follow the links on this page (follow). Off: nofollow.'),
                    'value'       => $webpage->follow_link ?? true,
                ],
            ], !$canEditWebpage, key: 'indexing') : null,
        ])));

        $sections[] = $this->section(__('FAQ'), __('Master text on the left, translated version on the right'), [
            $this->card(__('FAQ'), $byWebmaster, $familyRoute, ['faq' => $this->faqField($family, $languages)], !$canEdit, $faqCheck, 'faq'),
        ]);

        return [
            'notice'         => $webpage ? null : __('This family has no webpage yet, so only its content can be edited here.'),
            'workshop_route' => $webpage ? [
                'name'       => 'grp.org.shops.show.web.webpages.workshop',
                'parameters' => [
                    'organisation' => $family->organisation->slug,
                    'shop'         => $family->shop->slug,
                    'website'      => $webpage->website->slug,
                    'webpage'      => $webpage->slug,
                ],
            ] : null,
            'sections'       => $sections,
            'preview'        => $webpage ? $this->getWebpagePreview($webpage) : null,
            'structured_data_source' => $webpage ? GetWebpageStructuredDataSource::run($webpage) : null,
            'page_score'     => GetFamilyPageScore::run($family),
            'seo_review'     => $webpage ? $this->seoReview($family, $webpage, $keywords, [
                'keywords'         => $keywordsCheck,
                'title'            => $titleCheck,
                'description'      => $descriptionCheck,
                'url'              => $urlCheck,
                'breadcrumb_label' => $crumbCheck,
                'top_desc'         => $topCheck,
                'extra_desc'       => $extraCheck,
                'faq'              => $faqCheck,
            ], $this->canEditSeo($user, $family->shop)) : null,
            'webmaster_validation' => $webpage ? $this->webmasterValidation($family, $webpage, $user) : null,
            'seo_issues'           => GetFamilySeoIssues::run($family),
        ];
    }

    /**
     * The suggestions of the SEO review still to implement or validate, as one task per value, FAQ
     * item or new FAQ question.
     */
    private function webmasterValidation(ProductCategory $family, Webpage $webpage, User $user): array
    {
        $suggestions = SeoContentSuggestion::where('webpage_id', $webpage->id)
            ->where('reason', SeoContentSuggestion::REASON_SEO_REVIEW)
            ->whereIn('state', [SeoContentSuggestionStateEnum::PENDING, SeoContentSuggestionStateEnum::IMPLEMENTED])
            ->orderBy('id')
            ->get()
            ->keyBy('field');

        $task = fn (SeoContentSuggestion $suggestion, string $key, array $rows, string $copyText, bool $isDone, array $data = [], ?string $title = null, ?string $note = null) => [
            'key'             => $key,
            'card'            => $suggestion->field,
            'title'           => $title,
            'is_new'          => Arr::has($data, 'new'),
            'rows'            => $rows,
            'note'            => $note,
            'copy_text'       => $copyText,
            'is_done'         => $isDone,
            'can_implement'   => ImplementFamilySeoSuggestion::canImplement($user, $family, $suggestion->field),
            'implement_route' => [
                'name'       => 'grp.models.product_category.seo_review.implement',
                'parameters' => ['productCategory' => $family->id, 'seoContentSuggestion' => $suggestion->id],
            ],
            'implement_data'  => $data,
        ];
        $row = fn (?string $label, ?string $current, ?string $suggestion, bool $isHtml = false, bool $isCode = false) => [
            'label'      => $label,
            'current'    => $current,
            'suggestion' => $suggestion,
            'is_html'    => $isHtml,
            'is_code'    => $isCode,
        ];

        $tasks = [];
        foreach (StoreFamilySeoReview::currentValues($family, $webpage) as $field => $current) {
            if ($suggestion = $suggestions->get($field)) {
                $isDone  = $suggestion->state === SeoContentSuggestionStateEnum::IMPLEMENTED;
                $tasks[] = $task($suggestion, $field, [$row(null, $current, $suggestion->suggestion, in_array($field, ['top_desc', 'extra_desc']))], $suggestion->suggestion, $isDone);
            }
        }

        if ($suggestion = $suggestions->get(StoreFamilySeoReview::FIELD_FAQ)) {
            $review   = json_decode($suggestion->suggestion, true);
            $reviewed = array_values(json_decode((string) $suggestion->current_value, true) ?? []);

            foreach ($review['items'] as $index => $item) {
                if (ImplementFamilySeoSuggestion::isFaqChange($item)) {
                    $tasks[] = $task($suggestion, "faq-item-$index", array_values(array_filter([
                        $row(__('Question'), data_get($reviewed, "$index.question"), $item['remove'] ? __('Remove this question') : $item['question'], true),
                        $item['remove'] ? null : $row(__('Answer'), data_get($reviewed, "$index.answer"), $item['answer'], true),
                    ])), trim($item['question']."\n\n".$item['answer']), $item['implemented'] ?? false, ['item' => $index], __('Question :number', ['number' => $index + 1]));
                }
            }

            foreach ($review['new'] as $index => $item) {
                $tasks[] = $task($suggestion, "faq-new-$index", [
                    $row(__('Question'), null, $item['question'], true),
                    $row(__('Answer'), null, $item['answer'], true),
                ], trim($item['question']."\n\n".$item['answer']), $item['implemented'] ?? false, ['new' => $index], __('New question'));
            }
        }

        if ($suggestion = $suggestions->get(StoreFamilySeoReview::FIELD_STRUCTURED_DATA)) {
            foreach (StoreFamilySeoReview::structuredDataItems($suggestion->suggestion) as $index => $item) {
                $tasks[] = $task($suggestion, "structured_data-$index", [
                    $row(null, $webpage->structuredDataJson(), $item['json_ld'], isCode: true),
                ], $item['json_ld'], $item['implemented'] ?? $suggestion->state === SeoContentSuggestionStateEnum::IMPLEMENTED, ['item' => $index], __('New structured data :number', ['number' => $index + 1]), $item['why'] ?? null);
            }
        }

        $validatedAt = $suggestions->isEmpty()
            ? SeoContentSuggestion::where('webpage_id', $webpage->id)->where('reason', SeoContentSuggestion::REASON_SEO_REVIEW)->whereIn('state', [SeoContentSuggestionStateEnum::ACCEPTED, SeoContentSuggestionStateEnum::DISMISSED])->latest('updated_at')->value('updated_at')
            : null;
        $doneCount   = collect($tasks)->where('is_done', true)->count();

        return [
            'validate_route' => ['name' => 'grp.models.product_category.seo_review.validate', 'parameters' => ['productCategory' => $family->id]],
            'can_validate'   => ImplementFamilySeoSuggestion::isWebmaster($user, $family),
            'is_validated'   => $validatedAt !== null,
            'tasks'          => $tasks,
            'activity'       => collect([
                [__('Webmaster'), $webpage->created_at, __('Created the family page.')],
                [__('Webmaster'), $webpage->live_at, __('Published the page.')],
                [__('SEO team'), $suggestions->min('created_at'), trans_choice('Sent :count suggestion to the webmaster.|Sent :count suggestions to the webmaster.', count($tasks))],
                [__('Webmaster'), $doneCount ? $suggestions->max('decided_at') : null, __('Implemented :done of :total suggestions.', ['done' => $doneCount, 'total' => count($tasks)])],
                [__('Webmaster'), $validatedAt, __('Validated the SEO suggestions.')],
            ])
                ->filter(fn (array $entry) => $entry[1])
                ->sortByDesc(fn (array $entry) => $entry[1]->getTimestamp())
                ->map(fn (array $entry) => ['who' => $entry[0], 'at' => $entry[1]->toIso8601String(), 'text' => $entry[2]])
                ->values()
                ->all(),
        ];
    }

    private function seoReview(ProductCategory $family, Webpage $webpage, array $keywords, array $checks, bool $canReview): array
    {
        $pending = SeoContentSuggestion::where('webpage_id', $webpage->id)
            ->where('reason', SeoContentSuggestion::REASON_SEO_REVIEW)
            ->where('state', SeoContentSuggestionStateEnum::PENDING)
            ->pluck('suggestion', 'field');

        $labels = [
            'title'            => __('Meta title (Browser title)'),
            'description'      => __('Meta description'),
            'url'              => __('URL'),
            'breadcrumb_label' => __('Breadcrumb label'),
            'desc_title'       => __('Description title'),
            'top_desc'         => __('Top description'),
            'extra_desc'       => __('Extra description'),
        ];

        $faqSuggestion            = json_decode($pending->get(StoreFamilySeoReview::FIELD_FAQ, ''), true) ?? [];

        return [
            'can_review'                 => $canReview,
            'store_route'                => ['name' => 'grp.models.product_category.seo_review.store', 'parameters' => ['productCategory' => $family->id]],
            'sent_count'                 => $pending->count(),
            'is_online'                  => $webpage->state == WebpageStateEnum::LIVE,
            'keywords'                   => implode(', ', $keywords),
            'checks'                     => $checks,
            'fields'                     => collect(StoreFamilySeoReview::currentValues($family, $webpage))
                ->map(fn (?string $value, string $field) => [
                    'key'        => $field,
                    'label'      => $labels[$field],
                    'current'    => $value,
                    'suggestion' => $pending->get($field, ''),
                    'is_html'    => in_array($field, ['top_desc', 'extra_desc']),
                    'is_long'    => in_array($field, ['description', 'top_desc', 'extra_desc']),
                ])
                ->values()
                ->all(),
            'faq'                        => collect($family->faq)->values()->map(fn ($item, int $index) => [
                'question'           => (string) Arr::get($item, 'question'),
                'answer'             => (string) Arr::get($item, 'answer'),
                'suggested_question' => data_get($faqSuggestion, "items.$index.question", ''),
                'suggested_answer'   => data_get($faqSuggestion, "items.$index.answer", ''),
                'remove'             => (bool) data_get($faqSuggestion, "items.$index.remove", false),
            ])->all(),
            'new_faq'                    => data_get($faqSuggestion, 'new', []),
            'structured_data'            => $webpage->structuredDataJson() ?? '',
            'structured_data_suggestions' => collect(StoreFamilySeoReview::structuredDataItems($pending->get(StoreFamilySeoReview::FIELD_STRUCTURED_DATA)))
                ->map(fn (array $item) => ['json_ld' => $item['json_ld']])
                ->all(),
        ];
    }

    private function section(string $title, string $subtitle, array $cards): array
    {
        return ['title' => $title, 'subtitle' => $subtitle, 'cards' => $cards];
    }

    private function card(?string $label, ?string $owner, array $updateRoute, array $fields, bool $readonly, ?array $check = null, ?string $key = null): array
    {
        return [
            'key'          => $key,
            'label'        => $label,
            'owner'        => $owner,
            'check'        => $check,
            'update_route' => $updateRoute,
            'fields'       => array_map(fn (array $field) => $field + ['readonly' => $readonly], $fields),
        ];
    }

    private function bare(array $field): array
    {
        return $field + ['noTitle' => true, 'full' => true];
    }

    private function check(bool $ok, string $okNote, string $badNote): array
    {
        return ['ok' => $ok, 'note' => $ok ? $okNote : $badNote];
    }

    private function keywordCheck(?string $text, array $keywords): array
    {
        if (!$keywords) {
            return ['ok' => false, 'note' => __('Needs keywords')];
        }

        return $this->check(Str::contains(strip_tags((string) $text), $keywords, true), __('Contains keyword'), __('No keyword'));
    }

    private function translatedField(ProductCategory $family, string $field, bool $isRichText, array $languages): array
    {
        $richText = $isRichText ? [
            'toggle'               => self::EDITOR_TOOLBAR,
            'routeGetInternalLink' => [
                'name'       => 'grp.org.shops.show.web.webpages.index',
                'parameters' => [
                    'organisation' => $family->organisation->slug,
                    'shop'         => $family->shop->slug,
                    'website'      => $family->shop->website?->slug,
                ],
            ],
        ] : [];

        if (!$family->masterProductCategory) {
            return $this->bare([
                'type'    => $isRichText ? 'textEditor' : 'input',
                'options' => ['counter' => true],
                'value'   => $family->{$field},
            ] + $richText);
        }

        return $this->bare([
            'type'          => $isRichText ? 'textEditor_translation' : 'input_translation',
            'language_from' => 'en',
            'main'          => $family->masterProductCategory->{$field},
            'languages'     => $languages,
            'mode'          => 'single',
            'value'         => $family->{$field},
            'reviewed'      => $family->{'is_'.$field.'_reviewed'},
            'review_route'  => ['name' => 'grp.models.product_category.translation_review.store', 'parameters' => ['productCategory' => $family->id]],
        ] + $richText);
    }

    private function metaDescriptionField(ProductCategory $family, Webpage $webpage, array $languages): array
    {
        if (!$family->masterProductCategory) {
            return $this->bare(['type' => 'textarea', 'maxLength' => 150, 'counter' => true, 'value' => $webpage->description]);
        }

        return $this->bare([
            'type'          => 'input_translation',
            'textarea'      => true,
            'language_from' => 'en',
            'main'          => $family->masterProductCategory->meta_description,
            'languages'     => $languages,
            'mode'          => 'single',
            'value'         => $webpage->description,
        ]);
    }

    private function faqField(ProductCategory $family, array $languages): array
    {
        if (!$family->masterProductCategory) {
            return $this->bare(['type' => 'faq-shop', 'value' => $family->faq]);
        }

        return $this->bare([
            'type'          => 'faq-shop',
            'language_from' => 'en',
            'noSaveButton'  => true,
            'main'          => $family->masterProductCategory->faq,
            'languages'     => $languages,
            'mode'          => 'single',
            'value'         => $family->faq,
            'toggle'        => ['bold', 'italic', 'underline', 'bulletList', 'customLink', 'undo', 'redo', 'highlight', 'color', 'clear'],
        ]);
    }
}
