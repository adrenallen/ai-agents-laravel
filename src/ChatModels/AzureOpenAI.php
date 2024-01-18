<?php

namespace Adrenallen\AiAgentsLaravel\ChatModels;

use OpenAI;

class AzureOpenAI extends ChatGPT
{
    protected $model;
    protected $client;
    protected $openAiOptions;

    // class constructor
    /**
     * @param string $model
     * @param array $context
     * @param array $openAiOptions
     */
    public function __construct($context = [], $prePrompt = "", $functions = [], $model = 'gpt-3.5-turbo',  $openAiOptions = [])
    {

        parent::__construct($context, $prePrompt, $functions);

        $domain = config('azure_openai.custom_domain');
        $deployment = config('azure_openai.deployment');
        $api_key = config('azure_openai.api_key');
        $api_version = config('azure_openai.api_version');
        if (is_null($domain)) {
            throw new \RuntimeException('The Azure OpenAI configuration value for custom_domain is not set.');
        } elseif (is_null($deployment)) {
            throw new \RuntimeException('The Azure OpenAI configuration value for deployment is not set.');
        } elseif (is_null($api_key)) {
            throw new \RuntimeException('The Azure OpenAI configuration value for api_key is not set.');
        } elseif (is_null($api_version)) {
            throw new \RuntimeException('The Azure OpenAI configuration value for api_version is not set.');
        }

        $this->client = OpenAI::factory()
            ->withBaseUri("$domain.openai.azure.com/openai/deployments/$deployment")
            ->withHttpHeader('api-key', $api_key)
            ->withQueryParam('api-version', $api_version)
            ->make();
        $this->context = $context;
        $this->openAiOptions = $openAiOptions;
        $this->model = $model;  // this is not used by Azure OpenAI since the deployment dictates the model used
    }
}
