<?php

class Thl_Api_Api_Middleware_Store
{
	public function handle($request, $next)
	{
		$storeCode = $request->input('store',false);

		if(!$storeCode) {
			throw new \League\Route\Http\Exception(400,"Store code is required");
		}

		$store = Mage::getModel('core/store')->load($storeCode, 'code');
        if ($store->getId()) {
            Mage::app()->setCurrentStore($store->getId());
            Mage::getSingleton('core/translate')
            	->setLocale(
            		Mage::getStoreConfig('general/locale/code', Mage::app()->getStore()->getId())
            	)
            	->init('frontend', true);
        } else {
        	throw new \League\Route\Http\Exception(400,'Invalid store code given');
        }
		
		return $next($request);
	}
}