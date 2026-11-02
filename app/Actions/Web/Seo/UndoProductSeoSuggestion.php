<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Created: Mon, 02 Nov 2026
 * Copyright (c) 2026
 */

namespace App\Actions\Web\Seo;

use App\Actions\Catalogue\Product\UpdateProduct;
use App\Actions\OrgAction;
use App\Actions\Web\Webpage\UpdateWebpage;
use App\Enums\Web\Seo\SeoContentSuggestionStateEnum;
use App\Models\Catalogue\Product;
use App\Models\SysAdmin\User;
use App\Models\Web\SeoContentSuggestion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The webmaster takes back a suggestion of the product SEO review applied in the validation tab,
 * as UndoFamilySeoSuggestion does for families: the value goes back to what the page had when the
 * SEO team reviewed it, and the suggestion waits to be applied again.
 */
class UndoProductSeoSuggestion extends OrgAction
{
    private Product $product;

    private SeoContentSuggestion $suggestion;

    public static function canUndo(Product $product, SeoContentSuggestion $suggestion): bool
    {
        return $suggestion->state === SeoContentSuggestionStateEnum::IMPLEMENTED
            && array_key_exists($suggestion->field, StoreProductSeoReview::currentValues($product, $product->webpage));
    }

    /**
     * @throws ValidationException
     */
    public function handle(Product $product, SeoContentSuggestion $suggestion, ?User $user): SeoContentSuggestion
    {
        if (!self::canUndo($product, $suggestion)) {
            throw ValidationException::withMessages(['suggestion' => __('This suggestion cannot be undone.')]);
        }

        return DB::transaction(function () use ($product, $suggestion, $user) {
            $field = $suggestion->field;

            isset(ImplementProductSeoSuggestion::PRODUCT_FIELDS[$field])
                ? UpdateProduct::make()->action($product, [ImplementProductSeoSuggestion::PRODUCT_FIELDS[$field] => $suggestion->current_value])
                : UpdateWebpage::make()->action($product->webpage, [$field => $suggestion->current_value]);

            $suggestion->update([
                'state'              => SeoContentSuggestionStateEnum::PENDING,
                'decided_by_user_id' => $user?->id,
                'decided_at'         => null,
            ]);

            return $suggestion;
        });
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return ImplementProductSeoSuggestion::canImplement($request->user(), $this->product, $this->suggestion->field);
    }

    /**
     * @throws ValidationException
     */
    public function asController(Product $product, SeoContentSuggestion $seoContentSuggestion, ActionRequest $request): SeoContentSuggestion
    {
        abort_unless(
            $product->webpage && $seoContentSuggestion->webpage_id === $product->webpage->id && $seoContentSuggestion->reason === SeoContentSuggestion::REASON_SEO_REVIEW,
            404
        );

        $this->product    = $product;
        $this->suggestion = $seoContentSuggestion;
        $this->initialisationFromShop($product->shop, $request);

        return $this->handle($product, $seoContentSuggestion, $request->user());
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
