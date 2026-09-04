<?php

namespace HashtagCms\Workflows\Engine;

class WorkflowResponse
{
    private array $directives = [];
    private bool $success = true;
    private ?string $message = null;
    private array $data = [];

    /**
     * Transport-level HTTP status hint (e.g. 401 for an unauthorized run). Null
     * means "let the caller default it" (200 for the API controller). Not part
     * of the JSON body — read separately via getStatusCode().
     */
    private ?int $statusCode = null;

    public static function make(): self
    {
        return new self();
    }

    /**
     * An authentication failure: the credential was rejected, or the workflow is
     * flagged `auth_required` and no identity was resolved. Carries a 401 hint
     * and an error toast so SDUI clients can surface it.
     */
    public static function unauthorized(string $message = 'Authentication required.'): self
    {
        return (new self())
            ->setSuccess(false, $message)
            ->setStatusCode(401)
            ->toast($message, 'error');
    }

    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function toast(string $message, string $level = 'success'): self
    {
        $this->directives[] = [
            'type' => 'toast',
            'message' => $message,
            'level' => $level
        ];
        return $this;
    }

    public function addToast(string $message, string $level = 'success'): self
    {
        return $this->toast($message, $level);
    }

    public function mutateCart(array $cartData): self
    {
        $this->directives[] = array_merge(['type' => 'mutate_cart'], $cartData);
        return $this;
    }

    public function addDirective(array $directive): self
    {
        $this->directives[] = $directive;
        return $this;
    }

    public function openSheet(string $sheetId, array $params = []): self
    {
        $this->directives[] = [
            'type' => 'open_sheet',
            'sheetId' => $sheetId,
            'payload' => $params
        ];
        return $this;
    }

    public function navigate(string $target, array $params = []): self
    {
        $this->directives[] = [
            'type' => 'navigate',
            'target' => $target,
            'params' => $params
        ];
        return $this;
    }

    public function haptic(string $type = 'success'): self
    {
        $this->directives[] = [
            'type' => 'haptic',
            'intensity' => $type
        ];
        return $this;
    }

    public function setSuccess(bool $success, ?string $message = null): self
    {
        $this->success = $success;
        if ($message !== null) {
            $this->message = $message;
        }
        return $this;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function withData(array $data): self
    {
        $this->data = array_merge($this->data, $data);
        return $this;
    }

    public function getSuccess(): bool
    {
        return $this->success;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getDirectives(): array
    {
        return $this->directives;
    }

    /**
     * Replace the full directive list — used by capability negotiation to hand
     * back only the directives a given client can render.
     *
     * @param array<int, array<string, mixed>> $directives
     */
    public function setDirectives(array $directives): self
    {
        $this->directives = array_values($directives);
        return $this;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'message' => $this->message,
            'directives' => $this->directives,
            'data' => $this->data
        ];
    }
}
