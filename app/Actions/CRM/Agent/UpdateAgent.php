<?php

namespace App\Actions\CRM\Agent;

use App\Actions\OrgAction;
use App\Models\SysAdmin\Organisation;
use App\Models\CRM\Livechat\ChatAgent;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Validation\ValidationException;

class UpdateAgent extends OrgAction
{
    public function asController(Organisation $organisation, ChatAgent $agent, ActionRequest $request): ?ChatAgent
    {
        $this->initialisation($organisation, $request);

        try {
            return $this->handle($agent, $this->validatedData);
        } catch (ValidationException $e) {
            request()->session()->flash('notification', [
                'status'      => 'error',
                'title'       => __('Error!'),
                'description' => $e->getMessage(),
            ]);

            return null;
        } catch (\Exception $e) {
            request()->session()->flash('notification', [
                'status'      => 'error',
                'title'       => __('Error!'),
                'description' => __('Backend error occurred.'),
            ]);

            return null;
        }
    }


    public function htmlResponse(ChatAgent $agent = null): void
    {
        if (is_null($agent)) {
            return;
        }

        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Agent successfully updated.'),
        ]);
    }

    /**
     * Update logic
     */
    public function handle(ChatAgent $agent, array $modelData): ChatAgent
    {
        $agent->fill($modelData);
        $agent->save();

        return $agent;
    }


    public function rules(): array
    {
        return [
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
