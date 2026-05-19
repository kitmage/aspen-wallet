<?php

namespace FluentBookingPro\App\Services;

use FluentBooking\App\Models\Calendar;
use FluentBooking\App\Models\CalendarSlot;
use FluentBooking\App\Services\TimeSlotService;

class CollectiveTimeSlotService extends TimeSlotService
{
    public function __construct(Calendar $calendar, CalendarSlot $calendarSlot)
    {
        parent::__construct(
            $calendar,
            $calendarSlot
        );
    }

    protected function getRangedValidSlots($ranges, $duration, $bookedSlots, $cutOutTime, $maxBookingTime, $timezoneInfo, $rangedSlots = [], $hostId = null)
    {
        if (!$this->calendarSlot->isCollectiveDefaultSchedule()) {
            return call_user_func_array(
                [parent::class, 'getRangedValidSlots'],
                func_get_args()
            );
        }

        $hostRangedSlots = [];
        $hostIds = $this->calendarSlot->getHostIds($this->hostId);

        foreach ($hostIds as $hostId) {
            $timezoneInfo = $this->getTimezoneInfo($hostId);
            $hostRangedSlots[$hostId] = parent::getRangedValidSlots($ranges, $duration, $bookedSlots, $cutOutTime, $maxBookingTime, $timezoneInfo, $rangedSlots, $hostId);
        }

        $totalHosts = count($hostIds);
        $firstHostId = reset($hostIds);
        $firstHostSlots = $hostRangedSlots[$firstHostId];
        $rangedValidSlots = [];

        foreach ($firstHostSlots as $date => $slots) {
            foreach ($slots as $slot) {
                $hostAvailable = 1;
                $hasDateSlots = true;
                foreach ($hostIds as $hostId) {
                    if ($hostId == $firstHostId) {
                        continue;
                    }

                    if (!isset($hostRangedSlots[$hostId][$date])) {
                        $hasDateSlots = false;
                        break;
                    }

                    if (in_array($slot, $hostRangedSlots[$hostId][$date])) {
                        $hostAvailable++;
                    }
                }

                if (!$hasDateSlots) {
                    break;
                }

                if ($hostAvailable == $totalHosts) {
                    $rangedValidSlots[$date][] = $slot;
                }
            }
        }

        return $rangedValidSlots;
    }
}
