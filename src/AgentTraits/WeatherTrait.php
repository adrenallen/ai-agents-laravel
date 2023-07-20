<?php

namespace Adrenallen\AiAgentsLaravel\AgentTraits;

trait WeatherTrait {
 
    /**
     * 
     * @aiagent-description Gets the current weather for the given location by the lat and long
     * @param float $lat The latitude of the location 
     * @param float $long The longitude of the location
     * @return array Information about the weather at the lat/long provided
     */
    public function getTodayWeather(float $lat, float $long) : array {
        $lat = urlencode($lat);
        $long = urlencode($long);
        $url = "https://api.openweathermap.org/data/2.5/weather?units=imperial&lat=" . $lat . "&lon=" . $long . "&appid=" . config('openweathermap.api_key');
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // return the result on success, rather than just true
        $result = curl_exec($ch);
        if(curl_error($ch)) {
            throw new \Exception(curl_error($ch));
        }
        curl_close($ch);
        $result = json_decode($result, true);
        return $result;
    }

}