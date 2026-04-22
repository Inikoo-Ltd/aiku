<?php

namespace App\Actions\CRM\ChatSession;

use App\Actions\Helpers\AI\AskToAi;
use App\Models\CRM\Livechat\ChatSession;
use Lorisleiva\Actions\Concerns\AsAction;

class SummarizeChatSession
{
    use AsAction;

    public string $jobQueue = 'analytics';
    public int $jobTimeout = 300;
    public int $jobTries = 1;

    public function handle(ChatSession $chatSession): ChatSession
    {
        $messages = $chatSession->messages()
            ->orderBy('created_at')
            ->get()
            ->map(function ($msg) {
                $sender = $msg->sender_type->value;
                $text = (string) ($msg->original_text ?? $msg->message_text ?? '');
                return "{$sender}: {$text}";
            })
            ->filter(fn (string $line) => trim($line) !== '')
            ->join("\n");

        $messages = mb_substr($messages, 0, 6000);

        if (empty($messages)) {
            return $chatSession;
        }

        // 2. Prompt Engineering
        $prompt = <<<EOT
        You are a helpful CRM assistant. Summarize the following customer support chat session.
        Focus on:
        1. The core problem/issue reported.
        2. The resolution or current status.
        3. Any follow-up actions required.

        Chat History:
        {$messages}

        Output JSON format only:
        {
            "summary": "Short paragraph summary",
            "key_points": ["point 1", "point 2"],
            "status": "resolved/pending",
            "sentiment": "positive/neutral/negative"
        }
        EOT;

        // 3. Call AI
        $aiResponse = AskToAi::run($prompt);
        if (!is_string($aiResponse) || trim($aiResponse) === '') {
            return $chatSession;
        }

        // 4. Parse & Save
        $summaryData = json_decode($aiResponse, true);

        if (is_array($summaryData)) {
            $metadata = $chatSession->metadata ?? [];
            $metadata['ai_summary'] = $summaryData;

            $chatSession->update([
                'metadata' => $metadata
            ]);
        }

        return $chatSession;
    }
}
