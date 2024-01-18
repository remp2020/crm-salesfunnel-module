<?php

namespace Crm\SalesFunnelModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\PaymentsModule\Gateways\BankTransfer;
use Crm\PaymentsModule\Repository\PaymentGatewaysRepository;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsPaymentGatewaysRepository;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsSubscriptionTypesRepository;
use Crm\SubscriptionsModule\Models\Builder\SubscriptionTypeBuilder;
use Crm\SubscriptionsModule\Repositories\SubscriptionTypesRepository;
use Symfony\Component\Console\Output\OutputInterface;

class SalesFunnelsSeeder implements ISeeder
{
    private $salesFunnelsRepository;

    private $subscriptionTypesRepository;

    private $paymentGatewaysRepository;

    private $subscriptionTypeBuilder;

    private $salesFunnelsSubscriptionTypesRepository;

    private $salesFunnelsPaymentGatewaysRepository;

    public function __construct(
        SalesFunnelsRepository $salesFunnelsRepository,
        SubscriptionTypesRepository $subscriptionTypesRepository,
        PaymentGatewaysRepository $paymentGatewaysRepository,
        SubscriptionTypeBuilder $subscriptionTypeBuilder,
        SalesFunnelsSubscriptionTypesRepository $salesFunnelsSubscriptionTypesRepository,
        SalesFunnelsPaymentGatewaysRepository $salesFunnelsPaymentGatewaysRepository
    ) {
        $this->salesFunnelsRepository = $salesFunnelsRepository;
        $this->subscriptionTypesRepository = $subscriptionTypesRepository;
        $this->paymentGatewaysRepository = $paymentGatewaysRepository;
        $this->subscriptionTypeBuilder = $subscriptionTypeBuilder;
        $this->salesFunnelsSubscriptionTypesRepository = $salesFunnelsSubscriptionTypesRepository;
        $this->salesFunnelsPaymentGatewaysRepository = $salesFunnelsPaymentGatewaysRepository;
    }

    public function seed(OutputInterface $output)
    {
        $gateway = $this->paymentGatewaysRepository->findByCode(BankTransfer::GATEWAY_CODE);

        $subscriptionTypeCode = 'sample';
        $subscriptionType = $this->subscriptionTypesRepository->findByCode($subscriptionTypeCode);
        if (!$subscriptionType) {
            $subscriptionType = $this->subscriptionTypeBuilder->createNew()
                ->setNameAndUserLabel('Sample')
                ->setCode($subscriptionTypeCode)
                ->setPrice(13.37)
                ->setLength(31)
                ->setSorting(10)
                ->setActive(true)
                ->setVisible(true)
                ->setDescription('Sample subscription type created during installation')
                ->setUserLabel('Sample access')
                ->setContentAccessOption('web')
                ->save();
            $output->writeln("  <comment>* subscription type <info>{$subscriptionTypeCode}</info> created</comment>");
        } else {
            $output->writeln("  * subscription type <info>{$subscriptionTypeCode}</info> exists");
        }

        foreach (glob(__DIR__ . '/sales_funnels/*.twig') as $filename) {
            $info = pathinfo($filename);
            $key = $info['filename'];

            $funnel = $this->salesFunnelsRepository->findByUrlKey($key);
            if (!$funnel) {
                $funnel = $this->salesFunnelsRepository->add($key, $key, file_get_contents($filename));
                $output->writeln('  <comment>* funnel <info>' . $key . '</info> created</comment>');
            } else {
                $output->writeln('  * funnel <info>' . $key . '</info> exists');
            }

            $this->salesFunnelsSubscriptionTypesRepository->add($funnel, $subscriptionType);
            $this->salesFunnelsPaymentGatewaysRepository->add($funnel, $gateway);
        }
    }
}
