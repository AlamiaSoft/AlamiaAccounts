<?php

namespace Alamia360\Situations;

enum SituationStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case InProgress = 'in_progress';
    case Escalated = 'escalated';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
