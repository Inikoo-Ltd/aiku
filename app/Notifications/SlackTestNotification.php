<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\BlockKit\Blocks\SectionBlock;
use Illuminate\Notifications\Slack\SlackMessage;

class SlackTestNotification extends Notification
{
    use Queueable;

    protected $message;

    public function __construct($message = 'Hello from Laravel!')
    {
        $this->message = $message;
    }

    public function via($notifiable)
    {
        return ['slack'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        return (new SlackMessage())
            ->sectionBlock(function (SectionBlock $block) {
                $block->text($this->message);
            });
    }
}
