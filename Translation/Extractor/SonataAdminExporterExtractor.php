<?php

/*
 * This file is part of the EmharSonataTranslationBundle bundle.
 *
 * (c) Emmanuel Harleaux
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Emhar\SonataTranslationBundle\Translation\Extractor;

use Doctrine\Bundle\DoctrineBundle\Registry;
use JMS\TranslationBundle\Model\FileSource;
use JMS\TranslationBundle\Model\Message;
use JMS\TranslationBundle\Model\MessageCatalogue;
use JMS\TranslationBundle\Translation\ExtractorInterface;
use Psr\Log\LoggerInterface;
use Sonata\AdminBundle\Admin\AdminInterface;
use Sonata\AdminBundle\Admin\Pool;
use Sonata\AdminBundle\Security\Handler\SecurityHandlerInterface;
use Sonata\AdminBundle\Translator\Extractor\JMSTranslatorBundle\AdminExtractor;
use Sonata\AdminBundle\Translator\LabelTranslatorStrategyInterface;
use Symfony\Component\Translation\TranslatorInterface;

/**
 * Use in collaboration with base exporter
 * @see AdminExtractor
 *
 * Extract name from export header
 */
class SonataAdminExporterExtractor implements ExtractorInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Pool
     */
    private $adminPool;

    /**
     * @var MessageCatalogue|bool
     */
    private $catalogue;

    /**
     * @param Pool $adminPool
     * @param Registry $doctrine
     * @param LoggerInterface $logger
     */
    public function __construct(Pool $adminPool, Registry $doctrine = null, LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        $this->adminPool = $adminPool;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Extract messages to MessageCatalogue.
     *
     * @return MessageCatalogue
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \InvalidArgumentException
     * @throws \Exception|\RuntimeException
     */
    public function extract()
    {
        if ($this->catalogue) {
            throw new \RuntimeException('Invalid state');
        }

        $this->catalogue = new MessageCatalogue();

        foreach ($this->adminPool->getAdminServiceIds() as $id) {
            $admin = $this->getAdmin($id);
            if ($this->logger) {
                $this->logger->info(sprintf('Retrieving message from admin:%s - class: %s', $admin->getCode(), get_class($admin)));
            }
            try {
                $domain = $admin->getTranslationDomain();
                foreach ($admin->getExportFields() as $key => $field) {
                    $label = $admin->getTranslationLabel($field, 'export', 'label');

                    $this->catalogue->add(new Message($label, $domain));
                }
            } catch (\Exception $e) {
                if ($this->logger) {
                    $this->logger->error(sprintf('ERROR : admin:%s - Raise an exception : %s', $admin->getCode(), $e->getMessage()));
                }
                throw $e;
            }
        }

        $catalogue = $this->catalogue;
        $this->catalogue = false;

        return $catalogue;
    }

    /**
     * @param string $id
     *
     * @return AdminInterface
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException
     * @throws \Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException
     * @throws \InvalidArgumentException
     */
    private function getAdmin($id)
    {
        $admin = $this->adminPool->getContainer()->get($id);
        if (!$admin instanceof AdminInterface) {
            throw new \InvalidArgumentException(sprintf('ERROR : service:%s is not an AdminInteface instance.', $id));
        }
        return $admin;
    }
}
