<?php

namespace App\Actions\Catalogue\ReviewReply;

use App\Enums\Catalogue\Review\ReviewReplyReplierTypeEnum;
use App\Enums\Catalogue\Review\ReviewStatusEnum;
use App\Models\Reviews\ReviewReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateReviewReply
{
    use AsAction;

    public function handle(ReviewReply $reviewReply, array $modelData): ReviewReply
    {
        $reviewReply->update([
            'replier_type' => data_get($modelData, 'replier_type', $reviewReply->replier_type?->value),
            'body' => data_get($modelData, 'body', $reviewReply->body),
            'is_public' => data_get($modelData, 'is_public', $reviewReply->is_public),
            'status' => data_get($modelData, 'status', $reviewReply->status?->value),
        ]);

        return $reviewReply->refresh()->load(['organisation', 'user']);
    }

    public function asController(ReviewReply $reviewReply, ActionRequest $request): JsonResponse
    {
        $updatedReviewReply = $this->handle($reviewReply, $request->validated());

        return response()->json([
            'status' => 'success',
            'data' => $updatedReviewReply,
        ]);
    }

    public function rules(): array
    {
        return [
            'replier_type' => ['sometimes', Rule::enum(ReviewReplyReplierTypeEnum::class)],
            'body' => [
                'sometimes',
                'string',
                'max:10000',
                'not_regex:/<[^>]*>/',
                'not_regex:/(```|<\?php|<\?=|<\/?script\b|function\s+\w+\s*\(|\b(class|const|let|var)\b\s+\w+\s*[=:{(])/i',
            ],
            'is_public' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::enum(ReviewStatusEnum::class)],
        ];
    }
}
