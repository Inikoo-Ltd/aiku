<?php

namespace App\Actions\Maintenance\Reviews;

use App\Actions\Reviews\Import\TrustpilotImport;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Lorisleiva\Actions\Concerns\AsAction;
use Maatwebsite\Excel\Facades\Excel;

class ReadTrustpilotCSVReview
{
    use AsAction;

    public function handle(Command $command, Shop $shop): void
    {
        $filename = $command->argument('filename');

        $command->info("Reading from Trustpilot CSV from '$filename'");

        Excel::import(
            new TrustpilotImport($shop),
            Storage::disk('local')->path($filename),
            null,
            \Maatwebsite\Excel\Excel::CSV
        );
    }

    public string $commandSignature   = 'import:trustpilot_csv {filename} {shop}';

    public function asCommand(Command $command)
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
