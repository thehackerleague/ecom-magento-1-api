<?php

class Thl_Api_Api_Route 
{
	public function getRoutes($api)
	{
		$api->version('v1', function ($api) {
			$api->get('stores', 'tapi/v1_store@getStoreList');
		    $api->group(['middleware' => 'store_check'], function ($api) {
		        $api->post('token', 'tapi/v1_store@getToken');
		        $api->group(['middleware' => 'tokenized'], function ($api) {
		        	$this->addStoreRoute($api, 'v1');
		        	$this->addAuthRoute($api, 'v1');
		        	$this->addAccountRoute($api, 'v1');
		        	$this->addProductRoute($api, 'v1');
		        	$this->addCategoryRoute($api, 'v1');
		        	$this->addCartRoute($api, 'v1');
		        	$this->addCheckoutRoute($api, 'v1');
		        	$this->addOrderRoute($api, 'v1');
		        });
		    });
		});
	}

	protected function addStoreRoute($api, $version)
	{
		$api->get('country/list', "tapi/{$version}_store@getCountryList");
		$api->get('region/list', "tapi/{$version}_store@getRegionList");
		$api->get('block/{id}', "tapi/{$version}_store@getBlock");
		$api->get('cms/{id}', "tapi/{$version}_store@getCms");
	}

	protected function addAuthRoute($api, $version)
	{
		$api->post('/auth/login', "tapi/{$version}_account@authenticate");
		$api->post('/auth/register', "tapi/{$version}_account@register");
	}

	protected function addAccountRoute($api, $version)
	{
		$api->group(['middleware' => 'auth'], function ($api) use ($version) {
        	$api->get('/me/account', "tapi/{$version}_account@account");
        	$api->post('/me/account/update', "tapi/{$version}_account@update");
        	$api->post('/me/password/update', "tapi/{$version}_account@updatePassword");
        	$this->addAddressRoute($api, $version);
        	$this->addWhishlistRoute($api, $version);
        });
	}

	protected function addWhishlistRoute($api, $version)
	{
		$api->get('/me/whislist', "tapi/{$version}_account_wishlist@index");
		$api->post('/me/whislist', "tapi/{$version}_account_wishlist@add");
		$api->delete('/me/whislist/{id}/remove', "tapi/{$version}_account_wishlist@remove");
	}

	protected function addAddressRoute($api, $version)
	{
		$api->get('/me/address', "tapi/{$version}_account_address@index");
		$api->post('/me/address', "tapi/{$version}_account_address@add");
		$api->post('/me/address/{id}/update', "tapi/{$version}_account_address@update");
		$api->get('/me/address/{id}', "tapi/{$version}_account_address@view");
		$api->delete('/me/address/{id}', "tapi/{$version}_account_address@delete");
	}

	protected function addProductRoute($api, $version)
	{
		$api->get('products', "tapi/{$version}_product@index");
		$api->get('products/filters', "tapi/{$version}_product@getFilters");
		$api->get('products/{productId}', "tapi/{$version}_product@view");
	}

	protected function addCategoryRoute($api, $version)
	{
		$api->get('categories', "tapi/{$version}_category@index");
		$api->get('categories/{id}/products', "tapi/{$version}_category@listProducts");
		$api->get('categories/{id}/products/filters', "tapi/{$version}_category@getFilters");
	}

	protected function addCartRoute($api, $version)
	{
		$api->get('cart', "tapi/{$version}_cart@index");
		$api->post('cart', "tapi/{$version}_cart@add");
		$api->post('cart/update', "tapi/{$version}_cart@update");
		$api->post('cart/clear', "tapi/{$version}_cart@clear");
		$api->post('cart/coupon', "tapi/{$version}_cart@coupon");
		$api->delete('cart/coupon', "tapi/{$version}_cart@couponRemove");
		$api->delete('cart/item/{id}', "tapi/{$version}_cart@delete");
	}

	protected function addCheckoutRoute($api, $version)
	{
		$api->group(['middleware' => 'quote_token'], function ($api) use ($version) {
			$api->post('checkout/init', "tapi/{$version}_checkout@init");
			$api->post('checkout/guest', "tapi/{$version}_checkout@guest");
			$api->post('checkout/billing', "tapi/{$version}_checkout@billing");
			$api->post('checkout/shipping', "tapi/{$version}_checkout@shipping");
			$api->get('checkout/shipping/method', "tapi/{$version}_checkout@listShippingMethod");
			$api->post('checkout/shipping/method', "tapi/{$version}_checkout@shippingMethod");
			$api->get('checkout/payment/method', "tapi/{$version}_checkout@listPaymentMethod");
			$api->post('checkout/payment/method', "tapi/{$version}_checkout@paymentMethod");
			$api->post('checkout/order', "tapi/{$version}_checkout@order");
		});
	}

	protected function addOrderRoute($api, $version)
	{
		$api->post('order/guest', "tapi/{$version}_order@guestView");

		$api->group(['middleware' => 'auth'], function ($api) use ($version) {
			$api->get('me/order', "tapi/{$version}_order@index");
		});
	}

}