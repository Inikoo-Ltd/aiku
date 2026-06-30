<?php

namespace App\Actions\CRM\ChatAutomation;

use App\Actions\CRM\ChatAutomation\Knowledge\SyncChatKnowledgeFromFlow;
use App\Actions\OrgAction;
use App\Enums\CRM\Livechat\ChatAutomationTriggerEnum;
use App\Models\CRM\Livechat\ChatAutomation;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreChatAutomation extends OrgAction
{
    public function handle(array $modelData): ChatAutomation
    {
        $chatAutomation = ChatAutomation::create($modelData);

        SyncChatKnowledgeFromFlow::run($chatAutomation);

        return $chatAutomation;
    }

    public function rules(): array
    {
        return [
            'shop_id'      => [
                'required',
                'integer',
                Rule::exists('shops', 'id')->where('organisation_id', $this->organisation->id),
            ],
            'name'         => ['required', 'string', 'max:255'],
            'trigger_type' => ['required', Rule::enum(ChatAutomationTriggerEnum::class)],
            'is_enabled'   => ['sometimes', 'boolean'],
            'message'         => ['required', 'string', 'max:2000'],
            'flow'            => ['sometimes', 'nullable', 'array'],
            'flow.start'      => ['sometimes', 'nullable', 'string'],
            'flow.nodes'      => ['sometimes', 'nullable', 'array'],
            'flow.edges'      => ['sometimes', 'nullable', 'array'],
            'conditions'      => ['sometimes', 'nullable', 'array'],
            'priority'        => ['sometimes', 'integer', 'min:0', 'max:1000'],
            'send_once'       => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Organisation $organisation, ActionRequest $request): ChatAutomation
    {
        $this->initialisation($organisation, $request);

        return $this->handle($this->validatedData);
    }

    public function htmlResponse(ChatAutomation $chatAutomation): RedirectResponse
    {
        request()->session()->flash('notification', [
            'status'      => 'success',
            'title'       => __('Success!'),
            'description' => __('Automated message successfully created.'),
        ]);

        return Redirect::route('grp.org.chat.automations.edit', [
            'organisation'   => $this->organisation->slug,
            'chatAutomation' => $chatAutomation->id,
        ]);
    }
}
