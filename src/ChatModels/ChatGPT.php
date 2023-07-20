<?php

namespace Adrenallen\AiAgentsLaravel\ChatModels;

use OpenAI;
use Adrenallen\AiAgentsLaravel\Agents\AgentFunction;

class ChatGPT extends AbstractChatModel {

    private $model;
    private $client;

    // class constructor
    public function __construct($model = 'gpt-3.5-turbo', $history = []) {
        $this->model = $model;
        $this->client = OpenAI::client(config('openai.api_key'));
        $this->history = $history;
    }

    /**
     * sends a "user" roled message to the model
     *
     * @param [type] $message
     */
    public function sendUserMessage($message): ChatModelResponse {
        return $this->sendMessage(['role' => 'user', 'content' => $message]);
    }

    /**
     * Sends a function result to the model
     *
     * @param string $functionName
     * @param [type] $result
     */
    public function sendFunctionResult(string $functionName, $result): ChatModelResponse {
        $convertedResult = $result;

        if (is_array($result)) {
            $convertedResult = json_encode($result);
        }
        return $this->sendMessage(['role' => 'function', 'name' => $functionName, 'content' => (string)$convertedResult]);
    }

    /**
     * sends a "system" roled message to the model
     *
     * @param [type] $message
     */
    public function sendSystemMessage($message): ChatModelResponse {
        return $this->sendMessage(['role' => 'system', 'content' => $message]);
    }

    /**
     * sends a message to the open ai model and returns the message result
     *
     * @param [type] $messageObj
     */
    protected function sendMessage($messageObj) : ChatModelResponse{
        $result = $this->client->chat()->create([
            'model' => $this->model,
            'messages' => [
                ...$this->history,
                $messageObj,
            ],
            'functions' => $this->functions,
        ]);

        $response = $result->choices[0]->message;


        $this->addHistory($messageObj);
        $this->addHistory($response->toArray());

        
        // TODO - check if the $result->finishReason == `function_call` and if so then
        // pass in the function call, otherwise dont
        return new ChatModelResponse($response->content, (array) $response->functionCall);
    }

    /*
    * Converts a function from an AgentFunction into
    * a form that open ai accepts like below
    * [
    *       'name' => 'get_current_weather',
    *       'description' => 'Get the current weather in a given location',
    *       'parameters' => [
    *           'type' => 'object',
    *           'properties' => [
    *               'location' => [
    *                   'type' => 'string',
    *                   'description' => 'The city and state, e.g. San Francisco, CA',
    *               ],
    *               'unit' => [
    *                   'type' => 'string',
    *                   'enum' => ['celsius', 'fahrenheit']
    *               ],
    *           ],
    *           'required' => ['location'],
    *       ],
    *   ]
    */
    protected function convertFunctionsForModel(AgentFunction $function) {
        $parameters = [];
        foreach ($function->parameters as $parameter) {
            $parameters[$parameter["name"]] = [
                'type' => $parameter["type"], 
                'description' => $parameter["description"],
            ];
        }

        return [
            'name' => $function->name,
            'description' => $function->description,
            'parameters' => [
                'type' => 'object',

                //convert to object so json_encode works as expected
                // and converts [] to {}
                'properties' => (object)$parameters,    

                'required' => $function->requiredParameters,
            ]
        ];
    }


}