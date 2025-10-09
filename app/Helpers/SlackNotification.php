<?php

namespace App\Helpers;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;

class SlackNotification extends Notification
{
    use Queueable;

    protected SlackMessage $slackMessage;

    public function __construct(SlackMessage $slackMessage)
    {
        $this->slackMessage = $slackMessage;
    }

    public function via($notifiable)
    {
        return ['slack'];
    }

    public function toSlack(object $notifiable): SlackMessage
    {
        return $this->slackMessage;
    }
}
