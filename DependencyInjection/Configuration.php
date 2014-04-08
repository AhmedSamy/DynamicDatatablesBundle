<?php

namespace Hype\DynamicDatatablesBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;


class Configuration implements ConfigurationInterface {


    public function getConfigTreeBuilder() {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('hype_dynamic_datatables');

        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.

        $rootNode->children()
                    ->scalarNode('api_key')->isRequired()->cannotBeEmpty()->end()
                    ->scalarNode('default_list')->isRequired()->cannotBeEmpty()->end()
                    ->booleanNode('ssl')->defaultTrue()->end()
                    ->integerNode('timeout')->defaultValue(20)->end()
                  ->end();

        return $treeBuilder;
    }

}
