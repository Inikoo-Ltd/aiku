<?php

namespace App\Actions\CRM\ChatAutomation;

use App\Actions\OrgAction;
use App\Models\CRM\Livechat\ChatAutomation;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class ToggleChatAutomation extends OrgAction
{
    public function handle(ChatAutomation $chatAutomation): ChatAutomation
    {
        $chatAutomation->update([
            'is_enabled' => ! $chatAutomation->is_enabled,
        ]);

        return $chatAutomation;
    }

    public function asController(Organisation $organisation, ChatAutomation $chatAutomation, ActionRequest $request): ChatAutomation
    {
        $this->initialisation($organisation, $request);

        return $this->handle($chatAutomation);
    }

    public function htmlResponse(): void
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Automated message toggled.'),
        ]);
    }
}
