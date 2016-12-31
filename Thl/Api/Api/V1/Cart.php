<?php

use Thl_Api_Exceptions_NoItemException as NoItemException;
use Thl_Api_Exceptions_InvalidProductException as InvalidProductException;
use Thl_Api_Exceptions_InvalidCartDataException as InvalidCartDataException;

class Thl_Api_Api_V1_Cart extends Sunel_Api_Model_Resource
{
    protected function _getCart()
    {
        return Mage::getSingleton('tapi/cart');
    }

    public function index()
    {
        $token = Mage::helper('api3/auth')->decode($this->token);
        $payload = $token->getClaim('payload');
        $quoteId = $payload->quote_id;
        $noCart = false;

        try {
            if (!$quoteId) {
                throw new NoItemException();
            }
            $quote = Mage::getModel('sales/quote')->loadActive($quoteId);
            if (!$quote || !$quote->getId()) {
                throw new NoItemException();
            }
            $quote->collectTotals();

            $cartItems = [];
            $cartHelper = Mage::helper('tapi/cart_render');
            foreach ($quote->getAllVisibleItems() as $item) {
            	$itemRender = $cartHelper->getRenderer($item);
                $product = $itemRender->getProduct();
                //$isVisibleProduct = $product->isVisibleInSiteVisibility();
                $options = $itemRender->getOptionList();

                $messages = array();
                // Add basic messages occuring during this page load
                $baseMessages = $item->getMessage(false);
                if ($baseMessages) {
                    foreach ($baseMessages as $message) {
                        $messages[] = array(
                            'text' => $message,
                            'type' => $item->getHasError() ? 'error' : 'notice'
                        );
                    }
                }

                $productName = $product->getName();
                switch ($item->getProductType()) {
                    case 'grouped':
                        $parentProduct = $itemRender->getGroupedProduct();
                        break;
                    case 'configurable':
                        $parentProduct = $itemRender->getConfigurableProduct();
                        break;
                    case 'simple':    
                    default:
                        $parentProduct = $product;
                        break;
                }

                $cartItems[] = [
                    'id' => $item->getId(),
                    'product_id' => $parentProduct->getId(),
                    'sku' => $item->getSku(),
                    'name' => $productName,
                    'image' => (string) $itemRender->getProductThumbnail(),
                    'price' => $this->formatPrice($item->getCalculationPrice(), $quote->getStore()),
                    'row_total' => $this->formatPrice($item->getRowTotal(), $quote->getStore()),
                    'qty' => $itemRender->getQty(),
                    //'is_visible' => $isVisibleProduct,
                    'options' => $options,
                    'messages' => $messages,
                ];
            }

            $data = $this->getTotalsData($quote);

            $totals = $data['totals'];
            $messages = $data['messages'];
            $coupon = $data['coupon'];

            $cartModel = $this->_getCart()->setQuote($quote);
            $crossSell = Mage::helper('tapi/product')->formCollection($cartModel->getCrossSellItems());

        } catch (NoItemException $e) {
            return $this->errorNotFound($this->__('You have no items in your shopping cart.'));
        }

        return $this->success([
            'data' => [
                'collection' => $cartItems,
                'totals' => $totals,
                'coupon' => $coupon,
                'cross_sell' => [
                    'collection' => $crossSell,
                ],
                'messages' => $messages,
            ]
        ], 200);
    }

    public function add()
    {
    	$token = Mage::helper('api3/auth')->decode($this->token);
        $payload = $token->getClaim('payload');
        $quoteId = $payload->quote_id;
        $productId = $this->getRequest()->input('product', false);
        $related = $this->getRequest()->input('related_product', false);
        $params = $this->getRequest()->only([
        	'super_attribute',
        	'qty', 'links', 'bundle_option', 'bundle_option_qty',
        	'super_group'
        ]);

    	try {
    		if (!$productId) {
	            throw new InvalidProductException;
	        }
	        $product = Mage::getModel('catalog/product')
                ->setStoreId(Mage::app()->getStore()->getId())
                ->load($productId);
            if (!$product->getId()) {
                throw new InvalidProductException;
            }

            if (isset($params['qty'])) {
                $filter = new Zend_Filter_LocalizedToNormalized(
                    array('locale' => Mage::app()->getLocale()->getLocaleCode())
                );
                $params['qty'] = $filter->filter($params['qty']);
            }

            $quote = null;
    		if ($quoteId) {
            	$quote = Mage::getModel('sales/quote')->loadActive($quoteId);    
            }

            if(!$quote || !$quote->getId()) {
                $quote = $this->_getCart()->createQuote();
                $quoteId = false;    
            }
            

            $this->addInfoToQuote($quote);

            $cart = $this->_getCart()->setQuote($quote);

            $cart->addProduct($product, $params);
            if (!empty($related)) {
                $cart->addProductsByIds(explode(',', $related));
            }

            $cart->save();

            $message = $this->__('%s was added to your shopping cart.', Mage::helper('core')->escapeHtml($product->getName()));

            $data = [
                'message' => $message,
            ];

            if (!$quoteId) {
                $payload->quote_id = $quote->getId();
                try{
                	$customerId = $token->getClaim('uid');
    	        } catch (OutOfBoundsException $e) {
    	            $customerId = null;
    	        }
                $newToken = (string) Mage::helper('api3/auth')->create(
    				(array)$payload, 
    				$customerId
    			);
                $data['token'] = $newToken;
            }

            return $this->success([
                'data' => $data
            ], 201);

        } catch (InvalidProductException $e) { 
            return $this->errorBadRequest($this->__('Invalid Product given.'));
        } catch (Mage_Core_Exception $e) { 
        	$fixMessages = array_unique(explode("\n", $e->getMessage()));
        	$messages = [];
            foreach ($fixMessages as $message) {
               $messages[] =  Mage::helper('core')->escapeHtml($message);
            }
            return $this->success([
                'error' => [
                    'messages' => $messages,
                    'code' => 401
                ]
            ], 401);
        } catch (Exception $e) {
        	Mage::logException($e);
            return $this->errorBadRequest($this->__('Cannot add the item to shopping cart.'));
        }
    }

    public function delete($id)
    {
        $token = Mage::helper('api3/auth')->decode($this->token);
        $payload = $token->getClaim('payload');
        $quoteId = $payload->quote_id;

    	try {
    		if (!$quoteId) {
                throw new NoItemException();
            }
            $quote = Mage::getModel('sales/quote')->loadActive($quoteId);
            if (!$quote || !$quote->getId()) {
                throw new NoItemException();
            }
            $this->_getCart()->setQuote($quote)->removeItem($id)
              ->save();
        } catch (NoItemException $e) {
            return $this->errorNotFound($this->__('You have no items in your shopping cart.'));
        } catch (Exception $e) {
        	Mage::logException($e);
            return $this->errorBadRequest($this->__('Cannot remove the item.'));
        }

        return $this->success([
            'data' => [
                'message' => $this->__('Item has been from the cart.')
            ]
        ], 200);
    }

    public function update()
    {
        $token = Mage::helper('api3/auth')->decode($this->token);
        $payload = $token->getClaim('payload');
        $quoteId = $payload->quote_id;

        try {
            if (!$quoteId) {
                throw new NoItemException();
            }
            $quote = Mage::getModel('sales/quote')->loadActive($quoteId);
            if (!$quote || !$quote->getId()) {
                throw new NoItemException();
            }

            $cartData = $this->getRequest()->input('cart', []);

            if(empty($cartData)) {
                throw new InvalidCartDataException; 
            }

            $filter = new Zend_Filter_LocalizedToNormalized(
                array('locale' => Mage::app()->getLocale()->getLocaleCode())
            );
            foreach ($cartData as $index => $data) {
                if (isset($data['qty'])) {
                    $cartData[$index]['qty'] = $filter->filter(trim($data['qty']));
                }
            }

            $cart = $this->_getCart()->setQuote($quote);

            $cartData = $cart->suggestItemsQty($cartData);
            $cart->updateItems($cartData)
                ->save();

        } catch (NoItemException $e) {
            return $this->errorNotFound($this->__('You have no items in your shopping cart.'));
        } catch (InvalidCartDataException $e) {
            return $this->errorBadRequest($this->__('Invalid Cart data given.'));
        } catch (Mage_Core_Exception $e) {
            return $this->errorBadRequest(Mage::helper('core')->escapeHtml($e->getMessage()));
        } catch (Exception $e) {
            Mage::logException($e);
            return $this->errorBadRequest($this->__('Cannot update shopping cart.'));
        }

        return $this->success([
            'data' => [
                'message' => $this->__('Shopping cart is updated.')
            ]
        ], 200);   
    }

    public function clear()
    {
        $token = Mage::helper('api3/auth')->decode($this->token);
        $payload = $token->getClaim('payload');
        $quoteId = $payload->quote_id;

        try {
            if (!$quoteId) {
                throw new NoItemException();
            }
            $quote = Mage::getModel('sales/quote')->loadActive($quoteId);
            if (!$quote || !$quote->getId()) {
                throw new NoItemException();
            }

            $cart = $this->_getCart()->setQuote($quote);
            $cart->truncate()->save();

        } catch (NoItemException $e) {
            return $this->errorNotFound($this->__('You have no items in your shopping cart.'));
        } catch (Mage_Core_Exception $e) {
            return $this->errorBadRequest(Mage::helper('core')->escapeHtml($e->getMessage()));
        } catch (Exception $e) {
            Mage::logException($e);
            return $this->errorBadRequest($this->__('Cannot update shopping cart.'));
        }

        return $this->success([
            'data' => [
                'message' => $this->__('Shopping cart is cleared.')
            ]
        ], 200);
    }

    public function coupon()
    {
        $token = Mage::helper('api3/auth')->decode($this->token);
        $payload = $token->getClaim('payload');
        $quoteId = $payload->quote_id;

        try {
            if (!$quoteId) {
                throw new NoItemException();
            }
            $quote = Mage::getModel('sales/quote')->loadActive($quoteId);
            if (!$quote || !$quote->getId()) {
                throw new NoItemException();
            }

            $coupon = $this->getRequest()->input('coupon', false);

            if (!$coupon) {
                Mage::throwException($this->__('Given coupon code is invalid.'));
            }

            $oldCouponCode = $quote->getCouponCode();

            if ($oldCouponCode) {
                Mage::throwException($this->__('%s code is already applied.', $oldCouponCode));
            }

            $quote->getShippingAddress()->setCollectShippingRates(true);
            $quote->setCouponCode($coupon)
                ->collectTotals()
                ->save();
                
            if ($coupon != $quote->getCouponCode()) {
                Mage::throwException($this->__('Given coupon code is invalid.'));
            }    

        } catch (NoItemException $e) {
            return $this->errorNotFound($this->__('You have no items in your shopping cart.'));
        } catch (Mage_Core_Exception $e) {
            return $this->errorBadRequest(Mage::helper('core')->escapeHtml($e->getMessage()));
        } catch (Exception $e) {
            Mage::logException($e);
            return $this->errorBadRequest($this->__('Cannot apply the coupon code.'));
        }

        return $this->success([
            'data' => [
                'message' => $this->__('Coupon code "%s" was applied.', Mage::helper('core')->escapeHtml($coupon))
            ]
        ], 200);
    }

    public function couponRemove()
    {
        $token = Mage::helper('api3/auth')->decode($this->token);
        $payload = $token->getClaim('payload');
        $quoteId = $payload->quote_id;

        try {
            if (!$quoteId) {
                throw new NoItemException();
            }
            $quote = Mage::getModel('sales/quote')->loadActive($quoteId);
            if (!$quote || !$quote->getId()) {
                throw new NoItemException();
            }

            $oldCouponCode = $quote->getCouponCode();

            if (!$oldCouponCode) {
                Mage::throwException($this->__('No coupon code is applied.'));
            }

            $quote->getShippingAddress()->setCollectShippingRates(true);
            $quote->setCouponCode('')
                ->collectTotals()
                ->save();

        } catch (NoItemException $e) {
            return $this->errorNotFound($this->__('You have no items in your shopping cart.'));
        } catch (Mage_Core_Exception $e) {
            return $this->errorBadRequest(Mage::helper('core')->escapeHtml($e->getMessage()));
        } catch (Exception $e) {
            Mage::logException($e);
            return $this->errorBadRequest($this->__('Cannot update shopping cart.'));
        }

        return $this->success([
            'data' => [
                'message' => $this->__('Coupon code was canceled.')
            ]
        ], 200);
    }

    public function getTotals()
    {
    	$token = Mage::helper('api3/auth')->decode($this->token);
        $payload = $token->getClaim('payload');
        $quoteId = $payload->quote_id;

    	try {
    		if (!$quoteId) {
                throw new NoItemException();
            }
            $quote = Mage::getModel('sales/quote')->loadActive($quoteId);
            if (!$quote || !$quote->getId()) {
                throw new NoItemException();
            }

            $data = $this->getTotalsData($quote);

        } catch (NoItemException $e) {
            return $this->errorNotFound($this->__('You have no items in your shopping cart.'));
        }

        return $this->success([
            'data' => $data
        ], 200);
    }

    protected function addInfoToQuote($quote)
    {
    	if($cusotmer = $this->user) {
    		if($quote->getId()) {
    			if($quote->getCustomerId() != $cusotmer->getId()) {
    				Mage::throwException(Mage::helper('checkout')->__('Invalid cusotmer.'));
    			}
    		}
    	}
    	$quote->setRemoteIp($this->getRequest()->ip());
    }

    protected function getTotalsData($quote) 
    {
        $quote->getShippingAddress()->setCollectShippingRates(true);
        $quote->collectTotals();

        $totalsHelper = Mage::helper('tapi/cart_totals')->setTotals($quote->getTotals());

        $totals = "<table id='shopping-cart-totals-table'> <col /> col width='1' />";
        $totals .= "<tbody>". $totalsHelper->renderTotals() ."</tbody>";
        $totals .= "<tfoot>". $totalsHelper->renderTotals('footer') ."</tfoot>";
        $totals .= "</table>";

        // Compose array of messages to add
        $messages = array();
        foreach ($quote->getMessages() as $message) {
            if ($message) {
                // Escape HTML entities in quote message to prevent XSS
                $message->setCode(Mage::helper('core')->escapeHtml($message->getCode()));
                $messages[] = $message;
            }
        }

        $coupon = ($quote->getCouponCode())?:'';

        return ['totals' => $totals, 'messages' => $messages, 'coupon' => $coupon];
    }

    protected function formatPrice($price, $store)
    {
        $coreHelper = Mage::helper('core');
        return $coreHelper->formatPrice(
            $store->roundPrice($store->convertPrice($price)), false
        );
    }
}
