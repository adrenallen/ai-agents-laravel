<?php

namespace Adrenallen\AiAgentsLaravel\ChatModels;

use OpenAI;
use Yethee\Tiktoken\EncoderProvider;

use Adrenallen\AiAgentsLaravel\Agents\AgentFunction;

class ChatGPT extends AbstractChatModel
{

    protected $model;
    protected $client;
    protected $openAiOptions;
    public $maxTokensBuffer = 0.05; // 5% buffer for max tokens

    // class constructor
    /**
     * @param string $model
     * @param array $context
     * @param array $openAiOptions
     */
    public function __construct($context = [], $prePrompt = "", $functions = [], $model = 'gpt-3.5-turbo',  $openAiOptions = [])
    {

        parent::__construct($context, $prePrompt, $functions);
        $this->model = $model;
        $this->client = OpenAI::client(config('openai.api_key'));
        $this->context = $context;
        $this->openAiOptions = $openAiOptions;
    }

    /**
     * sends a "user" roled message to the model
     *
     * @param [type] $message
     */
    public function sendUserMessage($message): ChatModelResponse
    {
        return $this->sendMessage(['role' => 'user', 'content' => $message]);
    }

    // Force the model to call the given function and provide its own parameters
    public function sendFunctionCall(string $functionName): ChatModelResponse
    {

        // if openAiOptions has a `function_call` then we need to save it, else null
        $oldFunctionRequirement = $this->openAiOptions['function_call'] ?? null;

        // Set the option to force function_call
        $this->openAiOptions['function_call'] = ["name" => $functionName];

        $result = $this->sendMessage(null);

        //Unset the temp requirement and set it back to what it was previously
        unset($this->openAiOptions['function_call']);
        if ($oldFunctionRequirement) {
            $this->openAiOptions['function_call'] = $oldFunctionRequirement;
        }

        return $result;
    }

    public function generate() : ChatModelResponse
    {
        return $this->sendMessage(null);
    }

    /**
     * Sends a function result to the model
     *
     * @param string $functionName
     * @param [type] $result
     */
    public function sendFunctionResult(string $functionName, $result): ChatModelResponse
    {
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
    public function sendSystemMessage($message): ChatModelResponse
    {
        return $this->sendMessage(['role' => 'system', 'content' => $message]);
    }

    /**
     * records a "system" roled message to the model
     *
     * @param [type] $message
     */
    public function recordSystemMessage(string $message): void
    {
        $this->recordContext(['role' => 'system', 'content' => $message]);
    }

    /**
     * records a "user" roled message to the model
     *
     * @param [type] $message
     */
    public function recordUserMessage(string $message): void
    {
        $this->recordContext(['role' => 'user', 'content' => $message]);
    }

    /**
     * records a function result to the model
     *
     * @param string $functionName
     * @param [type] $result
     */
    public function recordFunctionResult(string $functionName, $result): void
    {
        if ($result == "") {
            return; // Don't record empty results (like from a thought or observation)
        }
        $this->recordContext(['role' => 'function', 'name' => $functionName, 'content' => $result]);
    }

    /**
     * records an "assistant" rol message to the model
     *
     * @param string $message
     */
    public function recordAssistantMessage(string $message): void
    {
        $this->recordContext(['role' => 'assistant', 'content' => $message]);
    }

    public function recordAssistantFunction($functionName, $functionArguments) : void{
        $this->recordContext([
            'role' => 'assistant',
            'content' => null,
            'function_call' => [
                'name' => $functionName,
                'arguments' => json_encode($functionArguments)
            ]
        ]);
    }

    /**
     * sends a message to the open ai model and returns the message result
     *
     * @param [type] $messageObj
     */
    protected function sendMessage($messageObj): ChatModelResponse
    {
        // Build the new context
        $newContext = [...$this->context];
        if ($messageObj) {
            $newContext[] = $messageObj;
        }

        $options = [
            'model' => $this->model,
            'messages' => $this->getTokenPreppedContext($newContext),
            ...$this->openAiOptions,
        ];

        if (count($this->functions) > 0) {
            $options['functions'] = $this->functions;
        }

        $result = $this->client->chat()->create($options);

        $response = $result->choices[0]->message;

        if ($messageObj) {
            $this->recordContext($messageObj);
        }

        $this->recordContext($response->toArray());

        $functionCall = $response->functionCall ?? null;

        // TODO - check if the $result->finishReason == `function_call` and if so then
        // pass in the function call, otherwise dont?
        return new ChatModelResponse($response->content, (array) $functionCall, null, [
            'id' => $result->id ?? null,
            'created' => $result->created ?? null,
            'model' => $result->model ?? null,
            'systemFingerprint' => $result->systemFingerprint ?? null,
            'usage' => $result->usage ?? null
        ]);
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
    protected function convertFunctionsForModel(AgentFunction $function)
    {
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


    // Given a context, and a max token count, it returns
    // a new context that is under the max tokens count
    // This will also guarantee the pre-prompt is included
    private function getTokenPreppedContext($context)
    {
        $provider = new EncoderProvider();
        $encoder = $provider->getForModel($this->model);

        $maxTokens = $this->getMaxTokenGuessByModel($this->model) * (1.0 - $this->maxTokensBuffer);

        $newContext = [];
        $tokenUsage = 0;

        // add the token usage for the pre-prompt
        $tokenUsage += count($encoder->encode((string) $this->prePrompt));

        // Go through context from newest first, dropping oldest ones off
        foreach (array_reverse($context) as $msg) {
            $tokens = $encoder->encode((string) $msg['content']);

            // If there is a function call then add those tokens too
            if (array_key_exists('function_call', $msg)) {
                $tokens = [...$tokens, $encoder->encode(json_encode($msg['function_call']))];
            }

            if ($tokenUsage + count($tokens) > $maxTokens) {
                break; //we have max tokens so break out and return
            }

            $newContext[] = $msg;
            $tokenUsage = $tokenUsage + count($tokens);
        }

        // now we add the pre-prompt in so it's there!
        // if the prompt is not empty and the first message is not already the pre-prompt
        // this will get reversed below so it's first instead
        if (
            strlen($this->prePrompt) > 0 &&
            (count($context) < 1 || $context[0]['role'] != 'system' || $context[0]['content'] != $this->prePrompt)
        ) {
            $newContext[] = ['role' => 'system', 'content' => $this->prePrompt];
        }

        //reverse so that it's chronological order again
        //since we went backwards above
        return array_reverse($newContext);
    }

    // Given a model, it returns the max token count
    // this is mostly a guess based on OpenAI latest published token counts....
    // https://platform.openai.com/docs/models/
    private function getMaxTokenGuessByModel(string $model): int
    {
        switch ($model) {
            case 'gpt-4-turbo-preview':
            case 'gpt-4-0125-preview':
            case 'gpt-4-1106-preview':
            case 'gpt-4-vision-preview':
            case 'gpt-4-1106-vision-preview':
                return 128000;
            case 'gpt-4':
            case 'gpt-4-0613':
                return 8192;
            case 'gpt-4-32k':
            case 'gpt-4-32k-0613':
                return 32768;
            case 'gpt-3.5-turbo-16k':
                return 16384;
            case 'gpt-3.5-turbo':
                return 4096;
            case 'gpt-3.5-turbo-0125':
                return 16385;
            case 'gpt-3.5-turbo-1106':
                return 16385;
            case 'gpt-3.5-turbo-instruct':
                return 4096;
            case 'gpt-3.5-turbo-16k-0613':
                return 16385;
            default:
                return 4096;
        }
    }
}
