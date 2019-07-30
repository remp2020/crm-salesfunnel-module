<?php

namespace Crm\SalesFunnelModule\Seeders;

use Crm\ApplicationModule\Seeders\ISeeder;
use Crm\SalesFunnelModule\Repository\SalesFunnelsRepository;
use Symfony\Component\Console\Output\OutputInterface;

class SalesFunnelsSeeder implements ISeeder
{
    private $salesFunnelsRepository;

    public function __construct(
        SalesFunnelsRepository $salesFunnelsRepository
    ) {
        $this->salesFunnelsRepository = $salesFunnelsRepository;
    }

    public function seed(OutputInterface $output)
    {
        foreach (glob(__DIR__ . '/sales_funnels/*.twig') as $filename) {
            $info = pathinfo($filename);
            $key = $info['filename'];

            if (!$this->salesFunnelsRepository->findByUrlKey($key)) {
                $this->salesFunnelsRepository->add($key, $key, file_get_contents($filename));
                $output->writeln('  <comment>* funnel <info>' . $key . '</info> created</comment>');
            } else {
                $output->writeln('  * funnel <info>' . $key . '</info> exists');
            }
        }
    }
}