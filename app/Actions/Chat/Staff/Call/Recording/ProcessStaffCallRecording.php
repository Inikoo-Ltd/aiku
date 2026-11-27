<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Call\Recording;

use App\Actions\Chat\Staff\SendStaffBotMessage;
use App\Actions\Helpers\AI\AskToAi;
use App\Models\Chat\StaffCallRecording;
use App\Models\Chat\StaffCallRecordingTrack;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;
use Throwable;

class ProcessStaffCallRecording
{
    use AsAction;

    public string $jobQueue = 'default';

    public int $jobTimeout = 1800;

    /** Each speaker's file is cut into pieces this long so every piece stays far below OpenAI's 25 MB upload limit. */
    public const int CHUNK_SECONDS = 1200;

    /** Kept when processing failed, so it can be retried; the purge removes it afterwards like any kept audio. */
    public const int FAILED_AUDIO_DAYS = 30;

    private const int SUMMARY_INPUT_CHARS = 300_000;

    private const int SUMMARY_TIMEOUT_SECONDS = 180;

    /** A job still processing after this long was killed; the sweep sets it to run again, keeping any saved transcript. */
    public const int STALE_PROCESSING_MINUTES = 90;

    /**
     * Queues the work once every track of a stopped recording has reported its file, so a call is never summarised from
     * half its speakers. The sweep calls it again with $force after the grace period for egresses that never reported.
     */
    public function whenTracksAreIn(StaffCallRecording $recording, bool $force = false): void
    {
        $waiting = $recording->tracks()->whereIn('status', [StaffCallRecordingTrack::STATUS_STARTING, StaffCallRecordingTrack::STATUS_ACTIVE, StaffCallRecordingTrack::STATUS_STOPPING])->exists();
        if ($recording->status === StaffCallRecording::STATUS_STOPPED && ($force || !$waiting)) {
            self::dispatch($recording);
        }
    }

    /**
     * Transcribes each speaker on their own (so who said what is known, never guessed), merges the pieces by time,
     * writes the summary, then deletes the audio unless it was chosen to be kept, and tells everyone who was in the
     * call. Runs once: the claim from stopped to processing is atomic.
     */
    public function handle(StaffCallRecording $recording): void
    {
        $claimed = StaffCallRecording::whereKey($recording->id)
            ->where('status', StaffCallRecording::STATUS_STOPPED)
            ->update(['status' => StaffCallRecording::STATUS_PROCESSING, 'attempts' => DB::raw('attempts + 1'), 'updated_at' => now()]);
        if (!$claimed) {
            return;
        }
        $recording->refresh();

        try {
            if ($recording->transcript === null) {
                $transcript = $this->transcribe($recording);
                if ($transcript === []) {
                    throw new RuntimeException('Nothing was said in the recording.');
                }
                $recording->update(['transcript' => $transcript]);
            }

            $recording->update([
                'summary'         => $this->summarise($recording->transcript),
                'status'          => StaffCallRecording::STATUS_DONE,
                'processed_at'    => now(),
                'error'           => null,
                'delete_audio_at' => $recording->keep_audio_months > 0 ? now()->addMonths($recording->keep_audio_months) : now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
            self::markFailed($recording, $exception->getMessage());

            return;
        }

        $this->tellEveryoneRecorded($recording, __('Summary of your call'), $recording->summary['summary'] ?? '');

        if ($recording->keep_audio_months === 0) {
            self::deleteAudio($recording);
        }
    }

    /**
     * The audio is kept for FAILED_AUDIO_DAYS so it can be retried, and the people in the call are told no summary is
     * coming rather than left waiting.
     */
    public static function markFailed(StaffCallRecording $recording, string $error): void
    {
        $recording->update([
            'status'          => StaffCallRecording::STATUS_FAILED,
            'error'           => Str::limit($error, 250),
            'delete_audio_at' => now()->addDays(max(self::FAILED_AUDIO_DAYS, $recording->keep_audio_months * 31)),
        ]);

        self::make()->tellEveryoneRecorded($recording, __('No summary for your call'), __('The recording of your call could not be transcribed, so there is no summary this time.'));
    }

    public static function deleteAudio(StaffCallRecording $recording): void
    {
        Storage::disk(StaffCallRecording::DISK)->deleteDirectory($recording->audioDirectory());
        $recording->update(['audio_deleted_at' => now(), 'delete_audio_at' => null]);
    }

    /**
     * @return array<int, array{at: int, speaker: string, text: string}>
     */
    private function transcribe(StaffCallRecording $recording): array
    {
        $lines = [];
        $tracks = $recording->tracks()->whereIn('status', [StaffCallRecordingTrack::STATUS_ENDED, StaffCallRecordingTrack::STATUS_STOPPING, StaffCallRecordingTrack::STATUS_ACTIVE])->get();
        foreach ($tracks as $track) {
            if (!Storage::disk(StaffCallRecording::DISK)->exists($track->path)) {
                continue;
            }
            $offset = max(0, (int) $recording->started_at->diffInSeconds($track->started_at, false));
            foreach ($this->pieces($track) as $index => $piece) {
                foreach ($this->whisper($piece) as $segment) {
                    $text = trim((string) ($segment['text'] ?? ''));
                    if ($text !== '' && !$this->isSilence($segment)) {
                        $lines[] = ['at' => $offset + $index * self::CHUNK_SECONDS + (int) ($segment['start'] ?? 0), 'speaker' => $track->speaker, 'text' => $text];
                    }
                }
            }
        }

        usort($lines, fn (array $a, array $b) => $a['at'] <=> $b['at']);

        return $lines;
    }

    /**
     * The speaker's file as mono 16 kHz Opus pieces of CHUNK_SECONDS: a 20 minute piece is about 4 MB.
     *
     * @return array<int, string> contents of each piece, in order
     */
    private function pieces(StaffCallRecordingTrack $track): array
    {
        $directory = storage_path('app/tmp/call-recording-'.Str::random(16));
        File::ensureDirectoryExists($directory);

        try {
            file_put_contents($directory.'/source.ogg', Storage::disk(StaffCallRecording::DISK)->get($track->path));

            $result = Process::timeout(600)->path($directory)->run([
                'ffmpeg', '-nostdin', '-loglevel', 'error', '-i', 'source.ogg',
                '-af', 'aresample=async=1:first_pts=0', '-ac', '1', '-ar', '16000', '-c:a', 'libopus', '-b:a', '24k',
                '-f', 'segment', '-segment_time', (string) self::CHUNK_SECONDS, '-reset_timestamps', '1', 'piece-%03d.ogg',
            ]);
            if ($result->failed()) {
                throw new RuntimeException('ffmpeg could not read a recorded track: '.Str::limit($result->errorOutput(), 150));
            }

            $paths = glob($directory.'/piece-*.ogg') ?: [];
            sort($paths);

            return array_map(fn (string $path) => file_get_contents($path), $paths);
        } finally {
            File::deleteDirectory($directory);
        }
    }

    /**
     * Each speaker's track is mostly silence while the others talk, and Whisper fills silence with invented words. Its
     * own test for that: likely no speech and a low-confidence guess.
     *
     * @param array{no_speech_prob?: float, avg_logprob?: float} $segment
     */
    private function isSilence(array $segment): bool
    {
        return ($segment['no_speech_prob'] ?? 0) > 0.6 && ($segment['avg_logprob'] ?? 0) < -1;
    }

    /**
     * @return array<int, array{start?: float, text?: string, no_speech_prob?: float, avg_logprob?: float}>
     */
    private function whisper(string $audio): array
    {
        $response = Http::timeout(300)
            ->withToken((string) config('services.openai.api_key'))
            ->attach('file', $audio, 'piece.ogg')
            ->post('https://api.openai.com/v1/audio/transcriptions', ['model' => 'whisper-1', 'response_format' => 'verbose_json']);

        if (!$response->successful()) {
            throw new RuntimeException('Transcription failed with HTTP '.$response->status());
        }

        return $response->json('segments') ?? [];
    }

    /**
     * @param array<int, array{at: int, speaker: string, text: string}> $transcript
     * @return array{summary: string, decisions: array<int, string>, action_items: array<int, string>}
     */
    private function summarise(array $transcript): array
    {
        $text = mb_substr(implode("\n", array_map(fn (array $line) => sprintf('[%02d:%02d] %s: %s', intdiv($line['at'], 60), $line['at'] % 60, $line['speaker'], $line['text']), $transcript)), 0, self::SUMMARY_INPUT_CHARS);

        $prompt = "Below is the transcript of a work call between colleagues, one line per speaker turn.\n"
            ."Write in the language most of the call was in. Reply with JSON only, no other text:\n"
            .'{"summary": "3 to 6 sentences: what the call was about and what came out of it", "decisions": ["each decision taken"], "action_items": ["Name: what they will do, and by when if said"]}'."\n"
            ."Use empty lists when there were none. Never invent names, dates or numbers that were not said.\n\nTranscript:\n".$text;

        $answer  = (string) AskToAi::run($prompt, (string) config('chat.call_summary_model'), [], self::SUMMARY_TIMEOUT_SECONDS);
        $decoded = json_decode((string) Str::of($answer)->after('{')->beforeLast('}')->prepend('{')->append('}'), true);
        if (!is_array($decoded) || !filled($decoded['summary'] ?? null)) {
            throw new RuntimeException('The summary could not be written.');
        }

        return [
            'summary'      => (string) $decoded['summary'],
            'decisions'    => array_values(array_filter(array_map('strval', (array) ($decoded['decisions'] ?? [])))),
            'action_items' => array_values(array_filter(array_map('strval', (array) ($decoded['action_items'] ?? [])))),
        ];
    }

    /**
     * Only the staff who were in the call while it was recorded, never someone who left before Record or came after Stop.
     */
    public function tellEveryoneRecorded(StaffCallRecording $recording, string $title, string $body): void
    {
        $url = route('grp.chat.staff.calls.recordings.show', $recording->ulid);

        $recording->peopleRecorded()->where('users.status', true)->get()->each(
            fn (User $user) => SendStaffBotMessage::run($user, $title, $body, $url)
        );
    }
}
