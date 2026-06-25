<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jun 2026 12:07:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Reviews\Import;

use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;
use Maatwebsite\Excel\Facades\Excel;

class ReadTrustPilotCSVReview
{
    use AsAction;

    public function handle(Command $command, Shop $shop): void
    {
        $filename = $command->argument('filename');

        $command->info("Reading from Trust pilot CSV from '$filename'");

        Excel::import(
            new TrustPilotImport($shop),
            Storage::disk('local')->path($filename),
            null,
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    public string $commandSignature   = 'import:trust_pilot_csv {filename} {shop}';

    public function asCommand(Command $command): int
    {
        if (!$command->argument(('shop'))) {
            $command->error('Please select a shop');
            return 1;
        }

        if (!Storage::disk('local')->exists($command->argument('filename'))) {
            $command->error('File doesnt exists');
            return 1;
        }

        $shop = Shop::where('slug', $command->argument('shop'))->first();

        $this->handle($command, $shop);

        return 0;
    }
}
