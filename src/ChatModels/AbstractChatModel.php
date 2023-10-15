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

    public $context = [];   //running context that is sent to model for completions
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
     * Set the pre-prompt message for the model
     * You may not want to actually send this but instead plug it in as the first message before the user message
     *
     * @param string $message
     * @return void
     */
    abstract public function setPrePrompt(string $message);

    /**
     * Get the pre-prompt message which will always be included in the context
     *
     * @return string
     */
    abstract public function getPrePrompt() : string;

    /**
     * Add a new message to the context history
     *
     * @param [type] $message
     * @return void
     */
    public function recordContext($message) {
        $this->context[] = $message;
    }



    public function setFunctions($functions = []) {
        // Parse the functions we get from AgentFunction into a format
        // the model can understand
        foreach($functions as $function) {
            $this->functions[] = $this->convertFunctionsForModel($function);
        }
    }

    

}