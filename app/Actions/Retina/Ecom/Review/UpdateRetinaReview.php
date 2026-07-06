<?php

namespace App\Actions\Retina\Ecom\Review;

use App\Actions\RetinaAction;
use App\Actions\Reviews\UpdateReview;
use App\Models\Reviews\Review;
use Illuminate\Validation\Rules\File;
use Lorisleiva\Actions\ActionRequest;

class UpdateRetinaReview extends RetinaAction
{
    public function handle(Review $review, array $modelData): Review
    {
        return UpdateReview::run($review, $modelData);
    }

    public function rules(): array
    {
        return [
            'rating'    => ['sometimes', 'numeric', 'min:1', 'max:5'],
            'rating_a'  => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_b'  => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_c'  => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_d'  => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'rating_e'  => ['sometimes', 'nullable', 'integer', 'min:1', 'max:5'],
            'title'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'message'   => ['sometimes', 'nullable', 'string', 'max:5000'],
            'is_public' => ['sometimes', 'nullable'],
            'images'    => ['sometimes', 'array'],
            'images.*'  => ['sometimes', File::image()->max(50 * 1024)],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        $review = $request->route('review');

        return $review instanceof Review && $review->customer_id === $this->customer?->id;
    }

    public function asController(Review $review, ActionRequest $request): Review
    {
        $this->initialisation($request);

        return $this->handle($review, $this->validatedData);
    }
}
