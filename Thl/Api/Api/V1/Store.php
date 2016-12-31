<?php

class Thl_Api_Api_V1_Store extends Sunel_Api_Model_Resource
{
	public function getStoreList()
	{
		$data = [
		 	'default_store' => Mage::app()
				    ->getWebsite(true)
				    ->getDefaultGroup()
				    ->getDefaultStore()
				    ->getCode()
		];
		foreach (Mage::app()->getWebsites() as $website) {
		    foreach ($website->getGroups() as $group) {
		        $stores = $group->getStores();
		        foreach ($stores as $store) {
		        	if($store->getIsActive()) {
			            $data['websites'][$website->getCode()][$store->getCode()] = [
			            	'name' => $store->getName(),
			            	'code' => $store->getCode(),
			            	'frontend_name' => $store->getFrontendName(),
			            	'currency_code' => $store->getBaseCurrencyCode(),
			            	'language_code' => Mage::getStoreConfig('general/locale/code', $store->getId()),

			            ];
			        }
		        }
		    }
		}

		return $this->success($data,200);
	}

	public function getToken()
	{
		$postData = $this->getRequest()->only(["device_token","device_type"]);

		if(empty($postData)) {
			return $this->errorBadRequest('No data given.');
		}

		$postData['quote_id'] = null;

		$token = (string) Mage::helper('api3/auth')->create($postData);
		return $this->success([
			'token' => $token
		], 201);
	}

	public function getCountryList()
	{
		$countryList = Mage::getModel('directory/country')
							->getResourceCollection()
	                          ->loadByStore()
	                          ->toOptionArray(true);
	                          
		return $this->success([
        	'data' => $countryList
        ], 200);
	}

	public function getRegionList()
	{
		$countryId = $this->getRequest()->input('country_id',false);
		if(!$countryId) {
			return $this->errorBadRequest('Country Id not given.');
		}
		$regionList = Mage::getModel('directory/region')
							->getResourceCollection()
	                          ->addCountryFilter($countryId)
	                          ->toOptionArray(true);
	                          
		return $this->success([
        	'data' => $regionList
        ], 200);
	}

	public function getBlock($blockIdentifier)
	{
		$blockId = preg_replace('/\s+/', '', $blockIdentifier);
		$layout = Mage::app()->getLayout();
		$html = $layout->createBlock('cms/block')
					->setBlockId($blockId)->toHtml();
		if (empty($html))  {
			return $this->error($this->__('This Block does not exist or has no content'), 403);
		}

		return $this->success([
        	'data' => [
        		'content' => $html
        	]
        ], 200);
	}

	public function getCms($cmsIdentifier)
	{
		$cmsId = preg_replace('/\s+/', '', $cmsIdentifier);
		$page = Mage::getModel('cms/page');
		$pageId = $page->checkIdentifier($cmsId, Mage::app()->getStore()->getId());
		if (!$pageId)  {
			return $this->error($this->__('This page does not exist or has no content'), 403);
		}

		$cmspage = $page->load($pageId);

		return $this->success([
        	'data' => [
        		'content' => $cmspage->getContent()
        	]
        ], 200);
	}
}