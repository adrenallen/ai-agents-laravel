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
    public $prePrompt = "";


    public function __construct($context = [], $prePrompt = "", $functions = []) {
        $this->context = $context;
        $this->prePrompt = $prePrompt;
        $this->setFunctions($functions);
    }

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
     * Records a "user" roled message to the model, without getting a response
     * This will not get a response, but will simply add the message to the history
     *
     * @param string $message
     */
    abstract public function recordUserMessage(string $message): void;
    
    /**
     * Records a "system" roled message to the model, without getting a response
     */
    abstract public function recordSystemMessage(string $message): void;

    /**
     * Records a function result to the model, without getting a response
     */
    abstract public function recordFunctionResult(string $functionName, $result): void;

    /**
     * Records a "assistant" roled message to the model, without getting a response
     */
    abstract public function recordAssistantMessage(string $message): void

    /**
     * Add a new message to the context history
     * The input SHOULD BE FORMATTED PROPERTLY
     * USE `recordUserMessage` IF YOU ARE UNSURE
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