<?php

namespace App\Actions\CRM\ChatAutomation;

use App\Actions\OrgAction;
use App\Models\Chat\ChatAutomation;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class DeleteChatAutomation extends OrgAction
{
    public function handle(ChatAutomation $chatAutomation): ChatAutomation
    {
        $chatAutomation->delete();

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
            'description' => __('Automated message successfully deleted.'),
        ]);
    }
}
