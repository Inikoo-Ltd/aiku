<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\HumanResources\Holiday;

use App\Enums\HumanResources\Holiday\HolidayTypeEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class GeneratePublicHolidays
{
    use AsAction;

    public string $commandSignature = 'hr:generate_public_holidays {organisation : slug} {country : gb, sk or es} {year} {--dry-run}';

    public string $commandDescription = 'Write the national public holidays of a country into an organisation calendar';

    /**
     * @return array<int, array{label: string, date: string}>
     */
    public function handle(Organisation $organisation, string $country, int $year, bool $dryRun = false): array
    {
        $holidays = $this->holidaysFor($country, $year);

        foreach ($holidays as $holiday) {
            if ($dryRun) {
                continue;
            }

            $exists = $organisation->holidays()
                ->where('type', HolidayTypeEnum::PUBLIC->value)
                ->where('year', $year)
                ->whereDate('from', $holiday['date'])
                ->exists();

            if ($exists) {
                continue;
            }

            $organisation->holidays()->create([
                'group_id' => $organisation->group_id,
                'type'     => HolidayTypeEnum::PUBLIC->value,
                'year'     => $year,
                'label'    => $holiday['label'],
                'from'     => $holiday['date'],
                'to'       => $holiday['date'],
                'data'     => ['is_recurring' => false, 'source' => 'generated:'.$country],
            ]);
        }

        return $holidays;
    }

    /**
     * @return array<int, array{label: string, date: string}>
     */
    public function holidaysFor(string $country, int $year): array
    {
        $easter = $this->easterSunday($year);

        return match (strtolower($country)) {
            'gb' => $this->substituteWeekends([
                ['label' => 'New Year\'s Day', 'date' => "$year-01-01"],
                ['label' => 'Good Friday', 'date' => $easter->copy()->subDays(2)->toDateString(), 'fixed' => true],
                ['label' => 'Easter Monday', 'date' => $easter->copy()->addDay()->toDateString(), 'fixed' => true],
                ['label' => 'Early May bank holiday', 'date' => $this->firstMonday($year, 5), 'fixed' => true],
                ['label' => 'Spring bank holiday', 'date' => $this->lastMonday($year, 5), 'fixed' => true],
                ['label' => 'Summer bank holiday', 'date' => $this->lastMonday($year, 8), 'fixed' => true],
                ['label' => 'Christmas Day', 'date' => "$year-12-25"],
                ['label' => 'Boxing Day', 'date' => "$year-12-26"],
            ]),
            'sk' => [
                ['label' => 'Deň vzniku Slovenskej republiky', 'date' => "$year-01-01"],
                ['label' => 'Zjavenie Pána', 'date' => "$year-01-06"],
                ['label' => 'Veľký piatok', 'date' => $easter->copy()->subDays(2)->toDateString()],
                ['label' => 'Veľkonočný pondelok', 'date' => $easter->copy()->addDay()->toDateString()],
                ['label' => 'Sviatok práce', 'date' => "$year-05-01"],
                ['label' => 'Deň víťazstva nad fašizmom', 'date' => "$year-05-08"],
                ['label' => 'Sviatok svätého Cyrila a Metoda', 'date' => "$year-07-05"],
                ['label' => 'Výročie SNP', 'date' => "$year-08-29"],
                ['label' => 'Deň Ústavy Slovenskej republiky', 'date' => "$year-09-01"],
                ['label' => 'Sedembolestná Panna Mária', 'date' => "$year-09-15"],
                ['label' => 'Sviatok všetkých svätých', 'date' => "$year-11-01"],
                ['label' => 'Deň boja za slobodu a demokraciu', 'date' => "$year-11-17"],
                ['label' => 'Štedrý deň', 'date' => "$year-12-24"],
                ['label' => 'Prvý sviatok vianočný', 'date' => "$year-12-25"],
                ['label' => 'Druhý sviatok vianočný', 'date' => "$year-12-26"],
            ],
            'es' => [
                ['label' => 'Año Nuevo', 'date' => "$year-01-01"],
                ['label' => 'Epifanía del Señor', 'date' => "$year-01-06"],
                ['label' => 'Viernes Santo', 'date' => $easter->copy()->subDays(2)->toDateString()],
                ['label' => 'Fiesta del Trabajo', 'date' => "$year-05-01"],
                ['label' => 'Asunción de la Virgen', 'date' => "$year-08-15"],
                ['label' => 'Fiesta Nacional de España', 'date' => "$year-10-12"],
                ['label' => 'Todos los Santos', 'date' => "$year-11-01"],
                ['label' => 'Día de la Constitución', 'date' => "$year-12-06"],
                ['label' => 'Inmaculada Concepción', 'date' => "$year-12-08"],
                ['label' => 'Natividad del Señor', 'date' => "$year-12-25"],
            ],
            default => throw new \InvalidArgumentException("No public holiday list for country [$country]"),
        };
    }

    /**
     * England and Wales move a bank holiday landing on a weekend to the next working day.
     *
     * @param  array<int, array{label: string, date: string, fixed?: bool}>  $holidays
     * @return array<int, array{label: string, date: string}>
     */
    private function substituteWeekends(array $holidays): array
    {
        $taken = [];

        foreach ($holidays as $index => $holiday) {
            $date = Carbon::parse($holiday['date']);

            if (!($holiday['fixed'] ?? false)) {
                while ($date->isWeekend() || in_array($date->toDateString(), $taken, true)) {
                    $date->addDay();
                }
            }

            $taken[]                    = $date->toDateString();
            $holidays[$index]['date']   = $date->toDateString();
            unset($holidays[$index]['fixed']);
        }

        return array_values($holidays);
    }

    private function firstMonday(int $year, int $month): string
    {
        return Carbon::create($year, $month, 1)->startOfMonth()
            ->when(true, fn ($date) => $date->dayOfWeekIso === 1 ? $date : $date->next(Carbon::MONDAY))
            ->toDateString();
    }

    private function lastMonday(int $year, int $month): string
    {
        $date = Carbon::create($year, $month, 1)->endOfMonth();

        return ($date->dayOfWeekIso === 1 ? $date : $date->previous(Carbon::MONDAY))->toDateString();
    }

    private function easterSunday(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);

        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day   = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::create($year, $month, $day);
    }

    public function asCommand(\Illuminate\Console\Command $command): int
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->first();

        if (!$organisation) {
            $command->error('Organisation not found');

            return 1;
        }

        $holidays = $this->handle(
            $organisation,
            $command->argument('country'),
            (int) $command->argument('year'),
            (bool) $command->option('dry-run')
        );

        $command->table(
            ['Date', 'Day', 'Holiday'],
            collect($holidays)->map(fn ($holiday) => [
                $holiday['date'],
                Carbon::parse($holiday['date'])->format('D'),
                $holiday['label'],
            ])->all()
        );

        $command->info(($command->option('dry-run') ? 'Dry run, nothing written: ' : 'Written: ').count($holidays).' holidays');

        return 0;
    }
}
