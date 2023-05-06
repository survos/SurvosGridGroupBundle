<?php

namespace Survos\GridGroupBundle;

use Survos\GridGroupBundle\Service\CsvCacheAdapter;
use Survos\GridGroupBundle\Service\CsvDatabase;
use Survos\GridGroupBundle\Service\CsvWriter;
use Survos\GridGroupBundle\Service\GridGroupService;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;


class SurvosGridGroupBundle extends AbstractBundle
{
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // $builder->setParameter('survos_workflow.direction', $config['direction']);

        $grid_group_service_id = 'survos.grid_group_service';
        $builder
            ->autowire($grid_group_service_id, GridGroupService::class)
            ->setPublic(true)
            ->setArgument('$slugger', new Reference('slugger'))
        ;
        $builder
            ->autowire(CsvWriter::class)
            ->setPublic(true);
        $container->services()->alias(GridGroupService::class, $grid_group_service_id);

//        $grid_group_service_id = 'survos.grid_group_csv_database';
//        $builder
//            ->autowire($grid_group_service_id, CsvDatabase::class)
//            ->setPublic(true)
//        ;
//
//        $grid_group_service_id = 'survos.grid_group_csv_adapter';
//        $builder
//            ->autowire($grid_group_service_id, CsvCacheAdapter::class)
//            ->setPublic(true)
//            ->setAutoconfigured(true)
//        ;

        // @todo: set the logger


    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
            ->scalarNode('direction')->defaultValue('LR')->end()
            ->scalarNode('base_layout')->defaultValue('base.html.twig')->end()
            ->arrayNode('entities')
            ->scalarPrototype()
            ->end()->end()
            ->booleanNode('enabled')->defaultTrue()->end()
//            ->integerNode('min_sunshine')->defaultValue(3)->end()
            ->end();
    }

}
