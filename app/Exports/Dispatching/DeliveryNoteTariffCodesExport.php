<?php

namespace App\Exports\Dispatching;

use App\Actions\Dispatching\DeliveryNote\UI\Traits\WithDeliveryNoteTariffCodesQuery;
use App\Models\Dispatching\DeliveryNote;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class DeliveryNoteTariffCodesExport implements FromArray, ShouldAutoSize, WithHeadings
{
    use WithDeliveryNoteTariffCodesQuery;

    /**
     * @param array<int, string> $fields Selected field keys; empty means all fields.
     */
    public function __construct(public DeliveryNote $deliveryNote, public array $fields = [])
    {
    }

    /**
     * @return array<string, array{heading: string, select: string}>
     */
    public static function fieldDefinitions(): array
    {
        return [
            'tariff_code' => ['heading' => 'Tariff code', 'select' => 'tariff_code'],
            'description' => ['heading' => 'Tariff code description', 'select' => 'description'],
            'origin'      => ['heading' => 'Origin', 'select' => 'origin'],
            'un_numbers'  => ['heading' => 'UN numbers', 'select' => 'un_numbers'],
            'parts'       => ['heading' => 'References', 'select' => 'parts'],
            'weight'      => ['heading' => 'Weight (kg)', 'select' => 'weight'],
            'units'       => ['heading' => 'Units', 'select' => 'units'],
            'amount'      => ['heading' => 'Amount', 'select' => 'amount'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function selectedFields(): array
    {
        $keys = array_keys(self::fieldDefinitions());

        if (count($this->fields) === 0) {
            return $keys;
        }

        return array_values(array_intersect($keys, $this->fields));
    }

    public function dataQuery(): Builder
    {
        $definitions = self::fieldDefinitions();
        $base        = $this->getTariffCodesBaseQuery($this->deliveryNote);

        $selects = array_map(
            fn ($field) => $definitions[$field]['select'].' as '.$field,
            $this->selectedFields()
        );

        return DB::query()->fromSub($base, 'tc')
            ->select($selects)
            ->orderBy('tariff_code');
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return $this->dataQuery()->get()
            ->map(fn ($row) => array_values((array) $row))
            ->all();
    }

    public function headings(): array
    {
        $definitions = self::fieldDefinitions();

        return array_map(
            fn ($field) => $definitions[$field]['heading'],
            $this->selectedFields()
        );
    }
}
