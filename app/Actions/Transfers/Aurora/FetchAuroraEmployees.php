<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Mon, 05 Sept 2022 01:50:00 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Actions\Transfers\Aurora;

use App\Actions\Helpers\Media\SaveModelImage;
use App\Actions\HumanResources\Employee\StoreEmployee;
use App\Actions\HumanResources\Employee\UpdateEmployee;
use App\Actions\HumanResources\Employee\UpdateEmployeeWorkingHours;
use App\Actions\SysAdmin\User\AttachEmployeeToUser;
use App\Actions\SysAdmin\User\StoreUser;
use App\Actions\SysAdmin\User\UpdateUser;
use App\Enums\SysAdmin\User\UserAuthTypeEnum;
use App\Models\HumanResources\Employee;
use App\Models\HumanResources\Workplace;
use App\Transfers\Aurora\WithAuroraAttachments;
use App\Transfers\SourceOrganisationService;
use Arr;
use Exception;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class FetchAuroraEmployees extends FetchAuroraAction
{
    use WithAuroraAttachments;

    public string $commandSignature = 'fetch:employees {organisations?*} {--s|source_id=} {--d|db_suffix=}';

    public function handle(SourceOrganisationService $organisationSource, int $organisationSourceId): ?Employee
    {
        setPermissionsTeamId($organisationSource->getOrganisation()->group_id);

        if ($employeeData = $organisationSource->fetchEmployee($organisationSourceId)) {
            $sourceId = $employeeData['employee']['source_id'];

            if ($employee = Employee::where('source_id', $sourceId)->first()) {
                try {
                    $employee = UpdateEmployee::make()->action(
                        employee: $employee,
                        modelData: $employeeData['employee'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );
                    $this->recordChange($organisationSource, $employee->wasChanged());
                } catch (Exception $e) {
                    $this->recordError($organisationSource, $e, $employeeData['employee'], 'Employee', 'update');

                    return null;
                }
            } else {
                /* @var $workplace Workplace */
                $workplace = $organisationSource->getOrganisation()->workplaces()->first();
                try {

                    $employeeData['employee']['password'] = wordwrap(Str::random(), 4, '-', true);
                    $employee = StoreEmployee::make()->action(
                        parent: $workplace,
                        modelData: $employeeData['employee'],
                        hydratorsDelay: $this->hydratorsDelay,
                        strict: false,
                        audit: false
                    );

                    Employee::enableAuditing();
                    $this->saveMigrationHistory(
                        $employee,
                        Arr::except($employeeData['employee'], ['fetched_at', 'last_fetched_at', 'source_id', 'positions', 'user_model_status'])
                    );

                    $this->recordNew($organisationSource);

                    $sourceData = explode(':', $employee->source_id);
                    DB::connection('aurora')->table('Staff Dimension')
                        ->where('Staff Key', $sourceData[1])
                        ->update(['aiku_id' => $employee->id]);
                } catch (Exception|Throwable $e) {
                    $this->recordError($organisationSource, $e, $employeeData['employee'], 'Employee', 'store');

                    return null;
                }
            }

            UpdateEmployeeWorkingHours::run($employee, $employeeData['working_hours']);

            if (Arr::has($employeeData, 'user.source_id')) {
                $updateUser = true;
                $user = $employee->getUser();
                if (! $user) {
                    $user = $employee->group->users()->where('username', $employeeData['user']['username'])->first();
                    if ($user) {
                        $updateUser = false;
                        $user = AttachEmployeeToUser::make()->action(
                            $user,
                            $employee,
                            [
                                'status' => $employeeData['user']['user_model_status'],
                                'source_id' => $employeeData['user']['source_id'],
                            ]
                        );
                    }
                }

                if ($user) {
                    if ($updateUser) {
                        try {
                            UpdateUser::make()->action(
                                $employee->getUser(),
                                [
                                    'legacy_password' => (string) Arr::get($employeeData, 'user.legacy_password'),
                                    'status' => Arr::get($employeeData, 'user.status'),
                                ],
                                strict: false
                            );
                        } catch (Exception $e) {
                            $this->recordError($organisationSource, $e, $employeeData['user'], 'User', 'update');

                            return null;
                        }
                    }
                } else {
                    try {
                        StoreUser::make()->action(
                            $employee,
                            array_merge(
                                $employeeData['user'],
                                [
                                    'password' => wordwrap(Str::random(), 4, '-', true),
                                    'contact_name' => $employee->contact_name,
                                    'email' => $employee->work_email,
                                    'reset_password' => true,
                                    'auth_type' => UserAuthTypeEnum::AURORA,
                                ]
                            ),
                            strict: false
                        );
                    } catch (Exception|Throwable $e) {
                        $this->recordError($organisationSource, $e, $employeeData['user'], 'User', 'store');

                        return null;
                    }
                }
            }

            //            if ($employee->getUser()) {
            //                foreach ($employeeData['photo'] ?? [] as $profileImage) {
            //                    if (isset($profileImage['image_path']) and isset($profileImage['filename'])) {
            //                        SaveModelImage::run(
            //                            $employee->getUser(),
            //                            [
            //                                'path'         => $profileImage['image_path'],
            //                                'originalName' => $profileImage['filename'],
            //
            //                            ],
            //                            'avatar'
            //                        );
            //                    }
            //                }
            //            }

            $this->processFetchAttachments($employee, 'Staff', $employeeData['employee']['source_id']);

            return $employee;
        }

        return null;
    }

    public function getModelsQuery(): Builder
    {
        return DB::connection('aurora')
            ->table('Staff Dimension')
            ->select('Staff Key as source_id')
            ->where('Staff Type', '!=', 'Contractor')
            ->orderBy('source_id')
            ->when(app()->environment('testing'), function ($query) {
                return $query->limit(20);
            });
    }

    public function count(): ?int
    {
        return DB::connection('aurora')->table('Staff Dimension')
            ->where('Staff Type', '!=', 'Contractor')
            ->count();
    }
}
