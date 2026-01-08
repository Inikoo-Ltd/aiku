<?php

namespace App\Actions\CRM\Agent;

use App\Actions\OrgAction;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;
use App\Models\CRM\Livechat\ChatAgent;

class DeleteAgent extends OrgAction
{
    public function asController(Organisation $organisation, ChatAgent $agent, ActionRequest $request)
    {
        $this->initialisation($organisation, $request);

        return $this->handle($agent);
    }


    public function handle(ChatAgent $agent): array
    {
        if ($agent->current_chat_count > 0) {
            return [
                'success' => false,
                'message' => __("This agent is still handling active chats."),
            ];
        }

        if ($agent->is_online) {
            return [
                'success' => false,
                'message' => __("This agent is still online."),
            ];
        }

        $agent->delete();

        return [
            'success' => true,
            'message' => __("Agent successfully deleted."),
        ];
    }

    public function htmlResponse(array $result): void
    {
        request()->session()->flash('notification', [
            'status'      => $result['success'] ? 'success' : 'error',
            'title'       => $result['success']
                ? __('Success!')
                : __('Error!'),
            'description' => $result['message'],
        ]);
    }
}
