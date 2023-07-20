<?php

namespace Adrenallen\AiAgentsLaravel\AgentTraits;

trait GeocodingTrait {
    /**
     * @aiagent-description Gets the lat and long of the given location
     * @param string $city
     * @param string $state
     * @param string $countryCode The country code of the location, defaults to US
     * @return array the lat and long information of the location
     */
    public function getLatLongOfLocation(string $city, string $state, string $countryCode = "US") : array {
        $city = urlencode($city);
        $state = urlencode($state);
        $url = "https://api.openweathermap.org/geo/1.0/direct?q=" . $city . "," . $state . "," . $countryCode . "&limit=1&appid=" . config('openweathermap.api_key');
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