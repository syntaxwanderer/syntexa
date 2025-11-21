<?php

declare(strict_types=1);

namespace Syntexa\UserFrontend\Application\Input\Http\Traits;

use Syntexa\Core\Attributes\AsRequestPart;
use Syntexa\UserFrontend\Application\Input\Http\LoginFormRequest;

#[AsRequestPart(base: LoginFormRequest::class)]
trait LoginFormRequiredFieldsTrait
{
    public ?string $email = null;
    public ?string $password = null;
    public bool $remember = false;
    protected ?\Syntexa\Core\Request $httpRequest = null;

    public function setHttpRequest(\Syntexa\Core\Request $request): void
    {
        $this->httpRequest = $request;
    }

    public function getEmail(): ?string
    {
        return $this->email ?: ($this->httpRequest ? $this->httpRequest->getPost('email') : null);
    }

    public function getPassword(): ?string
    {
        return $this->password ?: ($this->httpRequest ? $this->httpRequest->getPost('password') : null);
    }

    public function getRemember(): bool
    {
        return $this->remember || ($this->httpRequest && ($this->httpRequest->getPost('remember') === 'on' || $this->httpRequest->getPost('remember') === '1'));
    }

    public function isPost(): bool
    {
        return $this->httpRequest ? $this->httpRequest->isPost() : false;
    }

    public function getMethod(): string
    {
        return $this->httpRequest ? $this->httpRequest->getMethod() : 'GET';
    }

    public function getServerParam(string $key, string $default = ''): string
    {
        if ($this->httpRequest === null) {
            return $default;
        }
        return $this->httpRequest->getServer($key, $default);
    }
}

