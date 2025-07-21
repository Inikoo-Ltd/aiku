<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 21 Jul 2025 06:29:14 British Summer Time, Trnava, Slovakia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio\Shopify;

use App\Models\Dropshipping\Portfolio;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class FindShopifyProductVariant
{
    use asAction;

    public function handle(Portfolio $portfolio)
    {

        dd($portfolio);

    }

    public function getCommandSignature(): string
    {
        return 'shopify:find-variant {portfolio}';
    }

    public function asCommand(Command $command): void
    {
        $portfolio = Portfolio::find($command->argument('portfolio'));

        $this->handle($portfolio);

    }


}
