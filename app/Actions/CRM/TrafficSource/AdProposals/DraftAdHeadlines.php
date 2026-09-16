<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\CRM\TrafficSource\AdProposals;

use App\Actions\Helpers\AI\AskToAi;
use App\Models\Catalogue\Shop;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Writes extra headlines for an ad that has too few, in the voice of the ones already there.
 *
 * This is the only place in the feature where a model produces something a customer will read, and
 * the only place its output is not merely explanation. Which is why nothing it writes is applied
 * without a person seeing it, and why every line is checked here before it is offered: length against
 * Google's thirty characters, and repetition against the ad's existing copy and against the rest of
 * the draft. Google rejects an ad whose assets repeat, comparing them without regard to case, and one
 * bad line would otherwise fail the whole write.
 *
 * A draft that comes back empty is not a failure. The proposal is simply not raised, and a marketer
 * is spared a card offering nothing.
 */
class DraftAdHeadlines
{
    use AsAction;

    private const int LENGTH = 30;

    private const int ALLOWANCE = 15;

    /**
     * @param array<int, array> $candidates
     * @return array<int, array>
     */
    public function handle(Shop $shop, array $candidates, string $sells): array
    {
        $drafted = [];

        foreach ($candidates as $candidate) {
            $existing = Arr::get($candidate, 'evidence.headlines', []);
            $wanted   = self::ALLOWANCE - count($existing);

            try {
                $lines = $this->clean(
                    $this->parse(AskToAi::run($this->prompt($shop, $sells, $candidate, $wanted))),
                    $existing,
                    $wanted
                );
            } catch (Throwable $exception) {
                Log::warning('Ad headline drafting failed', ['shop' => $shop->slug, 'error' => $exception->getMessage()]);

                continue;
            }

            if ($lines === []) {
                continue;
            }

            $candidate['payload']['headlines'] = $lines;
            $candidate['rationale']            = __('Adds :count more headlines for Google to test, keeping the ones already running.', ['count' => count($lines)]);

            $drafted[] = $candidate;
        }

        return $drafted;
    }

    private function prompt(Shop $shop, string $sells, array $candidate, int $wanted): string
    {
        $existing     = implode("\n", array_map(fn ($h) => '- '.$h, Arr::get($candidate, 'evidence.headlines', [])));
        $descriptions = implode("\n", array_map(fn ($d) => '- '.$d, Arr::get($candidate, 'evidence.descriptions', [])));
        $adGroup      = Arr::get($candidate, 'evidence.ad_group') ?: Arr::get($candidate, 'evidence.campaign');

        return <<<PROMPT
        You write Google Ads headlines for "{$shop->name}", a business that sells: {$sells}.

        This ad sits in the ad group "{$adGroup}". Its current headlines are:
        {$existing}

        Its descriptions are:
        {$descriptions}

        Write up to {$wanted} more headlines for the same ad.

        Rules:
        - Each headline is at most 30 characters. Count them. A longer one is discarded.
        - Each must say something the existing ones do not. Do not reword them.
        - Match the voice of the existing headlines.
        - Each must stand on its own: Google shows them in any combination, so none may depend on
          another to make sense.
        - No claim you cannot support from what is above. No prices, no discounts, no delivery times,
          no guarantees, no superlatives you have not been told are true.
        - Reply with a JSON array of strings and nothing else: ["...", "..."]
        PROMPT;
    }

    /**
     * @return array<int, string>
     */
    private function parse(?string $answer): array
    {
        if (blank($answer) || !preg_match('/\[.*]/s', $answer, $matches)) {
            return [];
        }

        $decoded = json_decode($matches[0], true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<int, mixed> $lines
     * @param array<int, string> $existing
     * @return array<int, string>
     */
    private function clean(array $lines, array $existing, int $wanted): array
    {
        $seen = collect($existing)->map(fn ($text) => mb_strtolower(trim((string) $text)))->flip();
        $kept = [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            $key  = mb_strtolower($line);

            /* Length is checked here rather than trusted: models overshoot thirty characters
               constantly, and a headline trimmed mid-word reads worse than one left out. */
            if ($line === '' || mb_strlen($line) > self::LENGTH || $seen->has($key)) {
                continue;
            }

            $kept[] = $line;
            $seen->put($key, true);

            if (count($kept) >= $wanted) {
                break;
            }
        }

        return $kept;
    }
}
