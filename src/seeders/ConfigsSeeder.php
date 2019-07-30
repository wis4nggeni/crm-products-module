<?php

namespace Crm\ProductsModule\Seeders;

use Crm\ApplicationModule\Builder\ConfigBuilder;
use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\Config\Repository\ConfigCategoriesRepository;
use Crm\ApplicationModule\Config\Repository\ConfigsRepository;
use Crm\ApplicationModule\Seeders\ISeeder;
use Symfony\Component\Console\Output\OutputInterface;

class ConfigsSeeder implements ISeeder
{
    private $configCategoriesRepository;

    private $configsRepository;

    private $configBuilder;

    public function __construct(
        ConfigCategoriesRepository $configCategoriesRepository,
        ConfigsRepository $configsRepository,
        ConfigBuilder $configBuilder
    ) {
        $this->configCategoriesRepository = $configCategoriesRepository;
        $this->configsRepository = $configsRepository;
        $this->configBuilder = $configBuilder;
    }

    public function seed(OutputInterface $output)
    {
        $category = $this->configCategoriesRepository->loadByName('Všeobecne');
        if (!$category) {
            $category = $this->configCategoriesRepository->add('Všeobecne', 'fa fa-globe', 100);
            $output->writeln('  <comment>* config category <info>Všeobecne</info> created</comment>');
        } else {
            $output->writeln('  * config category <info>Všeobecne</info> exists');
        }
        
        $name = 'shop_host';
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName('Host shopu')
                ->setDescription('Host URL shopu (v prípade, že beží na vlastnej doméne; napr. obchod.dennikn.sk)')
                ->setType(ApplicationConfig::TYPE_STRING)
                ->setAutoload(true)
                ->setConfigCategory($category)
                ->setSorting(270)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");
        }

        $name = 'shop_header_block';
        $config = $this->configsRepository->loadByName($name);
        if (!$config) {
            $this->configBuilder->createNew()
                ->setName($name)
                ->setDisplayName('Kód v hlavičke pre OBCHOD')
                ->setDescription('Je možné vložiť ľubovoľný kód, ako napríklad Google analytics alebo ďalšie')
                ->setType(ApplicationConfig::TYPE_TEXT)
                ->setAutoload(true)
                ->setConfigCategory($category)
                ->setSorting(550)
                ->save();
            $output->writeln("  <comment>* config item <info>$name</info> created</comment>");
        } else {
            $output->writeln("  * config item <info>$name</info> exists");
        }
    }
}
