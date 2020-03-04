<?php

namespace Crm\ProductsModule\Repository;

use Crm\ApplicationModule\Repository;

class ProductTemplatePropertiesRepository extends Repository
{
    protected $tableName = 'product_template_properties';

    final public function findByTemplate($template)
    {
        return $this->getTable()->where(['product_template_id' => $template->id])->order('sorting');
    }

    final public function findByCode($code)
    {
        return $this->getTable()->where(['code' => $code]);
    }

    final public function exists($template, $code)
    {
        return $this->getTable()->where(['product_template_id' => $template->id, 'code' => $code])->count('*') > 0;
    }

    final public function add($title, $code, $required, $default, $visible, $sorting, $template, $hint = null)
    {
        return $this->insert([
            'title' => $title,
            'code' => $code,
            'required' => (bool)$required,
            'default' => (bool)$default,
            'visible' => (bool)$visible,
            'sorting' => (int)$sorting,
            'product_template_id' => $template->id,
            'hint' => $hint
        ]);
    }
}
