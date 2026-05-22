<?php

namespace App\Actions\Catalogue\ReviewReply;

use App\Enums\Catalogue\Review\ReviewReplyReplierTypeEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Models\Reviews\ProductCategoryReview;
use App\Models\Reviews\ProductReview;
use App\Models\Reviews\ReviewReply;
use App\Models\Reviews\ShopReview;
use App\Models\SysAdmin\User;
use Illuminate\Database\UniqueConstraintViolationException;
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
            'reviewable_type' => $reviewable->getTable(),
            'reviewable_id' => $reviewable->id,
            'organisation_id' => $reviewable->organisation_id,
            'user_id' => $user?->id,
            'replier_type' => data_get($modelData, 'replier_type', ReviewReplyReplierTypeEnum::Merchant->value),
            'body' => data_get($modelData, 'body'),
            'is_public' => $this->resolveIsPublic($modelData),
            'status' => data_get($modelData, 'status', ReviewStatusEnum::Approved->value),
        ]);
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        $reviewable = $this->resolveReviewable(
            $request->validated('reviewable_type'),
            (int) $request->validated('reviewable_id')
        );
        $validated = $request->validated();
        $replierType = (string) data_get($validated, 'replier_type', ReviewReplyReplierTypeEnum::Merchant->value);

        $existingReply = ReviewReply::query()
            ->where('reviewable_type', $reviewable->getTable())
            ->where('reviewable_id', $reviewable->id)
            ->where('replier_type', $replierType)
            ->first();

        if ($existingReply) {
            $existingReply->update([
                'body' => data_get($validated, 'body', $existingReply->body),
                'is_public' => $this->resolveIsPublic($validated),
                'status' => data_get($validated, 'status', $existingReply->status?->value ?? ReviewStatusEnum::Approved->value),
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Reply updated successfully',
                'data' => $existingReply->refresh()->load(['organisation', 'user']),
            ], 200);
        }

        try {
            $reviewReply = $this->handle($reviewable, $validated, $request->user());
        } catch (UniqueConstraintViolationException) {
            $latestReply = ReviewReply::query()
                ->where('reviewable_type', $reviewable->getTable())
                ->where('reviewable_id', $reviewable->id)
                ->where('replier_type', $replierType)
                ->first();

            if ($latestReply) {
                $latestReply->update([
                    'body' => data_get($validated, 'body', $latestReply->body),
                    'is_public' => $this->resolveIsPublic($validated),
                    'status' => data_get($validated, 'status', $latestReply->status?->value ?? ReviewStatusEnum::Approved->value),
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Reply updated successfully',
                    'data' => $latestReply->refresh()->load(['organisation', 'user']),
                ], 200);
            }

            return response()->json([
                'status' => 'failed',
                'message' => 'Failed to store reply',
            ], 422);
        }

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

    private function resolveIsPublic(array $modelData): bool
    {
        $value = data_get($modelData, 'is_public');

        if (\is_bool($value)) {
            return $value;
        }

        if (\is_string($value) || \is_numeric($value)) {
            $normalized = \filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return true;
    }
}
