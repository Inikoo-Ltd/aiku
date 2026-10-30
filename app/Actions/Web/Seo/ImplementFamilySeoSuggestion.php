<?php

/*
 * Created: Fri, 16 Oct 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Seo;

use App\Actions\Catalogue\ProductCategory\UpdateProductCategory;
use App\Actions\OrgAction;
use App\Actions\Web\Webpage\UpdateWebpage;
use App\Enums\Web\Seo\SeoContentSuggestionStateEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\SysAdmin\User;
use App\Models\Web\SeoContentSuggestion;
use App\Models\Web\Webpage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The webmaster applies one suggestion of the SEO review, as suggested or as edited in the
 * validation tab. Without an edited value, a value the webmaster already changed since the review
 * is kept as written; one still as the SEO team saw it is replaced by the suggestion. FAQ items
 * are applied one at a time.
 */
class ImplementFamilySeoSuggestion extends OrgAction
{
    /**
     * Suggestion fields stored on the family, keyed to the family attribute. The rest are webpage fields.
     */
    public const array FAMILY_FIELDS = ['desc_title' => 'description_title', 'top_desc' => 'description', 'extra_desc' => 'description_extra'];

    private ProductCategory $family;

    private SeoContentSuggestion $suggestion;

    public static function isWebmaster(User $user, ProductCategory $family): bool
    {
        return (bool) $family->webpage?->canBeEditedBy($user) && $user->authTo([
            'group-webmaster.view',
            "supervisor-web.$family->shop_id",
            "web.$family->shop_id.edit",
        ]);
    }

    public static function canImplement(User $user, ProductCategory $family, string $field): bool
    {
        if (isset(self::FAMILY_FIELDS[$field]) || $field === StoreFamilySeoReview::FIELD_FAQ) {
            return $user->authTo("products.$family->shop_id.edit");
        }

        return self::isWebmaster($user, $family);
    }

    /**
     * @throws ValidationException
     */
    public function handle(ProductCategory $family, SeoContentSuggestion $suggestion, array $modelData, ?User $user): SeoContentSuggestion
    {
        if ($suggestion->state !== SeoContentSuggestionStateEnum::PENDING) {
            throw ValidationException::withMessages(['suggestion' => __('This suggestion has already been implemented.')]);
        }

        return DB::transaction(function () use ($family, $suggestion, $modelData, $user) {
            $isDone = match ($suggestion->field) {
                StoreFamilySeoReview::FIELD_FAQ             => $this->implementFaq($family, $suggestion, $modelData),
                StoreFamilySeoReview::FIELD_STRUCTURED_DATA => self::implementStructuredDataItem($family->webpage, $suggestion, $modelData),
                default                                     => $this->implementValue($family, $suggestion, $modelData),
            };

            if ($isDone) {
                $suggestion->update([
                    'state'              => SeoContentSuggestionStateEnum::IMPLEMENTED,
                    'decided_by_user_id' => $user?->id,
                    'decided_at'         => now(),
                ]);
            }

            return $suggestion;
        });
    }

    private function implementValue(ProductCategory $family, SeoContentSuggestion $suggestion, array $modelData): bool
    {
        $field = $suggestion->field;
        $live  = StoreFamilySeoReview::currentValues($family, $family->webpage)[$field];

        if (Arr::has($modelData, 'value') || $this->isUnchanged($live, $suggestion->current_value)) {
            $value = Arr::get($modelData, 'value', $suggestion->suggestion);
            isset(self::FAMILY_FIELDS[$field])
                ? UpdateProductCategory::make()->action($family, [self::FAMILY_FIELDS[$field] => $value])
                : UpdateWebpage::make()->action($family->webpage, [$field => $value]);
        }

        return true;
    }

    /**
     * Each suggested item is added to the page's list on its own; nodes already on the page,
     * as when the webmaster pasted them by hand, are not added twice.
     *
     * @throws ValidationException
     */
    public static function implementStructuredDataItem(Webpage $webpage, SeoContentSuggestion $suggestion, array $modelData): bool
    {
        $items = StoreFamilySeoReview::structuredDataItems($suggestion->suggestion);
        $index = (int) Arr::get($modelData, 'item', count($items) === 1 ? 0 : -1);
        $item  = $items[$index] ?? throw ValidationException::withMessages(['suggestion' => __('This structured data suggestion does not exist.')]);

        $structuredData = self::structuredDataJsonWith($webpage, $item['json_ld']);
        if ($structuredData !== $webpage->structuredDataJson()) {
            UpdateWebpage::make()->action($webpage, ['structured_data' => $structuredData]);
        }

        $items[$index]['implemented'] = true;
        $suggestion->update(['suggestion' => json_encode(['items' => $items])]);

        return collect($items)->every(fn (array $item) => $item['implemented'] ?? false);
    }

    /**
     * Custom JSON-LD is kept as a list of nodes, so new JSON-LD is added to what the page has
     * instead of replacing it; a single node or an @graph is split into that list, and a node
     * the page already has is not added again.
     */
    public static function structuredDataJsonWith(Webpage $webpage, string ...$jsonLds): string
    {
        $nodes = self::structuredDataNodes(json_decode($webpage->structuredDataJson() ?? '', true));

        foreach ($jsonLds as $jsonLd) {
            foreach (self::structuredDataNodes(json_decode($jsonLd, true)) as $node) {
                if (!in_array($node, $nodes)) {
                    $nodes[] = $node;
                }
            }
        }

        return json_encode($nodes, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function structuredDataNodes(mixed $value): array
    {
        if (!is_array($value) || $value === []) {
            return [];
        }

        if (array_is_list($value)) {
            return array_merge(...array_map(fn (mixed $item) => self::structuredDataNodes($item), $value));
        }

        if (is_array($value['@graph'] ?? null)) {
            return array_values(array_map(
                fn (array $node) => ['@context' => $value['@context'] ?? 'https://schema.org', ...$node],
                array_filter($value['@graph'], fn (mixed $node) => is_array($node) && $node !== [])
            ));
        }

        return [$value];
    }

    /**
     * Items line up with the FAQ as the SEO team saw it, so the live question is found by that
     * question's text: removals and edits made since then do not shift the other items.
     *
     * @throws ValidationException
     */
    private function implementFaq(ProductCategory $family, SeoContentSuggestion $suggestion, array $modelData): bool
    {
        $review   = json_decode($suggestion->suggestion, true);
        $faq      = array_values($family->faq ?? []);
        $original = $faq;
        $list     = Arr::has($modelData, 'item') ? 'items' : 'new';
        $index    = Arr::get($modelData, $list === 'items' ? 'item' : 'new', -1);
        $item     = $review[$list][$index] ?? throw ValidationException::withMessages(['suggestion' => __('This FAQ suggestion does not exist.')]);
        $item     = array_merge($item, Arr::only($modelData, ['question', 'answer']));

        if ($list === 'items') {
            $reviewedQuestion = data_get(array_values(json_decode((string) $suggestion->current_value, true) ?? []), "$index.question");
            $position         = collect($faq)->search(fn ($entry) => Arr::get($entry, 'question') === $reviewedQuestion);

            if ($position !== false && $item['remove']) {
                array_splice($faq, $position, 1);
            } elseif ($position !== false) {
                $faq[$position] = array_merge($faq[$position], array_filter(Arr::only($item, ['question', 'answer']), 'filled'));
            }
        } elseif (!collect($faq)->contains(fn ($entry) => Arr::get($entry, 'question') === $item['question'])) {
            $faq[] = Arr::only($item, ['question', 'answer']);
        }

        if ($faq !== $original) {
            UpdateProductCategory::make()->action($family, ['faq' => $faq]);
        }

        $review[$list][$index]['implemented'] = true;
        $suggestion->update(['suggestion' => json_encode($review)]);

        return collect($review['items'])->filter(fn (array $item) => self::isFaqChange($item))->every(fn (array $item) => $item['implemented'] ?? false)
            && collect($review['new'])->every(fn (array $item) => $item['implemented'] ?? false);
    }

    /**
     * @param array{question: string, answer: string, remove: bool} $item
     */
    public static function isFaqChange(array $item): bool
    {
        return $item['remove'] || $item['question'] !== '' || $item['answer'] !== '';
    }

    private function isUnchanged(mixed $live, ?string $reviewed): bool
    {
        return blank($live) ? blank($reviewed) : $live === $reviewed;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return self::canImplement($request->user(), $this->family, $this->suggestion->field);
    }

    public function rules(): array
    {
        return [
            'item'     => ['sometimes', 'integer', 'min:0'],
            'new'      => ['sometimes', 'integer', 'min:0'],
            'value'    => ['sometimes', 'nullable', 'string', 'max:65500'],
            'question' => ['sometimes', 'nullable', 'string', 'max:65500'],
            'answer'   => ['sometimes', 'nullable', 'string', 'max:65500'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function asController(ProductCategory $productCategory, SeoContentSuggestion $seoContentSuggestion, ActionRequest $request): SeoContentSuggestion
    {
        abort_unless(
            $productCategory->webpage && $seoContentSuggestion->webpage_id === $productCategory->webpage->id && $seoContentSuggestion->reason === SeoContentSuggestion::REASON_SEO_REVIEW,
            404
        );

        $this->family     = $productCategory;
        $this->suggestion = $seoContentSuggestion;
        $this->initialisationFromShop($productCategory->shop, $request);

        return $this->handle($productCategory, $seoContentSuggestion, $this->validatedData, $request->user());
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
