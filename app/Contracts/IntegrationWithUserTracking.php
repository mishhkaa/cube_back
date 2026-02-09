<?php

namespace App\Contracts;

use App\Models\TrackingUser;

interface IntegrationWithUserTracking
{
    public static function userBelongToIntegration(TrackingUser|null $user): bool;
}
