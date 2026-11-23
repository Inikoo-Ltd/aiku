<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 23 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Chat\GetCrossChannelSessions;
use App\Actions\Chat\WithChatAgentAuthorisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The inbox's badges in one request. Each badge is the total of the list it opens, asked of
 * the same action with the same filters, so a badge can never promise rows its list refuses.
 */
class GetChatSessionCounts
{
    use AsAction;
    use WithChatAgentAuthorisation;

    public const int MAX_COUNTS = 8;

    public function rules(): array
    {
        return [
            'counts'          => ['required', 'array', 'max:'.self::MAX_COUNTS],
            'counts.*'        => ['array'],
            'counts.*.source' => ['required', 'string', 'in:sessions,all'],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $counts
     * @return array<string, int>
     */
    public function handle(User $user, array $counts): array
    {
        return collect($counts)->map(function (array $filters) use ($user) {
            $isCrossChannel = $filters['source'] === 'all';
            $source         = $isCrossChannel ? GetCrossChannelSessions::make() : GetChatSessions::make();

            $validated = Validator::make(
                collect($filters)->except(['source', 'page', 'limit'])->all(),
                $source->rules()
            )->validate();

            $scoped = $this->chatFiltersScopedTo($user, array_merge($validated, ['limit' => 1]));

            return $isCrossChannel
                ? (int) $source->handle($scoped)['total']
                : (int) $source->handle($scoped)->total();
        })->all();
    }

    public function asController(ActionRequest $request): array
    {
        abort_unless($request->user() instanceof User, 403);

        return $this->handle($request->user(), (array) $request->input('counts'));
    }

    public function jsonResponse(array $counts): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $counts]);
    }
}
