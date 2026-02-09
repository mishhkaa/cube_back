<?php

namespace App\Models\Enums;

enum JobStatus: string
{
    case NEW = 'new';
    case PROCESSING = 'processing';
    case DONE = 'done';
    case WARNING = 'warning';
    case ERROR = 'error';
}
