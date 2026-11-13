<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 13 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Enums\CRM\ShoppingAssistant\ShoppingAssistantChallengeTypeEnum;
use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;

class ShoppingAssistantUnmetNeedsNotification extends Notification
{
    use SendsMailThroughSes;

    /**
     * @param  array{from: string, to: string, needs: array<string, array<int, array{topic: string, conversations: int, shops: string}>>}  $needs  SendShoppingAssistantUnmetNeeds::needs()
     */
    public function __construct(public array $needs)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function title(): string
    {
        return __('Products customers asked for that we do not have, :from to :to', ['from' => $this->needs['from'], 'to' => $this->needs['to']]);
    }

    /**
     * @return array<string, array<int, string>>  section heading => lines
     */
    public function sections(): array
    {
        $headings = [
            ShoppingAssistantChallengeTypeEnum::UNMET_NEED->value   => __('Not in our range'),
            ShoppingAssistantChallengeTypeEnum::OUT_OF_STOCK->value => __('Out of stock when asked'),
        ];

        $sections = [];
        foreach ($this->needs['needs'] as $type => $rows) {
            if ($rows) {
                $sections[$headings[$type] ?? $type] = array_map(fn (array $row) => __(':topic – :count, :shops', [
                    'topic' => $this->plain($row['topic']),
                    'count' => trans_choice(':count conversation|:count conversations', $row['conversations']),
                    'shops' => $row['shops'],
                ]), $rows);
            }
        }

        return $sections;
    }

    public function toMail($notifiable): MailMessage
    {
        $mail = $this->newMailMessage()
            ->subject($this->title())
            ->line(__('What customers asked our AI shopping assistant for last week that we do not sell or did not have in stock. The assistant told them it would pass this on to our purchasing team.'));

        foreach ($this->sections() as $heading => $lines) {
            $mail->line('**'.$heading.'**');
            foreach ($lines as $line) {
                $mail->line($line);
            }
        }

        return $mail;
    }

    public function toSlackMessage(): SlackMessage
    {
        $message = (new SlackMessage())->headerBlock(':mag: '.mb_substr($this->title(), 0, 140));

        foreach ($this->sections() as $heading => $lines) {
            $message->sectionBlock(fn ($block) => $block->text(mb_substr('*'.$heading."*\n• ".implode("\n• ", $lines), 0, 2900))->markdown());
        }

        return $message;
    }

    /**
     * Topics are written by AI from what customers typed: as plain text, so they never turn into
     * links or formatting in the email (Markdown) or in Slack (mrkdwn).
     */
    public function plain(string $text): string
    {
        $text = str_replace(['://', 'www.', '&'], [":\u{200B}//", "www\u{200B}.", '+'], $text);

        return mb_substr(trim(preg_replace(['/[\\\\`*_{}\[\]<>#|~!]/u', '/\s+/u'], ' ', $text)), 0, 80);
    }
}
