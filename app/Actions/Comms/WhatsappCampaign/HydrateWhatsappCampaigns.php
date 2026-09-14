<?php

/*
 * Author: eka yudinata (https://github.com/ekayudinata)
 * Copyright (c) 2026, eka yudinata
 */

namespace App\Actions\Comms\WhatsappCampaign;

use App\Actions\Comms\WhatsappCampaign\Hydrators\WhatsappCampaignHydrateStats;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Comms\WhatsappCampaign;

class HydrateWhatsappCampaigns
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:whatsapp_campaigns {organisations?*} {--slugs}';

    public function __construct()
    {
        $this->model = WhatsappCampaign::class;
    }

    public function handle(WhatsappCampaign $campaign): void
    {
        WhatsappCampaignHydrateStats::run($campaign->id);
    }
}
