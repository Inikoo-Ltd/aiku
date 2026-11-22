<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Models\Inventory\OrgStockMovement;
use App\Notifications\Concerns\SendsMailThroughSes;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;

class ImplausibleStockCountNotification extends Notification
{
    use SendsMailThroughSes;

    public bool $byEmail = true;

    public function __construct(public OrgStockMovement $orgStockMovement)
    {
    }

    public function via($notifiable): array
    {
        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function title(): string
    {
        return __(':organisation :sko counted from :current to :new in :location (:value)', [
            'organisation' => $this->orgStockMovement->organisation->code,
            'sko'          => $this->orgStockMovement->orgStock->code,
            'current'      => trimDecimalZeros($this->countWarning()['current_stock']),
            'new'          => trimDecimalZeros($this->countWarning()['new_count']),
            'location'     => $this->orgStockMovement->location?->code,
            'value'        => Number::currency($this->countWarning()['value_change'], $this->countWarning()['currency']),
        ]);
    }

    public function body(): string
    {
        $countWarning = $this->countWarning();
        $userName     = $this->orgStockMovement->user?->contact_name ?? __('System');

        return implode("\n", [
            $countWarning['confirmed_by_user_id']
                ? __(':user saw the warning and confirmed with I know what I am doing.', ['user' => $userName])
                : __(':user saved it without seeing a warning (warehouse app, offline queue or system).', ['user' => $userName]),
            __('SKO').': '.$this->orgStockMovement->orgStock->code.' '.$this->orgStockMovement->orgStock->name,
            __('Location').': '.$this->orgStockMovement->location?->code,
            __('Current stock').': '.trimDecimalZeros($countWarning['current_stock']),
            __('New count').': '.trimDecimalZeros($countWarning['new_count']),
            __('Change in value').': '.Number::currency($countWarning['value_change'], $countWarning['currency']),
            __('Largest purchase ever').': '.trimDecimalZeros($countWarning['largest_purchase']),
            __('Typical monthly usage').': '.trimDecimalZeros($countWarning['monthly_usage']),
        ]);
    }

    public function url(): string
    {
        return route($this->route()['name'], $this->route()['parameters']);
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title(),
            'body'  => $this->body(),
            'type'  => 'implausible_stock_count',
            'slug'  => $this->orgStockMovement->orgStock->slug,
            'route' => $this->route(),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = $this->newMailMessage()->subject($this->title());
        foreach (explode("\n", $this->body()) as $line) {
            $message->line($line);
        }

        return $message
            ->action(__('Open stock history'), $this->url())
            ->line(__('You get this email because you subscribed to implausible stock counts in your notification settings.'));
    }

    /**
     * @return array{current_stock: float, new_count: float, value_change: float, currency: string, largest_purchase: float, monthly_usage: float, confirmed_by_user_id: int|null}
     */
    private function countWarning(): array
    {
        return $this->orgStockMovement->data['count_warning'];
    }

    private function route(): array
    {
        return [
            'name'       => 'grp.org.warehouses.show.inventory.org_stocks.all_org_stocks.show.stock_history',
            'parameters' => [
                'organisation' => $this->orgStockMovement->organisation->slug,
                'warehouse'    => $this->orgStockMovement->warehouse->slug,
                'orgStock'     => $this->orgStockMovement->orgStock->slug,
            ],
        ];
    }
}
