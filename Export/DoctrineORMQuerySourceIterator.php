<?php

/*
 * This file is part of the EmharSonataTranslationBundle bundle.
 *
 * (c) Emmanuel Harleaux
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Emhar\SonataTranslationBundle\Export;

use Doctrine\ORM\Query;
use Exporter\Source\DoctrineORMQuerySourceIterator as BaseDoctrineORMQuerySourceIterator;
use Fervo\EnumBundle\Enum\AbstractTranslatableEnum;
use JMS\TranslationBundle\Annotation\Ignore;
use Symfony\Component\Translation\TranslatorInterface;

class DoctrineORMQuerySourceIterator extends BaseDoctrineORMQuerySourceIterator
{
    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @param Query $query
     * @param array $fields
     * @param string $dateTimeFormat
     * @param TranslatorInterface $translator
     */
    public function __construct(Query $query, array $fields, $dateTimeFormat = 'r', TranslatorInterface $translator)
    {
        parent::__construct($query, $fields, $dateTimeFormat);
        $this->translator = $translator;
    }

    /**
     * @param $value
     *
     * @return null|string
     */
    protected function getValue($value)
    {
        if (class_exists('\\MyCLabs\\Enum\\Enum')) {
            if ($value instanceof AbstractTranslatableEnum) {
                $value = $this->translator->trans(/** @Ignore */
                    $value->getTranslationKey(), array(), 'enums');
            } elseif (is_array($value)) {
                return implode(' ,', array_map(function ($item) {
                    if ($item instanceof AbstractTranslatableEnum) {
                        return $this->translator->trans(/** @Ignore */
                            $item->getTranslationKey(), array(), 'enums');
                    }
                    if (is_object($item)) {
                        return (string)$item;
                    }
                    return '';
                }, $value));
            }
        }
        if (is_array($value) || $value instanceof \Traversable) {
            $value = null;
        } elseif ($value instanceof \DateTime) {
            $value = $value->format($this->dateTimeFormat);
        } elseif (is_object($value)) {
            $value = (string)$value;
        }
        if (is_string($value)) {
            $value = strip_tags($value);
        }

        return $value;
    }
}