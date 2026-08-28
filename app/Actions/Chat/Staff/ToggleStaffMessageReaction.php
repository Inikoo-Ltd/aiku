<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff;

use App\Events\StaffMessageSent;
use App\Http\Resources\Chat\StaffMessageResource;
use App\Models\Chat\StaffMessage;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ToggleStaffMessageReaction
{
    use AsAction;

    public function handle(StaffMessage $message, User $user, string $emoji): StaffMessage
    {
        $existing = $message->reactions()->where('user_id', $user->id)->where('emoji', $emoji);
        if ($existing->exists()) {
            $existing->delete();
        } else {
            $message->reactions()->create(['user_id' => $user->id, 'emoji' => $emoji]);
        }

        StaffMessageSent::dispatch($message, 'staff-message-reaction');

        return $message;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('staffMessage')->conversation->hasParticipant($request->user());
    }

    public function rules(): array
    {
        return ['emoji' => ['required', 'string', 'max:16']];
    }

    public function asController(StaffMessage $staffMessage, ActionRequest $request): StaffMessageResource
    {
        $message = $this->handle($staffMessage, $request->user(), $request->validated()['emoji']);
        $message->load(['user', 'translations', 'reactions', 'conversation']);

        return new StaffMessageResource($message);
    }
}
