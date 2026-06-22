<?php

namespace App\Actions\CRM\ChatAutomation;

use App\Actions\CRM\ChatAutomation\Knowledge\SyncChatKnowledgeFromFlow;
use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatAutomationTriggerEnum;
use App\Models\CRM\Livechat\ChatAutomation;
use App\Models\SysAdmin\Organisation;
use Lorisleiva\Actions\ActionRequest;

class UpdateChatAutomation extends OrgAction
{
    public function handle(ChatAutomation $chatAutomation, array $modelData): ChatAutomation
    {
        $chatAutomation->update($modelData);

        if (array_key_exists('flow', $modelData)) {
            SyncChatKnowledgeFromFlow::run($chatAutomation->refresh());
        }

        return $chatAutomation;
    }

    public function rules(): array
    {
        return [
            'name'         => ['sometimes', 'string', 'max:255'],
            'trigger_type' => ['sometimes', \Illuminate\Validation\Rule::enum(ChatAutomationTriggerEnum::class)],
            'is_enabled'   => ['sometimes', 'boolean'],
            'message'         => ['sometimes', 'string', 'max:2000'],
            'flow'            => ['sometimes', 'nullable', 'array'],
            'flow.start'      => ['sometimes', 'nullable', 'string'],
            'flow.nodes'      => ['sometimes', 'nullable', 'array'],
            'flow.edges'      => ['sometimes', 'nullable', 'array'],
            'conditions'      => ['sometimes', 'nullable', 'array'],
            'priority'        => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'send_once'       => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Organisation $organisation, ChatAutomation $chatAutomation, ActionRequest $request): ChatAutomation
    {
        $this->initialisation($organisation, $request);

        return $this->handle($chatAutomation, $this->validatedData);
    }

    public function htmlResponse(): void
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Automated message successfully updated.'),
        ]);
    }
}
