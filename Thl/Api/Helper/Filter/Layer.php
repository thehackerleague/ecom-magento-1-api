<?php

class Thl_Api_Helper_Filter_Layer extends Mage_Core_Helper_Abstract
{
	/**
     * State block name
     *
     * @var string
     */
    protected $_stateBlockName;

    /**
     * Category Block Name
     *
     * @var string
     */
    protected $_categoryBlockName;

    /**
     * Attribute Filter Block Name
     *
     * @var string
     */
    protected $_attributeFilterBlockName;

    /**
     * Price Filter Block Name
     *
     * @var string
     */
    protected $_priceFilterBlockName;

    /**
     * Decimal Filter Block Name
     *
     * @var string
     */
    protected $_decimalFilterBlockName;

    protected $_mode = 'normal';

    /**
     * Internal constructor
     */
    public function __construct()
    {
        $this->_initBlocks();
    }

	/**
     * Initialize blocks names
     */
    protected function _initBlocks()
    {
        $this->_stateBlockName              = 'catalog/layer_state';
        $this->_categoryBlockName           = 'catalog/layer_filter_category';
        $this->_attributeFilterBlockName    = 'catalog/layer_filter_attribute';
        $this->_priceFilterBlockName        = 'catalog/layer_filter_price';
        $this->_decimalFilterBlockName      = 'catalog/layer_filter_decimal';
    }

    public function setMode($mode)
    {
        $this->_mode = $mode;
    }

    public function apply()
    {
    	//$this->getLayer()->setProductCollection($collection);
    	$this->prepareFilter();

    	return $this;
    }

    /**
     * Prepare child blocks
     *
     * @return Mage_Catalog_Block_Layer_View
     */
    protected function prepareFilter()
    {
    	$categoryBlock = $this->createBlock($this->_categoryBlockName)
            ->setLayer($this->getLayer())
            ->init();

        $this->getLayer()->setData('category_filter', $categoryBlock);

        $filterableAttributes = $this->_getFilterableAttributes();
        foreach ($filterableAttributes as $attribute) {
            if ($attribute->getAttributeCode() == 'price') {
                $filterBlockName = $this->_priceFilterBlockName;
            } elseif ($attribute->getBackendType() == 'decimal') {
                $filterBlockName = $this->_decimalFilterBlockName;
            } else {
                $filterBlockName = $this->_attributeFilterBlockName;
            }

            $this->getLayer()->setData($attribute->getAttributeCode() . '_filter',
                $this->createBlock($filterBlockName)
                    ->setLayer($this->getLayer())
                    ->setAttributeModel($attribute)
                    ->init());
        }

        $this->getLayer()->apply();
    }

    /**
     * Get all layer filters
     *
     * @return array
     */
    public function getFilters()
    {
        $filters = array();
        if ($categoryFilter = $this->_getCategoryFilter()) {
            $filters[] = $categoryFilter;
        }

        $filterableAttributes = $this->_getFilterableAttributes();
        foreach ($filterableAttributes as $attribute) {
            $filters[] = $this->getLayer()->getData($attribute->getAttributeCode() . '_filter');
        }

        return $filters;
    }

	/**
     * Get layer object
     *
     * @return Thl_Api_Model_Catalog_Layer
     */
    public function getLayer()
    {
        $model = 'catalog/layer';

        if($this->_mode == 'search') {
            $model = 'catalogsearch/layer';
        }
        return Mage::getSingleton($model);
    }

    /**
     * Get all fiterable attributes of current category
     *
     * @return array
     */
    protected function _getFilterableAttributes()
    {
        $attributes = $this->getLayer()->getData('_filterable_attributes');
        if (is_null($attributes)) {
            $attributes = $this->getLayer()->getFilterableAttributes();
            $this->getLayer()->setData('_filterable_attributes', $attributes);
        }
        return $attributes;
    }

     /**
     * Get category filter block
     *
     * @return Mage_Catalog_Block_Layer_Filter_Category
     */
    protected function _getCategoryFilter()
    {
        return $this->getLayer()->getData('category_filter');
    }

    protected function createBlock($type)
    {
        $className = Mage::getConfig()->getBlockClassName($type);

        return new $className();
    }
}