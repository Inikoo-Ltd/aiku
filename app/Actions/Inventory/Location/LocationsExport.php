<?php

namespace App\Actions\Inventory\Location;

use App\Models\Inventory\Location;
use App\Models\Inventory\Warehouse;
use App\Events\FileDownloadProgress;
use Illuminate\Contracts\Queue\ShouldQueue;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class LocationsExport implements FromQuery, WithMapping, WithHeadings, ShouldQueue
{
    use Exportable;

    private Warehouse $warehouse;
    private array $columns;
    public int $processed = 0;
    public int $totalCount = 0;

    public function __construct(Warehouse $warehouse, array $columns)
    {
        $this->warehouse = $warehouse;
        $this->columns = $columns;
    }

    public function map($row): array
    {
        $this->processed++;

        $progress = $this->totalCount > 0
            ? ($this->processed / $this->totalCount) * 100
            : 100;

        broadcast(new FileDownloadProgress($this->warehouse->id, (int) $progress));

        return collect($this->columns)->map(fn($col) => $row->{$col} ?? '')->toArray();
    }

    public function headings(): array
    {
        return array_map(fn($col) => str_replace('_', ' ', ucfirst($col)), $this->columns);
    }

    public function query()
    {
        $query = Location::query()->where('warehouse_id', $this->warehouse->id);
        $this->totalCount = $query->count();

        return $query;
    }
}
