<?php

/*
 * Author Louis Perez
 * Created on 22-10-2026-15h-30m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\Design;

use App\Actions\Goods\ProductDevelopment\CreateProductDevelopmentTask;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentArtworkStatusEnum;
use App\Models\Goods\ProductDevelopmentArtwork;
use App\Models\Goods\ProductDevelopmentDesignSection;
use App\Models\Goods\ProductDevelopmentItem;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

/**
 * Opens the artwork of one SKU in the Aiku editor: a new layout (a box sized from the SKU, or a blank
 * page), the layout it already had, or a reopened approved or in-review layout, which then needs a new
 * review once it is submitted again. An uploaded PDF can be redone in Aiku once it is in review.
 */
class StartProductDevelopmentArtworkInAiku extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    private ProductDevelopmentDesignSection $section;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $productDevelopment = $this->section->productDevelopment;

        return $this->canViewProductDevelopment($request->user(), $productDevelopment)
            && $this->productDevelopmentAccess()->canManageArtwork($request->user(), $productDevelopment);
    }

    public function handle(ProductDevelopmentDesignSection $section, User $user, array $modelData): ProductDevelopmentArtwork
    {
        $productDevelopment = $section->productDevelopment;
        $item               = ProductDevelopmentItem::with('tradeUnit')->findOrFail($modelData['product_development_item_id']);

        /** @var ProductDevelopmentArtwork $artwork */
        $artwork = $section->artworks()->firstOrNew(['product_development_item_id' => $item->id]);

        $layout = $artwork->source === ProductDevelopmentArtwork::SOURCE_AIKU && !empty($artwork->layout)
            ? ProductDevelopmentDesignLayout::normalise($artwork->layout)
            : ($section->editor_kind === ProductDevelopmentDesignLayout::KIND_BOX ? ProductDevelopmentDesignLayout::blank($productDevelopment, $item) : ProductDevelopmentDesignLayout::blankSheet());

        $artwork->fill([
            'product_development_id' => $productDevelopment->id,
            'source'                 => ProductDevelopmentArtwork::SOURCE_AIKU,
            'layout'                 => $layout,
            'status'                 => in_array($artwork->status, [ProductDevelopmentArtworkStatusEnum::CHANGES_REQUIRED], true) ? $artwork->status : ProductDevelopmentArtworkStatusEnum::NOT_SUBMITTED,
            'updated_by_id'          => $user->id,
            'artwork_updated_at'     => now(),
        ])->save();

        CreateProductDevelopmentTask::make()->ensureForDevelopment($productDevelopment->refresh(), $user);

        return $artwork;
    }

    public function rules(): array
    {
        return [
            'product_development_item_id' => ['required', 'integer', Rule::exists('product_development_items', 'id')->where('product_development_id', $this->section->product_development_id)],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        if (!ProductDevelopmentArtworks::canChange(ProductDevelopmentArtworks::changeTarget($this->section, (int) $this->get('product_development_item_id')))) {
            $validator->errors()->add('product_development_item_id', ProductDevelopmentDesignRounds::isArtworkLocked($this->section->productDevelopment)
                ? __('The artwork is signed off and locked')
                : __('Approve the concept first'));

            return;
        }

        if ($this->section->artworks()->where('product_development_item_id', (int) $this->get('product_development_item_id'))->where('source', ProductDevelopmentArtwork::SOURCE_UPLOAD)->whereNotIn('status', [ProductDevelopmentArtworkStatusEnum::IN_REVIEW, ProductDevelopmentArtworkStatusEnum::CHANGES_REQUIRED])->exists()) {
            $validator->errors()->add('product_development_item_id', __('This artwork is an uploaded PDF: it can be made in Aiku instead once it is in review or sent back'));
        }
    }

    public function asController(ProductDevelopmentDesignSection $productDevelopmentDesignSection, ActionRequest $request): ProductDevelopmentArtwork
    {
        $this->section = $productDevelopmentDesignSection;
        $this->initialisationFromGroup($productDevelopmentDesignSection->productDevelopment->group, $request);

        return $this->handle($productDevelopmentDesignSection, $request->user(), $this->validatedData);
    }

    public function action(ProductDevelopmentDesignSection $section, User $user, array $modelData): ProductDevelopmentArtwork
    {
        $this->asAction = true;
        $this->section  = $section;
        $this->initialisationFromGroup($section->productDevelopment->group, $modelData);

        return $this->handle($section, $user, $this->validatedData);
    }

    public function htmlResponse(ProductDevelopmentArtwork $artwork): RedirectResponse
    {
        return Redirect::route('grp.product_developments.artwork', [
            'productDevelopment' => $artwork->productDevelopment->slug,
            'artwork'            => $artwork->id,
        ]);
    }
}
