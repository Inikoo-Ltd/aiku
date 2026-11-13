<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 12 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\CRM\WhatsappOptIn;

use App\Actions\RetinaAction;
use App\Models\CRM\Customer;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * "Not interested" on the dashboard WhatsApp card: the card is not offered on the dashboard again.
 * The customer can still opt in from Settings → Email management, which shows the same card.
 */
class DismissRetinaWhatsappOptIn extends RetinaAction
{
    public function handle(Customer $customer): void
    {
        $customer->comms?->update(['whatsapp_newsletter_opt_in_dismissed_at' => now()]);
    }

    public function asController(ActionRequest $request): void
    {
        $this->initialisation($request);

        $this->handle($this->customer);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
