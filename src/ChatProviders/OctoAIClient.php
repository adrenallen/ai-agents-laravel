<?php

use Psr\Http\Message\ResponseInterface;

class OctoAIClient {

public function __construct(
    protected string $apiKey,
    protected string $modelName = 'llama-2-70b-chat-fp16',
    protected array $additionalOptions = []
) {}

public function getCompletion(array $context, array $options = []) {
    //use guzzle client to make request
    $client = new \GuzzleHttp\Client();
    $response = $client->request('POST', 'https://text.octoai.run/v1/chat/completions', [
        'headers' => $this->getHeaders(),
        'json' => [
            'messages' => $context,
            ...$this->getOptions($options),
        ],
    ]);

    return $this->parseResponse($response);
}

private function parseResponse(ResponseInterface $response) {
    $parsedResponse = json_decode($response->getBody());
    // if parsed response has a choices array with first value
    // then return the choices[0]->message value
    if (property_exists($parsedResponse, 'choices') && count($parsedResponse->choices) > 0) {
        return $parsedResponse;
    }

    // TODO - make this a proper exception based on response from octoai
    // else throw an exception
    throw new \Exception("Invalid response from OctoAI: " . $response->getBody());

}

private function getOptions(array $options = []) {
    $defaultOptions = [
        'model' => 'llama-2-70b-chat-fp16',
        'max_tokens' => 128,
        'presence_penalty' => 0,
        'temperature' => 0.1,
        'top_p' => 0.9,
    ];

    return array_merge($defaultOptions, $this->additionalOptions, $options);
}

private function getHeaders() {
    return [
        'Authorization' => 'Bearer ' . $this->apiKey,
        'Content-Type' => 'application/json',
    ];
}



}