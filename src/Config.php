<?php

namespace Mygento\Yookassa;

class Config
{
    public function __construct(
        private int $shopId,
        private string $secret,
        private array $methods = [],
        private bool $twoStep = true,
        private bool $testMode = true,
    ) {}

    public function getShopId(): int
    {
        return $this->shopId;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function isTwoStep(): bool
    {
        return $this->twoStep;
    }

    public function getTestMode(): bool
    {
        return $this->testMode;
    }
}
