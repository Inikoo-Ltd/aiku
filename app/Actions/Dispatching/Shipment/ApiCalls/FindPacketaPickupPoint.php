<?php

namespace App\Actions\Dispatching\Shipment\ApiCalls;

use App\Models\Dispatching\Shipper;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * HELP-4946. Dropshippers write the Packeta point (Z-BOX or shop) their buyer chose in the notes or
 * the address, as a number ("[31693]", "Výdajné miesto: 32760", "Z-BOX/ID 34593") or, mostly in
 * Czechia, as its name and street. A wrong point is worse than none, so a point is only taken when
 * the text names Packeta, a number is backed by the point's town or street, and a name is unique
 * and written in the notes (a home address can be a shop that is also a Packeta point).
 * The point list (16k points, two feeds of minutes each) is downloaded daily, never while booking.
 * Whether a Z-BOX is full changes by the hour, so that is left to Packeta when booking.
 */
class FindPacketaPickupPoint
{
    use AsAction;

    public const string CACHE_KEY = 'packeta-pickup-points';

    public string $commandSignature = 'packeta:refresh_pickup_points';

    public string $commandDescription = 'Download the Packeta pickup points used to read the point the customer chose from the order notes (HELP-4946)';

    private const string PACKETA_TEXT_PATTERN = '/z-?box|z[aá]silkovn|z[aá]sielkov|packeta|v[ýy]dejn[íi]\s+m[íi]st|v[ýy]dajn[ée]\s+miest/iu';

    private const string POINT_REQUEST_PATTERN = '/z-?box|v[ýy]dejn[íi]\s+m[íi]st|v[ýy]dajn[ée]\s+miest/iu';

    /**
     * Group 1 and 2 are point numbers written next to a Packeta word, group 3 next to a bare "ID".
     * A number running into a second digit group ("155 00") is a postcode, not a point.
     */
    private const string POINT_NUMBER_PATTERN = '/\[\s*(\d{3,6})\s*]|(?:z-?box|packeta|z[aá]silkovna|z[aá]sielkov\w*|miesto|m[íi]sto)(?:[\s:\/#-]*id)?[\s:\/#-]{0,3}(\d{3,6})(?!\s?\d)|\bid[\s:]{0,2}(\d{3,6})(?!\s?\d)/iu';

    /**
     * @param  array<int, string|null>  $addressLines
     * @param  array<int, string|null>  $notes
     *
     * @return array{mentioned: bool, point: array<string, mixed>|null, candidates: array<int, array<string, mixed>>, unavailable: bool, asks_for_point: bool}
     */
    public function handle(?string $countryCode, array $addressLines, array $notes): array
    {
        $text   = implode("\n", array_filter([...$notes, ...$addressLines]));
        $result = ['mentioned' => (bool) preg_match(self::PACKETA_TEXT_PATTERN, $text), 'point' => null, 'candidates' => [], 'unavailable' => false, 'asks_for_point' => (bool) preg_match(self::POINT_REQUEST_PATTERN, $text)];
        if (!$result['mentioned']) {
            return $result;
        }

        $points = $this->getPoints();
        if (!$points) {
            return [...$result, 'unavailable' => true];
        }

        $country        = strtolower((string) $countryCode);
        $normalisedText = ' '.$this->normalise($text).' ';
        $notesText      = ' '.$this->normalise(implode("\n", array_filter($notes))).' ';

        foreach ($notes as $note) {
            if (preg_match('/^\s*(?:z-?box|packeta|z[aá]silkovna)\W{0,4}(\d{3,6})\s*$/iu', (string) $note, $writtenByStaff) && ($points[$writtenByStaff[1]]['country'] ?? null) === $country) {
                return [...$result, 'point' => $points[$writtenByStaff[1]]];
            }
        }

        preg_match_all(self::POINT_NUMBER_PATTERN, $text, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            $nextToPacketaWord = ($match[1] ?? '') !== '' || ($match[2] ?? '') !== '';
            $point             = $points[($match[1] ?? '') ?: (($match[2] ?? '') ?: ($match[3] ?? ''))] ?? null;
            if ($point && $point['country'] === $country
                && ($this->contains($normalisedText, $point['search_street']) || ($nextToPacketaWord && $this->contains($notesText, $point['search_city'])))) {
                return [...$result, 'point' => $point];
            }
        }

        $candidates = collect($points)
            ->filter(fn (array $point) => $point['country'] === $country
                && $this->contains($notesText, $point['search_street'])
                && $this->contains($notesText, $point['search_city']));

        $namedInFull = $candidates->filter(fn (array $point) => $this->contains($notesText, $point['search_name']));
        if ($namedInFull->isNotEmpty()) {
            return [...$result, 'point' => $namedInFull->sortByDesc(fn (array $point) => mb_strlen($point['name']))->first()];
        }

        $mentionsZBox = (bool) preg_match('/z-?box/iu', $text);
        $candidates   = $candidates
            ->filter(fn (array $point) => preg_match('/\d/', $point['street']) && ($point['type'] === 'zbox') === $mentionsZBox)
            ->values();

        if ($candidates->count() === 1) {
            return [...$result, 'point' => $candidates->first()];
        }

        return [...$result, 'candidates' => $candidates->take(5)->all()];
    }

    /**
     * @return array<string, array{id: string, type: string, name: string, street: string, city: string, zip: string, country: string, max_weight: float|null, search_name: string, search_street: string, search_city: string}>
     */
    public function getPoints(): array
    {
        return Cache::get(self::CACHE_KEY, []);
    }

    public function refresh(Shipper $shipper): int
    {
        $apiKey = Arr::get((new CallApiPacketaShipping())->getAccessToken($shipper), 'api_key');
        $points = [];
        foreach (['box', 'branch'] as $feed) {
            $feedPoints = Http::timeout(600)->get("https://pickup-point.api.packeta.com/v5/$apiKey/$feed/json", ['lang' => 'en'])->throw()->json();
            foreach ((array) $feedPoints as $point) {
                $points[(string) $point['id']] = [
                    'id'         => (string) $point['id'],
                    'type'       => $feed === 'box' ? 'zbox' : 'branch',
                    'name'       => (string) Arr::get($point, 'name'),
                    'street'     => (string) Arr::get($point, 'street'),
                    'city'       => (string) Arr::get($point, 'city'),
                    'zip'        => (string) Arr::get($point, 'zip'),
                    'country'    => strtolower((string) Arr::get($point, 'country')),
                    'max_weight' => is_numeric(Arr::get($point, 'maxWeight')) ? (float) Arr::get($point, 'maxWeight') : null,
                ];
                foreach (['name', 'street', 'city'] as $field) {
                    $points[(string) $point['id']]['search_'.$field] = $this->normalise($points[(string) $point['id']][$field]);
                }
            }
            unset($feedPoints);
        }

        if (count($points) < 10000) {
            throw new \RuntimeException('Packeta sent only '.count($points).' pickup points, the list kept is unchanged');
        }

        Cache::put(self::CACHE_KEY, $points, now()->addDays(14));

        return count($points);
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();
        ini_set('memory_limit', '2G');

        $shipper = Shipper::where('api_shipper', 'packeta-sk')->first();
        if (!$shipper) {
            $command->error('No Packeta shipper');

            return 1;
        }

        $command->info($this->refresh($shipper).' Packeta pickup points stored');

        return 0;
    }

    private function contains(string $normalisedText, string $value): bool
    {
        return $value !== '' && str_contains($normalisedText, ' '.$value.' ');
    }

    public function normalise(string $text): string
    {
        return trim(preg_replace('/[^a-z0-9]+/', ' ', Str::lower(Str::ascii($text))));
    }
}
