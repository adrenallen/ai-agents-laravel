<?php

namespace Adrenallen\AiAgentsLaravel\Agents;

class MathAgent extends BaseAgent {

    use \Adrenallen\AiAgentsLaravel\AgentTraits\MathTrait;
    
    public function getAgentDuty(): string {
        return "You are a helpful assistant with a specailization in math.";
    }

    

}