<?php

namespace Adrenallen\AiAgentsLaravel\AgentTraits;

trait MathTrait {
    /**
     * add two numbers together
     *
     * @aiagent-description add two numbers together
     * @param float $a
     * @param float $b
     * @return float
     */
    public function add(float $a, float $b): float {
        return $a + $b;
    }

    /**
     * subtract b from a
     *
     * @aiagent-description subtract b from a
     * @param float $a
     * @param float $b
     * @return float
     */
    public function subtract(float $a, float $b): float {
        return $a - $b;
    }

    /**
     * multiply two numbers
     *
     * @aiagent-description multiply two numbers
     * @param float $a
     * @param float $b
     * @return float
     */
    public function multiply(float $a, float $b): float {
        return $a * $b;
    }

    /**
     * divide a by b
     *
     * @aiagent-description divide a by b
     * @param float $a
     * @param float $b
     * @return float
     */
    public function divide(float $a, float $b): float {
        return $a / $b;
    }
}