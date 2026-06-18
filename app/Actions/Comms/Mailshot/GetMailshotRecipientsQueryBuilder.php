<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 19 Dec 2024 18:08:10 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Comms\Mailshot;

use App\Actions\CRM\Customer\GetCustomersQueryByRecipe;
use App\Models\Comms\Mailshot;
use Illuminate\Database\Query\Builder;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMailshotRecipientsQueryBuilder
{
    use AsObject;

    /**
     * @throws \Exception
     */
    public function handle(Mailshot $mailshot): ?Builder
    {
        if (!empty($mailshot->recipients_recipe)) {
            return GetCustomersQueryByRecipe::run($mailshot->shop_id, $mailshot->recipients_recipe);
        }

        return null;
    }
}
