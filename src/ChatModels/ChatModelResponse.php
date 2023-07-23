<?php

namespace Adrenallen\AiAgentsLaravel\ChatModels;

class ChatModelResponse {
    public $message;
    public $functionCall;
    public $error;
    public $metadata;

    function __construct($message, $functionCall = null, $error = null, $metadata = []) {
        $this->message = $message;
        $this->functionCall = $functionCall;
        $this->error = $error;
        $this->metadata = $metadata;
    }
}