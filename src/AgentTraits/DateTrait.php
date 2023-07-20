<?php

namespace Adrenallen\AiAgentsLaravel\AgentTraits;

trait DateTrait {

    /**
     * @aiagent-description Gets the current date
     * @param integer $timeZoneHoursOffset The number of hours to offset for the timezone
     * @return string The current date
     */
    public function currentDate($timeZoneHoursOffset = 0) : string {
        return (new \DateTime('now'))->format('Y-m-d H:i:s T');
    }

    /**
     * @aiagent-description Compares the two datetimes and returns the result
     * @param string $date1
     * @param string $date2
     * @return string The comparison result, 1 means the first date is bigger, 2 is second date bigger, 0 means equal
     */
    public function compareDates(string $date1, string $date2) : int {
        $date1 = new \DateTime($date1);
        $date2 = new \DateTime($date2);

        if ($date1 > $date2) {
            return 1;
        } else if ($date1 < $date2) {
            return 2;
        } else {
            return 0;
        }
    }

    /**
     * @aiagent-description Checks if the given date falls between the two given dates
     * @param string $dateToCheck The date to check
     * @param string $firstDate The first date in the range
     * @param string $secondDate The second date in the range
     * @return boolean True if the date falls between the two dates, false otherwise
     */
    public function dateFallBetweenDates(string $dateToCheck, string $firstDate, string $secondDate) : bool {
        $dateToCheck = new \DateTime($dateToCheck);
        $firstDate = new \DateTime($firstDate);
        $secondDate = new \DateTime($secondDate);

        if ($dateToCheck > $firstDate && $dateToCheck < $secondDate) {
            return true;
        } else {
            return false;
        }
    }
}