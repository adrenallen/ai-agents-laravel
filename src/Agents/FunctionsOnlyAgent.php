<?php

namespace Adrenallen\AiAgentsLaravel\Agents;

use Adrenallen\AiAgentsLaravel\ChatModels\ChatModelResponse;

/**
 * FunctionsOnlyAgent
 * An agent that requires all model responses to be function calls
 */
class FunctionsOnlyAgent extends BaseAgent {

    // override the ask function from base agent
    // and tell the agent it must call a function
    // before returning

    public string $functionRequiredMessage = "If you have answered the question, call 'completeTask' to return to the user. Otherwise, call a function.";

    //override php function from parent
    public function ask($message) : string {
        $this->hasCalledComplete = false;
        $result = parent::ask($message);
        if (!$this->didAskCallFunction()) {
            return $this->ask($this->functionRequiredMessage);
        }
        return $result;
    }

    public function didAskCallFunction() : bool {
        return true;    //it will always call a function or we reject it lol
    }

    protected $hasCalledComplete = false;
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

            if ($this->hasCalledComplete) {
                return "complete";
            }

            return $this->parseModelResponse(
                $this->chatModel->sendFunctionResult(
                    $functionName,
                    $functionResult
                )
            );
        }


        // if we get here, the response was not a function call

        // we remove the last context to remove the non-function call
        $this->chatModel->context = array_slice($this->chatModel->context, 0, -1);

        // and ask with direction
        return $this->ask($this->functionRequiredMessage);

    }

    public function getAgentFunctions(): array {
        $functions = parent::getAgentFunctions();
        $functions[] = AgentFunction::createFromMethodReflection(new \ReflectionMethod($this, 'completeTask'));
        return $functions;
    }

    /**
     * @aiagent-description Call this when you are done with all tasks and want to return to the user
     *
     * @return void
     */
    public function completeTask() : void {
        $this->hasCalledComplete = true;
    }


}
