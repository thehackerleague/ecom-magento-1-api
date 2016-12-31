<?php

class Thl_Api_Api_V1_Order extends Sunel_Api_Model_Resource
{
	public function guestView()
	{
		$postData = $this->getRequest()->only(['order_id','email']);

		if(empty($postData)) {
			return $this->errorBadRequest('No data given.');
		}

		$order = Mage::getModel('sales/order')->loadByIncrementId($postData['order_id']);

		if(!$order || !$order->getId()) {
			return $this->errorBadRequest('Given order id is invalid.');
		}

		if($order->getCustomerEmail() != $postData['email']) {
			return $this->errorBadRequest('Invalid Request.');
		}

		$orderHelper = Mage::helper('tapi/order');

		$data = $orderHelper->getOrderItems($order);

		return $this->success([
			'data' => $data
		],200);
	}

	public function index()
	{
		$user = $this->user;	

		$orderHelper = Mage::helper('tapi/order');

		$data = $orderHelper->getOrders($user, $this->getRequest()->all());
		
		return $this->success([
			'data' => [
				'collection' => $data,
			]
		],200);

	}
}