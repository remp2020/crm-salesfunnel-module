<?php

namespace Crm\SalesFunnelModule\Models\Distribution;

use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsConversionDistributionsRepository;
use Crm\SalesFunnelModule\Repositories\SalesFunnelsRepository;
use Crm\SubscriptionsModule\Repositories\SubscriptionsRepository;
use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Utils\ArrayHash;

abstract class AbstractFunnelDistribution
{
    public const TYPE = 'abstract-distribution';

    protected $paidStatuses = [PaymentStatusEnum::Paid->value, PaymentStatusEnum::Prepaid->value];

    protected $database;

    protected $subscriptionsRepository;

    protected $paymentsRepository;

    protected $salesFunnelsConversionDistributionsRepository;

    protected $salesFunnelsRepository;

    protected $distributionConfiguration;

    protected static int $paidPaymentsUserCount;

    public function __construct(
        Explorer $database,
        PaymentsRepository $paymentsRepository,
        SubscriptionsRepository $subscriptionsRepository,
        SalesFunnelsConversionDistributionsRepository $salesFunnelsConversionDistributionsRepository,
        SalesFunnelsRepository $salesFunnelsRepository
    ) {
        $this->database = $database;
        $this->subscriptionsRepository = $subscriptionsRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->salesFunnelsConversionDistributionsRepository = $salesFunnelsConversionDistributionsRepository;
        $this->salesFunnelsRepository = $salesFunnelsRepository;
    }

    abstract protected function getDistributionRows($funnelId, $userId = null): array;

    abstract protected function prepareInsertRow(ActiveRow $salesFunnel, ArrayHash $distributionRow): array;

    public function setDistributionConfiguration(array $distributionLevels): void
    {
        if (count($distributionLevels) < 3) {
            throw new \UnexpectedValueException('Required at least 3 elements of $distributionLevels array.');
        }

        sort($distributionLevels);
        if ($distributionLevels[0] !== 0) {
            array_unshift($distributionLevels, 0);
        }

        $this->distributionConfiguration = $distributionLevels;
    }

    public function getDistributionConfiguration()
    {
        return $this->distributionConfiguration;
    }

    public function getDistribution(int $funnelId)
    {
        $levelSelect = [];
        foreach ($this->distributionConfiguration as $i => $level) {
            if ($i+1 === count($this->distributionConfiguration)) {
                $levelSelect[] = "COALESCE(SUM(CASE WHEN value >= {$level} THEN 1 ELSE 0 END), 0) '{$i}'";
                break;
            }
            $levelSelect[] = "COALESCE(SUM(CASE WHEN value >= {$level} AND value < {$this->distributionConfiguration[$i+1]} THEN 1 ELSE 0 END), 0) '{$i}'";
        }

        $levelSql = implode(", ", $levelSelect);

        return $this->salesFunnelsConversionDistributionsRepository
            ->salesFunnelTypeDistributions($funnelId, static::TYPE)
            ->select($levelSql)
            ->fetch();
    }

    public function calculateDistribution(ActiveRow $salesFunnel):void
    {
        $this->salesFunnelsConversionDistributionsRepository
            ->deleteSalesFunnelTypeDistributions($salesFunnel->id, static::TYPE);

        $rows = [];
        foreach ($this->getDistributionRows($salesFunnel->id) as $distributionRow) {
            $rows[] = $this->prepareInsertRow($salesFunnel, $distributionRow);
        }

        if (!empty($rows)) {
            $this->salesFunnelsConversionDistributionsRepository->insert($rows);
        }
    }

    public function calculateUserDistribution(ActiveRow $salesFunnel, ActiveRow $user):void
    {
        $userDistribution = $this->salesFunnelsConversionDistributionsRepository
            ->salesFunnelUserTypeDistribution($salesFunnel->id, $user->id, static::TYPE)->fetch();

        // user has already paid in sales funnel
        if ($userDistribution) {
            return;
        }

        $rows = [];
        foreach ($this->getDistributionRows($salesFunnel->id, $user->id) as $distributionRow) {
            $rows[] = $this->prepareInsertRow($salesFunnel, $distributionRow);
        }

        $this->salesFunnelsConversionDistributionsRepository->insert($rows);
    }

    public function getUserSql($userId):string
    {
        $userSql = '';
        if (isset($userId)) {
            $userSql = "AND user_id = {$userId}";
        }

        return $userSql;
    }

    public function getStatusSql():string
    {
        return "('" . implode("','", $this->paidStatuses) . "')";
    }

    public function isDistributionActual($salesFunnelId)
    {
        $distributionsCount = $this->salesFunnelsConversionDistributionsRepository
            ->salesFunnelTypeDistributions($salesFunnelId, static::TYPE)->count();
        $paymentsCount = $this->paidPaymentsUserCount($salesFunnelId);

        return $distributionsCount === $paymentsCount;
    }

    final public function paidPaymentsUserCount($salesFunnelId)
    {
        if (!isset(self::$paidPaymentsUserCount)) {
            self::$paidPaymentsUserCount = $this->paymentsRepository->all()
                ->where(['sales_funnel_id' => $salesFunnelId, 'status' => $this->paidStatuses])->count('DISTINCT user_id');
        }

        return self::$paidPaymentsUserCount;
    }
}
