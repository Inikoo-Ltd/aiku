<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Notifications;

use App\Notifications\Concerns\SendsMailThroughSes;
use App\Models\Procurement\OrgSupplierProduct;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SupplierProductAvailabilityChangedNotification extends Notification
{
    use SendsMailThroughSes;

    public bool $byEmail = true;

    public function __construct(public OrgSupplierProduct $orgSupplierProduct, public string $title, public string $body)
    {
    }

    public function via($notifiable): array
    {
        return $this->byEmail ? ['database', 'mail'] : ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'title' => $this->title,
            'body'  => $this->body,
            'type'  => 'supplier_product_availability',
            'slug'  => (string) $this->orgSupplierProduct->id,
            'route' => $this->route(),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return $this->newMailMessage()
            ->subject($this->title)
            ->line($this->body)
            ->action(__('Open the product'), route($this->route()['name'], $this->route()['parameters']))
            ->line(__('You get this email because you buy this product or lead purchasing in :organisation.', ['organisation' => $this->orgSupplierProduct->organisation->name]));
    }

    private function route(): array
    {
        return [
            'name'       => 'grp.org.procurement.org_supplier_products.show',
            'parameters' => [$this->orgSupplierProduct->organisation->slug, $this->orgSupplierProduct->slug],
        ];
    }
}
