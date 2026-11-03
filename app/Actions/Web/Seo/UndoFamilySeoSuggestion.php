<?php

/*
 * Created: Mon, 02 Nov 2026
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
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The webmaster takes back a suggestion applied in the validation tab, before the review is
 * validated, and the suggestion waits to be applied again. A single value goes back to what the
 * page had when the SEO team reviewed it; a FAQ item is put back as the SEO team saw it, the
 * question added by a new item is removed.
 */
class UndoFamilySeoSuggestion extends OrgAction
{
    private ProductCategory $family;

    private SeoContentSuggestion $suggestion;

    public static function canUndo(ProductCategory $family, SeoContentSuggestion $suggestion): bool
    {
        if ($suggestion->field === StoreFamilySeoReview::FIELD_FAQ) {
            return in_array($suggestion->state, [SeoContentSuggestionStateEnum::PENDING, SeoContentSuggestionStateEnum::IMPLEMENTED]);
        }

        return $suggestion->state === SeoContentSuggestionStateEnum::IMPLEMENTED
            && array_key_exists($suggestion->field, StoreFamilySeoReview::currentValues($family, $family->webpage));
    }

    /**
     * @throws ValidationException
     */
    public function handle(ProductCategory $family, SeoContentSuggestion $suggestion, array $modelData, ?User $user): SeoContentSuggestion
    {
        if (!self::canUndo($family, $suggestion)) {
            throw ValidationException::withMessages(['suggestion' => __('This suggestion cannot be undone.')]);
        }

        return DB::transaction(function () use ($family, $suggestion, $modelData, $user) {
            $suggestion->field === StoreFamilySeoReview::FIELD_FAQ
                ? $this->undoFaq($family, $suggestion, $modelData)
                : $this->undoValue($family, $suggestion);

            $suggestion->update([
                'state'              => SeoContentSuggestionStateEnum::PENDING,
                'decided_by_user_id' => $user?->id,
                'decided_at'         => null,
            ]);

            return $suggestion;
        });
    }

    private function undoValue(ProductCategory $family, SeoContentSuggestion $suggestion): void
    {
        $field = $suggestion->field;

        isset(ImplementFamilySeoSuggestion::FAMILY_FIELDS[$field])
            ? UpdateProductCategory::make()->action($family, [ImplementFamilySeoSuggestion::FAMILY_FIELDS[$field] => $suggestion->current_value])
            : UpdateWebpage::make()->action($family->webpage, [$field => $suggestion->current_value]);
    }

    /**
     * @throws ValidationException
     */
    private function undoFaq(ProductCategory $family, SeoContentSuggestion $suggestion, array $modelData): void
    {
        $review = json_decode($suggestion->suggestion, true);
        $list   = Arr::has($modelData, 'item') ? 'items' : 'new';
        $index  = Arr::get($modelData, $list === 'items' ? 'item' : 'new', -1);
        $item   = $review[$list][$index] ?? throw ValidationException::withMessages(['suggestion' => __('This FAQ suggestion does not exist.')]);

        if (!($item['implemented'] ?? false) || (!($item['remove'] ?? false) && !array_key_exists('applied', $item))) {
            throw ValidationException::withMessages(['suggestion' => __('This suggestion cannot be undone.')]);
        }

        $faq      = array_values($family->faq ?? []);
        $original = $faq;
        $applied  = $item['applied'] ?? null;
        $reviewed = array_values(json_decode((string) $suggestion->current_value, true) ?? [])[$index] ?? null;
        $position = $applied ? ImplementFamilySeoSuggestion::faqPosition($faq, $applied['question'], $index) : false;

        if ($list === 'new') {
            if ($position !== false) {
                array_splice($faq, $position, 1);
            }
        } elseif ($item['remove']) {
            if ($reviewed && !collect($faq)->contains(fn ($entry) => Arr::get($entry, 'question') === $reviewed['question'])) {
                array_splice($faq, min($index, count($faq)), 0, [$reviewed]);
            }
        } elseif ($position !== false && $reviewed) {
            $faq[$position] = array_merge($faq[$position], Arr::only($reviewed, ['question', 'answer']));
        }

        if ($faq !== $original) {
            UpdateProductCategory::make()->action($family, ['faq' => $faq]);
        }

        unset($review[$list][$index]['implemented'], $review[$list][$index]['applied']);
        $suggestion->update(['suggestion' => json_encode($review)]);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return ImplementFamilySeoSuggestion::canImplement($request->user(), $this->family, $this->suggestion->field);
    }

    public function rules(): array
    {
        return [
            'item' => ['sometimes', 'integer', 'min:0'],
            'new'  => ['sometimes', 'integer', 'min:0'],
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
