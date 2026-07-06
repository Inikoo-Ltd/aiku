<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:09:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Agent;

use App\Actions\OrgAction;
use App\Models\Chat\ChatAgent;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

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

        $agent->shopAssignments()->delete();

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
