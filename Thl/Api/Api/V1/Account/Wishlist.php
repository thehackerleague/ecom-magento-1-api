<?php

class Thl_Api_Api_V1_Account_Wishlist extends Sunel_Api_Model_Resource
{
    public function index()
    {
        $customer = $this->user;
        $helper = Mage::helper('tapi/product');
        $wishList = Mage::getSingleton('wishlist/wishlist')->loadByCustomer($customer);
        $data = [];
        $inputs = $this->getRequest()->all();
        if ($wishList->getId()) {
            $wishListItemCollection = $wishList->getItemCollection()
                ->addFieldToSelect(['product_id'])
                ->addFieldToFilter('store_id', ['eq' => Mage::app()->getStore()->getId()]);

            if (count($wishListItemCollection)) {
                $arrProductIds = array();

                foreach ($wishListItemCollection as $item) {
                    /* @var $product Mage_Catalog_Model_Product */
                    $arrProductIds[] =    $item->getProductId();
                }
                $inputs['only'] = implode(',', $arrProductIds);
                $data = $helper->getProductList($inputs);
            }
        }
        
        return $this->success([
            'data' => $data
        ], 200);
    }

    public function add()
    {
        try {
            $this->save();
        } catch (Exception $e) {
            return $this->errorBadRequest($e->getMessage());
        }

        return $this->success([
            'data' => [
                'message' => $this->__('The Product has been added to your wishlist.')
            ]
        ], 201);
    }

    public function remove($id)
    {
        $customer = $this->user;
        $wishlist = Mage::getModel('wishlist/wishlist')->loadByCustomer($customer);
        if (!$wishlist->getId()) {
            return $this->errorBadRequest($this->__('No Items are Avaliable in Wishlist.'));
        }

        $wishListItemCollection = $wishlist->getItemCollection()
            ->addFieldToSelect(['product_id'])
            ->addFieldToFilter('store_id', ['eq' => Mage::app()->getStore()->getId()])
            ->addFieldToFilter('product_id', ['eq' => $id]);

        if (!count($wishListItemCollection)) {
            return $this->errorBadRequest($this->__('No Items are Avaliable in Wishlist.'));
        }

        foreach ($wishListItemCollection as $item) {
            /* @var $product Mage_Catalog_Model_Product */
            if ($item->getProductId() == $id) {
                $item->delete();
                break;
            }
        }

        return $this->success([
            'data' => [
                'message' => $this->__('The Product has been removed to your wishlist.')
            ]
        ], 200);
    }

    protected function save()
    {
        $customer = $this->user;

        $productId = $this->getRequest()->input('product_id');

        $wishlist = Mage::getModel('wishlist/wishlist')->loadByCustomer($customer, true);

        $product = Mage::getModel('catalog/product')->load($productId);

        if (!$product->getId()) {
            throw new Exception("Invalid product id given");
        }

        $buyRequest = new Varien_Object(array());

        $result = $wishlist->addNewItem($product, $buyRequest);
        $wishlist->save();
    }
}
