<?php

namespace App\Actions\CRM\Agent;

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

            $agent = ChatAgent::withTrashed()
                ->where('user_id', $modelData['user_id'])
                ->first();


            if ($agent && ! $agent->trashed()) {
                throw ValidationException::withMessages([
                    'user_id' => __('This user is already an active agent.'),
                ]);
            }


            if ($agent && $agent->trashed()) {

                $agent->shopAssignments()->delete();
                $agent->update([
                    'max_concurrent_chats'  => $modelData['max_concurrent_chats'],
                    'specialization'        => $modelData['specialization'] ?? null,
                    'auto_accept'           => $modelData['auto_accept'] ?? true,
                    'language_id'           => $modelData['language_id'],
                    'is_online'             => false,
                    'is_available'          => false,

                ]);


                $agent->restore();
            }


            if (! $agent) {
                $agent = ChatAgent::create([
                    'user_id'               => $modelData['user_id'],
                    'max_concurrent_chats'  => $modelData['max_concurrent_chats'],
                    'specialization'        => $modelData['specialization'] ?? null,
                    'auto_accept'           => $modelData['auto_accept'] ?? true,
                    'language_id'           => $modelData['language_id'],
                    'is_online'             => false,
                    'is_available'          => false,
                    'current_chat_count'    => 0,
                ]);
            }

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

            'shop_id' => ['nullable', 'array'],
            'shop_id.*' => ['integer', 'exists:shops,id'],

            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'language_id' => [
                'required',
                'integer',
                'exists:languages,id',
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
