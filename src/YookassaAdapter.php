<?php

namespace Mygento\Yookassa;

use Mygento\Payment\AbstractAdapter;
use Mygento\Payment\Api\InvoiceInterface;
use Mygento\Payment\Api\OrderInterface;
use YooKassa\Client;
use Symfony\Component\HttpKernel\Kernel;
use Mygento\Payment\Service\Management;

class YookassaAdapter extends AbstractAdapter
{
    private const CODE = 'yookassa';

    public function __construct(
        private Config $config,
        Management $service,
    ) {
        parent::__construct($service);
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

    public function authorize(string $method, InvoiceInterface $invoice): string
    {
        $client = $this->getClient();
        $orderId = $invoice->getOrder();
        $paymentId = uniqid('', true);
        $payment = [
            'amount' => [
                'value' => $invoice->getAmount(),
                'currency' => $invoice->getCurrency(),
            ],
            'payment_method_data' => [
                'type' => $method,
            ],
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $this->getService()->getRedirectUrl(),
            ],
            'capture' => !$this->isTwoStepPayment(),
            'description' => $invoice->getDescription() ?? 'Order #' . $orderId,
            'metadata' => [
                'orderNumber' => $orderId,
            ],
        ];

        $response = $client->createPayment(
            $payment,
            $paymentId,
        );

        $url = $response?->getConfirmation()?->getConfirmationUrl() ?? '';

        $this->getService()->createRegistration(self::CODE, $orderId, $response->getId(), $url, $this->isTwoStepPayment() ? 2 : 1);

        return $url;
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

    public function sale(string $method, InvoiceInterface $invoice): string
    {
        $client = $this->getClient();

        return '';
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
