<?php

use Phinx\Migration\AbstractMigration;

class ProductsTranslateConfigs extends AbstractMigration
{
    public function up()
    {
        $this->execute("
            update configs set display_name = 'products.config.shop_host.name' where name = 'shop_host';
            update configs set description = 'products.config.shop_host.description' where name = 'shop_host';
            
            update configs set display_name = 'products.config.shop_header_block.name' where name = 'shop_header_block';
            update configs set description = 'products.config.shop_header_block.description' where name = 'shop_header_block';
        ");
    }

    public function down()
    {

    }
}
