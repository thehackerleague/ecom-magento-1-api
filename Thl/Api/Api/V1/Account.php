<?php

class Thl_Api_Api_V1_Account extends Sunel_Api_Model_Resource
{
	public function authenticate()
	{
		$token = Mage::helper('api3/auth')->decode($this->token);

		$postData = $this->getRequest()->only(['email','password']);

		if(empty($postData)) {
			return $this->errorBadRequest('No data given.');
		}

		$customerHelper = Mage::helper('tapi/auth');
		try {
			$customerHelper->authenticate($postData['email'], $postData['password']);
		} catch (Mage_Core_Exception $e) {
			return $this->errorUnauthorized($e->getMessage());
		}

		$customer = $customerHelper->loadByEmail($postData['email']);
		$newToken = $customerHelper->regenerateToken($token,$customer);

		return $this->success([
			'data' => [
				'token' => $newToken
			]
		],201);
	}

	public function register()
	{
		$postData = $this->getRequest()->only([
			'firstname', 'lastname', 'email',
			'password'
		]);

		$customerHelper = Mage::helper('tapi/auth');
 
		try {
		    $customer = $customerHelper->register($postData);
		} catch (Exception $e) {
		    return $this->errorBadRequest($e->getMessage());
		}
		$token = Mage::helper('api3/auth')->decode($this->token);

		$newToken = $customerHelper->regenerateToken($token,$customer);
		return $this->success([
			'data' => [
				'token' => $newToken
			]
		],201);
	}

	public function update()
	{
		$customer = $this->user;
		
		$postData = $this->getRequest()->only([
			'firstname', 'lastname',
		]);

		$customerHelper = Mage::helper('tapi/auth');				
		 
		try {
			$customer = $customerHelper->register($customer, $postData);		    
		} catch (Exception $e) {
		    return $this->errorBadRequest($e->getMessage());
		}
		return $this->success([
			'data' => [
				'message' => $this->__('Details updated successfully.')
			]	
		],200);
	}

	public function updatePassword()
	{
		$customer = $this->user;
		
		$postData = $this->getRequest()->only([
			'password',
		]);

		$customer->setPassword($postData['password']);
		 
		try {
		    $customer->save();
		} catch (Exception $e) {
		    return $this->errorBadRequest($e->getMessage());
		}
		return $this->success([
			'data' => [
				'message' => $this->__('Your Password has been Changed Successfully.')
			]
		],200);
	}

	public function account()
	{
		$customer = $this->user;

		$data = [
			'id' => $customer->getId(),
			'firstname' => $customer->getFirstname(),
        	'lastname' => $customer->getLastname(),
        	'email' => $customer->getEmail(),
        	'dob' => $customer->getDob(),
        ];	

        $gender = null;
        if ($customer->getGender() == 2) {
            $gender = $this->__("Female");
        } elseif ($customer->getGender() == 1) {
            $gender = $this->__("Male");
        }
        $data['gender'] = $gender;

        return $this->success([
        	'data' => $data
        ]);
	}
}