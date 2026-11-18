<?php

/*
 * Author Louis Perez
 * Created on 18-11-2026-08h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Inbox;

use App\Models\SysAdmin\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class IndexMobileInbox
{
    use AsController;
    use WithMobileInbox;

    public const int DEFAULT_PER_PAGE = 30;

    public const int MAX_PER_PAGE = 100;

    /**
     * @param array{filter?: string|null, page?: int|null, per_page?: int|null} $filters
     */
    public function handle(User $user, array $filters): LengthAwarePaginator
    {
        return $this->inboxQuery($user)
            ->when(($filters['filter'] ?? 'all') === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(
                perPage: (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE),
                page: (int) ($filters['page'] ?? 1)
            );
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $filters = Validator::make($request->query(), [
            'filter'   => ['sometimes', 'nullable', Rule::in(['all', 'unread'])],
            'page'     => ['sometimes', 'nullable', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ])->validate();

        $notifications = $this->handle($user, $filters);

        return response()->json([
            'data'   => $this->inboxRows($user, collect($notifications->items())),
            'meta'   => [
                'current_page' => $notifications->currentPage(),
                'last_page'    => $notifications->lastPage(),
                'per_page'     => $notifications->perPage(),
                'total'        => $notifications->total(),
            ],
            'unread' => $this->inboxUnreadCount($user),
        ]);
    }
}
