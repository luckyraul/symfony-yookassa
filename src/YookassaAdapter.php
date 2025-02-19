<?php

namespace Mygento\Yookassa;

use Mygento\Payment\AbstractAdapter;
use Mygento\Payment\Api\OrderInterface;
use YooKassa\Client;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class YookassaAdapter extends AbstractAdapter
{
    private const CODE = 'yookassa';

    public function __construct(
        private Config $config,
        UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct($urlGenerator);
    }

    public function isTwoStepPayment(): bool
    {
        return $this->config->isTwoStep();
    }

    public function supportsTwoStepPayment(): bool
    {
        return true;
    }

    public static function getCode(): string
    {
        return self::CODE;
    }

    public function authorize(string $method, string $amount): void
    {
        $client = $this->getClient();
        $orderId = '123123';
        $paymentId = uniqid('', true);
        $payment = [
            'amount' => [
                'value' => $amount,
                'currency' => 'RUB',
            ],
            'confirmation' => [
                'type' => 'redirect',
                'locale' => 'ru_RU',
                'return_url' => $this->getRedirectUrl(),
            ],
            'capture' => !$this->isTwoStepPayment(),
            'description' => 'Заказ ' . $paymentId,
            'metadata' => [
                'orderNumber' => $orderId,
            ],
        ];
        dd($payment);
        $response = $client->createPayment(
            $payment,
            $paymentId,
        );
    }

    public function canCapture(OrderInterface $order): bool
    {
        return true;
    }

    public function canRefund(OrderInterface $order): bool
    {
        return true;
    }

    public function canSale(OrderInterface $order): bool
    {
        return true;
    }

    public function canVoid(OrderInterface $order): bool
    {
        return true;
    }

    public function capture(OrderInterface $order): void {}

    public function refund(OrderInterface $order): void {}

    public function registerAuthorizeNotification(string $amount): void {}

    public function registerCaptureNotification(string $amount): void {}

    public function registerRefundNotification(string $amount): void {}

    public function registerSaleNotification(string $amount): void {}

    public function registerVoidNotification(string $amount): void {}

    public function sale(string $method, string $amount): void
    {
        $client = $this->getClient();
    }

    public function void(OrderInterface $order): void {}

    private function getClient(): Client
    {
        $client = new Client();
        $client->setAuth($this->config->getShopId(), $this->config->getSecret());
        $userAgent = $client->getApiClient()->getUserAgent();
        $userAgent->setFramework('Symfony', Kernel::VERSION);
        $userAgent->setModule('Mygento.YooKassa', MygentoYookassaBundle::VERSION);

        return $client;
    }
}
