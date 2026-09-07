<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff;

use App\Http\Resources\Chat\StaffConversationResource;
use App\Models\Chat\StaffConversation;
use App\Models\SysAdmin\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreStaffConversation
{
    use AsAction;

    /**
     * @param array{user_ids: int[], name?: string, context_type?: string, context_id?: int} $modelData
     */
    public function handle(User $creator, array $modelData): StaffConversation
    {
        $userIds = array_values(array_unique(array_merge([$creator->id], $modelData['user_ids'])));
        $isDm    = count($userIds) === 2 && empty($modelData['context_type']);

        $dmKey = $isDm ? StaffConversation::dmKey($userIds) : null;

        if ($dmKey && ($existing = StaffConversation::where('dm_key', $dmKey)->first())) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($creator, $modelData, $userIds, $isDm, $dmKey) {
                $conversation = StaffConversation::create([
                    'group_id'           => $creator->group_id,
                    'type'               => $isDm ? 'dm' : 'group',
                    'name'               => $modelData['name'] ?? null,
                    'dm_key'             => $dmKey,
                    'context_type'       => $modelData['context_type'] ?? null,
                    'context_id'         => $modelData['context_id'] ?? null,
                    'created_by_user_id' => $creator->id,
                ]);
                $conversation->participants()->attach($userIds);

                return $conversation;
            });
        } catch (UniqueConstraintViolationException) {
            return StaffConversation::where('dm_key', $dmKey)->firstOrFail();
        }
    }

    public function rules(): array
    {
        return [
            'user_ids'     => ['required', 'array', 'min:1'],
            'user_ids.*'   => ['integer', Rule::exists('users', 'id')->where('group_id', request()->user()->group_id)],
            'name'         => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    public function asController(ActionRequest $request): StaffConversationResource
    {
        $conversation = $this->handle($request->user(), $request->validated());
        $conversation->load('participants');

        return new StaffConversationResource($conversation);
    }
}
