# AI Agents for Laravel
[![Latest Stable Version](https://poser.pugx.org/adrenallen/ai-agents-laravel/v)](https://packagist.org/packages/adrenallen/ai-agents-laravel) [![Latest Unstable Version](https://poser.pugx.org/adrenallen/ai-agents-laravel/v/unstable)](https://packagist.org/packages/adrenallen/ai-agents-laravel)  [![License](https://poser.pugx.org/adrenallen/ai-agents-laravel/license)](https://packagist.org/packages/adrenallen/ai-agents-laravel) [![PHP Version Require](https://poser.pugx.org/adrenallen/ai-agents-laravel/require/php)](https://packagist.org/packages/adrenallen/ai-agents-laravel)

Building with AI shouldn't be difficult, and AI Agents does its best to make it easy to build with AI inside of Laravel.

✍️ Spend more time writing code you care about, just provide comments and let the system take care of the rest!

📦 Agents are highly composable . Simply include the trait you need to give your AI the right capabilities for the job.

```php
class TextingAgent extends BaseAgent {

    use \Adrenallen\AiAgentsLaravel\AgentTraits\SMSTrait; // Access to send SMS via Twilio, all handled automatically

    public string $prePrompt = "You are a helpful assistant";   // Pre-prompt
}
```

🔧 Need custom functionality or have an idea for a new AgentTrait? Create your own! Just follow the comment structure and the system will do the rest to ensure the AI understand and can use your functions!

```php
/**
* @aiagent-description Adds two numbers together
* @param int $a
* @param int $b
* @return int
*/
public function add(int $a, int $b): int {
    return $a + $b;
}
```


🚀 Create a new AI Agent in <20 lines of code!

# Table of Contents
- [🔧 Setup](#-setup)
- [👨‍💻 Usage](#-usage)
  - [In Console/Testing](#in-consoletesting)
  - [In Code](#in-code)
- [🤖 Creating a new agent](#-creating-a-new-agent)
  - [Defining an agent function](#defining-an-agent-function)
- [🧰 Agent Traits](#-agent-traits)
- [📝 Chat Models](#-chat-models)
  - [Currently Supported](#currently-supported)
  - [Adding a new chat model](#adding-a-new-chat-model)
- [❤️ Contributing](#️-contributing)

## 🔧 Setup 

Install via composer

`composer require adrenallen/ai-agents-laravel`

You will need to publish the configuration files and fill out details based on the features you wish to use. You can publish the config files by running the following command:

`php artisan vendor:publish --provider="Adrenallen\AiAgentsLaravel\AiAgentsLaravelServiceProvider"`

## 👨‍💻 Usage 

### In Console/Testing 
You can test chatting with an agent directly by using the provided artisan command

`php artisan ai:chat <Agent Class>`

For example, to chat with the included `TestingAgent`

`php artisan ai:chat TestingAgent`

You can type `exit` to exit the chat.

### In Code

```php
$chat = new \Adrenallen\AiAgentsLaravel\ChatModels\ChatGPT();
// or
$chat = new \Adrenallen\AiAgentsLaravel\ChatModels\AzureOpenAI();
// or
$chat = new \Adrenallen\AiAgentsLaravel\ChatModels\AnthropicClaude();

$agent = new \Adrenallen\AiAgentsLaravel\Agents\TestingAgent($chat); // Ensures the agent gets a pre-prompt at creation
$agent->ask("Hello, is this thing on?"); // Yes, I'm here. How can I assist you today?
$agent->lastCallMetadata;
/*
return $agent->lastCallMetadata;
= [
    "id" => "chatcmpl-8123ABC",
    "created" => 1705545737,
    "model" => "gpt-4",
    "systemFingerprint" => "fp_l33t123",
    "usage" => OpenAI\Responses\Chat\CreateResponseUsage {#5004
      +promptTokens: 365,
      +completionTokens: 17,
      +totalTokens: 382,
    },
  ]
*/
```

## 🤖 Creating a new agent 
To create a new agent you will want to extend the `BaseAgent` class and define any additional functionality.

**NOTE: If you want to require your agent to always call a function, you can extend the `FunctionsAgent` instead!**

The `prePrompt` property is the pre-prompt that is passed to the chat model. This should describe how you want the agent to think and act.

You can use traits under `AgentTraits` to pull in specific functionality you may need.

i.e. if you want your agent to be able to send text messages, you could pull in the `SMSTrait` on your agent class. The bot will automatically know it is able to send text messages.

This is an example of an agent that can send text messages, do math, and get the weather.

**This is the total code required to create an agent.**
```php
class TestingAgent extends BaseAgent {

    use \Adrenallen\AiAgentsLaravel\AgentTraits\SMSTrait; // Access to send SMS via Twilio
    use \Adrenallen\AiAgentsLaravel\AgentTraits\MathTrait; // Access to math functions
    use \Adrenallen\AiAgentsLaravel\AgentTraits\DateTrait;  // Access to date functions
    use \Adrenallen\AiAgentsLaravel\AgentTraits\WeatherTrait; // Access to openweathermap API

    public string $prePrompt = "You are a helpful assistant";   // Pre-prompt
}
```

### Defining an agent function
To define an agent function, you should follow php DocBlock to describe the params, return type, and method.

For the agent to have access to the function, you must include an additional PHPDoc block param called `@aiagent-description`. This must be a string that describes the function. Any functions that include that property in the agent's class will automatically be made available to the agent.

Example of the `add` function:
```php
    /**
     * @param int $a
     * @param int $b
     * @return int
     * @aiagent-description Adds two numbers together
     */
    public function add(int $a, int $b): int {
        return $a + $b;
    }
```

## 🧰 Agent Traits
Agent Traits can be used to plug and play functionality for an agent. Some are included in this package under the `AgentTraits` namespace.

`DateTrait` - Provides access to date functions (i.e. `compareDates` or `getCurrentDate`)

`MathTrait` - Provides access to math functions (i.e. `add` or `subtract`)

`SMSTrait` - Provides access to send SMS messages via Twilio (i.e. `sendSMS`)

`WeatherTrait` - Provides access to weather functions (i.e. `getWeather`)

`GeocodingTrait` - Provides access to geocoding functions (i.e. `getLatLongOfLocation`)


It is highly encouraged that you place re-usable functions in a trait, and then pull that trait into your agent.


## 📝 Chat Models

### Currently Supported
- GPT-3.5-turbo
- GPT-4
- Azure OpenAI
- Anthropic Claude

### Adding a new chat model
New models can be added by extending `AbstractChatModel`. This class provides the basic functionality required to interact with the chat model.

## ❤️ Contributing
Opening new issues is encouraged if you have any questions, issues, or ideas.

Pull requests are also welcome!

[See our contribution guide](CONTRIBUTING.md)

## Star history
[![Star History Chart](https://api.star-history.com/svg?repos=adrenallen/ai-agents-laravel&type=Date)](https://star-history.com/#adrenallen/ai-agents-laravel&Date)
