<?php

namespace Alamia360\Responsibilities;

class ResponsibilityRegistry
{
    /** @var array<string, Responsibility> */
    protected array $responsibilities = [];

    public function define(string $situationType): Responsibility
    {
        $responsibility = new Responsibility($situationType);
        $this->responsibilities[$situationType] = $responsibility;
        return $responsibility;
    }

    public function for(string $situationType): ?Responsibility
    {
        return $this->responsibilities[$situationType] ?? null;
    }
}
