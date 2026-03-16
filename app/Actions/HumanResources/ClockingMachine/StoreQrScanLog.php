<?php

namespace App\Actions\HumanResources\ClockingMachine;

use App\Actions\OrgAction;
use App\Models\HumanResources\QrScanLog;
use App\Models\HumanResources\ClockingMachine;
use Illuminate\Support\Facades\Auth;
use Lorisleiva\Actions\Concerns\AsAction;

class StoreQrScanLog extends OrgAction
{
    use AsAction;

    public function handle(
        ?ClockingMachine $clockingMachine,
        string $status,
        ?string $reason = null,
        ?string $qrToken = null,
        ?float $lat = null,
        ?float $lng = null
    ): QrScanLog {

        $user = Auth::user();
        $employeeId = $user?->employee?->id ?? ($user?->id);

        return QrScanLog::create([
            'organisation_id'     => $clockingMachine?->organisation_id,
            'workplace_id'        => $clockingMachine?->workplace_id,
            'clocking_machine_id' => $clockingMachine?->id,
            'employee_id'         => $employeeId,
            'qr_token'            => $qrToken ? substr($qrToken, 0, 255) : null,
            'scanned_at'          => now(),
            'lat'                 => $lat,
            'lng'                 => $lng,
            'status'              => $status,
            'reason'              => $reason,
        ]);
    }
}
