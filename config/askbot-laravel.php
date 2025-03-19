<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 10-03-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

return [
    'model' => env('MODEL', 'deepseek-chat'),
    'ai_provider' => env('AI_PROVIDER', 'r1'),
    'deepseek_api_url' => env('R1_API_URL', 'https://api.deepseek.com/v1/chat/completions'),
    'deepseek_api_key' => env('R1_API_KEY', ''),
    'embedding_model' => env('EMBEDDING_MODEL', 'nomic-embed-text'),
];
