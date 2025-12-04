<?php

namespace App\Actions\CRM\Agent;

use App\Actions\OrgAction;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use App\Models\CRM\Livechat\ChatAgent;
use Illuminate\Validation\ValidationException;

class DeleteAgent extends OrgAction
{
    /**
     * Controller endpoint
     */
    public function asController(Organisation $organisation, ChatAgent $agent, ActionRequest $request)
    {
        $this->initialisation($organisation, $request);

        return $this->handle($agent);
    }

    /**
     * Delete logic with validation + session flash
     */
    public function handle(ChatAgent $agent): ?ChatAgent
    {
        try {
            if ($agent->current_chat_count > 0) {
                throw ValidationException::withMessages([
                    'agent' => __("This agent is still handling active chats."),
                ]);
            }

            if ($agent->is_online) {
                throw ValidationException::withMessages([
                    'agent' => __("This agent is still online."),
                ]);
            }

            $agent->delete();

            return $agent;

        } catch (ValidationException $e) {
            request()->session()->flash('notification', [
                'status'      => 'error',
                'title'       => __('Error!'),
                'description' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Frontend HTML response
     */
    public function htmlResponse(?ChatAgent $agent): void
    {
        if (is_null($agent)) {
            return;
        }

        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Agent successfully deleted.'),
        ]);
    }
}
