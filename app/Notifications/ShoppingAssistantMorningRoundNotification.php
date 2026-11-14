<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 14 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Actions\CRM\ShoppingAssistant\CloseUnansweredShoppingAssistantTeamQuestions;
use App\Actions\CRM\ShoppingAssistant\StoreShoppingAssistantTeamQuestion;
use App\Models\Chat\ChatKnowledgeEntry;
use App\Models\Tasks\StaffTask;
use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * The guru's morning round: the questions customers asked that nobody answered, and how many
 * customers their answers helped this week. To a manager, the questions a working day has not settled.
 */
class ShoppingAssistantMorningRoundNotification extends Notification
{
    use SendsMailThroughSes;

    public bool $byEmail = true;

    /**
     * @param  Collection<int, StaffTask>  $tasks
     * @param  Collection<int, StaffTask>|null  $researcherAnswers
     * @param  Collection<int, ChatKnowledgeEntry>|null  $facts
     */
    public function __construct(public Collection $tasks, public ?int $helpedCustomers = null, public bool $forManager = false, public ?Collection $researcherAnswers = null, public ?Collection $facts = null)
    {
    }

    public function via($notifiable): array
    {
        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title(),
            'body'  => $this->summary(),
            'type'  => 'assistant_question',
            'route' => $this->url(),
        ];
    }

    public function url(): string
    {
        return route('grp.tasks.index');
    }

    public function title(): string
    {
        return $this->forManager
            ? trans_choice('{1} Urgent: a customer has waited over a working day for an answer|[2,*] Urgent: :count customers have waited over a working day for an answer', $this->tasks->count())
            : ($this->tasks->isEmpty()
                ? __('Merlin: please check a few answers before it uses them')
                : trans_choice('{1} Merlin has 1 question for you|[2,*] Merlin has :count questions for you', $this->tasks->count()));
    }

    public function summary(): string
    {
        $lines = $this->tasks->map(fn (StaffTask $task) => $task->reference.' '.data_get($task->data, 'shop_code').': '.str_replace("\n", ' ', StoreShoppingAssistantTeamQuestion::questionText($task)).' ('.__('asked :ago', ['ago' => $task->created_at->diffForHumans()]).')');

        if ($this->forManager) {
            $lines->prepend(trans_choice(
                '{1} This customer is still waiting for an answer nobody in the team has given. Please make sure someone answers today: in :hours hours the question closes and the customer is told to contact customer services.|[2,*] These customers are still waiting for answers nobody in the team has given. Please make sure someone answers today: in :hours hours each question closes and the customer is told to contact customer services.',
                $this->tasks->count(),
                ['hours' => CloseUnansweredShoppingAssistantTeamQuestions::CLOSE_AFTER_HOURS]
            ));
        }

        if ($this->researcherAnswers?->isNotEmpty()) {
            $lines->push(__('Answered by Merlin\'s researcher from our data and code: check them, and correct the shop note if one is wrong.'));
            $lines->push(...$this->researcherAnswers->map(fn (StaffTask $task) => $task->reference.' '.data_get($task->data, 'shop_code').': '.str_replace("\n", ' ', StoreShoppingAssistantTeamQuestion::questionText($task)).' → '.str_replace("\n", ' ', (string) data_get($task->data, 'answer'))));
        }

        if ($this->facts?->isNotEmpty()) {
            $lines->push(__('Still true? Merlin does not use these until someone says yes. Use it, remove it or correct it on the shop\'s chat settings:'));
            foreach ($this->facts->groupBy('shop_id') as $shopFacts) {
                $shop = $shopFacts->first()->shop;
                $lines->push($shop->code.' '.route('grp.org.shops.show.chat.settings', [$shop->organisation->slug, $shop->slug]));
                $lines->push(...$shopFacts->map(fn (ChatKnowledgeEntry $fact) => '- '.$fact->title.': '.str_replace("\n", ' ', mb_substr($fact->body, 0, 300))));
            }
        }

        if ($this->helpedCustomers) {
            $lines->push(trans_choice('{1} Your answers helped 1 customer in the last 7 days.|[2,*] Your answers helped :count customers in the last 7 days.', $this->helpedCustomers));
        }

        return $lines->implode("\n");
    }

    public function toMail($notifiable): MailMessage
    {
        $message = $this->newMailMessage()->subject($this->title());

        foreach (explode("\n", $this->summary()) as $line) {
            $message->line($line);
        }

        return $message->action(__('Answer'), $this->url());
    }
}
