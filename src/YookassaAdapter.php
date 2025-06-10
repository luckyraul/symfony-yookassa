<?php

namespace Mygento\Yookassa;

use Mygento\Payment\AbstractAdapter;
use Mygento\Payment\Api\InvoiceInterface;
use Mygento\Payment\Api\OrderInterface;
use Mygento\Payment\Entity\Transaction;
use Mygento\Payment\Entity\Registration;
use Mygento\Payment\Model\PaymentInfo;
use Mygento\Payment\Service\Basic;
use Symfony\Component\HttpKernel\Kernel;
use YooKassa\Client;
use YooKassa\Model\Payment\PaymentStatus;
use YooKassa\Model\Refund\RefundStatus;
use YooKassa\Request\Payments\PaymentResponse;

class YookassaAdapter extends AbstractAdapter
{
    private const CODE = 'yookassa';

    public function __construct(
        private Config $config,
        Basic $service,
    ) {
        parent::__construct($service);
    }

    public function isTwoStepPayment(): bool
    {
        return $this->config->isTwoStep();
    }

    public function supportsRegistration(): bool
    {
        return true;
    }

    public function supportsTwoStepPayment(): bool
    {
        return true;
    }

    public static function getCode(): string
    {
        return self::CODE;
    }

    public function authorize(string $method, InvoiceInterface $invoice): ?Registration
    {
        $currentRegistration = $this->getService()->findRegistration(self::CODE, $invoice->getOrder());
        $client = $this->getClient();
        $orderId = $invoice->getOrder();
        $try = $currentRegistration ? $currentRegistration->getTry() : 1;
        $payment = [
            'amount' => [
                'value' => $invoice->getAmount(),
                'currency' => $invoice->getCurrency(),
            ],
            'capture' => false,
            'description' => $invoice->getDescription() ?? 'Order #' . $orderId,
            'save_payment_method' => $this->config->hasRecurrentPayments(),
            'metadata' => [
                'orderNumber' => $orderId,
            ],
        ];
        $recurrentPayment = $invoice->getRecurrentPaymentIdentifier() ? [
            'payment_method_id' => $invoice->getRecurrentPaymentIdentifier(),
        ] : [
            'payment_method_data' => [
                'type' => $method,
            ],
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $this->getService()->getCheckUrl(self::CODE, $orderId),
            ],
        ];

        $response = $client->createPayment(
            array_merge($payment, $recurrentPayment),
            $orderId . '-auth-' . $try,
        );

        if ($response && $response->getId()) {
            $url = $response->getConfirmation()?->getConfirmationUrl() ?? '';

            return $this->getService()->createRegistration(self::CODE, $orderId, $response->getId(), $url, $this->isTwoStepPayment() ? 2 : 1);
        }
        if ($currentRegistration) {
            $this->getService()->resetRegistration(self::CODE, $invoice->getOrder());
        }

        return $currentRegistration;
    }

    public function canSale(OrderInterface $order): bool
    {
        return true;
    }

    public function capture(string $paymentIdentifier, string $amount, string $currency, ?Transaction $parent = null): void
    {
        $key = $paymentIdentifier . '-capture';
        $client = $this->getClient();
        /** @var PaymentResponse|null $paymentResponse */
        $paymentResponse = $client->capturePayment([
            'amount' => [
                'value' => $amount,
                'currency' => $currency,
            ],
        ], $paymentIdentifier, $key);
        if (null === $paymentResponse || null === $paymentResponse->getId() || null === $paymentResponse->getAmount()) {
            throw new \Exception('Unexpected capture payment response');
        }
        $metadata = $paymentResponse->getMetadata()?->toArray() ?? [];
        if (!isset($metadata['orderNumber'])) {
            throw new \Exception('Unexpected capture payment metadata');
        }
        if (PaymentStatus::SUCCEEDED === $paymentResponse->getStatus()) {
            $this->getService()->createTransaction(
                self::CODE,
                $metadata['orderNumber'],
                $paymentResponse->getId(),
                $key,
                Transaction::CAPTURE,
                $paymentResponse->getAmount()->getValue(),
                $paymentResponse->getAmount()->getCurrency(),
                $parent,
                $paymentResponse->jsonSerialize(),
            );
        }
    }

    public function refund(string $paymentIdentifier, string $amount, string $currency, Transaction $parent): void
    {
        $key = $paymentIdentifier . '-refund';
        $client = $this->getClient();
        $paymentResponse = $client->createRefund([
            'payment_id' => $paymentIdentifier,
            'amount' => [
                'value' => $amount,
                'currency' => $currency,
            ],
        ], $key);
        if (null === $paymentResponse || null === $paymentResponse->getId() || null === $paymentResponse->getAmount()) {
            throw new \Exception('Unexpected refund payment response');
        }
        if (RefundStatus::SUCCEEDED === $paymentResponse->getStatus()) {
            $this->getService()->createTransaction(
                self::CODE,
                $parent->getOrder(),
                $paymentResponse->getId(),
                $key,
                Transaction::REFUND,
                $paymentResponse->getAmount()->getValue(),
                $paymentResponse->getAmount()->getCurrency(),
                $parent,
            );
        }
    }

    public function registerAuthorizeNotification(string $amount): void {}

    public function registerCaptureNotification(string $amount): void {}

    public function registerRefundNotification(string $amount): void {}

    public function registerSaleNotification(string $amount): void {}

    public function registerVoidNotification(string $amount): void {}

    public function sale(string $method, InvoiceInterface $invoice): ?Registration
    {
        $currentRegistration = $this->getService()->findRegistration(self::CODE, $invoice->getOrder());
        $client = $this->getClient();
        $orderId = $invoice->getOrder();
        $try = $currentRegistration ? $currentRegistration->getTry() : 1;
        $payment = [
            'amount' => [
                'value' => $invoice->getAmount(),
                'currency' => $invoice->getCurrency(),
            ],
            'capture' => true,
            'description' => $invoice->getDescription() ?? 'Order #' . $orderId,
            'save_payment_method' => $this->config->hasRecurrentPayments(),
            'metadata' => [
                'orderNumber' => $orderId,
            ],
        ];

        $recurrentPayment = $invoice->getRecurrentPaymentIdentifier() ? [
            'payment_method_id' => $invoice->getRecurrentPaymentIdentifier(),
        ] : [
            'payment_method_data' => [
                'type' => $method,
            ],
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $this->getService()->getCheckUrl(self::CODE, $orderId),
            ],
        ];

        $response = $client->createPayment(
            array_merge($payment, $recurrentPayment),
            $orderId . '-sales-' . $try,
        );

        if ($response && $response->getId()) {
            $url = $response->getConfirmation()?->getConfirmationUrl() ?? '';

            return $this->getService()->createRegistration(self::CODE, $orderId, $response->getId(), $url, $this->isTwoStepPayment() ? 2 : 1);
        }
        if ($currentRegistration) {
            $this->getService()->resetRegistration(self::CODE, $invoice->getOrder());
        }

        return $currentRegistration;
    }

    public function void(string $paymentIdentifier, string $amount, string $currency, Transaction $parent): void
    {
        $key = $paymentIdentifier . '-void';
        $client = $this->getClient();
        /** @var PaymentResponse|null $paymentResponse */
        $paymentResponse = $client->cancelPayment($paymentIdentifier, $key);
        if (null === $paymentResponse || null === $paymentResponse->getId() || null === $paymentResponse->getAmount()) {
            throw new \Exception('Unexpected void payment response');
        }
        $metadata = $paymentResponse->getMetadata()?->toArray() ?? [];
        if (!isset($metadata['orderNumber'])) {
            throw new \Exception('Unexpected void payment metadata');
        }
        if (PaymentStatus::CANCELED === $paymentResponse->getStatus()) {
            $this->getService()->createTransaction(
                self::CODE,
                $metadata['orderNumber'],
                $paymentResponse->getId(),
                $key,
                Transaction::VOID,
                $paymentResponse->getAmount()->getValue(),
                $paymentResponse->getAmount()->getCurrency(),
                $parent,
                $paymentResponse->jsonSerialize(),
            );
        }
    }

    public function canCheck(): bool
    {
        return true;
    }

    public function check(string $paymentIdentifier): PaymentInfo
    {
        $client = $this->getClient();
        /** @var PaymentResponse|null $paymentResponse */
        $paymentResponse = $client->getPaymentInfo($paymentIdentifier);
        if (null === $paymentResponse || null === $paymentResponse->getId() || null === $paymentResponse->getAmount()) {
            throw new \Exception('Unexpected check payment response');
        }
        if (!in_array($paymentResponse->getStatus(), PaymentStatus::getEnabledValues())) {
            throw new \Exception('Unexpected payment status:' . $paymentResponse->getStatus());
        }
        $metadata = $paymentResponse->getMetadata()?->toArray() ?? [];
        if (!isset($metadata['orderNumber'])) {
            throw new \Exception('Unexpected payment metadata');
        }

        $info = $this->getService()->getTransactionSummaryByPayment(self::CODE, $paymentIdentifier);
        $canAuth = PaymentStatus::WAITING_FOR_CAPTURE === $paymentResponse->getStatus() && 0 === bccomp($info->auth, '0');
        $canCapture = PaymentStatus::SUCCEEDED === $paymentResponse->getStatus() && 0 === bccomp($info->capture, '0') && $paymentResponse->getPaid();
        $canVoid = PaymentStatus::CANCELED === $paymentResponse->getStatus() && 0 === bccomp($info->void, '0');
        $shouldReset = PaymentStatus::CANCELED === $paymentResponse->getStatus() && 0 === bccomp($info->auth, '0');
        $shouldDelete = PaymentStatus::PENDING !== $paymentResponse->getStatus() && (1 === bccomp($info->auth, '0') ||  1 === bccomp($info->capture, '0'));

        if ($canAuth) {
            $this->getService()->createTransaction(
                self::CODE,
                $metadata['orderNumber'],
                $paymentResponse->getId(),
                $paymentResponse->getId(),
                Transaction::AUTH,
                $paymentResponse->getAmount()->getValue(),
                $paymentResponse->getAmount()->getCurrency(),
                null,
                $paymentResponse->jsonSerialize(),
            );
            $info = $this->getService()->getTransactionSummaryByPayment(self::CODE, $paymentIdentifier);
        } elseif ($canCapture) {
            $parent = $this->getService()->findAuthTransaction(self::CODE, $paymentIdentifier);
            $saved = $paymentResponse->getPaymentMethod()?->getSaved() ?? false;
            $this->getService()->createTransaction(
                self::CODE,
                $metadata['orderNumber'],
                $paymentResponse->getId(),
                $paymentIdentifier . '-capture',
                Transaction::CAPTURE,
                $paymentResponse->getAmount()->getValue(),
                $paymentResponse->getAmount()->getCurrency(),
                $parent,
                $paymentResponse->jsonSerialize(),
                $saved ? $paymentResponse->getPaymentMethod()?->getId() : null,
            );
            $info = $this->getService()->getTransactionSummaryByPayment(self::CODE, $paymentIdentifier);
        } elseif ($canVoid) {
            $parent = $this->getService()->findAuthTransaction(self::CODE, $paymentIdentifier);
            $this->getService()->createTransaction(
                self::CODE,
                $metadata['orderNumber'],
                $paymentResponse->getId(),
                $paymentIdentifier . '-void',
                Transaction::VOID,
                $paymentResponse->getAmount()->getValue(),
                $paymentResponse->getAmount()->getCurrency(),
                $parent,
                $paymentResponse->jsonSerialize(),
            );
            $info = $this->getService()->getTransactionSummaryByPayment(self::CODE, $paymentIdentifier);
        }

        if ($shouldReset) {
            $this->getService()->resetRegistration(self::CODE, $metadata['orderNumber']);
        }
        if ($shouldDelete) {
            $this->getService()->deleteRegistration(self::CODE, $metadata['orderNumber']);
        }

        return new PaymentInfo(
            $info->auth,
            $info->capture,
            $paymentResponse->getAmount()->getValue(),
            $info->void,
            $info->refund,
        );
    }

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
