<?php

namespace App\Actions\Retina\Ecom\Review;

use App\Actions\Reviews\StoreReview;
use App\Actions\RetinaAction;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Models\Ordering\Order;
use App\Models\Reviews\Review;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaReview extends RetinaAction
{
    public function handle(Order $order, array $modelData): Review
    {
        return StoreReview::run($order, $modelData);
    }

    public function rules(): array
    {
        return [
            'rating'   => ['required', 'numeric', 'min:1', 'max:5'],
            'rating_a' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_b' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_c' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_d' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_e' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'title'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'message'  => ['sometimes', 'nullable', 'string', 'max:5000'],
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

        return $this->handle($order, [
            ...$this->validatedData,
            'customer_id' => $this->customer?->id,
            'status'      => ReviewStatusEnum::PENDING->value,
            'order_id'    => $order->id,
        ]);
    }
}
