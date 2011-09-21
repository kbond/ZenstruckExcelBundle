<?php

namespace Zenstruck\Bundle\ExcelBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class Configuration implements ConfigurationInterface
{

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('zenstruck_excel');

        $rootNode
            ->children()
                ->scalarNode('loader_class')->defaultValue('Zenstruck\\Bundle\\ExcelBundle\\Loader\\ExcelLoader')->end()
            ->end()
        ;

        return $treeBuilder;
    }

}
