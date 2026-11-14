<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Mcp\Tools;

use App\Actions\CRM\ShoppingAssistant\ResearchShoppingAssistantTeamQuestion;
use App\Actions\CRM\ShoppingAssistant\StoreShoppingAssistantTeamQuestion;
use App\Models\SysAdmin\User;
use App\Models\Tasks\StaffTask;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Acts on a question Merlin passed to our team (TASK-n from merlin-questions-tool), for the researcher routine. save_draft: leaves answer as a draft for the guru, with evidence (what you looked at, what it showed, and what is missing); the guru\'s pop-up shows it in the answer box. Use it for policy, exceptions, promises, anything you are not sure of, or a question whose meaning you had to guess. answer: sends answer to the customer, only when every statement in it is proven by evidence you name (the query and its result, the file and method, or the guide and section); likely is not proven. An independent check reads the question, the answer and the evidence and, unless it is very sure the answer follows, keeps it as a draft instead (result drafted). The system translates the answer into the customer\'s language, adds it to their conversation, emails it when they stopped waiting, and keeps it as a shop note for every customer unless keep_for_everyone is false (false for an answer about this one customer). Write the answer to the customer, in English, without internal ids or table names. When the question shows something broken or data we do not hold, raise a ticket with ticket-write-tool and save_draft naming it, so the guru can give the customer a holding answer.')]
class MerlinQuestionWriteTool extends Tool
{
    public function handle(Request $request): Response
    {
        $request->validate([
            'action'            => ['required', 'in:save_draft,answer'],
            'reference'         => ['required', 'string', 'max:20'],
            'run'               => ['required', 'string', 'max:100'],
            'answer'            => ['required', 'string', 'max:2000'],
            'evidence'          => ['required', 'string', 'min:'.ResearchShoppingAssistantTeamQuestion::MIN_EVIDENCE_CHARACTERS, 'max:4000'],
            'keep_for_everyone' => ['sometimes', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();
        if (!$user->can_use_mcp_customer_service) {
            return Response::error('Researching Merlin\'s questions is not enabled for this user. Do not retry; an administrator enrols it on the user\'s edit page. Nothing was changed.');
        }

        $task = StaffTask::where('group_id', $user->group_id)->where('reference', strtoupper($request->string('reference')))->where('data->kind', StoreShoppingAssistantTeamQuestion::TASK_KIND)->first();
        if (!$task) {
            return Response::error('No question to the team with that reference. Nothing was changed.');
        }
        if (!ResearchShoppingAssistantTeamQuestion::make()->mayResearch($user, $task)) {
            return Response::error('This question is answered or closed, or its shop is not one whose chats this user works. Nothing was changed.');
        }
        if (ResearchShoppingAssistantTeamQuestion::claimedByAnother($task, $request->string('run')->toString())) {
            return Response::error('Another run is working this question. Nothing was changed.');
        }

        $answer   = $request->string('answer')->toString();
        $evidence = $request->string('evidence')->toString();

        if ($request->string('action')->toString() === 'save_draft') {
            return ResearchShoppingAssistantTeamQuestion::saveDraft($task, $user, $answer, $evidence)
                ? Response::json(['reference' => $task->reference, 'result' => 'drafted'])
                : Response::error('Someone answered this question meanwhile. Nothing was changed.');
        }

        $outcome = ResearchShoppingAssistantTeamQuestion::answer($task, $user, $answer, $evidence, $request->boolean('keep_for_everyone', true));

        return Response::json(['reference' => $task->reference] + $outcome + ['meaning' => match ($outcome['result']) {
            'answered'         => 'Sent to the customer; the gurus will see it in their next morning round to confirm.',
            'drafted'          => 'Not sent: the check was not sure the evidence proves it. Kept as a draft for the guru.',
            'not_an_answer'    => 'Refused: it reads as a question or a note for us, not an answer for the customer.',
            'already_answered' => 'Someone answered first. Nothing was sent.',
            default            => 'Nothing was sent.',
        }]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'action'            => $schema->string()->enum(['save_draft', 'answer'])->required(),
            'reference'         => $schema->string()->description('TASK-n from merlin-questions-tool')->required(),
            'run'               => $schema->string()->description('The same run label passed to merlin-questions-tool')->required(),
            'answer'            => $schema->string()->description('The answer to the customer, in English')->required(),
            'evidence'          => $schema->string()->description('For each statement: the query and its result, the file and method, or the guide and section that proves it; and what was not found')->required(),
            'keep_for_everyone' => $schema->boolean()->description('For answer: keep it as a shop note for every customer (default true); false for an answer about this one customer'),
        ];
    }
}
