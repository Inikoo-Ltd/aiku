<?php

namespace App\Actions\HumanResources\ClockingMachine;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithHumanResourcesEditAuthorisation;
use App\Models\HumanResources\ClockingMachine;
use App\Models\HumanResources\ClockingMachineQRCode;
use Lorisleiva\Actions\ActionRequest;
use Illuminate\Support\Str;

class StoreClockingMachineQRCode extends OrgAction
{
    use WithHumanResourcesEditAuthorisation;

    public function handle(ClockingMachine $clockingMachine, array $modelData): ClockingMachineQRCode
    {
        if (blank($modelData['label'] ?? null)) {
            $modelData['label'] = static::generateLabel();
        }

        do {
            $hash = static::generateHash();
        } while (ClockingMachineQRCode::where('hash', $hash)->exists());

        $modelData['hash'] = $hash;

        /** @var ClockingMachineQRCode $clockingMachineQrCode */
        $clockingMachineQrCode = $clockingMachine->clockingMachineQrCodes()->create($modelData);
        return $clockingMachineQrCode;
    }

    protected static function generateHash(): string
    {
        return hash('crc32b', Str::random(32));
    }

    protected static function generateLabel(): string
    {
        $adjectives = [
            'agile', 'amber', 'ancient', 'bold', 'brave', 'bright', 'calm', 'clever', 'cobalt', 'cosmic',
            'crimson', 'curious', 'daring', 'dawn', 'eager', 'electric', 'ember', 'fierce', 'gentle', 'golden',
            'grand', 'hidden', 'indigo', 'jade', 'jolly', 'keen', 'kind', 'lucky', 'lunar', 'mellow',
            'mighty', 'misty', 'noble', 'northern', 'oceanic', 'peaceful', 'quiet', 'rapid', 'rosy', 'royal',
            'ruby', 'serene', 'silver', 'solar', 'stellar', 'swift', 'timeless', 'uncanny', 'velvet', 'wild',
        ];
        $nouns = [
            'arch', 'bay', 'bridge', 'brook', 'canyon', 'castle', 'cedar', 'coast', 'comet', 'creek',
            'dawn', 'delta', 'falcon', 'field', 'forest', 'garden', 'glacier', 'grove', 'harbor', 'hill',
            'island', 'lake', 'lighthouse', 'maple', 'meadow', 'moon', 'mountain', 'oasis', 'ocean', 'orchard',
            'peak', 'pine', 'prairie', 'raven', 'reef', 'ridge', 'river', 'shore', 'sky', 'spring',
            'star', 'stone', 'summit', 'thunder', 'valley', 'waterfall', 'wave', 'willow', 'wind', 'woodland',
        ];

        return $adjectives[array_rand($adjectives)].'-'.$nouns[array_rand($nouns)];
    }

    public function rules(): array
    {
        return [
            'label'      => ['sometimes','nullable', 'string', 'max:255'],
            'expires_at' => ['required', 'date', 'after:now'],
        ];
    }

    public function asController(ClockingMachine $clockingMachine, ActionRequest $request): ClockingMachineQRCode
    {
        $this->initialisation($clockingMachine->organisation, $request);

        return $this->handle($clockingMachine, $this->validatedData);
    }
}
