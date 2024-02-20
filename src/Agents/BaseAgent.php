<?php

namespace Adrenallen\AiAgentsLaravel\Agents;


use Adrenallen\AiAgentsLaravel\ChatModels\ChatModelResponse;

/**
 * BaseAgent
 * Responsible for defining the responsibility of an "agent"
 * also includes a list of functions with descriptive docblocks that define what each function is for
 *
 * This class also includes the base functions to use reflection to pull in the "allowed" functions that are sent
 * to underlying chat model
 */
class BaseAgent {

    public $chatModel;
    public int $maxFunctionCalls = 10;  //max number of function loops that can occur without more user input.
    public string $prePrompt = "You are a helpful generalist assistant.";
    public $lastCallMetadata;

    function __construct($chatModel) {
        $this->chatModel = $chatModel;

        // We have to set the pre-prompt now that we have the chat model
        $this->chatModel->prePrompt = $this->prePrompt;

        // Now set the functions for our agent as well
        $this->chatModel->setFunctions($this->getAgentFunctions());
    }

    public function ask($message) : string {
        $this->functionCallLoops = 0;   //new question, so reset the max function loop
        try {
            return $this->parseModelResponse($this->chatModel->sendUserMessage($message));
        } catch (TooManyFunctionCallsException $e) {
            return $this->ask("You have run " . $this->maxFunctionCalls . " function calls without user input. You must ask the user if they would like you to proceed with calls before you can continue.");
        }
    }

    // Ask the model to complete a given function call
    // optional $message will be recorded before asking for a function call
    public function askFunction($functionName, $message = null) : string {
        if ($message) {
            $this->record($message);
        }
        return $this->parseModelResponse($this->chatModel->sendFunctionCall($functionName));
    }

    // Generate a response for the current context without adding anything new
    public function generate() : string {
        return $this->parseModelResponse($this->chatModel->generate());
    }

    /**
     * onSuccessfulFunctionCall
     *
     * This function is called when a function is called and returns a result
     * It is passed the function name, arguments, and result
     * Useful for recording actions the agent is taking
     *
     * @param string $functionName
     * @param array $functionArguments
     * @param mixed $functionResult
     * @return void
     */
    public function onSuccessfulFunctionCall($functionName, $functionArguments, $functionResult) : void {
        // Intentionally blank - this is meant to be overridden
    }

    /**
     * Records a "user" roled message to the model, without getting a response
     *
     * @param string $message
     */
    public function record($message) : void {
        $this->chatModel->recordUserMessage($message);
    }

    /**
     * Records a "assistant" roled message to the model, without getting a response
     *
     * @param string $message
     */
    public function recordAssistant($message) : void {
        $this->chatModel->recordAssistantMessage($message);
    }


    /**
     * Records a "system" roled message to the model, without getting a response
     *
     * @param string $message
     */
    public function recordSystem($message) : void {
        $this->chatModel->recordSystemMessage($message);
    }

    /**
     * Records a function result to the model, without getting a response
     *
     * @param string $functionName
     * @param [type] $result
     */
    public function recordFunction($functionName, $result) : void {
        $this->chatModel->recordFunctionResult($functionName, $result);
    }


    /**
     * Records a function call from the model, without getting a response
     *
     * @param string $functionName
     * @param array $functionArguments
     */
    public function recordAssistantFunction($functionName, $functionArguments) : void{
        $this->chatModel->recordAssistantFunction($functionName, $functionArguments);
    }

    // Did the agent call a function in the last ask loop?
    // this is not saved, so only applies to current in-memory instance
    public function didAskCallFunction() : bool {
        return $this->functionCallLoops > 1; // The model will always call once, if more than 1 then the agent called a function
    }

    protected $functionCallLoops = 0;
    protected function parseModelResponse(ChatModelResponse $response) : string {
        $this->lastCallMetadata = $response->metadata;

        $this->functionCallLoops++;

        if ($this->functionCallLoops > $this->maxFunctionCalls){
            // TODO - Optionally this could send a message to the system saying
            // it must ask the user for approval to continue?
            throw new TooManyFunctionCallsException("Too many function calls have occurred in a row (" . $this->maxFunctionCalls . "). Breaking the loop. Please try again.");
        }

        if ($response->error){
            throw new \Exception($response->error);
        }

        if ($response->functionCall){
            $functionCall = $response->functionCall;
            $functionName = $functionCall['name'];
            $functionArgs = $functionCall['arguments'];

            $functionResult = "";
            try {
                if (!method_exists($this, $functionName)){
                    $functionResult = "Function '". $functionName . "' does not exist.";
                } else {
                    $functionResult = call_user_func_array([$this, $functionName], (array)json_decode($functionArgs));
                    $this->onSuccessfulFunctionCall($functionName, $functionArgs, $functionResult);
                }

            } catch (\Throwable $e) {
                $errorMessage = $e->getMessage();
                $functionResult = "An error occurred while running the function "
                    . $functionName
                    . ":'" . strval($errorMessage);
                    //. "'. You may need to ask the user for more information.";
            }

            return $this->parseModelResponse(
                $this->chatModel->sendFunctionResult(
                    $functionName,
                    $functionResult
                )
            );
        }

        return $response->message;
    }


    /**
     * getAgentFunctions
     *
     * Returns a list of functions that the agent is allowed to use
     * These are passed into the chat model so it knows what it is capable of doing
     *
     * @return array
     */
    public function getAgentFunctions(): array {
        $reflector = new \ReflectionClass($this);
        $methods = $reflector->getMethods(\ReflectionMethod::IS_PUBLIC);
        $allowedFunctions = [];
        foreach ($methods as $method) {
            if (AgentFunction::isMethodForAgent($method)){
                $allowedFunctions[] = AgentFunction::createFromMethodReflection($method);
            }

        }
        return $allowedFunctions;
    }

}


class TooManyFunctionCallsException extends \Exception {

}
