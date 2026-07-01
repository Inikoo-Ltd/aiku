<?php

/*
 * @Author: andiferdiawan (https://github.com/andiferdiawan)
 * @Created: YYYY-MM-DD HH:mm:ss
 * @Copyright: Copyright (c) 2026, andiferdiawan
 */

namespace App\Actions\CRM\ChatAutomation;

use App\Actions\OrgAction;
use App\Models\Chat\ChatAutomation;
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

    public function htmlResponse(ChatAutomation $chatAutomation): void
    {
        $description = $chatAutomation->is_enabled
            ? __('Automated message enabled.')
            : __('Automated message disabled.');

        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => $description,
        ]);
    }
}
