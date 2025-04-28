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
use LLPhant\Chat\OpenAIChat;
use LLPhant\Chat\Vision\ImageSource;
use LLPhant\Chat\Vision\VisionMessage;
use LLPhant\OpenAIConfig;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\AsController;
use Lorisleiva\Actions\Concerns\WithAttributes;

class AskBotVision
{
    use AsAction;
    use WithAttributes;
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

            return response()->json([
                'content' => $response,
            ]);
        }

        return null;

    }

    public function asController(ActionRequest $request)
    {
        $this->fillFromRequest($request);
        $modelData = $this->validateAttributes();

        $image = $modelData['image'] ?? null;
        $urlOrBase64Image = '';
        if ($image && $image->isValid()) {
            $urlOrBase64Image = base64_encode(file_get_contents($image->getRealPath()));
        } else {
            $urlOrBase64Image = $request->input('url');
        }

        return $this->handle($urlOrBase64Image, $modelData['prompt']);
    }

    public function rules(): array
    {
        return [
            'url'   => ['required_without:image', 'url'],
            'image' => ['required_without:url', "mimes:jpg,png,jpeg", "max:10240"],
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



    public $commandSignature = 'ask:botvision {url}';

    public function asCommand($command): void
    {
        $urlOrBase64Image = $command->argument('url');

        dd($this->handle($urlOrBase64Image, 'alt'));

    }
}
