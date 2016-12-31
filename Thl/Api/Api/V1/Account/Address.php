<?php

class Thl_Api_Api_V1_Account_Address extends Sunel_Api_Model_Resource
{
	public function index()
	{
		$customer = $this->user;

		$country = Mage::getModel('directory/country');
		$region = Mage::getModel('directory/region');

		$data = [
			'default_billing' => $customer->getDefaultBilling(),
			'default_shipping'=> $customer->getDefaultShipping(),
			'list' => []
		];

		foreach ($customer->getAddresses() as $address) {
			$address = $address->getData();
			if(isset($address['region_id']) && !empty($address['region_id'])) {
				$address['region_name'] = $region->load($address['region_id'])->getName();
			} 
			if(isset($address['country_id']) && !empty($address['country_id'])){
				$address['country_name'] = $country->loadByCode($address['country_id'])->getName();
			}
			$data['list'][$address['entity_id']] = $address;
        }

        return $this->success([
			'data' => $data
		],200);	
	}

	public function view($id)
	{
		$customer = $this->user;
		$address = Mage::getModel("customer/address")->load($id);
		if(!$address->getId()) {
			return $this->errorBadRequest('Invalid Id given.');
		}

		if($address->getCustomerId() != $customer->getId()) {
			return $this->errorForbidden('Invalid Requets.');
		}

		$country = Mage::getModel('directory/country');
		$region = Mage::getModel('directory/region');

		$data = $address->getData();
		if(isset($data['region_id']) && !empty($data['region_id'])) {
			$data['region_name'] = $region->load($data['region_id'])->getName();
		} 
		if(isset($data['country_id']) && !empty($data['country_id'])){
			$data['country_name'] = $country->loadByCode($data['country_id'])->getName();
		}

		$data['is_default_billing'] = $customer->isAddressPrimary($address);
		$data['is_default_shipping'] = $customer->isAddressPrimary($address);
		
		return $this->success([
			'data' => $data
		],200);	
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
				'message' => $this->__('Your Address has been added Successfully.')
			]
		],201);	        
	}

	public function update($id)
	{
		$customer = $this->user;

		$address = Mage::getModel("customer/address")->load($id);
		if(!$address->getId()) {
			return $this->errorBadRequest('Invalid Id given.');
		}

		if($address->getCustomerId() != $customer->getId()) {
			return $this->errorForbidden('Invalid Requets.');
		}

		try {
		    $this->save($address);
		} catch (Exception $e) {
		     return $this->errorBadRequest($e->getMessage());
		}

		return $this->success([
			'data' => [
				'message' => $this->__('Your Address has been updated Successfully.')
			]
		],200);	        
	}

	public function delete($id)
	{
		$customer = $this->user;

		$address = Mage::getModel("customer/address")->load($id);
		if(!$address->getId()) {
			return $this->errorBadRequest('Invalid Id given.');
		}

		if($address->getCustomerId() != $customer->getId()) {
			return $this->errorForbidden('Invalid Requets.');
		}

		try {
		    $address->delete();
		} catch (Exception $e) {
		     return $this->errorBadRequest($e->getMessage());
		}

		return $this->success([
			'data' => [
				'message' => $this->__('Your Address has been deleted.')
			]
		],200);	        
	}

	protected function save($address=null)
	{
		$customer = $this->user;

		$postData = $this->getRequest()->only([
			'country_id', 'region_id', 'region', 
			'postcode', 'city','telephone', 
			'fax', 'company', 'street',
			'is_default_billing', 'is_default_shipping',
			'save_in_address_book'
		]);

		if(!$address) {
			$address = Mage::getModel("customer/address");	
			$id = null;
		} else {
			$id = $address->getId();
		}
		
		$address->setData($postData)
				->setId($id)
				->setCustomerId($customer->getId())
		        ->setFirstname($customer->getFirstname())
		        ->setMiddleName($customer->getMiddlename())
		        ->setLastname($customer->getLastname())
		       ;

		$address->save();
	}
}