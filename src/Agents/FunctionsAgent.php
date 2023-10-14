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

    //override php function from parent
    public function ask($message) : string {
        $result = parent::ask($message);
        if (!$this->didAskCallFunction()) {
            return $this->ask($this->getFunctionRequiredMessage());
        }
        return $result;
    }

    public function getFunctionRequiredMessage() : string {
        return "You must call a function before you can return.";
    }

}