<?php

namespace Crm\SalesFunnelModule\Commands;

use Crm\SalesFunnelModule\Events\CalculateSalesFunnelConversionDistributionEvent;
use Crm\SalesFunnelModule\Repository\SalesFunnelsRepository;
use League\Event\Emitter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CalculateSalesFunnelsConversionDistributionsCommand extends Command
{
    private $emitter;

    private $salesFunnelsRepository;

    public function __construct(
        Emitter $emitter,
        SalesFunnelsRepository $salesFunnelsRepository
    ) {
        parent::__construct();
        $this->emitter = $emitter;
        $this->salesFunnelsRepository = $salesFunnelsRepository;
    }

    protected function configure()
    {
        $this->setName('sales-funnel:distributions')
            ->setDescription('Calculates sales funnels conversion distributions for active sales funnels')
            ->addOption(
                'all',
                null,
                InputOption::VALUE_NONE,
                'Calculate the distribution for all sales funnels, even inactive.'
            )
            ->addArgument(
                'sales_funnel_url',
                InputArgument::OPTIONAL,
                'Url key for sales funnel to calculate distributions. Don\'t fill to calculate for active sales funnels.'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('');
        $output->writeln('<info>***** Sales funnels conversion distributions  *****</info>');
        $output->writeln('');

        $salesFunnelUrl = $input->getArgument('sales_funnel_url');
        $salesFunnels = [];

        if ($salesFunnelUrl) {
            $salesFunnel = $this->salesFunnelsRepository->findByUrlKey($salesFunnelUrl);
            if (!$salesFunnel) {
                throw new \Exception("Sales funnel with url key {$salesFunnelUrl} doesn't exist.");
            }
            $salesFunnels[] = $salesFunnel;
        } else {
            $isAll = $input->getOption('all');
            if ($isAll) {
                $salesFunnels = $this->salesFunnelsRepository->all();
            } else {
                $salesFunnels = $this->salesFunnelsRepository->active();
            }
        }

        foreach ($salesFunnels as $salesFunnel) {
            $output->writeln(" * calculate distributions for <info>{$salesFunnel->url_key}</info>");
            $this->emitter->emit(new CalculateSalesFunnelConversionDistributionEvent($salesFunnel));
        }

        $output->writeln('');
        $output->writeln('<info>Sales funnels conversion distributions calculated.</info>');
        $output->writeln('');

        return Command::SUCCESS;
    }
}
