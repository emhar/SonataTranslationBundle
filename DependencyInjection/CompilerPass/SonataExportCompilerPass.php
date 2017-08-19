<?php

/*
 * This file is part of the EmharSonataTranslationBundle bundle.
 *
 * (c) Emmanuel Harleaux
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Emhar\SonataTranslationBundle\DependencyInjection\CompilerPass;

use Emhar\SonataTranslationBundle\Model\Manager;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * {@inheritDoc}
 */
class SonataExportCompilerPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\OutOfBoundsException
     */
    public function process(ContainerBuilder $container)
    {
        $definition = $container->getDefinition('sonata.admin.manager.orm');
        $definition->setClass(Manager::class);
        $definition->addMethodCall('setTranslator', array(new Reference('translator')));
    }
}
