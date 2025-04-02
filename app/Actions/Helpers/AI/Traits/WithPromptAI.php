<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 28-03-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Helpers\AI\Traits;

trait WithPromptAI
{
    public function promptSeo(string $question): array
    {
        return [
            [
            'role' => 'system', 'content' => <<<EOD
                You are an AI assistant specialized in SEO data from the provided link. Your task is to parse the "description" and "keywords" and output them strictly in JSON format.

                INSTRUCTIONS:
                - Ensure the output is valid JSON.
                - Provide only one result in the response.
                - Do not include the question or any additional text in your response.
                
                EXAMPLE JSON OUTPUT:
                {
                    "description": "Mount Everest is the highest mountain in the world.",
                    "keywords": ["mountain", "highest", "world"]
                }
                EOD
            ],
            ['role' => 'user', 'content' => $question]
        ];
    }

    public function promptAltImageWithLLPhant(): array
    {
        return [
            'system' => 'You are an AI specializing in accessibility and SEO-focused alt text generation. ' .
                'Your responses must be: ' .
                '- Concise (under 125 characters) ' .
                '- Descriptive and literal (avoid metaphors or opinions) ' .
                '- Prioritize key objects, colors, and actions for uniqueness ' .
                '- If the image is purely decorative, return `alt=""` to meet accessibility best practices.',
            'user' => 'Generate an SEO-friendly, concise, and descriptive alt text for this image. ' .
                'Focus on key subjects and distinguishing features within 125 characters. ' .
                'Avoid generic phrases like "image of" or "picture showing". ' .
                'Use natural keywords when relevant. ' .
                'Example: "Gray tabby cat sleeping on a windowsill".'
        ];
    }


    public function promptLessResponse(string $question): array
    {
        return [
            ['role' => 'system', 'content' => <<<EOD
                You are a Chatbot Helpdesk Agent. Provide concise and accurate responses to user queries.

                RESPONSE:
                Provide a direct, concise, and accurate answer. Avoid being verbose and get straight to the point, but ensure the response feels human.
                EOD
            ],
            ['role' => 'user', 'content' => $question]
        ];
    }


    public function promptRag(string $context, string $question): array
    {
        return [
            [
                'role' => 'system',
                'content' => <<<EOD
                You are a Chatbot operating in a Retrieval-Augmented Generation (RAG) system. Your responsibility is to generate an accurate response to the user's query based strictly on the provided context.

                INSTRUCTIONS:
                - If the context does not contain enough information to answer the question, respond politely, for example: "I'm sorry, but I don't have enough information to answer that question based on what I know."
                - Do not include the question or context in your response.
                - Avoid using phrases like "Based on the provided context" in your response.
                EOD
            ],
            [
                'role' => 'user',
                'content' => <<<EOD
                **CONTEXT (JSON)**:
                {$context}

                **QUESTION**:
                {$question}
                EOD
            ]
        ];
    }

}
