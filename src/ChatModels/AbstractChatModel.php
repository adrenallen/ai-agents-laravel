<?php

namespace Adrenallen\AiAgentsLaravel\ChatModels;

use Adrenallen\AiAgentsLaravel\Agents\AgentFunction;

/**
 * AbstractChatModel
 * 
 * Responsible for abstracting the chat model API
 * 
 * Includes methods for turning an agent's duty and allowed functions
 * into a proper prompt to start a chat
 * 
 * 
 * 
 */
abstract class AbstractChatModel {

    public $history = [];
    protected $functions = [];  // list of functions that the agent is allowed to use


    abstract protected function convertFunctionsForModel(AgentFunction $function);

    /**
     * Sends a function result to the model
     *
     * @param string $functionName
     * @param [type] $result
     */
    abstract public function sendFunctionResult(string $functionName, $result): ChatModelResponse;

    /**
     * sends a "system" roled message to the model
     *
     * @param string $message
     */
    abstract public function sendSystemMessage(string $message): ChatModelResponse;
    

    /**
     * sends a "user" roled message to the model
     *
     * @param string $message
     */
    abstract public function sendUserMessage(string $message): ChatModelResponse;


    /**
     * Add a new message to the history
     *
     * @param [type] $message
     * @return void
     */
    public function addHistory($message) {
        $this->history[] = $message;
    }



    public function setFunctions($functions = []) {
        // Parse the functions we get from AgentFunction into a format
        // the model can understand
        foreach($functions as $function) {
            $this->functions[] = $this->convertFunctionsForModel($function);
        }
    }

    

}