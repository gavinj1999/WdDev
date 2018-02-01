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
namespace Bss\MultiStoreViewPricing\Model\Quote\Item;

/**
 * Quote item resource collection
 */
class Collection extends \Magento\Quote\Model\ResourceModel\Quote\Item\Collection
{
	/**
     * Add products to items and item options
     *
     * @return $this
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    protected function _assignProducts()
    {
    	$objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $helper = $objectManager->create('Bss\MultiStoreViewPricing\Helper\Data');
        if (!$helper->isScopePrice()) {
            return parent::_assignProducts();
        }

        \Magento\Framework\Profiler::start('QUOTE:' . __METHOD__, ['group' => 'QUOTE', 'method' => __METHOD__]);
        $productIds = [];
        foreach ($this as $item) {
            $productIds[] = (int)$item->getProductId();
        }
        $this->_productIds = array_merge($this->_productIds, $productIds);

        $productCollection = $this->_productCollectionFactory->create()->setStoreId(
            $this->getStoreId()
        )->addIdFilter(
            $this->_productIds
        )->addAttributeToSelect(
            $this->_quoteConfig->getProductAttributes()
        )->addOptionsToResult()->addStoreFilter()->addUrlRewrite()->addTierPriceData();

        $this->_eventManager->dispatch(
            'prepare_catalog_product_collection_prices',
            ['collection' => $productCollection, 'store_id' => $this->getStoreId()]
        );
        $this->_eventManager->dispatch(
            'sales_quote_item_collection_products_after_load',
            ['collection' => $productCollection]
        );

        $recollectQuote = false;
        foreach ($this as $item) {
            $product = $productCollection->getItemById($item->getProductId());
            if ($product) {
                $product->setCustomOptions([]);
                $qtyOptions = [];
                $optionProductIds = [];
                foreach ($item->getOptions() as $option) {
                    /**
                     * Call type-specific logic for product associated with quote item
                     */
                    $product->getTypeInstance()->assignProductToOption(
                        $productCollection->getItemById($option->getProductId()),
                        $option,
                        $product
                    );

                    if (is_object($option->getProduct()) && $option->getProduct()->getId() != $product->getId()) {
                        $optionProductIds[$option->getProduct()->getId()] = $option->getProduct()->getId();
                    }
                }

                if ($optionProductIds) {
                    foreach ($optionProductIds as $optionProductId) {
                        $qtyOption = $item->getOptionByCode('product_qty_' . $optionProductId);
                        if ($qtyOption) {
                            $qtyOptions[$optionProductId] = $qtyOption;
                        }
                    }
                }

                $item->setQtyOptions($qtyOptions)->setProduct($product);
            } else {
                $item->isDeleted(true);
                $recollectQuote = true;
            }
            $item->checkData();
        }

        $storeManager = $objectManager->get('Magento\Store\Model\StoreManagerInterface');
        if($storeManager->getStore()->isUseStoreInUrl()) {
        	$recollectQuote = true;
        }

        if ($recollectQuote && $this->_quote) {
            $this->_quote->collectTotals();
        }
        \Magento\Framework\Profiler::stop('QUOTE:' . __METHOD__);

        return $this;
    }
}