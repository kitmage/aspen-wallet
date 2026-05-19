<?php

namespace FluentBookingPro\App\Services;

use FluentBooking\App\Services\Libs\RRule\RRule;
use FluentBooking\App\Services\Libs\RRule\RSet;
use FluentBooking\App\Services\Helper;

class RecurringHelper
{
    public static function generateRRuleString($recurrenceConfig)
    {
        $frequency = strtoupper($recurrenceConfig['frequency']);
        $validFrequencies = ['DAILY', 'WEEKLY', 'MONTHLY', 'YEARLY'];
        if (!in_array($frequency, $validFrequencies)) {
            return null;
        }

        $rruleParts = [
            'FREQ'     => $frequency,
            'INTERVAL' => $recurrenceConfig['interval']
        ];

        // Handle end condition
        if (!empty($recurrenceConfig['count'])) {
            $rruleParts['COUNT'] = $recurrenceConfig['count'];
        } else if (!empty($recurrenceConfig['until'])) {
            $until = new \DateTime($recurrenceConfig['until'], new \DateTimeZone('UTC'));
            $rruleParts['UNTIL'] = $until->format('Ymd\THis\Z');
        }

        // Handle BYDAY for weekly/monthly
        if ($frequency === 'WEEKLY' && !empty($recurrenceConfig['byday'])) {
            $rruleParts['BYDAY'] = implode(',', $recurrenceConfig['byday']);
        } else if ($frequency === 'MONTHLY' && !empty($recurrenceConfig['bymonthday'])) {
            $rruleParts['BYMONTHDAY'] = $recurrenceConfig['bymonthday'];
        } else if ($frequency === 'MONTHLY' && !empty($recurrenceConfig['byday'])) {
            $rruleParts['BYDAY'] = $recurrenceConfig['byday'];
        }

        // Build RRule string
        $rruleString = 'RRULE:';
        $parts = [];
        foreach ($rruleParts as $key => $value) {
            $parts[] = $key . '=' . $value;
        }
        $rruleString .= implode(';', $parts);

        return $rruleString;
    }

    public static function getOccurrences($rruleString, $bookingData)
    {
        $duration = $bookingData['slot_minutes'];
        $dtstart  = gmdate('Ymd\THis\Z', strtotime($bookingData['start_time']));

        try {
            $rset = new RSet();
            $rrule = new RRule($rruleString, $dtstart);

            $rset->addRRule($rrule);
            $occurrences = $rset->getOccurrences();

            return array_map(function($occurrence) use ($duration) {
                return [
                    'start_time' => gmdate('Y-m-d H:i:s', strtotime($occurrence->format('Ymd\THis\Z'))),
                    'end_time'   => gmdate('Y-m-d H:i:s', strtotime($occurrence->format('Ymd\THis\Z')) + ($duration * 60))
                ];
            }, $occurrences);
        } catch (\Exception $e) {
            Helper::debugLog([
                'message' => $e->getMessage(),
                'method' => __METHOD__,
                'rrule' => $rruleString
            ]);
            return [];
        }
    }
}