<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff;

use App\Http\Resources\Chat\StaffConversationResource;
use App\Models\Chat\StaffConversation;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Inventory\PickingSession;
use App\Models\Ordering\Order;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class OpenStaffContextConversation
{
    use AsAction;

    public function handle(User $user, DeliveryNote|Order|PickingSession $context, string $audience): StaffConversation
    {
        $key = 'ctx:'.class_basename($context).':'.$context->id.':'.$audience;

        return DB::transaction(function () use ($user, $context, $audience, $key) {
            $conversation = StaffConversation::where('dm_key', $key)->first();
            $userIds      = GetStaffAudience::run($audience, $context)->pluck('id')->push($user->id)->unique()->values()->all();

            if (!$conversation) {
                $conversation = StaffConversation::create([
                    'group_id'           => $user->group_id,
                    'type'               => 'group',
                    'name'               => GetStaffAudience::label($audience).' · '.$context->reference,
                    'dm_key'             => $key,
                    'context_type'       => class_basename($context),
                    'context_id'         => $context->id,
                    'created_by_user_id' => $user->id,
                ]);
            }
            $conversation->participants()->syncWithoutDetaching($userIds);

            return $conversation;
        });
    }

    public function rules(): array
    {
        return [
            'context_type' => ['required', Rule::in(['DeliveryNote', 'Order', 'PickingSession'])],
            'context_id'   => ['required', 'integer'],
            'audience'     => ['required', Rule::in(GetStaffAudience::AUDIENCES)],
        ];
    }

    public function asController(ActionRequest $request): StaffConversationResource
    {
        $validated = $request->validated();
        $context   = match ($validated['context_type']) {
            'DeliveryNote'   => DeliveryNote::findOrFail($validated['context_id']),
            'PickingSession' => PickingSession::findOrFail($validated['context_id']),
            default          => Order::findOrFail($validated['context_id']),
        };
        abort_unless($context->group_id === $request->user()->group_id, 403);

        $conversation = $this->handle($request->user(), $context, $validated['audience']);
        $conversation->load('participants');

        return new StaffConversationResource($conversation);
    }
}
