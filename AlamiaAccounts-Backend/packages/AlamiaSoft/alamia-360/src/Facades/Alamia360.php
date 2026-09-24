<?php

namespace Alamia360\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Alamia360\Semantic\EntityDefinition entity(string $name)
 * @method static \Alamia360\Capabilities\Capability capability(string $name)
 * @method static \Alamia360\Responsibilities\Responsibility responsibility(string $situationType)
 * @method static \Alamia360\Observation\Observer observer()
 * @method static \Alamia360\Situations\SituationRegistry situations()
 * @method static \Alamia360\Orchestration\Orchestrator orchestrator()
 * @method static \Alamia360\Context\ContextBuilder context()
 */
class Alamia360 extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'alamia360';
    }
}
