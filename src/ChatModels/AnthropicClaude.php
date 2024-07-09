<?php

namespace Adrenallen\AiAgentsLaravel\ChatModels;

use OpenAI;
use Yethee\Tiktoken\EncoderProvider;

use Adrenallen\AiAgentsLaravel\Agents\AgentFunction;

class AnthropicClaude extends AbstractChatModel
{
    protected AnthropicClient $client;
    public $maxTokensBuffer = 0.05; // add a 5% buffer to max token count

    // class constructor
    /**
     * @param string $model
     * @param array $context
     * @param array $claudeOptions
     */
    public function __construct(public $context = [], $prePrompt = "", $functions = [], protected $model = 'claude-3-opus-20240229',  protected $claudeOptions = [])
    {
        parent::__construct($context, $prePrompt, $functions);
        $this->client = new AnthropicClient(config('anthropic.api_key'), $claudeOptions['anthropic-version'] ?? "2023-06-01");
    }

    /**
     * sends a "user" roled message to the model
     *
     * @param [type] $message
     */
    public function sendUserMessage($message): ChatModelResponse
    {
        return $this->sendMessage(['role' => 'user', 'content' => [['type' => 'text', 'text' => $message]]]);
    }

    // Force the model to call the given function and provide its own parameters
    public function sendFunctionCall(string $functionName): ChatModelResponse
    {
        // if openAiOptions has a `tool_choice` then we need to save it, else null
        $oldFunctionRequirement = $this->claudeOptions['tool_choice'] ?? null;

        // Set the option to force tool_choice
        $this->claudeOptions['tool_choice'] = ["type" => "tool", "name" => $functionName];

        $result = $this->sendMessage(null);

        //Unset the temp requirement and set it back to what it was previously
        unset($this->claudeOptions['tool_choice']);
        if ($oldFunctionRequirement) {
            $this->claudeOptions['tool_choice'] = $oldFunctionRequirement;
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
    public function sendFunctionResult(string $functionName, mixed $result, string $id = null): ChatModelResponse
    {
        $id = $id ?? uniqid();

        $convertedResult = $result;

        if (is_array($result)) {
            $convertedResult = json_encode($result);
        }
        return $this->sendMessage([
            'role' => 'user',
            'content' => [
                [
                    "type" => "tool_result",
                    "tool_use_id" => $id,
                    "content" => (string)$convertedResult
                ]
            ]
        ]);
    }

    /**
     * sends a "system" roled message to the model
     *
     * @param [type] $message
     */
    public function sendSystemMessage($message): ChatModelResponse
    {
        return $this->sendMessage(['role' => 'user', 'content' => [['type' => 'text', 'text' => $message]]]);
    }

    /**
     * records a "system" roled message to the model
     *
     * @param [type] $message
     */
    public function recordSystemMessage(string $message): void
    {
        $this->recordContext(['role' => 'user', 'content' => [['type' => 'text', 'text' => $message]]]);
    }

    /**
     * records a "user" roled message to the model
     *
     * @param [type] $message
     */
    public function recordUserMessage(string $message): void
    {
        $this->recordContext(['role' => 'user', 'content' => [['type' => 'text', 'text' => $message]]]);
    }

    /**
     * records a function result to the model
     *
     * @param string $functionName
     * @param [type] $result
     */
    public function recordFunctionResult(string $functionName, mixed $result, string $id = null): void
    {
        $id = $id ?? uniqid();
        $this->recordContext([
            'role' => 'user',
            'content' => [
                [
                    "type" => "tool_result",
                    "tool_use_id" => $id,
                    "content" => $result
                ]
            ]
        ]);
    }

    /**
     * records an "assistant" rol message to the model
     *
     * @param string $message
     */
    public function recordAssistantMessage(string $message): void
    {
        $this->recordContext(['role' => 'assistant', 'content' => [['type' => 'text', 'text' => $message]]]);
    }

    public function recordAssistantFunction($functionName, $functionArguments, string $id = null) : void
    {
        $this->recordContext([
            'role' => 'assistant',
            'content' => [
                [
                    'type' => 'tool_use',
                    'id' => $id ?? uniqid(),
                    'name' => $functionName,
                    'inputs' => $functionArguments,
                ]
            ]
        ]);
    }

    // Override, we must combine all contexts that are the same "role" in a row into a single message
    public function recordContext($message) {
        if (count($this->context) > 0) {
            $lastIdx = count($this->context) - 1;
            $lastMessage = $this->context[$lastIdx];
            if ($lastMessage['role'] == $message['role']) {
                $this->context[$lastIdx]['content'] = array_merge($message['content'], $this->context[$lastIdx]['content']);
                return;
            }
        }
        parent::recordContext($message);
    }

    // Gets a new context based on the current context and the new message
    // does not update this models context though! We record it after we get the response and it's successful
    private function getContextForNewMessage($message) {
        $newContext = [...$this->context];
        if ($message) {
            // if the last message in the context is the same role, combine them
            if (count($newContext) > 0) {
                $lastIdx = count($newContext) - 1;
                $lastMessage = $newContext[$lastIdx];
                if ($lastMessage['role'] == $message['role']) {
                    $newContext[$lastIdx]['content'] = array_merge($message['content'], $newContext[$lastIdx]['content']);
                    return $newContext;
                }
            }
            $newContext[] = $message;
        }
        return $newContext;
    }

    /**
     * sends a message to the open ai model and returns the message result
     *
     * @param [type] $messageObj
     */
    protected function sendMessage($messageObj): ChatModelResponse
    {
        // Build the new context
        $newContext = $this->getContextForNewMessage($messageObj);

        $options = [
            'max_tokens' => $this->claudeOptions['max_tokens'] ?? 1024,
            'model' => $this->model,
            'messages' => $this->getTokenPreppedContext($newContext),
            ...$this->claudeOptions,
            'system' => $this->getSystemMessage()
        ];

        if (count($this->functions) > 0) {
            $options['tools'] = $this->functions;
        }

        $result = $this->client->getCompletion($options);

        if (isset($result['error'])) {
            throw new \Exception(json_encode($result['error']));
        }

        // response text should be in here as a "text" entry. So find them all and combine in weird case if there are many
        $response = "";
        foreach ($result['content'] as $content) {
            if ($content['type'] == 'text') {
                $response .= $content['text'];
            }
        }

        if ($messageObj) {
            $this->recordContext($messageObj);
        }

        // Just pull the concat into history
        $this->recordContext($result);

        $functionCalls = $this->parseFunctionCalls($result) ?? [];

        return new ChatModelResponse($response, (array) $functionCalls, null, [
            'id' => $result['id'] ?? null,
            'model' => $result['model'] ?? null,
            'usage' => $result['usage'] ?? null
        ]);
    }

    /**
     * Based on https://docs.anthropic.com/claude/docs/functions-external-tools
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
            "name" => $function->name,
            "description" => $function->description,
            'input_schema' => [
                'type' => 'object',

                //convert to object so json_encode works as expected
                // and converts [] to {}
                'properties' => (object)$parameters,

                'required' => $function->requiredParameters,
            ]
        ];
    }

    protected function getSystemMessage() : string {
        $message = $this->prePrompt;

        return $message;
    }


    // Given a context, and a max token count, it returns
    // a new context that is under the max tokens count
    // This will also guarantee the pre-prompt is included
    private function getTokenPreppedContext($context)
    {
        $provider = new EncoderProvider();
        $encoder = $provider->getForModel('gpt-4');

        $maxTokens = $this->maxContextHistoryTokens ?? $this->getMaxTokenGuessByModel($this->model) * (1.0 - $this->maxTokensBuffer);

        $newContext = [];
        $tokenUsage = 0;

        // add the token usage for the pre-prompt
        $tokenUsage += count($encoder->encode((string) $this->getSystemMessage()));

        // Go through context from newest first, dropping oldest ones off
        foreach (array_reverse($context) as $msg) {
            $tokens = $encoder->encode(json_encode($msg['content']));

            if ($tokenUsage + count($tokens) > $maxTokens) {
                break; //we have max tokens so break out and return
            }

            $newContext[] = $msg;
            $tokenUsage = $tokenUsage + count($tokens);
        }

        //reverse so that it's chronological order again
        //since we went backwards above
        return array_reverse($newContext);
    }

    // Given a model, it returns the max token count
    // this is mostly a guess
    private function getMaxTokenGuessByModel(string $model): int
    {
        switch ($model) {
            default:    // all are 200k at this time i believe
                return 200000;
        }
    }

    private function parseFunctionCalls($response)
    {
        $functionCalls = [];

        $actions = $response['content'];

        // foreach $actions if "type" == "tool_use" then add to functionCalls
        foreach($actions as $action) {
            if ($action['type'] == 'tool_use') {
                $functionCalls[] = [
                    'name' => $action['name'],
                    'arguments' => $action['input'],
                    'id' => $action['id']
                ];
            }
        }

        return $functionCalls;
    }
}

class AnthropicClient {
    // constructor
    public function __construct(public $apiKey, private $anthropicVersion = "2023-06-01")
    {}

    function getCompletion($data) : array {
        $url = "https://api.anthropic.com/v1/messages";
        $headers = [
            "x-api-key: $this->apiKey",
            "anthropic-version: $this->anthropicVersion",
            "content-type: application/json"
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }
}
