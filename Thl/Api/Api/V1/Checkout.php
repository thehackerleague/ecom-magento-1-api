<?php

use Thl_Api_Exceptions_NoItemException as NoItemException;
use Thl_Api_Exceptions_InvalidAddressException as InvalidAddressException;

class Thl_Api_Api_V1_Checkout extends Sunel_Api_Model_Resource
{
    public function init()
    {
        $steps = [
            'billing',
            'shipping',
            'shipping_method',
            'payment',
            'order'
        ];

        if (!$this->user) {
            array_unshift($steps, 'guest');
        }

        return $this->success([
            'data' => [
                'allow_sections' => $steps,
            ]
        ], 200);
    }

    public function guest()
    {
        if (!$this->user) {
            $token = Mage::helper('api3/auth')->decode($this->token);
            $payload = $token->getClaim('payload');
            $quoteId = $payload->quote_id;

            try {
                $quote = Mage::getModel('sales/quote')->loadActive($quoteId);
                if (!$quote || !$quote->getId()) {
                    throw new NoItemException();
                }
                $email = $this->getRequest()->input('email', false);

                if(!$email) {
                    return $this->errorBadRequest($this->__('Email is required.'));
                }

                $quote->setCustomerEmail($email)
                        ->setCustomerIsGuest(1)->save();
             } catch (NoItemException $e) {
                return $this->errorNotFound($this->__('You have no items in your shopping cart.'));
            }   
        }

        return $this->success([
            'data' => [
                'goto_section' => 'billing',
            ]
        ], 200);
    }

    public function billing()
    {
        $token = Mage::helper('api3/auth')->decode($this->token);
        $payload = $token->getClaim('payload');
        $quoteId = $payload->quote_id;

        try {
            $quote = Mage::getModel('sales/quote')->loadActive($quoteId);
            if (!$quote || !$quote->getId()) {
                throw new NoItemException();
            }
            $data = $this->getRequest()->input('billing', array());
            $customerAddressId = $this->getRequest()->input('billing_address_id', false);

            $checkoutHelper = Mage::helper('tapi/checkout');

            $checkoutHelper->saveBilling($quote, $data, $customerAddressId);

            $result = [];
            if ($quote->isVirtual()) {
                $result['goto_section'] = 'payment';
                $result['payment_method'] = $checkoutHelper->listPaymentMethod($quote);
            } elseif (isset($data['use_for_shipping']) && $data['use_for_shipping'] == 1) {
                $result['goto_section'] = 'shipping_method';
                $result['allow_sections'] = array('shipping');
                $result['duplicateBillingInfo'] = 'true';
                $result['shipping_method'] = $checkoutHelper->listShippingMethod($quote);
            } else {
                $result['goto_section'] = 'shipping';
            }
            
        } catch (NoItemException $e) {
            return $this->errorNotFound($this->__('You have no items in your shopping cart.'));
        } catch(InvalidAddressException $e) {
            return $this->success([
                'error' => [
                    'messages' => $e->messages,
                    'code' => 401
                ]
            ], 401);
        }catch (Mage_Core_Exception $e) {
            return $this->errorBadRequest(Mage::helper('core')->escapeHtml($e->getMessage()));
        } catch (Exception $e) {
            Mage::logException($e);
            return $this->errorBadRequest($this->__('Cannot proceed checkout.'));
        }

        return $this->success([
            'data' => $result
        ], 200);
    }

    public function shipping()
    {
        $token = Mage::helper('api3/auth')->decode($this->token);
        $payload = $token->getClaim('payload');
        $quoteId = $payload->quote_id;

        try {
            $quote = Mage::getModel('sales/quote')->loadActive($quoteId);
            if (!$quote || !$quote->getId()) {
                throw new NoItemException();
            }
            $data = $this->getRequest()->input('shipping', array());
            $customerAddressId = $this->getRequest()->input('shipping_address_id', false);

            $checkoutHelper = Mage::helper('tapi/checkout');
            $checkoutHelper->saveShipping($quote, $data, $customerAddressId);

            $result = [
                'goto_section' => 'shipping_method',
                'shipping_method' => $checkoutHelper->listShippingMethod($quote)
            ];
            
        } catch (NoItemException $e) {
            return $this->errorNotFound($this->__('You have no items in your shopping cart.'));
        } catch(InvalidAddressException $e) {
            return $this->success([
                'error' => [
                    'messages' => $e->messages,
                    'code' => 401
                ]
            ], 401);
        }catch (Mage_Core_Exception $e) {
            return $this->errorBadRequest(Mage::helper('core')->escapeHtml($e->getMessage()));
        } catch (Exception $e) {
            Mage::logException($e);
            return $this->errorBadRequest($this->__('Cannot proceed checkout.'));
        }

        return $this->success([
            'data' => $result
        ], 200);
    }

    public function listShippingMethod()
    {
        $token = Mage::helper('api3/auth')->decode($this->token);
        $payload = $token->getClaim('payload');
        $quoteId = $payload->quote_id;

        try {
            $quote = Mage::getModel('sales/quote')->loadActive($quoteId);
            if (!$quote || !$quote->getId()) {
                throw new NoItemException();
            }

            $checkoutHelper = Mage::helper('tapi/checkout');
            $result = $checkoutHelper->listShippingMethod($quote); 
            
        } catch (NoItemException $e) {
            return $this->errorNotFound($this->__('You have no items in your shopping cart.'));
        } catch (Mage_Core_Exception $e) {
            return $this->errorBadRequest(Mage::helper('core')->escapeHtml($e->getMessage()));
        } catch (Exception $e) {
            Mage::logException($e);
            return $this->errorBadRequest($this->__('Cannot proceed checkout.'));
        }

        return $this->success([
            'data' => $result
        ], 200);
    }

    public function shippingMethod()
    {
        $token = Mage::helper('api3/auth')->decode($this->token);
        $payload = $token->getClaim('payload');
        $quoteId = $payload->quote_id;

        try {
            $quote = Mage::getModel('sales/quote')->loadActive($quoteId);
            if (!$quote || !$quote->getId()) {
                throw new NoItemException();
            }

            $data = $this->getRequest()->input('shipping_method', '');

            $checkoutHelper = Mage::helper('tapi/checkout');
            $checkoutHelper->saveShippingMethod($quote, $data); 

            Mage::dispatchEvent(
                'checkout_controller_onepage_save_shipping_method',
                array(
                      'request' => Mage::app()->getRequest(),
                      'quote'   => $quote
                )
            );
            $quote->collectTotals();

            $result = [
                'goto_section' => 'payment',
                'payment_method' => $checkoutHelper->listPaymentMethod($quote)
            ];
            
        } catch (NoItemException $e) {
            return $this->errorNotFound($this->__('You have no items in your shopping cart.'));
        } catch (Mage_Core_Exception $e) {
            return $this->errorBadRequest(Mage::helper('core')->escapeHtml($e->getMessage()));
        } catch (Exception $e) {
            Mage::logException($e);
            return $this->errorBadRequest($this->__('Cannot proceed checkout.'));
        }

        return $this->success([
            'data' => $result
        ], 200);
    }

    public function listPaymentMethod()
    {
        $token = Mage::helper('api3/auth')->decode($this->token);
        $payload = $token->getClaim('payload');
        $quoteId = $payload->quote_id;

        try {
            $quote = Mage::getModel('sales/quote')->loadActive($quoteId);
            if (!$quote || !$quote->getId()) {
                throw new NoItemException();
            }

            $checkoutHelper = Mage::helper('tapi/checkout');
            $result = $checkoutHelper->listPaymentMethod($quote); 
            
        } catch (NoItemException $e) {
            return $this->errorNotFound($this->__('You have no items in your shopping cart.'));
        } catch (Mage_Core_Exception $e) {
            return $this->errorBadRequest(Mage::helper('core')->escapeHtml($e->getMessage()));
        } catch (Exception $e) {
            Mage::logException($e);
            return $this->errorBadRequest($this->__('Cannot proceed checkout.'));
        }

        return $this->success([
            'data' => $result
        ], 200);
    }

    public function paymentMethod()
    {
        $token = Mage::helper('api3/auth')->decode($this->token);
        $payload = $token->getClaim('payload');
        $quoteId = $payload->quote_id;

        try {
            $quote = Mage::getModel('sales/quote')->loadActive($quoteId);
            if (!$quote || !$quote->getId()) {
                throw new NoItemException();
            }

            $data = $this->getRequest()->input('payment', '');

            $checkoutHelper = Mage::helper('tapi/checkout');
            $checkoutHelper->savePayment($quote, $data); 

            // get section and redirect data
            $redirectUrl = $quote->getPayment()->getCheckoutRedirectUrl();
            if (!$redirectUrl) {
                $result['goto_section'] = 'review';
            } else {
                $result['redirect'] = $redirectUrl;
            }
            
        } catch (Mage_Payment_Exception $e) {
            if ($e->getFields()) {
                $result['error']['fields'] = $e->getFields();
            }
            $result['error']['message']= $e->getMessage();
            return $this->success($result, 401);
        } catch (NoItemException $e) {
            return $this->errorNotFound($this->__('You have no items in your shopping cart.'));
        } catch (Mage_Core_Exception $e) {
            return $this->errorBadRequest(Mage::helper('core')->escapeHtml($e->getMessage()));
        } catch (Exception $e) {
            Mage::logException($e);
            return $this->errorBadRequest($this->__('Cannot proceed checkout.'));
        }

        return $this->success([
            'data' => $result
        ], 200);
    }

    public function order()
    {
        $token = Mage::helper('api3/auth')->decode($this->token);
        $payload = $token->getClaim('payload');
        $quoteId = $payload->quote_id;

        $checkoutHelper = Mage::helper('tapi/checkout');
        try {
            $quote = Mage::getModel('sales/quote')->loadActive($quoteId);
            if (!$quote || !$quote->getId()) {
                throw new NoItemException();
            }
            
            $order = $checkoutHelper->saveOrder($quote, $this->user);

            $quote->setActive(0)->save();
            
        } catch (Mage_Payment_Model_Info_Exception $e) {  
            $result = [
                'goto_section' => 'payment',
                'payment_method' => $checkoutHelper->listPaymentMethod($quote)
            ]; 
            $message = $e->getMessage();
            if (!empty($message)) {
                $result['error']['message'] = $message;
            }  
            return $this->success($result, 401);
        } catch (NoItemException $e) {
            return $this->errorNotFound($this->__('You have no items in your shopping cart.'));
        } catch (Mage_Core_Exception $e) {
            return $this->errorBadRequest(Mage::helper('core')->escapeHtml($e->getMessage()));
        } catch (Exception $e) {
            Mage::logException($e);
            return $this->errorBadRequest($this->__('Cannot proceed checkout.'));
        }

        $data =  [
            'success' => true,
            'order_id' => $order->getId()
        ];

        $payload->quote_id = null;
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

        return $this->success([
            'data' => $data
        ], 200);
    }
}
