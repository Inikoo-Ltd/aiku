<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 20 May 2024 16:19:39 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\HumanResources\Employee;

use App\Actions\OrgAction;
use App\Models\HumanResources\Employee;
use App\Models\SysAdmin\Organisation;
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

    public function handle(Employee $employee): bool|string
    {
        return $this->setPin($employee);
    }

    public function setPin($employee, $try = 1): bool|string
    {
        try {
            $pin = $this->generateUnusedPin($employee);

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
                return $this->setPin($employee, $try + 1);
            }

            return false;
        }
    }

    /**
     * The pin is the credential the clocking kiosks authenticate on. Machines are shared across
     * the group and ResolvesEmployeeByCode matches a typed code against the prefixed pin of every
     * organisation in it, so uniqueness has to hold group-wide: two people in different
     * organisations sharing a bare code would let one of them clock the other in.
     *
     * @throws Exception
     */
    private function generateUnusedPin(Employee $employee): string
    {
        $organisationIds = Organisation::where('group_id', $employee->group_id)->pluck('id');

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $code = $this->generatePinCode();

            $taken = Employee::whereIn('pin', $organisationIds->map(fn ($organisationId) => $organisationId.':'.$code))
                ->where('id', '!=', $employee->id)
                ->exists();

            if (!$taken) {
                return $employee->organisation_id.':'.$code;
            }
        }

        throw new Exception('Unable to generate an unused pin for organisation '.$employee->organisation_id);
    }

    public function generatePinCode(): string
    {
        list($letters, $numbers) = $this->pinCharacterSet();

        return $letters[array_rand($letters)].$letters[array_rand($letters)].$letters[array_rand($letters)].
            $numbers[array_rand($numbers)].$numbers[array_rand($numbers)].$numbers[array_rand($numbers)];
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
