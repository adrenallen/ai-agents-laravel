<?php

namespace Adrenallen\AiAgentsLaravel\Agents;

class TestingAgent extends BaseAgent {

    use \Adrenallen\AiAgentsLaravel\AgentTraits\SMSTrait;
    use \Adrenallen\AiAgentsLaravel\AgentTraits\MathTrait;
    use \Adrenallen\AiAgentsLaravel\AgentTraits\DateTrait;
    use \Adrenallen\AiAgentsLaravel\AgentTraits\WeatherTrait;

    public function getAgentDuty(): string {
        return "You are a helpful assistant";
    }
}