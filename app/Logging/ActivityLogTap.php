<?php

namespace App\Logging;

use Spatie\Activitylog\Contracts\Activity;

class ActivityLogTap
{
    public function __invoke(Activity $activity): void
    {
        if (request()) {
            $activity->properties = $activity->properties->merge([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        }
    }
}