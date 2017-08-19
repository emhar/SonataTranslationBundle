<?php

/*
 * This file is part of the EmharSonataTranslationBundle bundle.
 *
 * (c) Emmanuel Harleaux
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Emhar\SonataTranslationBundle;

use Emhar\SonataTranslationBundle\DependencyInjection\CompilerPass\SonataExportCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * {@inheritDoc}
 */
class EmharSonataTranslationBundle extends Bundle
{
    /**
     * {@inheritdoc}
     */
    public function build(ContainerBuilder $container)
    {
        $container->addCompilerPass(new SonataExportCompilerPass());
    }
}