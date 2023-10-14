<?php

namespace Adrenallen\AiAgentsLaravel\Agents;

/**
 * FunctionsAgent
 * An agent that requires a function to be called between each user message
 * before the agent returns
 */
class FunctionsAgent extends BaseAgent {

    // override the ask function from base agent
    // and tell the agent it must call a function
    // before returning

    // The message to send to the agent if it fails to call a function
    public $functionRequiredMessage = "You must call a function before you can return.";

    //override php function from parent
    public function ask($message) : string {
        $result = parent::ask($message);
        if (!$this->didAskCallFunction()) {
            return $this->ask($this->functionRequiredMessage);
        }
        return $result;
    }

}