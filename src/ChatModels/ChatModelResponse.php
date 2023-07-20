<?php

namespace Adrenallen\AiAgentsLaravel\ChatModels;

class ChatModelResponse {
    public $message;
    public $functionCall;
    public $error;

    function __construct($message, $functionCall = null, $error = null) {
        $this->message = $message;
        $this->functionCall = $functionCall;
        $this->error = $error;
    }
}