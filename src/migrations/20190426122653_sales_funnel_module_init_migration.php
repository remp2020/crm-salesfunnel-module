<?php


use Phinx\Migration\AbstractMigration;
use Phinx\Migration\IrreversibleMigrationException;

class SalesFunnelModuleInitMigration extends AbstractMigration
{
    public function up()
    {
        $sql = <<<SQL
SET NAMES utf8mb4;
SET time_zone = '+00:00';


CREATE TABLE IF NOT EXISTS `sales_funnels` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `url_key` varchar(255) NOT NULL,
  `start_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `body` longtext NOT NULL,
  `error_html` text,
  `no_access_html` text,
  `head` text,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `segment_id` int(11) DEFAULT NULL,
  `only_logged` tinyint(1) NOT NULL DEFAULT '0',
  `only_not_logged` tinyint(1) NOT NULL DEFAULT '0',
  `total_show` int(11) NOT NULL DEFAULT '0',
  `total_conversions` int(11) NOT NULL DEFAULT '0',
  `total_errors` int(11) NOT NULL DEFAULT '0',
  `last_use` datetime DEFAULT NULL,
  `last_conversion` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `url_key` (`url_key`),
  KEY `created_at` (`created_at`),
  KEY `segment_id` (`segment_id`),
  CONSTRAINT `sales_funnels_ibfk_1` FOREIGN KEY (`segment_id`) REFERENCES `segments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `sales_funnels_meta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sales_funnel_id` int(11) NOT NULL,
  `key` varchar(255) NOT NULL,
  `value` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_funnel_id` (`sales_funnel_id`,`key`),
  CONSTRAINT `sales_funnels_meta_ibfk_1` FOREIGN KEY (`sales_funnel_id`) REFERENCES `sales_funnels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `sales_funnels_payment_gateways` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sales_funnel_id` int(11) NOT NULL,
  `payment_gateway_id` int(11) NOT NULL,
  `sorting` int(11) NOT NULL DEFAULT '100',
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_funnel_id` (`sales_funnel_id`,`payment_gateway_id`),
  KEY `payment_gateway_id` (`payment_gateway_id`),
  CONSTRAINT `sales_funnels_payment_gateways_ibfk_1` FOREIGN KEY (`sales_funnel_id`) REFERENCES `sales_funnels` (`id`),
  CONSTRAINT `sales_funnels_payment_gateways_ibfk_2` FOREIGN KEY (`payment_gateway_id`) REFERENCES `payment_gateways` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `sales_funnels_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sales_funnel_id` int(11) NOT NULL,
  `date` datetime NOT NULL,
  `type` varchar(255) NOT NULL,
  `value` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_funnel_id` (`sales_funnel_id`,`date`,`type`),
  CONSTRAINT `sales_funnels_stats_ibfk_1` FOREIGN KEY (`sales_funnel_id`) REFERENCES `sales_funnels` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `sales_funnels_subscription_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sales_funnel_id` int(11) NOT NULL,
  `subscription_type_id` int(11) NOT NULL,
  `sorting` int(11) NOT NULL DEFAULT '100',
  PRIMARY KEY (`id`),
  UNIQUE KEY `sales_funnel_id` (`sales_funnel_id`,`subscription_type_id`),
  KEY `subscription_type_id` (`subscription_type_id`),
  CONSTRAINT `sales_funnels_subscription_types_ibfk_1` FOREIGN KEY (`sales_funnel_id`) REFERENCES `sales_funnels` (`id`),
  CONSTRAINT `sales_funnels_subscription_types_ibfk_2` FOREIGN KEY (`subscription_type_id`) REFERENCES `subscription_types` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
        $this->execute($sql);

        // add column sales_funnel_id to payments table
        if(!$this->table('payments')->hasColumn('sales_funnel_id')) {
            $this->table('payments')
                ->addColumn('sales_funnel_id', 'integer', array('null'=>true))
                ->addForeignKey('sales_funnel_id', 'sales_funnels', 'id', array('delete' => 'RESTRICT', 'update'=> 'NO_ACTION'))
                ->update();
        }
    }

    public function down()
    {
        $this->output->writeln('Down migration is not possible.');
        throw new IrreversibleMigrationException();
    }
}
