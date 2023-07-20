<?php

namespace Adrenallen\AiAgentsLaravel\AgentTraits;
use Twilio\Rest\Client;

trait SMSTrait {
    
    /**
     * 
     * @aiagent-description Send a text message to someone with the given contents
     * @param string $toNumber The number to send the text message to (this must be a real number)
     * @param string $content Content to send
     */
    public function sendSMS(string $toNumber, string $content): void {
        $fromNumber = config('twilio.phone_number');
        $accountSid = config('twilio.account_sid');
        $authToken  = config('twilio.auth_token');
        $client = new Client($accountSid, $authToken);
        try
        {
            // Use the client to do fun stuff like send text messages!
            $client->messages->create(
            // the number you'd like to send the message to
                $toNumber,
                [
                    // A Twilio phone number you purchased at twilio.com/console
                    'from' => $fromNumber, 
                    // the body of the text message you'd like to send
                    'body' => $content
                ]
            );
        }
        catch (\Exception $e)
        {
            throw $e;   // TODO - handle this gooder
        }
    }
}