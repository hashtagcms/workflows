<?php

namespace HashtagCms\Workflows\Engine;

/**
 * Normalized identity of whoever is executing a workflow.
 *
 * This is the single shape the engine understands, regardless of where the
 * identity actually came from — the local Laravel guard, a verified SSO token,
 * a forwarded gateway header, or a value handed straight into
 * `Workflows::execute(..., identity: ...)`. Producing one of these is the job of
 * a {@see \HashtagCms\Workflows\Contracts\WorkflowIdentityResolver}.
 *
 * `id` is intentionally `int|string|null`: an integer id is treated as a local
 * user (it maps to `workflow_logs.user_id`), while a string id is an external
 * subject (a UUID / SSO `sub`) that has no local users-table row. Callers read
 * {@see localUserId()} and {@see externalUserId()} instead of touching `id`
 * directly so that routing never has to be re-derived.
 */
final class WorkflowIdentity
{
    /**
     * @param int|string|null $id       Subject id. int = local user, string = external subject, null = anonymous.
     * @param array           $claims   Normalized claims (email, roles, tenant, …); empty when unknown.
     * @param mixed           $user     Eloquent user model when one was loaded, else null.
     * @param string|null     $provider SSO provider alias that resolved this, or null for local/explicit.
     * @param bool            $failed   True when a credential was present but rejected (reject-mode providers).
     * @param array           $raw      Opt-in raw passthrough of the validator's response (exposed as
     *                                  {{ identity.raw.* }}). Empty unless a provider's `identity.raw`
     *                                  mapping populates it. Prefer curated `claims`; use `raw` only when
     *                                  you knowingly accept coupling to the provider's response shape.
     */
    public function __construct(
        public readonly int|string|null $id = null,
        public readonly array $claims = [],
        public readonly mixed $user = null,
        public readonly ?string $provider = null,
        public readonly bool $failed = false,
        public readonly array $raw = [],
    ) {}

    /** No identity could be resolved — a valid, non-error "nobody". */
    public static function anonymous(): self
    {
        return new self();
    }

    /** A credential was supplied but is invalid/expired under a reject-mode provider. */
    public static function rejected(?string $provider = null): self
    {
        return new self(provider: $provider, failed: true);
    }

    /** Build from a (local) user model, deriving the id from `$user->id`. */
    public static function fromUser(mixed $user, array $claims = [], ?string $provider = null): self
    {
        if ($user === null) {
            return self::anonymous();
        }

        return new self(id: $user->id ?? null, claims: $claims, user: $user, provider: $provider);
    }

    /**
     * Normalize whatever a caller passed into `execute(..., identity: ...)`:
     * an already-built WorkflowIdentity (returned as-is), a user/model object
     * (id taken from `->id`), a scalar id (int|string), or null (anonymous).
     */
    public static function from(mixed $value): self
    {
        if ($value instanceof self) {
            return $value;
        }
        if ($value === null) {
            return self::anonymous();
        }
        if (is_int($value) || is_string($value)) {
            return new self(id: $value);
        }
        if (is_object($value)) {
            return new self(id: $value->id ?? null, user: $value);
        }

        return self::anonymous();
    }

    public function isAuthenticated(): bool
    {
        return $this->id !== null;
    }

    public function isAnonymous(): bool
    {
        return $this->id === null && ! $this->failed;
    }

    /**
     * Local integer user id for `workflow_logs.user_id`, or null when the subject
     * is external. Only an integer id maps to the local users table.
     */
    public function localUserId(): ?int
    {
        return is_int($this->id) ? $this->id : null;
    }

    /**
     * External subject reference (stringified) for logging, or null when the
     * identity is a local user or anonymous.
     */
    public function externalUserId(): ?string
    {
        return ($this->id !== null && ! is_int($this->id)) ? (string) $this->id : null;
    }

    public function claim(string $key, mixed $default = null): mixed
    {
        return $this->claims[$key] ?? $default;
    }
}
