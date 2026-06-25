<?php

namespace App\Actions\Retina\Ecom\Review;

use App\Actions\Reviews\StoreReview;
use App\Actions\RetinaAction;
use App\Enums\Catalogue\Review\ReviewScopeEnum;
use App\Models\Catalogue\Product;
use App\Models\Catalogue\ProductCategory;
use App\Models\Ordering\Order;
use App\Models\Reviews\Review;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaReview extends RetinaAction
{
    public function handle(Order $order, array $modelData): Review
    {
        $scope = ReviewScopeEnum::from($modelData['scope']);

        $reviewable = match ($scope) {
            ReviewScopeEnum::SHOP    => $order->shop,
            ReviewScopeEnum::PRODUCT => Product::findOrFail((int) $modelData['reviewable_id']),
            ReviewScopeEnum::FAMILY  => ProductCategory::findOrFail((int) $modelData['reviewable_id']),
            default                  => $order,
        };

        return StoreReview::make()->action($reviewable, [
            ...$modelData,
            'customer_id' => $this->customer?->id,
            'order_id'    => $order->id,
            'is_public'   => filter_var($modelData['is_public'] ?? true, FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function rules(): array
    {
        return [
            'scope'         => ['required', Rule::in(ReviewScopeEnum::values())],
            'reviewable_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'rating'   => ['required', 'numeric', 'min:1', 'max:5'],
            'rating_a' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_b' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_c' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_d' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_e' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'title'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'message'  => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_public' => ['sometimes', 'nullable'],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        $order = $request->route('order');

        return $order instanceof Order && $order->customer_id === $this->customer?->id;
    }

    public function asController(Order $order, ActionRequest $request): Review
    {
        $this->initialisation($request);

        return $this->handle($order, $this->validatedData);
    }
}
