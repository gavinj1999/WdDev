<?php
/**
 * BSS Commerce Co.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://bsscommerce.com/Bss-Commerce-License.txt
 *
 * =================================================================
 *                 MAGENTO EDITION USAGE NOTICE
 * =================================================================
 * This package designed for Magento COMMUNITY edition
 * BSS Commerce does not guarantee correct work of this extension
 * on any other Magento edition except Magento COMMUNITY edition.
 * BSS Commerce does not provide extension support in case of
 * incorrect edition usage.
 * =================================================================
 *
 * @category  BSS
 * @package   Bss_MultiStoreViewPricing
 * @author    Extension Team
 * @copyright Copyright (c) 2016-2017 BSS Commerce Co. ( http://bsscommerce.com )
 * @license   http://bsscommerce.com/Bss-Commerce-License.txt
 */
namespace Bss\MultiStoreViewPricing\Model\Product\Attribute\Backend;

use \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;

class Price extends \Magento\Catalog\Model\Product\Attribute\Backend\Price
{
    // public function setScope($attribute)
    // {
    //     $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    //     $helper = $objectManager->create('Bss\MultiStoreViewPricing\Helper\Data');
    //     if ($helper->isScopePrice()) {
    //         $attribute->setIsGlobal(ScopedAttributeInterface::SCOPE_STORE);
    //     } else {
    //         return parent::setScope($attribute);
    //     }
        
    //     return $this;
    // }

    /**
     * After Save Attribute manipulation
     *
     * @param                                        \Magento\Catalog\Model\Product $object
     * @return                                       $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function afterSave($object)
    {
        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $helper = $objectManager->create('Bss\MultiStoreViewPricing\Helper\Data');
        if (!$helper->isScopePrice()) {
            parent::afterSave($object);
        }

        $value = $object->getData($this->getAttribute()->getAttributeCode());
        /**
         * Orig value is only for existing objects
         */
        $oridData = $object->getOrigData();
        $origValueExist = $oridData && array_key_exists($this->getAttribute()->getAttributeCode(), $oridData);
        if ($object->getStoreId() != 0 || !$value || $origValueExist) {
            return $this;
        }

        if ($this->getAttribute()->getIsGlobal() == ScopedAttributeInterface::SCOPE_STORE
        ) {
            $baseCurrency = $this->_config->getValue(
                \Magento\Directory\Model\Currency::XML_PATH_CURRENCY_BASE,
                'default'
            );

            $storeIds = $object->getStoreIds();
            if (is_array($storeIds)) {
                foreach ($storeIds as $storeId) {
                    $storeCurrency = $this->_storeManager->getStore($storeId)->getBaseCurrencyCode();
                    if ($storeCurrency == $baseCurrency) {
                        continue;
                    }
                    $rate = $this->_currencyFactory->create()->load($baseCurrency)->getRate($storeCurrency);
                    if (!$rate) {
                        $rate = 1;
                    }
                    $newValue = $value * $rate;
                    $object->addAttributeUpdate($this->getAttribute()->getAttributeCode(), $newValue, $storeId);
                }
            }
        }

        return $this;
    }
}
