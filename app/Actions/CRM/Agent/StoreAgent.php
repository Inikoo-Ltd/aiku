<?php

namespace App\Actions\CRM\Agent;

use Exception;
use App\Actions\OrgAction;
use Illuminate\Support\Facades\DB;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use App\Models\CRM\Livechat\ChatAgent;
use Illuminate\Validation\ValidationException;

class StoreAgent extends OrgAction
{
    public function asController(Organisation $organisation, ActionRequest $request): ?ChatAgent
    {

        $this->initialisation($organisation, $request);
        return $this->handle($this->validatedData);
    }


    public function htmlResponse(): void
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Agent successfully created.'),
        ]);
    }


    public function handle(array $modelData): ChatAgent
    {
        return DB::transaction(function () use ($modelData) {

            $exists = ChatAgent::query()
                ->where('user_id', $modelData['user_id'])
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

            $agent = ChatAgent::create([
                'user_id'               => $modelData['user_id'],
                'max_concurrent_chats'  => $modelData['max_concurrent_chats'],
                'specialization'        => $modelData['specialization'] ?? null,
                'auto_accept'           => $modelData['auto_accept'] ?? true,
                'is_online'             => false,
                'is_available'          => false,
                'current_chat_count'    => 0,
            ]);

            AssignChatAgentToScope::run($modelData, $agent);

            return $agent;
        });
    }


    public function rules(): array
    {
        return [
            'organisation_id' => [
                'required',
                'integer',
                'exists:organisations,id',
            ],

            'shop_id' => [
                'nullable',
                'integer',
                'exists:shops,id',
            ],

            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'max_concurrent_chats' => [
                'required',
                'integer',
                'min:1',
                'max:100',
            ],

            'is_online' => [
                'sometimes',
                'boolean',
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
}
