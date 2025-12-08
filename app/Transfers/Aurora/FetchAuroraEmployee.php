<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 09 Jun 2024 12:55:10 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Transfers\Aurora;

use App\Enums\HumanResources\Employee\EmployeeStateEnum;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FetchAuroraEmployee extends FetchAurora
{
    use WithAuroraImages;
    use WithAuroraParsers;

    public function fetch(int $id): ?array
    {
        $this->auroraModelData = $this->fetchData($id);

        if ($this->auroraModelData) {
            if ($this->auroraModelData->{'Staff Type'} == 'Contractor') {
                return null;
            }

            $data = [];
            $errors = [];
            if ($this->parseDatetime($this->auroraModelData->{'Staff Valid From'}) == '') {
                $errors = [
                    'missing' => ['created_at', 'employment_start_at'],
                ];
            }

            $working_hours = json_decode($this->auroraModelData->{'Staff Working Hours'}, true);
            if ($working_hours) {
                $working_hours['week_distribution'] = array_change_key_case(
                    json_decode($this->auroraModelData->{'Staff Working Hours Per Week Metadata'}, true)
                );
            }
            $workingHours = json_decode($this->auroraModelData->{'Staff Working Hours'}, true);
            $weekDistribution = json_decode($this->auroraModelData->{'Staff Working Hours Per Week Metadata'}, true);

            if ($workingHours and $weekDistribution) {
                $workingHours['week_distribution'] = array_change_key_case($weekDistribution);
            }

            $salary = json_decode($this->auroraModelData->{'Staff Salary'}, true);
            if ($salary) {
                $salary = array_change_key_case($salary);
            }

            if ($this->auroraModelData->{'Staff Address'}) {
                $data['address'] = $this->auroraModelData->{'Staff Address'};
            }

            $this->parsedData['working_hours'] = $working_hours ?? [];

            if ($this->auroraModelData->{'Staff ID'}) {
                $workerNumber = preg_replace('/[()]/', '', $this->auroraModelData->{'Staff ID'});
                $workerNumber = preg_replace('/\s+/', '-', $workerNumber);
            } else {
                $workerNumber = $this->auroraModelData->{'Staff Key'};
            }

            $createdAt = $this->auroraModelData->{'Staff Valid From'};
            if (! $createdAt) {
                $firstAction = DB::connection('aurora')->table('History Dimension')
                    ->select('History Date as date')
                    ->where('Subject', 'Staff')
                    ->where('Subject Key', $this->auroraModelData->{'Staff Key'})
                    ->orderBy('History Date')->first();

                if ($firstAction) {
                    $createdAt = $firstAction->date;
                }
            }

            $this->parsedData['employee'] = [
                'alias' => $this->auroraModelData->{'Staff Alias'},
                'contact_name' => $this->auroraModelData->{'Staff Name'},
                'email' => $this->auroraModelData->{'Staff Email'} ?: null,
                'phone' => $this->auroraModelData->{'Staff Telephone'} ?: null,
                'identity_document_number' => $this->auroraModelData->{'Staff Official ID'} ?: null,
                'date_of_birth' => $this->parseDate($this->auroraModelData->{'Staff Birthday'}),
                'worker_number' => $workerNumber,
                'emergency_contact' => $this->auroraModelData->{'Staff Next of Kind'} ?: null,
                'job_title' => $this->auroraModelData->{'Staff Job Title'} ?: null,
                'salary' => $salary,
                'employment_start_at' => $this->parseDatetime($this->auroraModelData->{'Staff Valid From'}),
                'employment_end_at' => $this->parseDatetime($this->auroraModelData->{'Staff Valid To'}),
                'type' => Str::snake($this->auroraModelData->{'Staff Type'}, '-'),
                'state' => match ($this->auroraModelData->{'Staff Currently Working'}) {
                    'No' => EmployeeStateEnum::LEFT,
                    default => EmployeeStateEnum::WORKING
                },
                'data' => $data,
                'errors' => $errors,
                'source_id' => $this->organisation->id.':'.$this->auroraModelData->{'Staff Key'},
                'fetched_at' => now(),
                'last_fetched_at' => now(),
            ];

            if ($createdAt) {
                $this->parsedData['employee']['created_at'] = $createdAt;
            }

            $userData = $this->parseUserFromEmployee();
            $this->parsedData['user'] = $userData;

            $this->parsedData['photo'] = $this->parseUserPhoto();

            $userId = null;

            if (Arr::has($this->parsedData, 'user.source_id')) {
                $userSourceData = explode(':', $this->parsedData['user']['source_id']);
                $userId = $userSourceData[1];
            }

            $this->parsedData['employee']['positions'] = $this->parsePositions($userId);
        }

        return $this->parsedData;
    }

    private function parseUserFromEmployee(): ?array
    {
        $auroraUserData = DB::connection('aurora')
            ->table('User Dimension as users')
            ->leftJoin('Staff Dimension', 'Staff Key', 'User Parent Key')
            ->selectRaw('*,(select GROUP_CONCAT(`Role Code`) from `Staff Role Bridge` SRB where (SRB.`Staff Key`=`Staff Dimension`.`Staff Key`) ) as staff_positions')
            ->whereIn('User Type', ['Staff', 'Contractor'])
            ->where('users.aiku_ignore', 'No')
            ->where('User Parent Key', $this->auroraModelData->{'Staff Key'})->first();

        if ($auroraUserData) {

            $legacyPassword = $auroraUserData->{'User Password'};
            if (app()->isLocal()) {
                $legacyPassword = hash('sha256', 'hello');
            }

            if ($auroraUserData->aiku_alt_username) {
                $username = $auroraUserData->aiku_alt_username;
            } else {
                $username = $auroraUserData->{'User Handle'};
            }

            $status = $auroraUserData->{'User Active'} == 'Yes';

            $employeeState = Arr::get($this->parsedData, 'employee.state');
            if ($employeeState == EmployeeStateEnum::LEFT) {
                $status = false;
            }

            return [
                'source_id' => $this->organisation->id.':'.$auroraUserData->{'User Key'},
                'username' => Str::kebab(Str::lower($username)),
                'status' => $status,
                'user_model_status' => $status,
                'created_at' => $auroraUserData->{'User Created'},
                'legacy_password' => $legacyPassword,
                // 'password'          => (app()->isLocal() ? 'hello' : wordwrap(Str::random(), 4, '-', true)),
                'language_id' => $this->parseLanguageID($auroraUserData->{'User Preferred Locale'}),
                'reset_password' => false,
                'fetched_at' => now(),
                'last_fetched_at' => now(),
            ];
        }

        return null;
    }

    protected function fetchData($id): ?object
    {
        return DB::connection('aurora')
            ->table('Staff Dimension')
            ->leftJoin('User Dimension', 'Staff Key', 'User Parent Key')
            ->selectRaw('*,(select GROUP_CONCAT(`Role Code`) from `Staff Role Bridge` SRB where (SRB.`Staff Key`=`Staff Dimension`.`Staff Key`) ) as staff_positions')
            ->selectRaw('(select GROUP_CONCAT(`User Group Key`) from `User Group User Bridge` UGUB where (UGUB.`User Key`=`User Dimension`.`User Key`) ) as staff_groups')
            ->where('Staff Key', $id)->first();
    }
}
