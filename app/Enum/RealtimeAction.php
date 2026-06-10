<?php

namespace App\Enum;

enum RealtimeAction: string
{
    case BULK_CREATED = 'bulk_created';
    case BULK_UPDATED = 'bulk_updated';
    case CREATED = 'created';
    case UPDATED = 'updated';
    case DELETED = 'deleted';

    case STARTED = 'started';
    case COMPLETED = 'completed';

    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    case REFRESH = 'refresh';

    case NOTIFICATION = 'notification';
}