<?php

namespace App\Actions\CRM\Agent;

use Exception;
use App\Actions\OrgAction;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use App\Models\CRM\Livechat\ChatAgent;
use Illuminate\Validation\ValidationException;

class StoreAgent extends OrgAction
{
    public function asController(Organisation $organisation, ActionRequest $request): ?ChatAgent
    {
        $this->initialisation($organisation, $request);

        try {
            return $this->handle($this->validatedData);
        } catch (ValidationException $e) {
            request()->session()->flash('notification', [
                'status'      => 'error',
                'title'       => __('Error!'),
                'description' => $e->getMessage(),
            ]);

            return null;
        } catch (Exception $e) {
            request()->session()->flash('notification', [
                'status'      => 'error',
                'title'       => __('Error!'),
                'description' => __('Error Backhend'),
            ]);

            return null;
        }
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
        $exists = ChatAgent::where('user_id', $modelData['user_id'])
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'user_id' => __('User already has an active chat agent profile.'),
            ]);
        }


        $modelData['is_online']          ??= false;
        $modelData['is_available']       ??= false;
        $modelData['auto_accept']        ??= true;
        $modelData['current_chat_count'] ??= 0;

        return ChatAgent::create($modelData);
    }


    public function rules(): array
    {
        return [
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
