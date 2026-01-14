<?php

namespace App\Actions\CRM\Agent;

use App\Actions\OrgAction;
use Illuminate\Support\Facades\DB;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use App\Models\CRM\Livechat\ChatAgent;
use Illuminate\Validation\ValidationException;

class UpdateAgent extends OrgAction
{
    public function asController(Organisation $organisation, ChatAgent $agent, ActionRequest $request): ?ChatAgent
    {
        $this->initialisation($organisation, $request);

        return $this->handle($organisation, $agent, $this->validatedData);
    }


    public function htmlResponse(ChatAgent $agent): void
    {
        if (! $agent) {
            return;
        }

        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Agent successfully updated.'),
        ]);
    }


    public function handle(Organisation $organisation, ChatAgent $agent, array $modelData): ChatAgent
    {
        return DB::transaction(function () use ($organisation, $agent, $modelData) {

            $fillable = [
                'user_id',
                'max_concurrent_chats',
                'specialization',
                'auto_accept',
                'is_online',
                'is_available',
                'current_chat_count',
                'language_id',
            ];

            $updateData = array_intersect_key(
                $modelData,
                array_flip($fillable)
            );

            if (! empty($updateData)) {

                if (
                    isset($updateData['user_id']) &&
                    $updateData['user_id'] !== $agent->user_id
                ) {
                    $exists = ChatAgent::query()
                        ->where('user_id', $updateData['user_id'])
                        ->whereNull('deleted_at')
                        ->exists();

                    if ($exists) {
                        session()->flash('notification', [
                            'status'      => 'error',
                            'title'       => __('Error'),
                            'description' => __('User already has an active chat agent profile.'),
                        ]);

                        throw ValidationException::withMessages([
                            'user_id' => __('User already has an active chat agent profile.'),
                        ]);
                    }
                }

                $agent->update($updateData);
            }


            if (array_key_exists('shop_id', $modelData)) {
                AssignChatAgentToScope::make()->update([
                    'organisation_id' => $organisation->id,
                    'shop_id'         => $modelData['shop_id'],
                ], $agent);
            }

            return $agent;
        });
    }


    public function rules(): array
    {
        return [
            'shop_id' => [
                'sometimes',
                'nullable',
                'array',
            ],
            'shop_id.*' => [
                'integer',
                'exists:shops,id',
            ],

            'organisation_id' => [
                'sometimes',
                'nullable',
                'integer',
                'exists:organisations,id',
            ],

            'user_id' => [
                'sometimes',
                'integer',
                'exists:users,id',
            ],

            'max_concurrent_chats' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],

            'is_online' => [
                'sometimes',
                'boolean',
            ],

            'language_id' => [
                'sometimes',
                'integer',
                'exists:languages,id',
            ],

            'is_available' => [
                'sometimes',
                'boolean',
            ],

            'current_chat_count' => [
                'sometimes',
                'integer',
                'min:0',
            ],

            'specialization' => [
                'sometimes',
                'nullable',
                'array',
            ],

            'specialization.*' => [
                'string',
                'max:255',
            ],

            'auto_accept' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    public function setOnline(int $userId): ?ChatAgent
    {
        $agent = ChatAgent::where('user_id', $userId)->first();

        if (!$agent) {
            return null;
        }

        $agent->setOnline(true);

        $agent->update([
            'is_available' => $agent->isAvailable(),
        ]);

        return $agent;
    }

    public function setOffline(int $userId): ?ChatAgent
    {
        $agent = ChatAgent::where('user_id', $userId)->first();

        if (!$agent) {
            return null;
        }

        $agent->setOnline(false);

        $agent->update([
            'is_available' => false,
        ]);

        return $agent;
    }
}
