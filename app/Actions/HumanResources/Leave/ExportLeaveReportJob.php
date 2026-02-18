<?php

namespace App\Actions\HumanResources\Leave;

use App\Exports\HumanResources\LeavesExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExportLeaveReportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private int $organisationId,
        private array $filters,
        private string $format,
        private int $userId
    ) {
        $this->onQueue('exports');
    }

    public function handle(): void
    {
        $export = new LeavesExport($this->organisationId, $this->filters);

        $filename = now()->format('Y-m-d') . '-leaves-' . rand(111, 999);

        if ($this->format === 'xlsx') {
            $filename .= '.xlsx';
            $path = storage_path("app/exports/{$filename}");
            \Maatwebsite\Excel\Facades\Excel::store($export, $filename, 'local', \Maatwebsite\Excel\Excel::XLSX);
        } else {
            $filename .= '.csv';
            \Maatwebsite\Excel\Facades\Excel::store($export, $filename, 'local', \Maatwebsite\Excel\Excel::CSV);
        }

        logger('Leave report export job completed', [
            'organisation_id' => $this->organisationId,
            'user_id' => $this->userId,
            'filename' => $filename,
            'filters' => $this->filters,
        ]);
    }
}
