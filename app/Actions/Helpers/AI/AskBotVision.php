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
        if (!$request->input('url')) {
            $urlOrBase64Image = $request->input('image_base64');
        } else {
            $urlOrBase64Image = $request->input('url');
        }

        return $this->handle($urlOrBase64Image, $request->input('prompt'));
    }


    public function rules(): array
    {
        return [
            'url'   => ['required_without:image', 'url'],
            'image_base64' => ['required_without:url', 'string'],
            'prompt' => ['required', 'in:default,alt'],
        ];
    }

    // public function afterValidator($validator): void
    // {
    //     if(!$this->get("url")) {
    //         $imageBase64 = $this->get('image_base64');
    //         if (preg_match('/^data:image\/(\w+);base64,/', $imageBase64, $type)) {
    //             $imageBase64 = substr($imageBase64, strpos($imageBase64, ',') + 1);
    //             $imageBase64 = base64_decode($imageBase64);
    //             if ($imageBase64 === false) {
    //                 $this->error(0->add('Invalid base64 string');
    //             }
    //         } else {
    //             $this->error('Invalid base64 string');
    //         }

    //     }

    // }



    // public $commandSignature = 'ask:botvision {url} {--prompt=default}';

    // public function asCommand($command): void
    // {
    //     $urlOrBase64Image = $command->argument('url');
    //     $prompt = $command->option('prompt');

    //     if ($prompt == 'alt') {
    //         $this->promptAltImageWithLLPhant();
    //     }

    //     if (config('askbot-laravel.ai_provider') == 'openai') {
    //         $this->handle($urlOrBase64Image, $prompt);
    //     } else {
    //         $this->error('AI provider not supported');
    //     }

    // }
}
