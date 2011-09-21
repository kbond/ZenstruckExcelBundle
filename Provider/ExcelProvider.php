<?php

namespace Zenstruck\Bundle\ExcelBundle\Provider;

use Symfony\Component\DependencyInjection\Container;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class ExcelProvider
{
    protected $container;
    protected $loaderClass;

    public function __construct(Container $container, $loaderClass)
    {
        $this->container = $container;
        $this->loaderClass = $loaderClass;
    }

    public function load($filename, $shortClassName, $idField = null, $truncate = false)
    {
        $loader = new $this->loaderClass($this->container);

        $loader->load($filename, $shortClassName, $idField, $truncate);
    }
}
