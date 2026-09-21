<?php

namespace App\Domain\Game\Services;

use Carbon\CarbonImmutable;

class GameAutomationClock
{
    public function nextProcessingAt(): CarbonImmutable
    {
        $now = CarbonImmutable::instance(now());
        [$hour, $minute] = array_map('intval', explode(':', config('game.automation.daily_at')));
        $next = $now->setTime($hour, $minute);

        return $next->lessThanOrEqualTo($now) ? $next->addDay() : $next;
    }
}
