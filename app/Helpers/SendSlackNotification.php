<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 12 Aug 2025 15:09:41 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Helpers;

use App\Actions\GrpAction;
use App\Actions\Masters\MasterAsset\Slack\NewMasterAssetCreated;
use App\Actions\Masters\MasterProductCategory\Slack\NewMasterProductCategoryCreated;
use App\Models\Masters\MasterAsset;
use App\Models\Masters\MasterProductCategory;
use Illuminate\Notifications\AnonymousNotifiable;

class SendSlackNotification extends GrpAction
{
    public function handle(MasterAsset|MasterProductCategory $parent): void
    {
        if ($parent instanceof MasterAsset) {
            $template = NewMasterAssetCreated::run($parent);
        } else {
            $template = NewMasterProductCategoryCreated::run($parent);
        }

        $notifiable = (new AnonymousNotifiable())
                ->route('slack', []);

        try {
            $notifiable->notify(new SlackNotification($template));
        }catch (\Exception ){
            //
        }
    }
}
