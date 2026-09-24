<?php

namespace Alamia360\Observation;

enum ObservationSource: string
{
    case DomainEvent = 'domain_event';
    case StateScan = 'state_scan';
    case Scheduled = 'scheduled';
    case ExternalEvent = 'external_event';
    case UserAction = 'user_action';
    case SystemEvent = 'system_event';
}
