<?php

use Phinx\Migration\AbstractMigration;

class ProductsModuleInitMigration extends AbstractMigration
{
    public function up()
    {
        $sql = <<<SQL
SET NAMES utf8mb4;
SET time_zone = '+00:00';


CREATE TABLE IF NOT EXISTS `postal_fees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `country_postal_fees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `country_id` int(11) NOT NULL,
  `postal_fee_id` int(11) NOT NULL,
  `sorting` int(11) NOT NULL DEFAULT '10',
  `default` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `country_id` (`country_id`),
  KEY `postal_fee_id` (`postal_fee_id`),
  CONSTRAINT `country_postal_fees_ibfk_1` FOREIGN KEY (`country_id`) REFERENCES `countries` (`id`),
  CONSTRAINT `country_postal_fees_ibfk_2` FOREIGN KEY (`postal_fee_id`) REFERENCES `postal_fees` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `distribution_centers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(255) NOT NULL,
  `icon` varchar(255) NOT NULL,
  `visible` tinyint(1) NOT NULL DEFAULT '0',
  `sorting` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `product_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `product_template_properties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `required` tinyint(1) NOT NULL DEFAULT '1',
  `default` tinyint(1) NOT NULL DEFAULT '0',
  `visible` tinyint(1) NOT NULL DEFAULT '1',
  `sorting` int(11) NOT NULL,
  `product_template_id` int(11) NOT NULL,
  `hint` text,
  PRIMARY KEY (`id`),
  KEY `product_template_id` (`product_template_id`),
  CONSTRAINT `product_template_properties_ibfk_1` FOREIGN KEY (`product_template_id`) REFERENCES `product_templates` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `price` float NOT NULL,
  `catalog_price` float DEFAULT NULL,
  `vat` int(11) NOT NULL,
  `user_label` varchar(255) NOT NULL,
  `bundle` tinyint(1) NOT NULL,
  `shop` tinyint(1) NOT NULL DEFAULT '0',
  `visible` tinyint(1) DEFAULT '0',
  `sorting` int(11) DEFAULT NULL,
  `ean` varchar(255) DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `og_image_url` varchar(255) DEFAULT NULL,
  `images` text,
  `product_template_id` int(11) DEFAULT NULL,
  `stored` tinyint(1) NOT NULL DEFAULT '0',
  `unique_per_user` tinyint(1) DEFAULT NULL,
  `has_delivery` tinyint(1) DEFAULT NULL,
  `stock` int(11) NOT NULL DEFAULT '0',
  `distribution_center` varchar(255) DEFAULT NULL,
  `description` text,
  `created_at` datetime NOT NULL,
  `modified_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `product_template_id` (`product_template_id`),
  KEY `distribution_center` (`distribution_center`),
  CONSTRAINT `products_ibfk_1` FOREIGN KEY (`product_template_id`) REFERENCES `product_templates` (`id`),
  CONSTRAINT `products_ibfk_2` FOREIGN KEY (`distribution_center`) REFERENCES `distribution_centers` (`code`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `product_bundles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `bundle_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `bundle_id` (`bundle_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `product_bundles_ibfk_1` FOREIGN KEY (`bundle_id`) REFERENCES `products` (`id`),
  CONSTRAINT `product_bundles_ibfk_2` FOREIGN KEY (`item_id`) REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `product_properties` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `value` text NOT NULL,
  `product_id` int(11) NOT NULL,
  `product_template_property_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `product_template_property_id` (`product_template_property_id`),
  CONSTRAINT `product_properties_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `product_properties_ibfk_2` FOREIGN KEY (`product_template_property_id`) REFERENCES `product_template_properties` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `product_tags` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `tag_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  KEY `tag_id` (`tag_id`),
  CONSTRAINT `product_tags_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `product_tags_ibfk_2` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `shipping_address_id` int(11) DEFAULT NULL,
  `licence_address_id` int(11) DEFAULT NULL,
  `billing_address_id` int(11) DEFAULT NULL,
  `postal_fee_id` int(11) DEFAULT NULL,
  `coupon_note` text,
  `status` varchar(255) NOT NULL DEFAULT 'new',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `backup_postal_fee_amount` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_id` (`payment_id`),
  KEY `shipping_address_id` (`shipping_address_id`),
  KEY `billing_address_id` (`billing_address_id`),
  KEY `postal_fee_id` (`postal_fee_id`),
  KEY `licence_address_id` (`licence_address_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`),
  CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`shipping_address_id`) REFERENCES `addresses` (`id`),
  CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`billing_address_id`) REFERENCES `addresses` (`id`),
  CONSTRAINT `orders_ibfk_4` FOREIGN KEY (`postal_fee_id`) REFERENCES `postal_fees` (`id`),
  CONSTRAINT `orders_ibfk_5` FOREIGN KEY (`licence_address_id`) REFERENCES `addresses` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;

        $this->execute($sql);

        // add column product_id to payment_items table
        if(!$this->table('payment_items')->exists()) {
            throw new Exception('Cannot find table `payment_items`. Unable to add `product_id` column.');
        }

        if(!$this->table('payment_items')->hasColumn('product_id')) {
            $this->table('payment_items')
                ->addColumn('product_id', 'integer', ['null' => true, 'after' => 'subscription_type_id'])
                ->addForeignKey('product_id', 'products', 'id', array('delete' => 'RESTRICT', 'update'=> 'NO_ACTION'))
                ->update();
        }
    }

    public function down()
    {
        // TODO: [refactoring] add down migrations for module init migrations (needs confirmation dialog)
        $this->output->writeln('Down migration is not available.');
    }
}
