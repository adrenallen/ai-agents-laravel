<?php

namespace Adrenallen\AiAgentsLaravel\ChatModels;

use Yethee\Tiktoken\EncoderProvider;
use Adrenallen\AiAgentsLaravel\Agents\AgentFunction;
use Psr\Http\Message\ResponseInterface;

class OctoML extends AbstractChatModel {

    public $client;


    // class constructor
    public function __construct(public $context = [], public $prePrompt = "", $functions = [], $model = 'llama-2-70b-chat-fp16', $octoMLOptions = []) {

        parent::__construct($context, $prePrompt, $functions);
        $this->client = new OctoMLClient(config('octoml.api_key'), $model, $octoMLOptions);
        $this->context = $context;
    }

    // /**
    //  * sends a "user" roled message to the model
    //  *
    //  * @param [type] $message
    //  */
    // public function sendUserMessage($message): ChatModelResponse {
    //     return $this->sendMessage(['role' => 'user', 'content' => $message]);
    // }

    // /**
    //  * Sends a function result to the model
    //  *
    //  * @param string $functionName
    //  * @param [type] $result
    //  */
    // public function sendFunctionResult(string $functionName, $result): ChatModelResponse {
    //     $convertedResult = $result;

    //     if (is_array($result)) {
    //         $convertedResult = json_encode($result);
    //     }
    //     return $this->sendMessage(['role' => 'function', 'name' => $functionName, 'content' => (string)$convertedResult]);
    // }

    // /**
    //  * sends a "system" roled message to the model
    //  *
    //  * @param [type] $message
    //  */
    // public function sendSystemMessage($message): ChatModelResponse {
    //     return $this->sendMessage(['role' => 'system', 'content' => $message]);
    // }

    // /**
    //  * records a "system" roled message to the model
    //  *
    //  * @param [type] $message
    //  */
    // public function recordSystemMessage(string $message): void
    // {
    //     $this->recordContext(['role' => 'system', 'content' => $message]);
    // }

    // /**
    //  * records a "user" roled message to the model
    //  *
    //  * @param [type] $message
    //  */
    // public function recordUserMessage(string $message): void
    // {
    //     $this->recordContext(['role' => 'user', 'content' => $message]);
    // }

    // /**
    //  * records a function result to the model
    //  *
    //  * @param string $functionName
    //  * @param [type] $result
    //  */
    // public function recordFunctionResult(string $functionName, $result): void
    // {
    //     $this->recordContext(['role' => 'function', 'name' => $functionName, 'content' => $result]);
    // }

    // /**
    //  * records an "assistant" rol message to the model
    //  * 
    //  * @param string $message
    //  */
    // public function recordAssistantMessage(string $message): void
    // {
    //     $this->recordContext(['role' => 'assistant', 'content' => $message]);
    // }

    // /**
    //  * sends a message to the open ai model and returns the message result
    //  *
    //  * @param [type] $messageObj
    //  */
    // protected function sendMessage($messageObj) : ChatModelResponse{
    //     $options = [
    //         'model' => $this->model,
    //         'messages' => $this->getTokenPreppedContext([
    //             ...$this->context,
    //             $messageObj,
    //         ]),
    //         ...$this->openAiOptions,
    //     ];

    //     if (count($this->functions) > 0) {
    //         $options['functions'] = $this->functions;
    //     }

    //     $result = $this->client->chat()->create($options);

    //     $response = $result->choices[0]->message;

    //     $this->recordContext($messageObj);
    //     $this->recordContext($response->toArray());

        
    //     // TODO - check if the $result->finishReason == `function_call` and if so then
    //     // pass in the function call, otherwise dont?
    //     return new ChatModelResponse($response->content, (array) $response->functionCall, null, ['usage' => $result->usage]);
    // }

    // /*
    // * Converts a function from an AgentFunction into
    // * a form that open ai accepts like below
    // * [
    // *       'name' => 'get_current_weather',
    // *       'description' => 'Get the current weather in a given location',
    // *       'parameters' => [
    // *           'type' => 'object',
    // *           'properties' => [
    // *               'location' => [
    // *                   'type' => 'string',
    // *                   'description' => 'The city and state, e.g. San Francisco, CA',
    // *               ],
    // *               'unit' => [
    // *                   'type' => 'string',
    // *                   'enum' => ['celsius', 'fahrenheit']
    // *               ],
    // *           ],
    // *           'required' => ['location'],
    // *       ],
    // *   ]
    // */
    // protected function convertFunctionsForModel(AgentFunction $function) {
    //     $parameters = [];
    //     foreach ($function->parameters as $parameter) {
    //         $parameters[$parameter["name"]] = [
    //             'type' => $parameter["type"], 
    //             'description' => $parameter["description"],
    //         ];
    //     }

    //     return [
    //         'name' => $function->name,
    //         'description' => $function->description,
    //         'parameters' => [
    //             'type' => 'object',

    //             //convert to object so json_encode works as expected
    //             // and converts [] to {}
    //             'properties' => (object)$parameters,    

    //             'required' => $function->requiredParameters,
    //         ]
    //     ];
    // }


    // // Given a context, and a max token count, it returns 
    // // a new context that is under the max tokens count
    // // This will also guarantee the pre-prompt is included
    // private function getTokenPreppedContext($context, $maxTokens = 8192) {
    //     if (count($context) < 1) {
    //         return [
    //             ['role' => 'system', 'content' => $this->prePrompt],
    //         ];
    //     }

    //     // If the first message is a system message we assume its a prompt
    //     if ($context[0]['role'] == 'system') {   
    //         // call recursive but drop the first one
    //         return $this->getTokenPreppedContext(array_slice($context, 1), $maxTokens);
    //     }

    //     $provider = new EncoderProvider();
    //     $encoder = $provider->getForModel($this->model);

    //     $newContext = [];
    //     $tokenUsage = 0;

    //     // add the token usage for the pre-prompt
    //     $tokenUsage += count($encoder->encode((string) $this->prePrompt));

    //     // Go through context from newest first, dropping oldest ones off
    //     foreach(array_reverse($context) as $msg) {
    //         $tokens = $encoder->encode((string) $msg['content']);
            
    //         // If there is a function call then add those tokens too
    //         if (array_key_exists('function_call', $msg)) {
    //             $tokens = [...$tokens, $encoder->encode(json_encode($msg['function_call']))];
    //         }

    //         if ($tokenUsage + count($tokens) > $maxTokens) {
    //             break; //we have max tokens so break out and return
    //         }

    //         $newContext[] = $msg;
    //         $tokenUsage = $tokenUsage + count($tokens);
    //     }
    
    //     // now we add the pre-prompt in so it's there!
    //     // this will get reversed below so it's first instead
    //     $newContext[] = ['role' => 'system', 'content' => $this->prePrompt];

    //     //reverse so that it's chronological order again
    //     //since we went backwards above
    //     return array_reverse($newContext);  
    // }


}


class OctoMLClient {

    public function __construct(
        protected string $apiKey,
        protected string $modelName = 'llama-2-70b-chat-fp16',
        protected array $additionalOptions = []
    ) {}

    public function getCompletion(array $context) {
        //use guzzle client to make request
        $client = new \GuzzleHttp\Client();
        $response = $client->request('POST', 'https://text.octoai.run/v1/chat/completions', [
            'headers' => $this->getHeaders(),
            'json' => [
                'messages' => $context,
                ...$this->getOptions(),
            ],
        ]);

        return $this->parseResponse($response);
    }

    private function parseResponse(ResponseInterface $response) {
        $parsedResponse = json_decode($response->getBody());
        // if parsed response has a choices array with first value
        // then return the choices[0]->message value
        if (property_exists($parsedResponse, 'choices') && count($parsedResponse->choices) > 0) {
            return $parsedResponse->choices[0]->message;
        }

        // TODO - make this a proper exception based on response from octoml
        // else throw an exception
        throw new \Exception("Invalid response from OctoML: " . $response->getBody());

    }

    private function getOptions() {
        $defaultOptions = [
            'model' => 'llama-2-70b-chat-fp16',
            'max_tokens' => 128,
            'presence_penalty' => 0,
            'temperature' => 0.1,
            'top_p' => 0.9,
        ];

        return array_merge($defaultOptions, $this->additionalOptions);
    }

    private function getHeaders() {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
        ];
    }



}