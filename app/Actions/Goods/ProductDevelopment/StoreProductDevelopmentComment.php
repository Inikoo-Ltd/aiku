<?php

/*
 * Author Louis Perez
 * Created on 16-10-2026-16h-40m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Goods\ProductDevelopment;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProductDevelopmentAuthorisation;
use App\Models\Goods\ProductDevelopment;
use App\Models\Goods\ProductDevelopmentComment;
use App\Models\SysAdmin\User;
use App\Enums\SysAdmin\User\UserNotificationEnum;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreProductDevelopmentComment extends OrgAction
{
    use WithProductDevelopmentAuthorisation;

    public const int MAX_STROKE_POINTS = 600;

    private ?ProductDevelopment $productDevelopment = null;

    private function target(): ProductDevelopment
    {
        return $this->productDevelopment ?? request()->route('productDevelopment');
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->canViewProductDevelopment($request->user(), $this->target());
    }

    public function handle(ProductDevelopment $productDevelopment, User $author, array $modelData): ProductDevelopmentComment
    {
        $body      = trim((string) Arr::get($modelData, 'body', ''));
        $images    = Arr::get($modelData, 'images', []);
        $mentioned = $this->mentionedUsers($productDevelopment, $body, $author);

        return DB::transaction(function () use ($productDevelopment, $author, $modelData, $body, $mentioned, $images) {
            /** @var ProductDevelopmentComment $comment */
            $comment = $productDevelopment->comments()->create([
                'user_id'   => $author->id,
                'parent_id' => Arr::get($modelData, 'parent_id'),
                'body'      => $body,
                'mentions'  => $mentioned->pluck('id')->values()->all(),
                ...$this->designPlacement($productDevelopment, $modelData),
            ]);

            $comment->attachTicketImages($images);

            if ($mentioned->isNotEmpty()) {
                NotifyProductDevelopmentUsers::run(
                    $productDevelopment,
                    $mentioned,
                    UserNotificationEnum::PRODUCT_DEVELOPMENT_MENTION,
                    __(':name mentioned you on :code', ['name' => $author->contact_name ?: $author->username, 'code' => $productDevelopment->code]),
                    Str::limit($body, 140),
                );
            }

            return $comment;
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function mentionedUsers(ProductDevelopment $productDevelopment, string $body, User $author)
    {
        preg_match_all('/@([A-Za-z0-9._-]+)/', $body, $matches);
        $usernames = collect($matches[1] ?? [])->map(fn (string $username) => rtrim($username, '.'))->unique()->values();

        if ($usernames->isEmpty()) {
            return collect();
        }

        $access = $this->productDevelopmentAccess();

        return User::where('group_id', $productDevelopment->group_id)
            ->where('status', true)
            ->whereIn('username', $usernames)
            ->where('id', '!=', $author->id)
            ->get()
            ->filter(fn (User $user) => $access->canView($user, $productDevelopment))
            ->values();
    }

    public function rules(): array
    {
        return [
            'body'      => ['required_without:images', 'nullable', 'string', 'max:5000'],
            'images'    => ['sometimes', 'array', 'max:5'],
            'images.*'  => ProductDevelopmentComment::ticketFileRules(),
            'parent_id' => ['sometimes', 'nullable', 'integer', Rule::exists('product_development_comments', 'id')->where('product_development_id', $this->target()->id)],
            'design_round_id' => ['sometimes', 'nullable', 'integer', Rule::exists('product_development_design_rounds', 'id')->where('product_development_id', $this->target()->id)],
            'artwork_id'      => ['sometimes', 'nullable', 'integer', 'prohibits:design_round_id', Rule::exists('product_development_artworks', 'id')->where('product_development_id', $this->target()->id)],
            'sample_id'       => ['sometimes', 'nullable', 'integer', 'prohibits:design_round_id,artwork_id', Rule::exists('product_development_samples', 'id')->where('product_development_id', $this->target()->id)],
            'pin'             => ['sometimes', 'nullable', 'array'],
            'pin.x'           => ['required_with:pin', 'numeric', 'between:0,1'],
            'pin.y'           => ['required_with:pin', 'numeric', 'between:0,1'],
            'pin.page'        => ['sometimes', 'integer', 'min:1', 'max:500'],
            'stroke'          => ['sometimes', 'nullable', 'array'],
            'stroke.page'     => ['sometimes', 'integer', 'min:1', 'max:500'],
            'stroke.points'   => ['required_with:stroke', 'array', 'min:2', 'max:'.self::MAX_STROKE_POINTS],
            'stroke.points.*'   => ['array', 'size:2'],
            'stroke.points.*.*' => ['numeric', 'between:0,1'],
        ];
    }

    /**
     * A comment on a SKU's artwork belongs to that artwork. A comment on a design version belongs to it, and a top-level one may carry a numbered pin at a point
     * of the box net (x and y as fractions of the net's width and height). A reply follows its parent.
     *
     * @return array<string, mixed>
     */
    private function designPlacement(ProductDevelopment $productDevelopment, array $modelData): array
    {
        $parentId = Arr::get($modelData, 'parent_id');
        if ($parentId) {
            $parent = $productDevelopment->comments()->find($parentId);

            return $parent?->model_type ? ['model_type' => $parent->model_type, 'model_id' => $parent->model_id] : [];
        }

        $artworkId = Arr::get($modelData, 'artwork_id');
        if ($artworkId) {
            return ['model_type' => 'ProductDevelopmentArtwork', 'model_id' => $artworkId];
        }

        $sampleId = Arr::get($modelData, 'sample_id');
        if ($sampleId) {
            return ['model_type' => 'ProductDevelopmentSample', 'model_id' => $sampleId];
        }

        $designRoundId = Arr::get($modelData, 'design_round_id');
        if (!$designRoundId) {
            return [];
        }

        $placement = ['model_type' => 'ProductDevelopmentDesignRound', 'model_id' => $designRoundId];
        $pin       = Arr::get($modelData, 'pin');
        $stroke    = Arr::get($modelData, 'stroke');
        if (!is_array($pin) && !is_array($stroke)) {
            return $placement;
        }

        $number = $productDevelopment->comments()
            ->where('model_type', 'ProductDevelopmentDesignRound')
            ->where('model_id', $designRoundId)
            ->where(fn ($query) => $query->whereNotNull('data->pin')->orWhereNotNull('data->stroke'))
            ->count() + 1;

        if (is_array($stroke)) {
            $points            = collect($stroke['points'])->map(fn ($point) => [round((float) $point[0], 4), round((float) $point[1], 4)])->values()->all();
            $placement['data'] = ['stroke' => ['page' => (int) Arr::get($stroke, 'page', 1), 'points' => $points, 'number' => $number]];
        } else {
            $placement['data'] = ['pin' => ['x' => round((float) $pin['x'], 4), 'y' => round((float) $pin['y'], 4), 'page' => (int) Arr::get($pin, 'page', 1), 'number' => $number]];
        }

        return $placement;
    }

    public function getValidationMessages(): array
    {
        return ProductDevelopmentComment::ticketFileValidationMessages();
    }

    public function asController(ProductDevelopment $productDevelopment, ActionRequest $request): ProductDevelopmentComment
    {
        $this->productDevelopment = $productDevelopment;
        $this->initialisationFromGroup($productDevelopment->group, $request);

        return $this->handle($productDevelopment, $request->user(), $this->validatedData);
    }

    public function action(ProductDevelopment $productDevelopment, User $author, array $modelData): ProductDevelopmentComment
    {
        $this->asAction           = true;
        $this->productDevelopment = $productDevelopment;
        $this->initialisationFromGroup($productDevelopment->group, $modelData);

        return $this->handle($productDevelopment, $author, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
