<?php

namespace Adrenallen\AiAgentsLaravel\AgentFactories;

use Adrenallen\AiAgentsLaravel\Agents\BaseAgent;

/**
 * Factory to create a new TestingAgent using ChatGPT model
 */
class TestingAgentChatGPTFactory {

    /**
     * Creates and returns a new instance of the agent
     *
     * @return BaseAgent
     */
    static public function create() : BaseAgent {
        return new \Adrenallen\AiAgentsLaravel\Agents\TestingAgent(
            new \Adrenallen\AiAgentsLaravel\ChatModels\ChatGPT()
        );
    }
}