<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 02-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\AI;

use App\Actions\Helpers\AI\Traits\WithPromptAI;
use App\Actions\OrgAction;
use Illuminate\Http\JsonResponse;
use LLPhant\Chat\OpenAIChat;
use LLPhant\Chat\Vision\ImageSource;
use LLPhant\Chat\Vision\VisionMessage;
use LLPhant\OpenAIConfig;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class AskBotVision extends OrgAction
{
    use AsController;
    use WithPromptAI;

    public function handle(string $urlOrBase64Image, string $prompt)
    {
        if (config('askbot-laravel.ai_provider') == 'openai') {
            $config = new OpenAIConfig();
            $config->model = 'gpt-4o-mini';
            $chat = new OpenAIChat($config);

            $messages = [
                VisionMessage::fromImages([
                    new ImageSource($urlOrBase64Image)
                ], $prompt == 'alt' ? $this->promptAltImageWithLLPhant()['user'] : null)
            ];

            if ($prompt == 'alt') {
                $chat->setSystemMessage($this->promptAltImageWithLLPhant()['system']);
            }


            $response = $chat->generateChat($messages);

            return JsonResponse::create([
                'content' => $response,
            ]);
        }

        return null;

    }

    public function asController(ActionRequest $request)
    {
        $image = $request->file('image');
        $urlOrBase64Image = '';
        if ($image && $image->isValid()) {
            $urlOrBase64Image = base64_encode(file_get_contents($image->getRealPath()));
        } else {
            $urlOrBase64Image = $request->input('url');
        }

        return $this->handle($urlOrBase64Image, $request->input('prompt'));
    }


    public function rules(): array
    {
        return [
            'url'   => ['required_without:image', 'url'],
            'image' => ['required_without:url', 'file', 'mimes:jpeg,png,jpg', 'max:50000'],
            'prompt' => ['required', 'in:default,alt'],
        ];
    }



    public $commandSignature = 'ask:bot {q}';

    public function asCommand($command): void
    {
    }
}
