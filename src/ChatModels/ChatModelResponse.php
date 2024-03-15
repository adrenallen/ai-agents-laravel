<?php

namespace Adrenallen\AiAgentsLaravel\ChatModels;

class ChatModelResponse {
    function __construct(public $message, public $functionCalls = null, public $error = null, public $metadata = []) {}
}