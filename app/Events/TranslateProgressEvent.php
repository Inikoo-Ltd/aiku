<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TranslateProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;


    public string $text;
    public string $randomString;

    public function __construct(string $text, string $randomString)
    {
        $this->text = $text;
        $this->randomString = $randomString;
    }

    public function broadcastWith(): array
    {
        return [
            'translated_text' => $this->text
        ];
    }


    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("translate.{$this->randomString}.channel");
    }

    public function broadcastAs(): string
    {
        return 'translate-progress';
    }
}
