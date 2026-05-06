<?php

namespace App\Actions\Catalogue\ReviewReply;

use App\Enums\Catalogue\Review\ReviewReplyReplierTypeEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Models\Reviews\ProductCategoryReview;
use App\Models\Reviews\ProductReview;
use App\Models\Reviews\ReviewReply;
use App\Models\Reviews\ShopReview;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreReviewReply
{
    use AsAction;

    public function handle(
        ProductReview|ShopReview|ProductCategoryReview $reviewable,
        array $modelData,
        ?User $user = null
    ): ReviewReply {
        return $reviewable->replies()->create([
            'organisation_id' => $reviewable->organisation_id,
            'user_id' => $user?->id,
            'replier_type' => data_get($modelData, 'replier_type', ReviewReplyReplierTypeEnum::Merchant->value),
            'body' => data_get($modelData, 'body'),
            'is_public' => data_get($modelData, 'is_public', true),
            'status' => data_get($modelData, 'status', ReviewStatusEnum::Approved->value),
        ]);
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $reviewable = $this->resolveReviewable(
            $request->validated('reviewable_type'),
            (int) $request->validated('reviewable_id')
        );

        $reviewReply = $this->handle($reviewable, $request->validated(), $request->user());

        return response()->json([
            'status' => 'success',
            'data' => $reviewReply->load(['organisation', 'user']),
        ], 201);
    }

    public function rules(): array
    {
        return [
            'reviewable_type' => ['required', Rule::in(['product_reviews', 'shop_reviews', 'product_category_reviews'])],
            'reviewable_id' => ['required', 'integer', 'min:1'],
            'replier_type' => ['sometimes', Rule::enum(ReviewReplyReplierTypeEnum::class)],
            'body' => [
                'required',
                'string',
                'max:10000',
                'not_regex:/<[^>]*>/',
                'not_regex:/(```|<\?php|<\?=|<\/?script\b|function\s+\w+\s*\(|\b(class|const|let|var)\b\s+\w+\s*[=:{(])/i',
            ],
            'is_public' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::enum(ReviewStatusEnum::class)],
        ];
    }

    private function resolveReviewable(string $reviewableType, int $reviewableId): ProductReview|ShopReview|ProductCategoryReview
    {
        return match ($reviewableType) {
            'product_reviews' => ProductReview::query()->findOrFail($reviewableId),
            'shop_reviews' => ShopReview::query()->findOrFail($reviewableId),
            'product_category_reviews' => ProductCategoryReview::query()->findOrFail($reviewableId),
        };
    }
}
