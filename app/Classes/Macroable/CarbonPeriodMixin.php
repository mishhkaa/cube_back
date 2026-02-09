<?php

namespace App\Classes\Macroable;

use Carbon\CarbonPeriod;
use Closure;
use InvalidArgumentException;

class CarbonPeriodMixin
{
    public function rangeChunks(): Closure
    {
        /**
         * @param  int  $days
         * @return \Illuminate\Support\Collection<CarbonPeriod>
         */
        return function (int $days) {
            /** @var CarbonPeriod $this */
            $start = $this->getStartDate();
            $end = $this->getEndDate();

            if (!$end) {
                throw new InvalidArgumentException('CarbonPeriod must have both start and end dates.');
            }

            if ($start->gt($end)) {
                throw new InvalidArgumentException('Start date must be before or equal to end date.');
            }

            $next = ($start->copy())->addDays($days);
            if ($end->lt($next)) {
                return collect([CarbonPeriod::between($start, $end)->copy()]);
            }

            $ranges = collect([CarbonPeriod::between($start, $next->copy())->copy()]);

            $next->addDay();
            while ($end->gte($next)) {
                $startPeriod = $next->copy();
                $next->addDays($days);

                if ($end->lte($next)) {
                    $ranges->add(CarbonPeriod::between($startPeriod, $end)->copy());
                    break;
                }

                $ranges->add(CarbonPeriod::between($startPeriod, $next->copy())->copy());
                $next->addDay();
            }

            return $ranges;
        };
    }
}
