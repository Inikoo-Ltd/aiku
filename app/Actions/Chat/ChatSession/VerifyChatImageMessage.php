<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Jun 2026 21:07:06 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\ChatSession;

use App\Actions\Helpers\AI\Traits\WithPromptAI;
use App\Events\BroadcastRealtimeChat;
use App\Http\Resources\CRM\Livechat\ChatMessageResource;
use App\Models\Chat\ChatAgent;
use App\Models\Chat\ChatMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use LLPhant\Chat\OpenAIChat;
use LLPhant\Chat\Vision\ImageSource;
use LLPhant\Chat\Vision\VisionMessage;
use LLPhant\OpenAIConfig;
use Lorisleiva\Actions\Concerns\AsAction;

class VerifyChatImageMessage
{
    use AsAction;
    use WithPromptAI;


    private const int NOT_AI_GENERATED_CONFIDENCE_THRESHOLD = 70;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(ChatMessage $chatMessage): ChatMessage
    {
        if (!$chatMessage->isVerifiableCustomerImage()) {
            throw ValidationException::withMessages([
                'message' => __('Only images uploaded by the customer can be verified'),
            ]);
        }

        $verification = $this->askIsImageAiGenerated(
            base64_encode(file_get_contents($chatMessage->attachment->getPath()))
        );

        $isAiGenerated = $verification['is_ai_generated']
            || $verification['confidence'] < self::NOT_AI_GENERATED_CONFIDENCE_THRESHOLD;

        $chatMessage->update([
            'is_ai_generated' => $isAiGenerated,
            'is_validated' => true,
            'metadata' => array_merge($chatMessage->metadata ?? [], [
                'ai_verification' => [
                    'model_verdict' => $verification['is_ai_generated'],
                    'confidence' => $verification['confidence'],
                    'reasoning' => $verification['reasoning'],
                    'verified_at' => now()->toIso8601String(),
                ],
            ]),
        ]);

        $chatMessage->refresh();

        BroadcastRealtimeChat::dispatch($chatMessage);

        return $chatMessage;
    }

    /**
     * @return array{is_ai_generated: bool, confidence: int, reasoning: string}
     */
    protected function askIsImageAiGenerated(string $base64Image): array
    {
        $config = new OpenAIConfig(apiKey: config('auto-translations.drivers.gpt-5-nano.api_key'));
        $config->model = 'gpt-5';
        $chat = new OpenAIChat($config);

        $chat->setSystemMessage(
            'You are an image forensics assistant. Analyse the given image and judge whether AI tooling ' .
            'played any meaningful part in producing it, as opposed to being an unedited real photograph ' .
            'or an untouched screenshot. Flag it as AI-generated (is_ai_generated: true) for ANY of these: ' .
            '(1) fully synthetic imagery from a generator such as Midjourney, DALL-E, Stable Diffusion, ' .
            'Gemini/Imagen — look for unnatural textures, inconsistent lighting or shadows, warped ' .
            'hands/text/patterns, overly smooth "airbrushed" surfaces, generator watermarks; ' .
            '(2) AI-assisted composites or mockups, e.g. a real photo or screenshot combined with an ' .
            'AI-generated background, decorative frame, floating device mockup, staged product scene, or ' .
            'AI-added/removed objects — look for a real element pasted onto a synthetic background, ' .
            'inconsistent shadow/perspective between layers, or overly polished marketing-style staging ' .
            'that would not occur in an organic photo or plain screenshot. ' .
            'Only judge it as NOT AI-generated when the whole image looks like an ordinary, unstaged ' .
            'photograph or a plain, unedited screenshot with no added synthetic elements. ' .
            'The image may have been compressed or re-encoded, which hides some artefacts and lowers ' .
            'certainty — reflect that honestly in the confidence score instead of defaulting to ' .
            '"not AI-generated" when the evidence is inconclusive. ' .
            'Respond with strict JSON only, no markdown fences, in this exact shape: ' .
            '{"is_ai_generated": true|false, "confidence": 0-100, "reasoning": "one concise sentence ' .
            'naming which part (if any) looks AI-made"}.'
        );

        $messages = [
            VisionMessage::fromImages(
                [new ImageSource($base64Image)],
                'Did AI tooling play any part in producing this image (fully generated, or a real photo/' .
                'screenshot composited with AI-made elements)? Respond with the JSON object described in ' .
                'your instructions.'
            ),
        ];

        $response = trim((string) $chat->generateChat($messages));
        $response = trim((string) preg_replace('/^```(?:json)?|```$/m', '', $response));
        $decoded = json_decode($response, true);

        if (!is_array($decoded) || !array_key_exists('is_ai_generated', $decoded)) {
            return [
                'is_ai_generated' => false,
                'confidence' => 0,
                'reasoning' => 'Model response could not be parsed.',
            ];
        }

        return [
            'is_ai_generated' => (bool) $decoded['is_ai_generated'],
            'confidence' => max(0, min(100, (int) ($decoded['confidence'] ?? 0))),
            'reasoning' => (string) ($decoded['reasoning'] ?? ''),
        ];
    }

    public function asController(string $organisation, ChatMessage $chatMessage): JsonResponse
    {
        $user = Auth::user();
        $agent = $user ? ChatAgent::where('user_id', $user->id)->first() : null;

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => __('Only agents can verify images'),
            ], 403);
        }

        try {
            $chatMessage = $this->handle($chatMessage);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Image verification complete'),
            'data' => new ChatMessageResource($chatMessage),
        ]);
    }
}
