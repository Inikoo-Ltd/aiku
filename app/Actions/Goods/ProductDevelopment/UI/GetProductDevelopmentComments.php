<?php

/*
 * Author Louis Perez
 * Created on 16-10-2026-16h-55m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment\UI;

use App\Enums\Goods\ProductDevelopment\ProductDevelopmentMemberRoleEnum;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentArtwork;
use App\Models\Goods\ProductDevelopmentComment;
use App\Models\Goods\ProductDevelopmentSample;
use App\Models\Goods\ProductDevelopmentDesignRound;
use Illuminate\Database\Eloquent\Builder;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class GetProductDevelopmentComments
{
    use AsAction;

    /**
     * @var array<int, int> version number by design round id
     */
    private array $versions = [];

    /**
     * @var array<int, string> the development roles of each commenter, e.g. "Owner, Buyer"
     */
    private array $roles = [];


    /**
     * @return array<string, mixed>
     */
    public function handle(ProductDevelopment $productDevelopment, ?ProductDevelopmentDesignRound $designRound = null, ProductDevelopmentArtwork|ProductDevelopmentSample|null $artwork = null): array
    {
        $subject  = $designRound ?? $artwork;
        $comments = $productDevelopment->comments()
            ->with(['user', 'media', 'resolvedBy'])
            ->when(
                $subject,
                fn (Builder $query) => $query->where('model_type', $subject->getMorphClass())->where('model_id', $subject->id),
                fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('model_type')->orWhereNotIn('model_type', ['ProductDevelopmentDesignRound', 'ProductDevelopmentArtwork', 'ProductDevelopmentSample']))
            )
            ->orderBy('id')
            ->get();
        $this->roles = $this->roles($productDevelopment);
        $this->versions = $designRound
            ? $productDevelopment->designRounds()->where('round', $designRound->round->value)->where('product_development_item_id', $designRound->product_development_item_id)->pluck('version', 'id')->all()
            : [];
        $replies  = $comments->whereNotNull('parent_id')->groupBy('parent_id');

        $mentionables = $productDevelopment->members()->with('user')->get()->pluck('user')
            ->push($productDevelopment->owner)
            ->filter()
            ->unique('id')
            ->sortBy(fn (User $user) => $user->contact_name ?: $user->username)
            ->map(fn (User $user) => [
                'id'       => $user->id,
                'name'     => $user->contact_name ?: $user->username,
                'username' => $user->username,
                'avatar'   => $user->image_id ? $user->imageSources(48, 48) : null,
            ])
            ->values()
            ->all();

        return [
            'threads'      => $comments->whereNull('parent_id')->sortByDesc('id')->map(fn (ProductDevelopmentComment $comment) => [
                ...$this->comment($comment),
                'replies' => $replies->get($comment->id, collect())->map(fn (ProductDevelopmentComment $reply) => $this->comment($reply))->values()->all(),
            ])->values()->all(),
            'mentionables' => $mentionables,
            'route'        => ['name' => 'grp.models.product_development.comments.store', 'parameters' => ['productDevelopment' => $productDevelopment->id]],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function roles(ProductDevelopment $productDevelopment): array
    {
        $labels = ProductDevelopmentMemberRoleEnum::labels();
        $roles  = $productDevelopment->members()->get(['user_id', 'role'])
            ->groupBy('user_id')
            ->map(fn ($members) => $members->map(fn ($member) => $labels[$member->role->value])->all());

        if ($productDevelopment->owner_id) {
            $roles->put($productDevelopment->owner_id, [__('Owner'), ...$roles->get($productDevelopment->owner_id, [])]);
        }

        return $roles->map(fn (array $names) => implode(', ', $names))->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function comment(ProductDevelopmentComment $comment): array
    {
        return [
            'id'         => $comment->id,
            'body'       => $comment->body,
            'user_id'    => $comment->user_id,
            'user'       => $comment->user?->contact_name ?: $comment->user?->username,
            'username'   => $comment->user?->username,
            'role'       => $this->roles[$comment->user_id] ?? null,
            'avatar'     => $comment->user?->image_id ? $comment->user->imageSources(48, 48) : null,
            'created_at' => $comment->created_at?->toIso8601String(),
            'images'      => $comment->ticketImageSources(),
            'attachments' => $comment->ticketAttachments('grp.media.download'),
            'pin'         => $comment->data['pin'] ?? null,
            'stroke'      => $comment->data['stroke'] ?? null,
            'version'     => $this->versions[$comment->model_id] ?? $comment->data['version'] ?? null,
            'review'      => $comment->data['review'] ?? null,
            'status'      => $comment->status?->value,
            'resolved_by' => $comment->resolvedBy?->contact_name ?: $comment->resolvedBy?->username,
            'resolved_in' => $comment->data['resolved_in_version'] ?? null,
        ];
    }
}
