<?php

namespace App\Notifications;

use App\Channel\CustomMailMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Arr;
use NotificationChannels\Fcm\FcmMessage;

class MeasurementShareNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private array $measurement;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($measurement)
    {
        $this->measurement = $measurement;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable): MailMessage
    {
        return (new CustomMailMessage($notifiable))
                    ->line('The introduction to the notification.')
                    ->action('Notification Action', url('/'))
                    ->line('Thank you for using our application!');
    }

    public function toFcm($notifiable): FcmMessage
    {
        $measurement = $this->measurement;

        return FcmMessage::create()
            ->data([
                'type' => 'measurement-share'
            ])
            ->notification(
                \NotificationChannels\Fcm\Resources\Notification::create()
                ->title($measurement['title'])
                ->body($measurement['body'])
            );
    }


    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable): array
    {
        $measurement = $this->measurement;

        return [
            'title'  => Arr::get($measurement, 'title'),
            'body'   => Arr::get($measurement, 'body'),
            'type'   => Arr::get($measurement, 'type'),
            'slug'   => Arr::get($measurement, 'slug'),
            'route'  => Arr::get($measurement, 'route'),
            'id'     => Arr::get($measurement, 'id')
        ];
    }
}
