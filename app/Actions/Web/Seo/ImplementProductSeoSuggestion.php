<?php

/*
 * Author: Rifqi <rifqitaufiqurrohman1@gmail.com>
 * Created: Fri, 16 Oct 2026
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
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The webmaster applies one suggestion of the product SEO review, as ImplementFamilySeoSuggestion
 * does for families: a value already changed since the review is kept as written, one still as
 * the SEO team saw it is replaced by the suggestion.
 */
class ImplementProductSeoSuggestion extends OrgAction
{
    /**
     * Suggestion fields stored on the product, keyed to the product attribute. The rest are webpage fields.
     */
    public const array PRODUCT_FIELDS = ['product_name' => 'name', 'top_desc' => 'description', 'extra_desc' => 'description_extra'];

    private Product $product;

    private SeoContentSuggestion $suggestion;

    public static function isWebmaster(User $user, Product $product): bool
    {
        return (bool)$product->webpage?->canBeEditedBy($user) && $user->authTo([
            'group-webmaster.view',
            "supervisor-web.$product->shop_id",
            "web.$product->shop_id.edit",
        ]);
    }

    public static function canImplement(User $user, Product $product, string $field): bool
    {
        if (isset(self::PRODUCT_FIELDS[$field])) {
            return $user->authTo("products.$product->shop_id.edit");
        }

        return self::isWebmaster($user, $product);
    }

    /**
     * @throws ValidationException
     */
    public function handle(Product $product, SeoContentSuggestion $suggestion, array $modelData, ?User $user): SeoContentSuggestion
    {
        if ($suggestion->state !== SeoContentSuggestionStateEnum::PENDING) {
            throw ValidationException::withMessages(['suggestion' => __('This suggestion has already been implemented.')]);
        }

        $webpage = $product->webpage;
        $field   = $suggestion->field;

        if ($field === StoreFamilySeoReview::FIELD_STRUCTURED_DATA) {
            if (!ImplementFamilySeoSuggestion::implementStructuredDataItem($webpage, $suggestion, Arr::only($modelData, 'item'))) {
                return $suggestion;
            }
        } elseif ($this->isUnchanged(StoreProductSeoReview::currentValues($product, $webpage)[$field], $suggestion->current_value)) {
            isset(self::PRODUCT_FIELDS[$field])
                ? UpdateProduct::make()->action($product, [self::PRODUCT_FIELDS[$field] => $suggestion->suggestion])
                : UpdateWebpage::make()->action($webpage, [$field => $suggestion->suggestion]);
        }

        $suggestion->update([
            'state'              => SeoContentSuggestionStateEnum::IMPLEMENTED,
            'decided_by_user_id' => $user?->id,
            'decided_at'         => now(),
        ]);

        return $suggestion;
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

        return self::canImplement($request->user(), $this->product, $this->suggestion->field);
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

        return $this->handle($product, $seoContentSuggestion, $this->validatedData, $request->user());
    }

    public function rules(): array
    {
        return [
            'item' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
