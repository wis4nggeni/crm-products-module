<?php

namespace Crm\ProductsModule\Seeders;

use Crm\ApplicationModule\Builder\ConfigBuilder;
use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\Config\Repository\ConfigCategoriesRepository;
use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\ApplicationModule\Seeders\ConfigsTrait;
use Crm\ApplicationModule\Seeders\ISeeder;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigsSeeder implements ISeeder
{
    use ConfigsTrait;

    private $configBuilder;

    private $configCategoriesRepository;

    private $configsRepository;

    public function __construct(
        ConfigBuilder $configBuilder,
        ConfigCategoriesRepository $configCategoriesRepository,
        ConfigsRepository $configsRepository
    ) {
        $this->configBuilder = $configBuilder;
        $this->configCategoriesRepository = $configCategoriesRepository;
        $this->configsRepository = $configsRepository;
    }

    public function seed(OutputInterface $output)
    {
        $category = $this->getCategory($output, 'products.config.category', 'fas fa-shopping-cart', 140);

        $this->addConfig(
            $output,
            $category,
            'shop_host',
            ApplicationConfig::TYPE_STRING,
            'products.config.shop_host.name',
            'products.config.shop_host.description',
            null,
            100
        );

        $this->addConfig(
            $output,
            $category,
            'shop_header_block',
            ApplicationConfig::TYPE_TEXT,
            'products.config.shop_header_block.name',
            'products.config.shop_header_block.description',
            null,
            200
        );

        $this->addConfig(
            $output,
            $category,
            'shop_og_image_url',
            ApplicationConfig::TYPE_STRING,
            'products.config.shop_og_image_url.name',
            'products.config.shop_og_image_url.description',
            null,
            300
        );

        $this->addConfig(
            $output,
            $category,
            'shop_terms_and_conditions_url',
            ApplicationConfig::TYPE_STRING,
            'products.config.shop_terms_and_conditions_url.name',
            'products.config.shop_terms_and_conditions_url.description',
            null,
            400
        );
    }
}
