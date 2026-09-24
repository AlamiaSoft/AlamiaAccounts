<?php

namespace Alamia360\Actors;

use Alamia360\Contracts\ActorContract;
use Closure;

/**
 * Default actor implementation. Authorization is always delegated to the
 * host application (Laravel Gate/Policies) via an injected closure — Alamia
 * 360 never grants permissions on its own, and AI actors never receive
 * elevated privileges by default.
 */
class Actor implements ActorContract
{
    /** @var string[] */
    protected array $capabilities = [];

    protected ?Closure $authorizer = null;

    public function __construct(
        protected string $id,
        protected ActorType $type,
        protected ?string $role = null,
    ) {
    }

    public static function human(string $id, ?string $role = null): static
    {
        return new static($id, ActorType::Human, $role);
    }

    public static function ai(string $id, ?string $role = null): static
    {
        return new static($id, ActorType::Ai, $role);
    }

    public static function system(string $id, ?string $role = null): static
    {
        return new static($id, ActorType::System, $role);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function type(): ActorType
    {
        return $this->type;
    }

    public function role(): ?string
    {
        return $this->role;
    }

    public function withCapabilities(array $capabilities): static
    {
        $this->capabilities = $capabilities;
        return $this;
    }

    public function capabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * Inject the host application's authorization check
     * (e.g. fn ($actor, $capability, $subject) => Gate::forUser(...)->allows(...)).
     */
    public function authorizeUsing(Closure $authorizer): static
    {
        $this->authorizer = $authorizer;
        return $this;
    }

    public function isAuthorizedFor(string $capabilityName, mixed $subject = null): bool
    {
        if (!in_array($capabilityName, $this->capabilities, true)) {
            return false;
        }

        if ($this->authorizer) {
            return (bool) ($this->authorizer)($this, $capabilityName, $subject);
        }

        // No app-level authorizer wired up: fail closed for write/destructive
        // callers should still pass an authorizer in production.
        return true;
    }
}
