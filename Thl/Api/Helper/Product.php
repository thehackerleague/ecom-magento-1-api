<?php

class Thl_Api_Helper_Product extends Mage_Core_Helper_Abstract
{
    protected $taxHelper;

    protected $filterHelper;
    
    public function __construct()
    {
        $this->taxHelper = $taxHelper = Mage::helper('tax');
        $this->filterHelper = Mage::helper('tapi/filter_layer');
    }
    /**
     * Check if a product can be shown
     *
     * @param  Mage_Catalog_Model_Product|int $product
     * @return boolean
     */
    public function canShow($product)
    {
        /* @var $product Mage_Catalog_Model_Product */

        if (!$product->getId()) {
            return false;
        }

        return $product->isVisibleInCatalog() && $product->isVisibleInSiteVisibility();
    }

    public function init($productId)
    {
        $product = Mage::getModel('catalog/product')
            ->setStoreId(Mage::app()->getStore()->getId())
            ->load($productId);

        if (!$this->canShow($product)) {
            return false;
        }
        if (!in_array(Mage::app()->getStore()->getWebsiteId(), $product->getWebsiteIds())) {
            return false;
        }

        $product->load('media_gallery');

        // check cat
        #$product->canBeShowInCategory($categoryId)

        Mage::dispatchEvent('catalog_controller_product_init', array('product' => $product));
        Mage::dispatchEvent('catalog_controller_product_view', array('product' => $product));

        return $product;
    }

    public function fetchAttributes($product)
    {
        $attributes = $product->getAttributes();
        $data  = [];
        foreach ($attributes as $attribute) {
            if ($attribute->getIsVisibleOnFront()) {
                $value = $attribute->getFrontend()->getValue($product);

                if (!$product->hasData($attribute->getAttributeCode())) {
                    $value = Mage::helper('catalog')->__('N/A');
                } elseif ((string)$value == '') {
                    $value = Mage::helper('catalog')->__('No');
                } elseif ($attribute->getFrontendInput() == 'price' && is_string($value)) {
                    $value = Mage::app()->getStore()->convertPrice($value, true);
                }

                if (is_string($value) && strlen($value)) {
                    $data[$attribute->getAttributeCode()] = array(
                        'label' => $attribute->getStoreLabel(),
                        'value' => $value,
                        'code'  => $attribute->getAttributeCode()
                    );
                }
            }
        }

        return $data;
    }

    public function getProductList($filterParams = [])
    {
        $index = 1;
        $pageSize = 20;
        $curPage = 1;

        if (isset($filterParams['q'])) {
            $this->filterHelper->setMode('search');
        }
        /* @var Mage_Catalog_Model_Resource_Product_Collection  $products */
        $products = $this->filterHelper->getLayer()->getProductCollection();
        
        $products->setStore(Mage::app()->getStore());
        $products->addAttributeToSelect('*');
        $products->addAttributeToFilter('visibility', array('neq' => 1));
        $products->addAttributeToFilter('status', 1);

        if (Mage::registry('current_category') !==  null) {
            $category = Mage::registry('current_category');
            $products->addCategoryFilter($category);
        }

        if (isset($filterParams['only'])) {
            $ids = array_unique(explode(',', $filterParams['only']));
            $products->addIdFilter($ids);
        }

        if (isset($filterParams['limit'])) {
            $pageSize = $filterParams['limit'];
        }

        if (isset($filterParams['page'])) {
            $curPage = $filterParams['page'];
        }

        $this->filterHelper->apply();

        if($curPage == 1) {
            $filtersArray = $this->getFilterForCollections($products);
        }

        $products->setPageSize($pageSize)->setCurPage($curPage);

        $products->load();
        
        $collection = $this->formCollection($products);

        if($curPage == 1) {
            return ['filters' => $filtersArray, 'collections' => $collection];
        }

        return ['collections' => $collection];
    }

    public function formCollection($products)
    {
        $collection = array();
        foreach ($products as $product) {
            $ids         = array();
            $categoryIds = (int) $product->getCategoryIds();
            $categoryId  = $categoryIds[0];
            foreach ($product->getCategoryIds() as $id) {
                array_push($ids, (int) $id);
                // Add any parent IDs as well.
                /*$category = \Mage::getModel('catalog/category')->load($id);
                if ($category->parent_id) {
                    $parentCategory = \Mage::getModel('catalog/category')->load($category->parent_id);
                    if ($parentCategory->parent_id) {;
                        array_push($ids, (int) $parentCategory->parent_id);
                    }
                    array_push($ids, (int) $category->parent_id);
                }*/
            }
            $store = $product->getStore();
            $collection[] = array(
                'id'                => (int) $product->getId(),
                'name'              => trim($product->getName()),
                'prices'            => $this->getPrices($product, $store),
                'image'             => (string) $product->getMediaConfig()->getMediaUrl($product->getData('image')),
                'categories'        => array_unique($ids),
                'type'              => (string) $product->getTypeId(),
            );
        }

        return $collection;
    }

    public function getProducFilters($filterParams = [])
    {
        $collection = array();
        if (isset($filterParams['q'])) {
            $this->filterHelper->setMode('search');
        }
        /* @var Mage_Catalog_Model_Resource_Product_Collection  $products */
        $products = $this->filterHelper->getLayer()->getProductCollection();
        $products->setStore(Mage::app()->getStore());
        $products->addAttributeToSelect('*');
        $products->addAttributeToFilter('visibility', array('neq' => 1));
        $products->addAttributeToFilter('status', 1);

        if (Mage::registry('current_category') !==  null) {
            $category = Mage::registry('current_category');
            $products->addCategoryFilter($category);
        }

        if (isset($filterParams['only'])) {
            $ids = array_unique(explode(',', $filterParams['only']));
            $products->addIdFilter($ids);
        }
        $this->filterHelper->apply();

        $filtersArray = $this->getFilterForCollections($products);

        return ['filters' => $filtersArray];
    }

    public function getFilterForCollections($collection) {

        $layer = $this->filterHelper;

        $filters = $layer->getFilters();    

        $filtersArray = [];

        foreach ($filters as $filter)  {
            if($filter->getItemsCount()) {
                $tempFilter = [
                    'name' => $this->__($filter->getName()),
                ]; 

                foreach ($filter->getItems() as $item) {
                    $tempFilter['items'][] = [
                        'lable' => $item->getLabel(),
                        'count' => $item->getCount(),
                        'code' => $item->getFilter()->getRequestVar(),
                        'value' => $item->getValue(),
                    ];
                }

                $filtersArray[] = $tempFilter;  
            } 
        }

        return $filtersArray;
    }

    /**
     * Returns product information for one product.
     *
     * @method getProduct
     * @param int $productId
     * @return array
     */
    public function getProduct($productId)
    {
        /** @var \Mage_Catalog_Model_Product $product */
        $product = $this->init((int) $productId);

        if (!$product) {
            return [];
        }

        Mage::register('product', $product);
        $models     = array();
        $store = $product->getStore();
        $attributes = $this->fetchAttributes($product);
        
        $viewBlock = $this->getBlock('catalog/product_view_type_'.$product->getTypeId());
        $viewBlock->setProductId($product->getId());

        $additionalProducts = [
            'similar'       => $product->getRelatedProductIds(),
        ];
        if ($product->getTypeId() === 'configurable') {
            if ($viewBlock->hasOptions()) {
                foreach ($viewBlock->getAllowProducts() as $innerProduct) {
                    $models[$innerProduct->getId()] = $this->getProductSimple($innerProduct);
                }
            }
            $additionalProducts['json_config'] = json_decode($viewBlock->getJsonConfig(), true);
        } elseif ($product->getTypeId() === 'grouped') {
            $innerProducts = $viewBlock->setPreconfiguredValue()->getAssociatedProducts();
            foreach ($innerProducts as $innerProduct) {
                $models[$innerProduct->getId()] = $this->getProductSimple($innerProduct);
            }
            $additionalProducts['associated'][] = $innerProduct->getId();
        }

        if ($product->getTypeId() !== 'configurable') {
            if ($product->hasOptions()) {
                $viewBlock = $this->getBlock('catalog/product_view_type_options');
                $viewBlock->setProductId($product->getId());
                $additionalProducts['custom_optins'] = json_decode($viewBlock->getJsonConfig(), true);
            }
        }

        /** @var Mage_CatalogInventory_Model_Stock_Item $stockModel */
        $stockModel = \Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
        return array(
            'id'            => $product->getId(),
            'sku'           => $product->getSku(),
            'name'          => $product->getName(),
            'type'          => $product->getTypeId(),
            'quantity'      => (int) $stockModel->getQty(),
            'prices'        => $this->getPrices($product, $store),
            'description'   => nl2br(trim($product->getDescription())),
            'large_image'   => (string) $product->getMediaConfig()->getMediaUrl($product->getData('image')),
            'attributes'    => $attributes,
            'gallery'       => $product->getMediaGalleryImages()->toArray(),
            'product'       => $additionalProducts,
            'models'        => $models,
            'notice'        => $product->getTypeInstance(true)->getSpecifyOptionMessage(),
        );
    }

    public function getPrices($product, $store)
    {
        $price = $product->getPrice();
        $finalPrice = $product->getFinalPrice();
        $taxHelper = $this->taxHelper;
        return [
            'price'         => $this->formatPrice($price, $store),
            'final_price'   => $this->formatPrice($finalPrice, $store),
            'price_strick'  => ($price > $finalPrice),
            'with_tax'      => [
                'price'         => $this->formatPrice(
                    $taxHelper->getPrice($product, $price, true), $store
                ),
                'final_price'   => $this->formatPrice(
                    $taxHelper->getPrice($product, $finalPrice, true), $store
                ),
            ],
            'without_tax'      => [
                'price'         => $this->formatPrice(
                    $taxHelper->getPrice($product, $price), $store
                ),
                'final_price'   => $this->formatPrice(
                    $taxHelper->getPrice($product, $finalPrice), $store
                ),
            ]
        ];
    }

    protected function formatPrice($price, $store)
    {
        $coreHelper = Mage::helper('core');
        return $coreHelper->formatPrice(
            $store->roundPrice($store->convertPrice($price)), false
        );
    }

    protected function getProductSimple($product)
    {
        $stockModel = \Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);

        return array(
            'id'            => $product->getId(),
            'sku'           => $product->getSku(),
            'name'          => $product->getName(),
            'type'          => $product->getTypeId(),
            'quantity'      => (int) $stockModel->getQty(),
            'price'         => (float) $product->getPrice(),
            'description'   => nl2br(trim($product->getDescription())),
            'largeImage'    => (string) $product->getMediaConfig()->getMediaUrl($product->getData('image')),
        );
    }

    protected function getBlock($type)
    {
        $className = Mage::getConfig()->getBlockClassName($type);

        return new $className();
    }
}
