<?php

namespace Mygento\Yookassa;

class Config
{
    /**
     * @param string[] $methods
     */
    public function __construct(
        private int $shopId,
        private string $secret,
        private array $methods = [],
        private bool $twoStep = true,
        private bool $recurrentPayments = false,
    ) {}

    public function getShopId(): int
    {
        return $this->shopId;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    /**
     * @return string[]
     */
    public function getMethods(): array
    {
        return $this->methods;
    }

    public function isTwoStep(): bool
    {
        return $this->twoStep;
    }

    public function hasRecurrentPayments(): bool
    {
        return $this->recurrentPayments;
    }
}
