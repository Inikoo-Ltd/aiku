<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\SupplierWechatConversation;

use App\Actions\OrgAction;
use App\Models\Helpers\TicketProjectMilestone;
use App\Models\Procurement\SupplierWechatConversation;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class AssignSupplierWechatConversationToProject extends OrgAction
{
    private ?SupplierWechatConversation $conversation = null;

    /**
     * The whole chat is filed under the project, what was said before and what comes after, so the
     * project shows the conversation as it is rather than the messages someone happened to pick.
     */
    public function handle(SupplierWechatConversation $conversation, array $modelData): SupplierWechatConversation
    {
        $projectId = Arr::get($modelData, 'ticket_project_id');
        $projectId = $projectId ? (int) $projectId : null;

        $conversation->update([
            'ticket_project_id'           => $projectId,
            'ticket_project_milestone_id' => $projectId ? Arr::get($modelData, 'ticket_project_milestone_id') : null,
        ]);

        return $conversation;
    }

    /**
     * Filing a chat under a project is procurement's call; the project page still only shows it to
     * people who may read that organisation's supplier chats.
     */
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->conversation->organisation_id}.edit");
    }

    public function rules(): array
    {
        return [
            'ticket_project_id'           => ['present', 'nullable', Rule::exists('ticket_projects', 'id')->where('group_id', $this->group->id)->whereNull('deleted_at')],
            'ticket_project_milestone_id' => [
                'sometimes',
                'nullable',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value && ! TicketProjectMilestone::whereKey($value)->where('ticket_project_id', $this->get('ticket_project_id'))->exists()) {
                        $fail(__('That milestone is not in this project.'));
                    }
                },
            ],
        ];
    }

    public function action(SupplierWechatConversation $conversation, array $modelData): SupplierWechatConversation
    {
        $this->asAction     = true;
        $this->conversation = $conversation;
        $this->initialisation($conversation->organisation, $modelData);

        return $this->handle($conversation, $this->validatedData);
    }

    public function asController(SupplierWechatConversation $supplierWechatConversation, ActionRequest $request): SupplierWechatConversation
    {
        $this->conversation = $supplierWechatConversation;
        $this->initialisation($supplierWechatConversation->organisation, $request);

        return $this->handle($supplierWechatConversation, $this->validatedData);
    }

    public function jsonResponse(SupplierWechatConversation $conversation): JsonResponse
    {
        $project = $conversation->project;

        return response()->json([
            'ticket_project_id'           => $conversation->ticket_project_id,
            'ticket_project_milestone_id' => $conversation->ticket_project_milestone_id,
            'context_label'               => $project ? __('Project: :name', ['name' => $project->name]) : __('Supplier WeChat'),
            'context_url'                 => $project ? route('grp.projects.show', $project->slug) : null,
        ]);
    }
}
