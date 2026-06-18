<?php

namespace App\Support;

class ActivityLogger
{
    public static function log(
        string $action,
        $subject = null,
        array $properties = []
    ): void {
        activity('activity')
            ->causedBy(auth()->user())
            ->performedOn($subject)
            ->withProperties($properties)
            ->log($action);
    }
}