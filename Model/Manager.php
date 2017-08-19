<?php

/*
 * This file is part of the EmharSonataTranslationBundle bundle.
 *
 * (c) Emmanuel Harleaux
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Emhar\SonataTranslationBundle\Model;

use Emhar\SonataTranslationBundle\Export\DoctrineORMQuerySourceIterator;
use Sonata\AdminBundle\Datagrid\DatagridInterface;
use Sonata\AdminBundle\Datagrid\ProxyQueryInterface;
use Sonata\DoctrineORMAdminBundle\Model\ModelManager as BaseModelManager;
use Symfony\Component\Translation\TranslatorInterface;

class Manager extends BaseModelManager
{
    /**
     * @var TranslatorInterface
     */
    protected $translator;

    public function setTranslator(TranslatorInterface $translator)
    {
        $this->translator = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function getDataSourceIterator(DatagridInterface $datagrid, array $fields, $firstResult = null, $maxResult = null)
    {
        $datagrid->buildPager();
        $query = $datagrid->getQuery();

        $query->select('DISTINCT ' . $query->getRootAlias());
        $query->setFirstResult($firstResult);
        $query->setMaxResults($maxResult);

        if ($query instanceof ProxyQueryInterface) {
            $query->addOrderBy($query->getSortBy(), $query->getSortOrder());

            $query = $query->getQuery();
        }

        return new DoctrineORMQuerySourceIterator($query, $fields, 'd/m/Y H:i', $this->translator);
    }
}