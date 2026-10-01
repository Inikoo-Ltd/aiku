<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 23:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Helpers\Translations\DetectLanguageWithJev;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Laravel\Nightwatch\Facades\Nightwatch;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Writes suggestions again for past chats, from what the customer wrote, what we said and the
 * facts the live suggestion had, and has Jev say how close each comes to what the agent really
 * sent, beside the live suggestion's own closeness. Changes to the writers are tried on real
 * chats before staff see them.
 */
class ReplayChatSuggestions
{
    use AsAction;

    public string $commandSignature = 'chat:replay-suggestions {file : JSON export of past suggestions with the agent reply} {output : JSON lines written here} {--part=1 : This part} {--of=1 : Of this many parts} {--model= : First writer, instead of the live one} {--live : Score the live suggestion too} {--examples : Look up staff examples now, from replies before --before, instead of the exported ones} {--before= : Only examples replied before this time}';

    /**
     * @param  array<string, mixed>  $case
     * @return array<string, mixed>
     */
    public function handle(array $case, ?string $model = null, bool $scoreLive = false, ?Carbon $examplesBefore = null): array
    {
        if ($examplesBefore) {
            $case['examples'] = JudgeChatSuggestion::staffExamples(Shop::findOrFail($case['shop_id']), $case['customer_wrote'], before: $examplesBefore);
        }

        $judge    = JudgeChatSuggestion::make();
        $staffWords = trim((string) preg_replace('~https?://\S+~', '', $case['staff_reply']));
        $language   = DetectLanguageWithJev::run(mb_strlen($staffWords) >= 10 ? $staffWords : $case['customer_wrote']);
        $result   = $language
            ? DraftChatReply::make()->composeSuggestion($case['customer_wrote'], $case['we_said'], $case['facts'] ?: [], $language, $model ?: ($case['model'] ?: config('chat.suggestion_models')[0]), $case['examples'] ?? [])
            : null;

        return [
            'id'          => $case['id'],
            'reply'       => $result['reply'] ?? null,
            'model'       => $result['model'] ?? null,
            'judge'       => $result['judge'] ?? null,
            'has_gap'     => $result && preg_match(DraftChatReply::GAP, $result['reply']) === 1,
            'match'       => $result ? $judge->nearMatch($case['customer_wrote'], $case['staff_reply'], $result['reply']) : null,
            'live_match'  => $scoreLive ? $judge->nearMatch($case['customer_wrote'], $case['staff_reply'], $case['draft']) : null,
        ];
    }

    public function asCommand(Command $command): int
    {
        Nightwatch::dontSample();

        $cases = json_decode((string) file_get_contents($command->argument('file')), true);
        $part  = (int) $command->option('part');
        $of    = max(1, (int) $command->option('of'));
        $out   = fopen($command->argument('output'), 'a');

        foreach ($cases as $i => $case) {
            if ($i % $of !== $part - 1) {
                continue;
            }

            fwrite($out, json_encode($this->handle($case, $command->option('model'), (bool) $command->option('live'), $command->option('examples') ? Carbon::parse($command->option('before') ?? now()) : null), JSON_UNESCAPED_UNICODE)."\n");
        }

        fclose($out);

        return 0;
    }
}
