<?php

/*
 * Author Louis Perez
 * Created on 17-10-2026-12h-00m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\Goods\ProductDevelopment\Design\ProductDevelopmentDesignRounds;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Enums\Goods\ProductDevelopment\ProductDevelopmentCommentStatusEnum;
use App\Models\Goods\ProductDevelopmentComment;
use App\Models\Goods\ProductDevelopmentArtwork;
use App\Models\Goods\ProductDevelopmentDesignRound;
use App\Models\SysAdmin\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

/**
 * Resolves or reopens a comment. A pin on a design version records the latest version of its round at
 * the time, so a reader sees "Resolved in v2" on a comment left on v1.
 */
class UpdateProductDevelopmentCommentStatus extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        /** @var ProductDevelopmentComment $comment */
        $comment            = $request->route('productDevelopmentComment');
        $productDevelopment = $comment->productDevelopment;
        $user               = $request->user();
        $access             = $this->productDevelopmentAccess();

        return $access->canView($user, $productDevelopment)
            && ($comment->user_id === $user->id
                || $access->canEdit($user, $productDevelopment)
                || $access->canDesign($user, $productDevelopment)
                || $access->canApproveDesign($user, $productDevelopment));
    }

    public function handle(ProductDevelopmentComment $comment, User $user, array $modelData): ProductDevelopmentComment
    {
        $status = ProductDevelopmentCommentStatusEnum::from($modelData['status']);
        $data   = $comment->data;

        if ($status === ProductDevelopmentCommentStatusEnum::RESOLVED) {
            $designRound = $comment->model instanceof ProductDevelopmentDesignRound ? $comment->model : null;
            if ($designRound) {
                $data['resolved_in_version'] = ProductDevelopmentDesignRounds::latest($comment->productDevelopment, $designRound->round)?->version;
            } elseif ($comment->model instanceof ProductDevelopmentArtwork) {
                $data['resolved_in_version'] = $comment->model->version;
            }
            $comment->update(['status' => $status, 'resolved_by_id' => $user->id, 'resolved_at' => now(), 'data' => $data]);
        } else {
            unset($data['resolved_in_version']);
            $comment->update(['status' => $status, 'resolved_by_id' => null, 'resolved_at' => null, 'data' => $data]);
        }

        return $comment;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(ProductDevelopmentCommentStatusEnum::class)],
        ];
    }

    public function asController(ProductDevelopmentComment $productDevelopmentComment, ActionRequest $request): ProductDevelopmentComment
    {
        $this->initialisationFromGroup($productDevelopmentComment->productDevelopment->group, $request);

        return $this->handle($productDevelopmentComment, $request->user(), $this->validatedData);
    }

    public function action(ProductDevelopmentComment $comment, User $user, array $modelData): ProductDevelopmentComment
    {
        $this->asAction = true;
        $this->initialisationFromGroup($comment->productDevelopment->group, $modelData);

        return $this->handle($comment, $user, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
