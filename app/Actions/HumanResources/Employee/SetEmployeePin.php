<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 May 2024 16:19:39 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Employee;

use App\Actions\OrgAction;
use App\Models\HumanResources\Employee;
use Exception;
use Illuminate\Console\Command;
use Lorisleiva\Actions\Concerns\AsAction;

class SetEmployeePin extends OrgAction
{
    use AsAction;

    private mixed $updateQuietly = false;
    /**
     * @var array|\ArrayAccess|mixed
     */
    private mixed $needGeneratedPin = false;

    public function handle(Employee $employee): string
    {
        return $this->setPin($employee);
    }

    public function setPin($employee, $try = 1): bool|string
    {
        try {


            list($letters, $numbers) = $this->pinCharacterSet();

            $pin = $employee->organisation_id.':'.
                $letters[array_rand($letters)].$letters[array_rand($letters)].$letters[array_rand($letters)].
                $numbers[array_rand($numbers)].$numbers[array_rand($numbers)].$numbers[array_rand($numbers)];

            if ($this->needGeneratedPin) {
                return $pin;
            }

            if ($this->updateQuietly) {
                $employee->updateQuietly(['pin' => $pin]);
            } else {
                $employee->update(['pin' => $pin]);
            }


            return true;
        } catch (Exception) {
            if ($try < 100) {
                $this->setPin($employee, $try + 1);
            } else {
                return false;
            }
        }

        return false;
    }


    public function pinCharacterSet(): array
    {
        $letters = array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'X', 'Y', 'Z');
        $numbers = array('0', '1', '2', '3', '4', '5', '6', '7', '8', '9');


        return [
            $letters,$numbers
        ];


    }

    public string $commandSignature = 'employee:set_pin {employee}';


    public function action(Employee $employee, $updateQuietly = false, $needGeneratedPin = false): bool|string
    {
        $this->updateQuietly = $updateQuietly;
        $this->needGeneratedPin = $needGeneratedPin;

        return $this->setPin($employee);
    }

    public function asCommand(Command $command): int
    {
        try {
            $employee = Employee::where('slug', $command->argument('employee'))->firstOrFail();
        } catch (Exception) {
            $command->error('Employee not found');

            return 1;
        }

        if ($this->handle($employee)) {
            $command->info('Pin set for '.$employee->alias.' pin: '.$employee->pin);

            return 0;
        } else {
            $command->error('Pin could not be set for '.$employee->alias);

            return 1;
        }
    }

}
