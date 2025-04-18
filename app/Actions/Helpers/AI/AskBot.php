<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 29-11-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Helpers\AI;

use App\Actions\Helpers\AI\Traits\WithAIBot;
use App\Actions\Helpers\AI\Traits\WithPromptAI;
use App\Actions\OrgAction;
use Illuminate\Support\Facades\Response;
use LLPhant\Chat\OpenAIChat;
use LLPhant\OpenAIConfig;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class AskBot extends OrgAction
{
    use AsController;
    use WithAIBot;
    use WithPromptAI;

    public function handle($q)
    {
        if (config('askbot-laravel.ai_provider') == 'r1') {
            return $this->askDeepseek($this->promptLessResponse($q));
        } elseif (config('askbot-laravel.ai_provider') == 'openai') {
            $config = new OpenAIConfig();
            $chat = new OpenAIChat($config);

            $stream = $chat->generateStreamOfText($q);

            return Response::stream(function () use ($stream) {
                try {
                    while (!$stream->eof()) {
                        $chunk = $stream->read(1024); // Increased buffer size

                        if ($chunk !== false && $chunk !== '') {
                            // Properly format as SSE message
                            echo "data: " . json_encode(['choices' => [['delta' => ['content' => $chunk]]]]) . "\n\n";

                            ob_flush();
                            flush();

                            // Check if client disconnected
                            if (connection_aborted()) {
                                break;
                            }
                        }
                    }

                    // Send completion event
                    echo "event: data\n[done]\n\n";
                    ob_flush();
                    flush();
                } finally {
                    // Ensure stream is always closed
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            }, 200, [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]);
        }

        return $this->askLlama($q);
    }

    public function asController(ActionRequest $request)
    {
        $q = $request->input('q');
        return $this->handle($q);
    }

    public $commandSignature = 'ask:bot {q}';

    public function asCommand($command): void
    {

        // generate chunk vector for all product
        // $product = Product::query()->orderBy('id')->get();

        // foreach ($product as $p) {
        //     $metadata = $p->toArray();
        //     $content = Arr::only($metadata, ['code', 'status', 'slug', 'units', 'unit' ,'name', 'price', 'description']);
        //     data_set($content, 'symbol_currency', Currency::find($metadata['currency_id'])->symbol);
        //     ChunkText::make()->handle(json_encode($content), $metadata);
        // }
        // dd('done');
        // $client = DeepSeekClient::build(apiKey:env('R1_API_KEY'), baseUrl:'https://api.deepseek.com/v3', timeout:30, clientType:'guzzle');

        // $response = $client
        // ->query($command->argument('q'))
        // ->withModel(Model)
        // ->setTemperature(1.5)
        // ->run();
        // dd(AskBotResource::collection($this->handle($command->argument('q'))));

    }
}
