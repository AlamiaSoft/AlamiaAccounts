<?php

namespace Alamia360\Actors;

enum ActorType: string
{
    case Human = 'human';
    case Ai = 'ai';
    case System = 'system';
}
