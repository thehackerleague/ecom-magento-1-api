<?php

class Thl_Api_Api_V1_Product extends Sunel_Api_Model_Resource
{
	public function index()
	{
        $helper = Mage::helper('tapi/product');
        return $this->success([
        	'data' => $helper->getProductList($this->getRequest()->all())
        ], 200);
	}

    public function getFilters()
    {
        $helper = Mage::helper('tapi/product');
        return $this->success([
            'data' => $helper->getProducFilters($this->getRequest()->all())
        ], 200);
    }

	public function view($productId)
    {
        $helper = Mage::helper('tapi/product');
	 	return $this->success([
        	'data' =>  $helper->getProduct($productId)
        ], 200);
	}
    
    /**
     * @method getProductOptions
     * @param string $attributeName
     * @param bool $processCounts
     * @return string
     */
    public function getProductOptions($attributeName, $processCounts)
    {
        /**
         * @method getCount
         * @param number $value
         * @return int
         */
        $getCount = function ($value) use ($attributeName) {
            $collection = \Mage::getModel('catalog/product')->getCollection();
            $collection->addFieldToFilter(array(array('attribute' => $attributeName, 'eq' => $value)));
            return count($collection);
        };
        $attribute = \Mage::getSingleton('eav/config')->getAttribute('catalog_product', $attributeName);
        $options   = array();
        if ($attribute->usesSource()) {
            $options = $attribute->getSource()->getAllOptions(false);
        }
        $response = array();
        foreach ($options as $option) {
            $current = array(
                'id'    => (int) $option['value'],
                'label' => $option['label']
            );
            if ($processCounts) {
                // Process the counts if the developer wants them to be!
                $response['count'] = $getCount($option['value']);
            }
            $response[] = $current;
        }
        return $response;
    }
}