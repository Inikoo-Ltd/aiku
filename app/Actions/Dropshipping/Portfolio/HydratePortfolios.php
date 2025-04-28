<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 28 Apr 2025 12:49:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Portfolio;

use App\Actions\Dropshipping\Portfolio\Hydrators\PortfolioHydrateItemDescriptions;
use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Models\Dropshipping\Portfolio;

class HydratePortfolios
{
    use WithHydrateCommand;

    public string $commandSignature = 'hydrate:portfolios {organisations?*} {--s|slugs=}';

    public function __construct()
    {
        $this->model = Portfolio::class;
    }

    public function handle(Portfolio $portfolio): void
    {
        PortfolioHydrateItemDescriptions::run($portfolio);
    }


}
