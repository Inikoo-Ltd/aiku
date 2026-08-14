<?php

namespace App\Actions\HumanResources\ClockingMachine;

use App\Actions\OrgAction;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\QrScanLog;
use App\Models\HumanResources\ClockingMachine;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreQrScanLog extends OrgAction
{
    use AsAction;

    /**
     * The employee is the one the scan was resolved to, passed in rather than looked up again, so
     * the log names the same employment the clocking is recorded against. It stays null when the
     * scan failed before an employee could be resolved: employee_id is a foreign key, and the
     * scanning user's own id is not an employee id.
     */
    public function handle(
        ?ClockingMachine $clockingMachine,
        string $status,
        ?string $reason = null,
        ?string $qrToken = null,
        ?float $lat = null,
        ?float $lng = null,
        ?Employee $employee = null
    ): QrScanLog {

        return QrScanLog::create([
            'organisation_id'     => $clockingMachine?->organisation_id,
            'workplace_id'        => $clockingMachine?->workplace_id,
            'clocking_machine_id' => $clockingMachine?->id,
            'employee_id'         => $employee?->id,
            'qr_token'            => $qrToken ? substr($qrToken, 0, 255) : null,
            'scanned_at'          => now(),
            'lat'                 => $lat,
            'lng'                 => $lng,
            'status'              => $status,
            'reason'              => $reason,
        ]);
    }
}
