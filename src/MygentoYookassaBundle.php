<?php

namespace Mygento\Yookassa;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use YooKassa\Model\Payment\PaymentMethodType;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

class MygentoYookassaBundle extends AbstractBundle
{
    public const VERSION = '1.0.0';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
    }

    /**
     * @param mixed[] $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()->set('mygento.yookassa_payment_config', Config::class)
            ->arg(0, $config['config']['shop_id'] ?? '')
            ->arg(1, $config['config']['secret'] ?? '')
            ->arg(2, $config['config']['methods'] ?? [])
            ->arg(3, $config['config']['two_step'] ?? true)
            ->arg(4, $config['config']['recurrent_payments'] ?? false);

        $container->services()->set('mygento.yookassa_payment_adapter', YookassaAdapter::class)
            ->tag('mygento.payment_adapter_factory')
            ->arg(0, service('mygento.yookassa_payment_config'))
            ->arg(1, service('mygento.payment_basic'));
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
            ->arrayNode('config')
            ->children()
            ->integerNode('shop_id')->end()
            ->scalarNode('secret')->end()
            ->enumNode('methods')->values(PaymentMethodType::getEnabledValues())->end()
            ->booleanNode('two_step')->defaultValue(true)->end()
            ->booleanNode('recurrent_payments')->defaultValue(false)->end()
            ->end()
            ->end()
            ->end();
    }
}
