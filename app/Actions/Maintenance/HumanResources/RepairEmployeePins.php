<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 30 Jul 2026 05:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Maintenance\HumanResources;

use App\Actions\HumanResources\Employee\SetEmployeePin;
use App\Models\HumanResources\Employee;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class RepairEmployeePins
{
    use AsAction;

    protected function handle(Employee $employee): void
    {
        SetEmployeePin::make()->action($employee, updateQuietly: true);
    }

    public string $commandSignature = 'employees:repair_pins';

    /**
     * Only employees whose pin is missing, in the pre 3 letters + 3 numbers format, or shared
     * with another employee of the same organisation are re-pinned. Re-running the command must
     * not invalidate the pins employees already carry on their printed barcode or phone.
     */
    public function asCommand(Command $command): void
    {
        $repaired = 0;
        $seen     = [];

        Employee::orderBy('id')->chunkById(500, function ($employees) use (&$repaired, &$seen) {
            foreach ($employees as $employee) {
                $key = $employee->organisation_id.'|'.$employee->pin;

                if ($this->hasValidPin($employee) && !isset($seen[$key])) {
                    $seen[$key] = true;
                    continue;
                }

                $this->handle($employee);
                $seen[$employee->organisation_id.'|'.$employee->fresh()->pin] = true;
                $repaired++;
            }
        });

        $command->info($repaired.' employee pins repaired');
    }

    private function hasValidPin(Employee $employee): bool
    {
        list($letters, $numbers) = SetEmployeePin::make()->pinCharacterSet();

        $pattern = '/^'.$employee->organisation_id.':['.implode('', $letters).']{3}['.implode('', $numbers).']{3}$/';

        return (bool) preg_match($pattern, (string) $employee->pin);
    }
}
