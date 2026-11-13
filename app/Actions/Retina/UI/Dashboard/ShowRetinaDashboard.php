<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 19 Sep 2023 14:12:54 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Dashboard;

use App\Actions\Retina\CRM\WhatsappOptIn\GetRetinaWhatsappOptInData;
use App\Actions\RetinaAction;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Comms\Mailshot\MailshotReadyMadeLinkEnum;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Illuminate\Support\Arr;

class ShowRetinaDashboard extends RetinaAction
{
    use AsAction;


    public function handle(ActionRequest $request): Response
    {
        $inertiaPage = 'Dashboard/RetinaFulfilmentDashboard';
        if ($this->shop->type === ShopTypeEnum::DROPSHIPPING) {
            $inertiaPage = 'Dashboard/RetinaDropshippingDashboard';
        } elseif ($this->shop->type === ShopTypeEnum::B2B) {
            $inertiaPage = 'Dashboard/RetinaB2BDashboard';
        }

        if ($this->asPupil) {
            $inertiaPage = 'Dashboard/PupilFulfilmentDashboard';
        }

        $props = [
            'breadcrumbs' => $this->getBreadcrumbs(),
            'data'        => match ($this->shop->type) {
                ShopTypeEnum::FULFILMENT => GetRetinaFulfilmentHomeData::run($this->fulfilmentCustomer, $request),
                ShopTypeEnum::DROPSHIPPING => GetRetinaDropshippingHomeData::run($this->customer),
                ShopTypeEnum::B2B => GetRetinaB2BHomeData::run($this->customer),
                default => []
            },
            'welcome_message' => Arr::get($this->website->settings, 'welcome_message'),
        ];

        if ($this->shop->type === ShopTypeEnum::B2B && !$this->asPupil) {
            $customer          = $this->customer;
            $props['insights'] = Inertia::defer(fn () => GetRetinaB2BDashboardInsights::run($customer));
        }

        if (!$this->asPupil && $this->customer && GetRetinaWhatsappOptInData::isOfferedIn($this->shop)) {
            $props['whatsapp_optin'] = GetRetinaWhatsappOptInData::run($this->customer, $this->website, dismissible: true);
        }

        return Inertia::render($inertiaPage, $props);
    }

    public function asController(ActionRequest $request): Response|RedirectResponse
    {
        $this->initialisation($request);

        if ($this->dismissedWhatsappOptInCampaign($request)) {
            return redirect()->route('retina.sysadmin.email.edit', Arr::only($request->query(), ['utm_source', 'utm_medium', 'utm_campaign']));
        }

        return $this->handle($request);
    }

    /**
     * A WhatsApp opt-in campaign link lands on the dashboard card; a customer who chose Not interested
     * no longer has that card, so the link goes to Email management, which shows the same card. The
     * query (utm) is kept, and the browser keeps the #whatsapp-updates anchor through the redirect.
     */
    private function dismissedWhatsappOptInCampaign(ActionRequest $request): bool
    {
        $comms = $this->customer?->comms;

        return $request->query('utm_campaign') === MailshotReadyMadeLinkEnum::WHATSAPP_OPT_IN->campaign()
            && GetRetinaWhatsappOptInData::isOfferedIn($this->shop)
            && $comms?->whatsapp_newsletter_opt_in_dismissed_at
            && !$comms->is_subscribed_to_whatsapp_newsletter;
    }

    public function inPupil(ActionRequest $request): Response
    {
        $this->initialisationFromPupil($request);

        return $this->handle($request);
    }

    public function getBreadcrumbs(): array
    {
        return [


        ];
    }
}
