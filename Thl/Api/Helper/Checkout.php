<?php

use Thl_Api_Exceptions_InvalidAddressException as InvalidAddressException;

class Thl_Api_Helper_Checkout extends Mage_Core_Helper_Abstract
{
	/**
     * Save billing address information to quote
     * This method is called by One Page Checkout JS (AJAX) while saving the billing information.
     *
     * @param   array $data
     * @param   int $customerAddressId
     * @return  Mage_Checkout_Model_Type_Onepage
     */
    public function saveBilling($quote, $data, $customerAddressId)
    {
        if (empty($data)) {
        	Mage::throwException(Mage::helper('checkout')->__('Invalid data.'));
        }

        $address = $quote->getBillingAddress();
        /* @var $addressForm Mage_Customer_Model_Form */
        $addressForm = Mage::getModel('customer/form');
        $addressForm->setFormCode('customer_address_edit')
            ->setEntityType('customer_address')
            ->setIsAjaxRequest(1);

        if (!empty($customerAddressId)) {
            $customerAddress = Mage::getModel('customer/address')->load($customerAddressId);
            if ($customerAddress->getId()) {
                if ($customerAddress->getCustomerId() != $quote->getCustomerId()) {
                	Mage::throwException(Mage::helper('checkout')->__('Customer Address is not valid.'));
                }

                $address->importCustomerAddress($customerAddress)->setSaveInAddressBook(0);
                $addressForm->setEntity($address);
                $addressErrors  = $addressForm->validateData($address->getData());
                if ($addressErrors !== true) {
                	throw new InvalidAddressException($addressErrors); 
                }
            }
        } else {
            $addressForm->setEntity($address);
            // emulate request object
            $addressData    = $addressForm->extractData($addressForm->prepareRequest($data));
            $addressErrors  = $addressForm->validateData($addressData);
            if ($addressErrors !== true) {
                return array('error' => 1, 'message' => array_values($addressErrors));
            }
            $addressForm->compactData($addressData);
            //unset billing address attributes which were not shown in form
            foreach ($addressForm->getAttributes() as $attribute) {
                if (!isset($data[$attribute->getAttributeCode()])) {
                    $address->setData($attribute->getAttributeCode(), NULL);
                }
            }
            $address->setCustomerAddressId(null);
            // Additional form data, not fetched by extractData (as it fetches only attributes)
            $address->setSaveInAddressBook(empty($data['save_in_address_book']) ? 0 : 1);
        }

        // set email for newly created user
        if (!$address->getEmail() && $quote->getCustomerEmail()) {
            $address->setEmail($quote->getCustomerEmail());
        }

        // validate billing address
        if (($validateRes = $address->validate()) !== true) {
        	throw new InvalidAddressException($validateRes);
        }

        $address->implodeStreetAddress();

        $shippingComplete = false;

        if (!$quote->isVirtual()) {
            /**
             * Billing address using otions
             */
            $usingCase = isset($data['use_for_shipping']) ? (int)$data['use_for_shipping'] : 0;

            switch ($usingCase) {
                case 0:
                    $shipping = $quote->getShippingAddress();
                    $shipping->setSameAsBilling(0);
                    break;
                case 1:
                    $billing = clone $address;
                    $billing->unsAddressId()->unsAddressType();
                    $shipping = $quote->getShippingAddress();
                    $shippingMethod = $shipping->getShippingMethod();

                    // Billing address properties that must be always copied to shipping address
                    $requiredBillingAttributes = array('customer_address_id');

                    // don't reset original shipping data, if it was not changed by customer
                    foreach ($shipping->getData() as $shippingKey => $shippingValue) {
                        if (!is_null($shippingValue) && !is_null($billing->getData($shippingKey))
                            && !isset($data[$shippingKey]) && !in_array($shippingKey, $requiredBillingAttributes)
                        ) {
                            $billing->unsetData($shippingKey);
                        }
                    }
                    $shipping->addData($billing->getData())
                        ->setSameAsBilling(1)
                        ->setSaveInAddressBook(0)
                        ->setShippingMethod($shippingMethod)
                        ->setCollectShippingRates(true);

                    $shippingComplete = true;
                    break;
            }
        }

        $quote->collectTotals();
        $quote->save();

        if (!$quote->isVirtual() && $shippingComplete == true) {
            //Recollect Shipping rates for shipping methods
            $quote->getShippingAddress()->setCollectShippingRates(true);
        }

        return true;	
    }

    /**
     * Save checkout shipping address
     *
     * @param   array $data
     * @param   int $customerAddressId
     * @return  Mage_Checkout_Model_Type_Onepage
     */
    public function saveShipping($quote, $data, $customerAddressId)
    {
        if (empty($data)) {
        	Mage::throwException(Mage::helper('checkout')->__('Invalid data.'));
        }
        $address = $quote->getShippingAddress();

        /* @var $addressForm Mage_Customer_Model_Form */
        $addressForm    = Mage::getModel('customer/form');
        $addressForm->setFormCode('customer_address_edit')
            ->setEntityType('customer_address')
            ->setIsAjaxRequest(1);

        if (!empty($customerAddressId)) {
            $customerAddress = Mage::getModel('customer/address')->load($customerAddressId);
            if ($customerAddress->getId()) {
                if ($customerAddress->getCustomerId() != $quote->getCustomerId()) {
                	Mage::throwException(Mage::helper('checkout')->__('Customer Address is not valid.'));
                }

                $address->importCustomerAddress($customerAddress)->setSaveInAddressBook(0);
                $addressForm->setEntity($address);
                $addressErrors  = $addressForm->validateData($address->getData());
                if ($addressErrors !== true) {
                	throw new InvalidAddressException($addressErrors);
                }
            }
        } else {
            $addressForm->setEntity($address);
            // emulate request object
            $addressData    = $addressForm->extractData($addressForm->prepareRequest($data));
            $addressErrors  = $addressForm->validateData($addressData);
            if ($addressErrors !== true) {
                throw new InvalidAddressException($addressErrors);
            }
            $addressForm->compactData($addressData);
            // unset shipping address attributes which were not shown in form
            foreach ($addressForm->getAttributes() as $attribute) {
                if (!isset($data[$attribute->getAttributeCode()])) {
                    $address->setData($attribute->getAttributeCode(), NULL);
                }
            }

            $address->setCustomerAddressId(null);
            // Additional form data, not fetched by extractData (as it fetches only attributes)
            $address->setSaveInAddressBook(empty($data['save_in_address_book']) ? 0 : 1);
            $address->setSameAsBilling(empty($data['same_as_billing']) ? 0 : 1);
        }

        $address->implodeStreetAddress();
        $address->setCollectShippingRates(true);

        if (($validateRes = $address->validate())!==true) {
        	throw new InvalidAddressException($validateRes);
        }

        $quote->collectTotals()->save();

        return true;
    }

    public function listShippingMethod($quote)
    {
    	$store = $quote->getStore();
    	$flag = Mage::helper('tax')->displayShippingPriceIncludingTax();
     	$address = $quote->getShippingAddress();
        $address->collectShippingRates()->save();
        $shippingRateGroups = $address->getGroupedAllShippingRates();
        $result = [];
        $sole = count($shippingRateGroups) == 1;
        foreach ($shippingRateGroups as $code => $rates) {
            if ($name = Mage::getStoreConfig('carriers/'.$code.'/title')) {
                $name = $code;
            }
            $result[$code]['title'] = $name;
            $sole = $sole && count($rates) == 1;
            foreach ($rates as $rate) {
                $ratesData = [];
                if($rate->getErrorMessage()) {
                    $ratesData['error'] = $rate->getErrorMessage();
                } else {
                    $ratesData[$rate->getCode()]['title'] = $rate->getMethodTitle();
                    $ratesData[$rate->getCode()]['code'] = $rate->getCode();
                    $ratesData[$rate->getCode()]['prices'] = [
                        'price' => (float)$rate->getPrice(),
                        'include_tax' => $this->getShippingPrice($rate->getPrice(), true, $store, $address),
                        'exclude_tax' => $this->getShippingPrice($rate->getPrice(), $flag, $store, $address),
                    ];
                    if($sole) {
                    	$ratesData[$rate->getCode()]['selected'] = true;
                    } else {
                    	$ratesData[$rate->getCode()]['selected'] = ($address->getShippingMethod() == $rate->getCode());
                    }
                }
                $result[$code]['rates'] = $ratesData;
            }
        }
      	return $result;  
    }

    public function saveShippingMethod($quote, $shippingMethod)
    {
    	if (empty($shippingMethod)) {
    		Mage::throwException(Mage::helper('checkout')->__('Invalid shipping method.'));
        }
        $rate = $quote->getShippingAddress()->getShippingRateByCode($shippingMethod);
        if (!$rate) {
        	Mage::throwException(Mage::helper('checkout')->__('Invalid shipping method.'));
        }
        $quote->getShippingAddress()
            ->setShippingMethod($shippingMethod);

        $quote->save();    
        return true;    
    }

    public function listPaymentMethod($quote)
    {
    	$store = $quote ? $quote->getStoreId() : null;
    	$methodsList = array();
    	$methods = Mage::helper('payment')->getStoreMethods($store, $quote);
    	$oneMethod = count($methods) <= 1;
        foreach ($methods as $method) {
            if ($this->_canUseMethod($method, $quote) && $method->isApplicableToQuote(
                $quote,
                Mage_Payment_Model_Method_Abstract::CHECK_ZERO_TOTAL
            )) {
                $method->setInfoInstance($quote->getPayment());
                $methodsList[$method->getCode()]['title'] = $method->getTitle();
                $methodsList[$method->getCode()]['code'] = $method->getCode();
                if($oneMethod) {
                	$methodsList[$method->getCode()]['selected'] = true;
                } else {
                	$methodsList[$method->getCode()]['selected'] = ($method->getCode() == $quote->getPayment()->getMethod());
                }
            }
        }

        return $methodsList;
    }

    public function savePayment($quote, $data)
    {
    	if (empty($data)) {
    		Mage::throwException(Mage::helper('checkout')->__('Invalid payment method.'));
        }
        if ($quote->isVirtual()) {
            $quote->getBillingAddress()->setPaymentMethod(isset($data['method']) ? $data['method'] : null);
        } else {
            $quote->getShippingAddress()->setPaymentMethod(isset($data['method']) ? $data['method'] : null);
        }

        // shipping totals may be affected by payment method
        if (!$quote->isVirtual() && $quote->getShippingAddress()) {
            $quote->getShippingAddress()->setCollectShippingRates(true);
        }

        $data['checks'] = Mage_Payment_Model_Method_Abstract::CHECK_USE_CHECKOUT
            | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_COUNTRY
            | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_CURRENCY
            | Mage_Payment_Model_Method_Abstract::CHECK_ORDER_TOTAL_MIN_MAX
            | Mage_Payment_Model_Method_Abstract::CHECK_ZERO_TOTAL;

        $payment = $quote->getPayment();
        $payment->importData($data);

        $quote->save();

        return true;
    }

    /**
     * Create order based on checkout type. Create customer if necessary.
     *
     * @return Mage_Checkout_Model_Type_Onepage
     */
    public function saveOrder($quote, $customer)
    {
        if($customer) {
        	$this->_prepareCustomerQuote($quote, $customer);
        } else {
        	$this->_prepareGuestQuote($quote);
        }
        $quote->setTotalsCollectedFlag(true);
        
        $service = Mage::getModel('sales/service_quote', $quote);
        $service->submitAll();

        $order = $service->getOrder();
        if ($order) {
            Mage::dispatchEvent('checkout_type_onepage_save_order_after',
                array('order'=>$order, 'quote'=>$quote));

            /**
             * a flag to set that there will be redirect to third party after confirmation
             * eg: paypal standard ipn
             */
            $redirectUrl = $quote->getPayment()->getOrderPlaceRedirectUrl();
            /**
             * we only want to send to customer about new order when there is no redirect to third party
             */
            if (!$redirectUrl && $order->getCanSendNewEmailFlag()) {
                try {
                    $order->queueNewOrderEmail();
                } catch (Exception $e) {
                    Mage::logException($e);
                }
            }
        }

        // add recurring profiles information to the session
        $profiles = $service->getRecurringPaymentProfiles();

        Mage::dispatchEvent(
            'checkout_submit_all_after',
            array('order' => $order, 'quote' => $quote, 'recurring_profiles' => $profiles)
        );

        return $order;
    }

    /**
     * Prepare quote for guest checkout order submit
     *
     * @return Mage_Checkout_Model_Type_Onepage
     */
    protected function _prepareGuestQuote($quote)
    {
        $quote->setCustomerId(null)
            ->setCustomerEmail($quote->getBillingAddress()->getEmail())
            ->setCustomerIsGuest(true)
            ->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
    }

    /**
     * Prepare quote for customer order submit
     *
     * @return Mage_Checkout_Model_Type_Onepage
     */
    protected function _prepareCustomerQuote($quote, $customer)
    {
        $billing    = $quote->getBillingAddress();
        $shipping   = $quote->isVirtual() ? null : $quote->getShippingAddress();

        if (!$billing->getCustomerId() || $billing->getSaveInAddressBook()) {
            $customerBilling = $billing->exportCustomerAddress();
            $customer->addAddress($customerBilling);
            $billing->setCustomerAddress($customerBilling);
        }
        if ($shipping && !$shipping->getSameAsBilling() &&
            (!$shipping->getCustomerId() || $shipping->getSaveInAddressBook())) {
            $customerShipping = $shipping->exportCustomerAddress();
            $customer->addAddress($customerShipping);
            $shipping->setCustomerAddress($customerShipping);
        }

        if (isset($customerBilling) && !$customer->getDefaultBilling()) {
            $customerBilling->setIsDefaultBilling(true);
        }
        if ($shipping && isset($customerShipping) && !$customer->getDefaultShipping()) {
            $customerShipping->setIsDefaultShipping(true);
        } else if (isset($customerBilling) && !$customer->getDefaultShipping()) {
            $customerBilling->setIsDefaultShipping(true);
        }
        $quote->setCustomer($customer);
    }

    /**
     * Check payment method model
     *
     * @param Mage_Payment_Model_Method_Abstract $method
     * @return bool
     */
    protected function _canUseMethod($method, $quote)
    {
        return $method->isApplicableToQuote($quote, Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_COUNTRY
            | Mage_Payment_Model_Method_Abstract::CHECK_USE_FOR_CURRENCY
            | Mage_Payment_Model_Method_Abstract::CHECK_ORDER_TOTAL_MIN_MAX
        );
    }

    protected function formatPrice($price, $store)
    {
        $coreHelper = Mage::helper('core');
        return $coreHelper->formatPrice(
            $store->roundPrice($store->convertPrice($price)), false
        );
    }

    protected function getShippingPrice($price, $flag, $store, $address)
    {
        return $this->formatPrice(Mage::helper('tax')->getShippingPrice($price, $flag, $address), $store);
    }
}