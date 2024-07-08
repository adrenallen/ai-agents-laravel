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
        return $this->sendMessage(['role' => 'user', 'content' => $message]);
    }

    // Force the model to call the given function and provide its own parameters
    public function sendFunctionCall(string $functionName, string $id = null): ChatModelResponse
    {
        // TODO - make this better using partial completions
        return $this->sendUserMessage("Call the function $functionName");
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
        return $this->sendMessage([
            'role' => 'user',
            'content' => $this->formatFunctionResultString($functionName, $result)
        ]);
    }

    /**
     * sends a "system" roled message to the model
     *
     * @param [type] $message
     */
    public function sendSystemMessage($message): ChatModelResponse
    {
        return $this->sendMessage(['role' => 'user', 'content' => $message]);
    }

    /**
     * records a "system" roled message to the model
     *
     * @param [type] $message
     */
    public function recordSystemMessage(string $message): void
    {
        $this->recordContext(['role' => 'user', 'content' => $message]);
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
    public function recordFunctionResult(string $functionName, mixed $result, string $id = null): void
    {
        if ($result == "") {
            return; // Don't record empty results (like from a thought or observation)
        }

        $this->recordContext([
            'role' => 'user',
            'content' => $this->formatFunctionResultString($functionName, $result)
        ]);
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

    public function recordAssistantFunction($functionName, $functionArguments, string $id = null) : void
    {
        $this->recordContext([
            'role' => 'assistant',
            'content' => $this->formatFunctionCallString($functionName, $functionArguments)
        ]);
    }

    // Override, we must combine all contexts that are the same "role" in a row into a single message
    public function recordContext($message) {
        if (count($this->context) > 0) {
            $lastIdx = count($this->context) - 1;
            $lastMessage = $this->context[$lastIdx];
            if ($lastMessage['role'] == $message['role']) {
                $this->context[$lastIdx]['content'] .= "\n" . $message['content'];
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
                    $newContext[$lastIdx]['content'] .= "\n" . $message['content'];
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

        $result = $this->client->getCompletion($options);

        if (isset($result['error'])) {
            throw new \Exception(json_encode($result['error']));
        }

        if (!isset($result["content"][0]["text"])) {
            throw new \Exception("No text in response from model: ". json_encode($result));
        }

        $response = $result["content"][0]["text"];

        if ($messageObj) {
            $this->recordContext($messageObj);
        }

        $this->recordContext(['role' => 'assistant', 'content' => $response]);


        $functionCalls = $this->parseFunctionCallsString($response) ?? [];

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
         foreach($function->parameters as $parameter) {
             $parameters[] = sprintf(
                '<parameter><name>%s</name><type>%s</type><description>%s</description></parameter>',
                $parameter["name"],
                $parameter["type"],
                $parameter["description"]
             );
         }

        return sprintf(
            '<tool_description>
            <tool_name>%s</tool_name>
            <description>%s</description>
            <parameters>
            %s
            </parameters>
            </tool_description>',
            $function->name,
            $function->description,
            implode("\n", $parameters)
        );
    }

    protected function getSystemMessage() : string {
        $message = $this->prePrompt;

        if (count($this->functions) > 0) {

            $message .= <<<EOD

In this environment you have access to a set of tools you can use to answer the user's question.

You may call them like this:
<function_calls>
<invoke>
<tool_name>\$TOOL_NAME</tool_name>
<parameters>
<\$PARAMETER_NAME>\$PARAMETER_VALUE</\$PARAMETER_NAME>
...
</parameters>
</invoke>
</function_calls>

You must wait for the user to respond with the function_results.

EOD;

            $message .= sprintf(
                "Here are the tools available: <tools>%s</tools>",
                implode("", $this->functions)
            );

        }

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
            $tokens = $encoder->encode((string) $msg['content']);

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

    private function formatFunctionCallString($functionName, $functionArguments)
    {
        $functionCall = "<function_calls>";
        $functionCall .= "<invoke>";
        $functionCall .= "<tool_name>$functionName</tool_name>";
        $functionCall .= "<parameters>";

        foreach ($functionArguments as $key => $value) {
            $functionCall .= "<$key>$value</$key>";
        }

        $functionCall .= "</parameters>";
        $functionCall .= "</invoke>";
        $functionCall .= "</function_calls>";

        return $functionCall;
    }

    private function parseFunctionCallsString($functionCallString)
    {
        $functionCalls = [];
        $findXml = '/<function_calls>.*<\/function_calls>/Ums';
        preg_match_all($findXml, $functionCallString, $matches, PREG_SET_ORDER, 0);

        foreach($matches as $match) {

            // each of these looks like this
            // <function_calls>\n<invoke>\n<tool_name>messageDriver<\/tool_name>\n<parameters>\n<message>I'm doing well, thanks for asking! I see you are currently at the Ullrich, Gottlieb and Zboncak stop. How is everything going there so far? Let me know if you need any assistance with this load.<\/message>\n<expectAnswer>true<\/expectAnswer>\n<\/parameters>\n<\/invoke>\n<\/function_calls>

            // Make this xml compatible...
            $xmlString = $match[0];
            $xmlString = str_replace('\n', "\n", $xmlString);
            $xmlString = str_replace("<\\/", "</", $xmlString);
            $xmlString = str_replace('>\n<', '><', $xmlString);
            $xml = simplexml_load_string($xmlString);
            if ($xml) {
                $functionCall = [];
                $functionCall['name'] = (string) $xml->invoke->tool_name;
                $functionCall['arguments'] = [];
                if ($xml->invoke->parameters->children()) {
                    foreach ($xml->invoke->parameters->children() as $key => $value) {
                        $functionCall['arguments'][$key] = (string) $value;
                    }
                }
                $functionCalls[] = $functionCall;
            }
        }

        return $functionCalls;

    }

    private function formatFunctionResultString($functionName, $result)
    {

        $convertedResult = $result;

        if (is_array($result)) {
            $convertedResult = json_encode($result);
        }

        return sprintf('
                <function_results>
                <result>
                <tool_name>%s</tool_name>
                <stdout>
                %s
                </stdout>
                </result>
                </function_results>
            ', $functionName, $convertedResult);
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
