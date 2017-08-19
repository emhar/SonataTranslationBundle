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
 * Extract form tab names, form group names, on a new object and an object with id defined
 * Replay extraction of form properties on an object with id defined
 * (Base exporter do this only on a new object)
 */
class SonataAdminFormTabGroupExtractor implements ExtractorInterface, TranslatorInterface, SecurityHandlerInterface, LabelTranslatorStrategyInterface
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
     * @var TranslatorInterface|bool
     */
    private $translator;

    /**
     * @var LabelTranslatorStrategyInterface|bool
     */
    private $labelStrategy;

    /**
     * @var string|bool
     */
    private $domain;

    /**
     * @var Registry
     */
    private $doctrine;

    /**
     * @param Pool $adminPool
     * @param Registry $doctrine
     * @param LoggerInterface $logger
     */
    public function __construct(Pool $adminPool, Registry $doctrine = null, LoggerInterface $logger = null)
    {
        $this->logger = $logger;
        $this->adminPool = $adminPool;
        $this->doctrine = $doctrine;

        // state variable
        $this->catalogue = false;
        $this->translator = false;
        $this->labelStrategy = false;
        $this->domain = false;
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
                //Extract tabs and group
                $admin->getFormBuilder();
                $this->extractTabsAndGroups($admin);

                //Simulate subject has identifier defined
                if ($this->doctrine) {
                    $instance = $admin->getNewInstance();
                    $reflexionClass = new \ReflectionClass($instance);
                    $metadata = $this->doctrine->getManager()->getMetadataFactory()->getMetadataFor(get_class($instance));
                    foreach ($metadata->getIdentifier() as $identifierName) {
                        $currentReflexionClass = $reflexionClass;
                        while (!$currentReflexionClass->hasProperty($identifierName)){
                            $currentReflexionClass = $currentReflexionClass->getParentClass();
                        }
                        $reflexionProperty = $currentReflexionClass->getProperty($identifierName);
                        $reflexionProperty->setAccessible(true);
                        $reflexionProperty->setValue($instance, 1);
                    }
                    $admin->setSubject($instance);
                }

                //Extract tabs and group
                $admin->getFormBuilder();
                $this->extractTabsAndGroups($admin);

                //Replay base exporter
                $this->translator = $admin->getTranslator();
                $this->labelStrategy = $admin->getLabelTranslatorStrategy();
                $this->domain = $admin->getTranslationDomain();

                $admin->setTranslator($this);
                $admin->setSecurityHandler($this);
                $admin->setLabelTranslatorStrategy($this);

                try {
                    $admin->getForm();
                } catch (\Exception $e) {
                    if ($this->logger) {
                        $this->logger->error(sprintf('ERROR : admin:%s - Raise an exception : %s', $admin->getCode(), $e->getMessage()));
                    }

                    throw $e;
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

    protected function extractTabsAndGroups(AdminInterface $admin)
    {
        $tabs = $admin->getFormTabs();
        if (is_array($tabs)) {
            foreach ($tabs as $tabName => $tabInformation) {
                $this->addMessage($tabName, $admin->getTranslationDomain());
                $groupNames = $tabInformation['groups'] ?? array();
                /** @var array $groupNames */
                foreach ($groupNames as $groupName) {
                    $this->addMessage(
                        str_replace($tabName . '.', '', $groupName),
                        $admin->getTranslationDomain()
                    );
                }
            }
        }
    }

    /**
     * @param string $id
     * @param string $domain
     */
    private function addMessage($id, $domain)
    {
        $message = new Message($id, $domain);

        //        $this->logger->debug(sprintf('extract: %s - domain:%s', $id, $domain));

        $trace = debug_backtrace(false);
        if (isset($trace[1]['file'])) {
            $message->addSource(new FileSource($trace[1]['file']));
        }

        $this->catalogue->add($message);
    }

    /**
     * {@inheritdoc}
     */
    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        $this->addMessage($id, $domain);

        return $id;
    }

    /**
     * {@inheritdoc}
     */
    public function transChoice($id, $number, array $parameters = array(), $domain = null, $locale = null)
    {
        $this->addMessage($id, $domain);

        return $id;
    }

    /**
     * {@inheritdoc}
     */
    public function setLocale($locale)
    {
        $this->translator->setLocale($locale);
    }

    /**
     * {@inheritdoc}
     */
    public function getLocale()
    {
        return $this->translator->getLocale();
    }

    /**
     * {@inheritdoc}
     */
    public function isGranted(AdminInterface $admin, $attributes, $object = null)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function buildSecurityInformation(AdminInterface $admin)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function createObjectSecurity(AdminInterface $admin, $object)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function deleteObjectSecurity(AdminInterface $admin, $object)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getBaseRole(AdminInterface $admin)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getLabel($label, $context = '', $type = '')
    {
        $label = $this->labelStrategy->getLabel($label, $context, $type);

        $this->addMessage($label, $this->domain);

        return $label;
    }
}