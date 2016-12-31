<?php

class Thl_Api_Helper_Cart_Render extends Mage_Core_Helper_Abstract
{
	protected $_itemRenders = array();

    public function __construct()
    {
        $this->addItemRender(
            'default', 'checkout/cart_item_renderer',
            'checkout/cart/item/default.phtml'
        );
        $this->addItemRender(
        	'simple', 'checkout/cart_item_renderer', 
        	'checkout/cart/item/default.phtml'
        );
        $this->addItemRender(
        	'grouped', 'checkout/cart_item_renderer_grouped', 
        	'checkout/cart/item/default.phtml'
        );
        $this->addItemRender(
        	'configurable', 'checkout/cart_item_renderer_configurable', 
        	'checkout/cart/item/default.phtml'
        );
    }

    /**
     * Add renderer for item product type
     *
     * @param   string $productType
     * @param   string $blockType
     * @param   string $template
     * @return  Mage_Checkout_Block_Cart_Abstract
     */
    public function addItemRender($productType, $blockType, $template)
    {
        $this->_itemRenders[$productType] = array(
            'block' => $blockType,
            'template' => $template,
            'blockInstance' => null
        );
        return $this;
    }

    /**
     * Get renderer block instance by product type code
     *
     * @param   string $type
     * @return  array
     */
    public function getItemRenderer($type)
    {
        if (!isset($this->_itemRenders[$type])) {
            $type = 'default';
        }
        if (is_null($this->_itemRenders[$type]['blockInstance'])) {
             $this->_itemRenders[$type]['blockInstance'] = $this
                ->getBlock($this->_itemRenders[$type]['block'])
                    ->setTemplate($this->_itemRenders[$type]['template']);
        }

        return $this->_itemRenders[$type]['blockInstance'];
    }

    /**
     * Get item row html
     *
     * @param   Mage_Sales_Model_Quote_Item $item
     * @return  string
     */
    public function getRenderer(Mage_Sales_Model_Quote_Item $item)
    {
        return $this->getItemRenderer($item->getProductType())->setItem($item);
    }

    protected function getBlock($type)
    {
        $className = Mage::getConfig()->getBlockClassName($type);

        return new $className();
    }
}