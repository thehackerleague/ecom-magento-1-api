<?php

class Thl_Api_Api_V1_Category extends Sunel_Api_Model_Resource
{
	public function index()
	{
		$categories = Mage::helper('catalog/category')->getStoreCategories();

        $helper = Mage::helper('tapi/category');
		$data = $helper->getTreeCategories($categories);

		return $this->success([
        	'data' => $data
        ], 200);
	}

    public function listProducts($categoryId)
    {
        $category = Mage::getModel('catalog/category')
            ->setStoreId(Mage::app()->getStore()->getId())
            ->load($categoryId);
        Mage::register('current_category', $category);    
        $helper = Mage::helper('tapi/product');
        return $this->success([
            'data' => $helper->getProductList($this->getRequest()->all())
        ], 200);
    }

    public function getFilters($categoryId)
    {
        $category = Mage::getModel('catalog/category')
            ->setStoreId(Mage::app()->getStore()->getId())
            ->load($categoryId);
        Mage::register('current_category', $category);    
        $helper = Mage::helper('tapi/product');
        return $this->success([
            'data' => $helper->getProducFilters($this->getRequest()->all())
        ], 200);
    }
}