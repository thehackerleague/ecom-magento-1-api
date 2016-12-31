<?php

class Thl_Api_Helper_Quote extends Mage_Core_Helper_Abstract
{
    public function checkQuote($oldQuoteId, $customer)
    {	
    	$quote = null;
        $customerQuote = Mage::getModel('sales/quote')
            ->setStoreId(Mage::app()->getStore()->getId())
            ->loadByCustomer($customer->getId());

        if ($customerQuote->getId() && $oldQuoteId != $customerQuote->getId()) {
        	if($oldQuoteId) {
	        	$quote = Mage::getModel('sales/quote')->loadActive($oldQuoteId);
	        	if($quote->getId()) {
		        	$customerQuote->merge($quote)
		                    ->collectTotals()
		                    ->save();
		            $quote->delete();     
		        }
	        }
	        $quote = $customerQuote; 
        } else {
        	if($oldQuoteId) {
        		$quote = Mage::getModel('sales/quote')->loadActive($oldQuoteId);
        		$quote->getBillingAddress();
	            $quote->getShippingAddress();
	            $quote->setCustomer($customer)
                    ->setCustomerIsGuest(0)
	                ->setTotalsCollectedFlag(false)
	                ->collectTotals()
	                ->save();
        	}
        }

        if($quote) {
        	return $quote->getId();
        }

        return null;
    }
}
