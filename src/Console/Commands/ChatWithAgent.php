<?php

namespace Adrenallen\AiAgentsLaravel\Console\Commands;

use Illuminate\Console\Command;

class ChatWithAgent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai:chat {agent : The class name of the agent to chat with}}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Open a chat with an agent of your choice';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $agentClass = new static("\Adrenallen\AiAgentsLaravel\Agents\\" . $this->argument('agent'));
        $agent = new $agentClass(new \Adrenallen\AiAgentsLaravel\ChatModels\ChatGPT());

        $this->info($agentClass . " is now chatting with you.");
        $this->info("Send the message 'exit' to exit the chat.");

        $userMsg = $this->ask("You");
        while($userMsg != "exit") {   
            $agentMsg = $agent->ask($userMsg);
            $this->info("Agent: " . $agentMsg);
            $userMsg = $this->ask("You");
        }

        $this->info("Agent: Thanks for chatting!");
    }
}
