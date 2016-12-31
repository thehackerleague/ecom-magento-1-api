<?php

class Thl_Api_Helper_Auth extends Mage_Core_Helper_Abstract
{
	/**
	 * @param string $email
	 * @param string $password
	 *  
	 * @return bool
	 */
	public function authenticate($email, $password)
	{
		$customerModel = Mage::getModel('customer/customer');
		return $customerModel
				->setWebsiteId(Mage::app()->getWebsite()->getId())
				->authenticate($email, $password);
	}

	/**
	 * @param string $email
	 *  
	 * @return Mage_Customer_Model_Customer
	 */
	public function loadByEmail($email)
	{
		$customerModel = Mage::getModel('customer/customer');
		return $customerModel
			->setWebsiteId(Mage::app()->getWebsite()->getId())
			->loadByEmail($email);
	}

	/**
	 * @param array $data
	 *  
	 * @return Mage_Customer_Model_Customer
	 */
	public function register($data)
	{
		$websiteId = Mage::app()->getWebsite()->getId();
		$store = Mage::app()->getStore();

		$customer = Mage::getModel("customer/customer");
		$customer   ->setWebsiteId($websiteId)
		            ->setStore($store)
		            ->setFirstname($data['firstname'])
		            ->setLastname($data['lastname'])
		            ->setEmail($data['email'])
		            ->setPassword($data['password']);
		$customer->save();
		return $customer;	            
	}

	/**
	 * @param Mage_Customer_Model_Customer $customer
	 * @param array $data
	 *  
	 * @return Mage_Customer_Model_Customer
	 */
	public function update($customer, $data)
	{
		$customer   ->setFirstname($data['firstname'])
		            ->setLastname($data['lastname']);
		$customer->save();		
		return $customer;
	}

	public function regenerateToken($token, $customer)
	{
		$payload = $token->getClaim('payload');

		$quoteId = Mage::helper('tapi/quote')->checkQuote($payload->quote_id, $customer);

		$payload->quote_id = $quoteId;
		$newToken = (string) Mage::helper('api3/auth')->create(
			(array)$payload, 
			$customer->getId()
		);

		return $newToken;
	}
}