<?php

namespace Adrenallen\AiAgentsLaravel;

use Adrenallen\AiAgentsLaravel\Console\Commands\ChatWithAgent;

class AiAgentsLaravelServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/openai.php' => config_path('openai.php'),
            __DIR__.'/../config/openweathermap.php' => config_path('openweathermap.php'),
            __DIR__.'/../config/twilio.php' => config_path('twilio.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                ChatWithAgent::class
            ]);
        }
    }
}