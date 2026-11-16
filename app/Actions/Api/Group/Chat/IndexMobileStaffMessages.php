<?php

/*
 * Author Louis Perez
 * Created on 13-11-2026-15h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Chat;

use App\Actions\Chat\Staff\MarkStaffConversationRead;
use App\Models\Chat\StaffConversation;
use App\Models\Chat\StaffMessage;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobileStaffMessages
{
    use AsController;
    use WithMobileStaffChat;

    public const int PER_PAGE = 30;

    public function asController(StaffConversation $staffConversation, ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        abort_unless($staffConversation->canBeAccessedBy($user), 403);

        $filters = Validator::make($request->query(), [
            'before_id' => ['sometimes', 'nullable', 'integer'],
            'after_id'  => ['sometimes', 'nullable', 'integer'],
        ])->validate();

        $leftAt = $staffConversation->leftAtFor($user);
        $query  = $staffConversation->messages()
            ->with('user')
            ->when($leftAt, fn ($query) => $query->where('created_at', '<=', $leftAt))
            ->when($filters['before_id'] ?? null, fn ($query, $beforeId) => $query->where('id', '<', $beforeId))
            ->when($filters['after_id'] ?? null, fn ($query, $afterId) => $query->where('id', '>', $afterId));

        $messages = empty($filters['after_id'])
            ? $query->latest('id')->limit(self::PER_PAGE + 1)->get()
            : $query->oldest('id')->limit(self::PER_PAGE + 1)->get();

        $hasMore  = $messages->count() > self::PER_PAGE;
        $messages = $messages->take(self::PER_PAGE)->sortBy('id')->values();

        MarkStaffConversationRead::run($staffConversation, $user);

        return response()->json([
            'data'     => $messages->map(fn (StaffMessage $message) => $this->messagePayload($message, $user))->all(),
            'has_more' => $hasMore,
        ]);
    }
}
