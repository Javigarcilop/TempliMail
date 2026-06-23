<?php

declare(strict_types=1);

namespace TempliMail\Services;

use Exception;

class AiService
{
    private const MODEL   = 'llama-3.3-70b-versatile';
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';

    public static function suggestSubjects(string $topic): array
    {
        $apiKey = $_ENV['GROQ_API_KEY'] ?? '';

        if ($apiKey === '' || $apiKey === 'your-api-key-here') {
            throw new Exception('GROQ_API_KEY not configured');
        }

        $prompt = <<<PROMPT
Generate exactly 3 email subject lines for the following topic: "{$topic}".

Rules:
- Return only the 3 subject lines, one per line
- No numbering, no bullet points, no extra text
- Match the language of the topic
- Make them engaging and professional
PROMPT;

        $payload = json_encode([
            'model'    => self::MODEL,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ],
        ]);

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            throw new Exception('Groq API error (HTTP ' . $httpCode . '): ' . $response);
        }

        $data = json_decode($response, true);
        $text = $data['choices'][0]['message']['content'] ?? '';

        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $text)),
            fn(string $line) => $line !== ''
        ));

        return array_slice($lines, 0, 3);
    }
}
