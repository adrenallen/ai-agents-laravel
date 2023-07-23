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

    function __construct($chatModel) {
        $this->chatModel = $chatModel;

        // Set the model to have this agents functions now
        $this->chatModel->setFunctions($this->getAgentFunctions());
        $this->chatModel->setPrePrmopt($this->prePrompt);
    }

    public function ask($message) : string {
        $this->functionCallLoops = 0;   //new question, so reset the max function loop
        return $this->parseModelResponse($this->chatModel->sendUserMessage($message));
    }

    private $functionCallLoops = 0;
    private function parseModelResponse(ChatModelResponse $response) : string {
        $this->functionCallLoops++;

        if ($this->functionCallLoops > $this->maxFunctionCalls){
            // TODO - Optionall this could send a message to the system saying
            // it must ask the user for approval to continue?
            throw new \Exception("Too many function calls have occurred in a row (" . $this->maxFunctionCalls . "). Breaking the loop. Please try again.");
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
                }
                
            } catch (\Throwable $e) {
                $errorMessage = $e->getMessage();
                $functionResult = "An error occurred while running the function " 
                    . $functionName 
                    . ":'" . str($errorMessage) . "'. You may need to ask the user for more information.";
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