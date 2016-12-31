<?php

class Thl_Api_Helper_Order extends Mage_Core_Helper_Abstract
{
	public function getOrders($customer, $filterParams = [])
	{
		$pageSize = 10;
        $curPage = 1;

		$orders = Mage::getResourceModel('sales/order_collection')
            ->addFieldToSelect('*')
            ->addFieldToFilter('customer_id', $customer->getId())
            ->addFieldToFilter('state', array('in' => Mage::getSingleton('sales/order_config')->getVisibleOnFrontStates()))
            ->setOrder('created_at', 'desc')
        ;

        if (isset($filterParams['limit'])) {
            $pageSize = $filterParams['limit'];
        }

        if (isset($filterParams['page'])) {
            $curPage = $filterParams['page'];
        }

        $orders->setPageSize($pageSize)->setCurPage($curPage);

        $data = [];
        foreach ($orders as $order) {
        	$data[] = $this->getOrderItems($order);
        }

        return $data;
	}

	public function getOrderItems($order)
	{
		$breaks = array("<br />","<br>","<br/>");  
		$data = [
			'order_id' => $order->getId(),
			'increment_id' => $order->getRealOrderId(),
			'status' => $order->getStatusLabel(),
			'shipped_to' => $order->getShippingAddress()->getName(),
			'info' => [
				'order_date' => (string)$order->getCreatedAtStoreDate(),
				'billing_address' => str_ireplace($breaks, "\r\n", $order->getBillingAddress()->format('html')),
				'payment_method' => $order->getPayment()->getMethodInstance()->getTitle(),
			],
			'items' => [],
			'history' => [],
			'totals' => $this->_initTotals($order),
		];

		if (!$order->getIsVirtual()) {
			$data['info']['shipping_address'] = str_ireplace($breaks, "\r\n", $order->getShippingAddress()->format('html'));
			$data['info']['shipping_method'] = $order->getShippingDescription();

		}

		$items = $order->getItemsCollection();
		foreach ($items as $item) {
			if ($item->getParentItem()) {
				continue;
			}

	        $itemData = [
        		'id' => $item->getId(),
        		'name' => $item->getName(),
        		'description' => $item->getDescription(),
        		'sku' => $item->getSku(),
        		'price' => $this->formatPrice($item->getPrice()),
        		'qty' => [
        			'ordered' => $item->getQtyOrdered(),
        			'shipped' => $item->getQtyShipped(),
        			'canceled' => $item->getQtyCanceled(),
        			'refunded' => $item->getQtyRefunded(),
        		],
        		'subtotal' => $this->formatPrice($item->getRowTotal()),
        	];

        	if($options = $this->getItemOptions($item)) {
        		foreach ($options as $option) {
        			$formatedOptionValue = $this->getFormatedOptionValue($option);
        			$itemData['options'][] = [
        				'value' => $formatedOptionValue['value'],
        				'label' => $option['label'],
        				'full_view' => $formatedOptionValue['full_view'],
        			];
        		}
        	}

	        $data['items'][] = $itemData;
		}

		$history = $order->getVisibleStatusHistory();
		if (count($history)) {
			foreach ($history as $historyItem) {
				$data['history'][] = [
					'date' => $historyItem->getCreatedAtStoreDate(),
					'comment' =>  $historyItem->getComment(),
				];
			}
		}

		return $data;
	}

	/**
     * Initialize order totals array
     *
     */
    protected function _initTotals($order)
    {
        $totals = array();
        $totals['subtotal'] = array(
            'code'  => 'subtotal',
            'value' => $this->formatPrice($order->getSubtotal()),
            'label' => $this->__('Subtotal')
        );


        /**
         * Add shipping
         */
        if (!$order->getIsVirtual() && ((float) $order->getShippingAmount() || $order->getShippingDescription()))
        {
            $totals['shipping'] = array(
                'code'  => 'shipping',
                'field' => 'shipping_amount',
                'value' => $this->formatPrice($order->getShippingAmount()),
                'label' => $this->__('Shipping & Handling')
            );
        }

        /**
         * Add discount
         */
        if (((float)$order->getDiscountAmount()) != 0) {
            if ($order->getDiscountDescription()) {
                $discountLabel = $this->__('Discount (%s)', $order->getDiscountDescription());
            } else {
                $discountLabel = $this->__('Discount');
            }
            $totals['discount'] = array(
                'code'  => 'discount',
                'field' => 'discount_amount',
                'value' => $this->formatPrice($order->getDiscountAmount()),
                'label' => $discountLabel
            );
        }

        $totals['grand_total'] = array(
            'code'  => 'grand_total',
            'field'  => 'grand_total',
            'strong'=> true,
            'value' => $this->formatPrice($order->getGrandTotal()),
            'label' => $this->__('Grand Total')
        );

        /**
         * Base grandtotal
         */
        if ($order->isCurrencyDifferent()) {
            $totals['base_grandtotal'] = array(
                'code'  => 'base_grandtotal',
                'value' => $order->formatBasePrice($order->getBaseGrandTotal()),
                'label' => $this->__('Grand Total to be Charged'),
                'is_formated' => true,
            );
        }
        return $totals;
    }

	protected function formatPrice($price)
    {
    	$store = Mage::app()->getStore();
        $coreHelper = Mage::helper('core');
        return $coreHelper->formatPrice(
            $store->roundPrice($store->convertPrice($price)), false
        );
    }

	public function getItemOptions($item)
    {
        $result = array();
        if ($options = $item->getProductOptions()) {
            if (isset($options['options'])) {
                $result = array_merge($result, $options['options']);
            }
            if (isset($options['additional_options'])) {
                $result = array_merge($result, $options['additional_options']);
            }
            if (isset($options['attributes_info'])) {
                $result = array_merge($result, $options['attributes_info']);
            }
        }
        return $result;
    }

    public function getFormatedOptionValue($optionValue)
    {
        $optionInfo = array();

        // define input data format
        if (is_array($optionValue)) {
            if (isset($optionValue['option_id'])) {
                $optionInfo = $optionValue;
                if (isset($optionInfo['value'])) {
                    $optionValue = $optionInfo['value'];
                }
            } elseif (isset($optionValue['value'])) {
                $optionValue = $optionValue['value'];
            }
        }

        // render customized option view
        if (isset($optionInfo['custom_view']) && $optionInfo['custom_view']) {
            $_default = array('value' => $optionValue);
            if (isset($optionInfo['option_type'])) {
                try {
                    $group = Mage::getModel('catalog/product_option')->groupFactory($optionInfo['option_type']);
                    return array('value' => $group->getCustomizedView($optionInfo));
                } catch (Exception $e) {
                    return $_default;
                }
            }
            return $_default;
        }

        // truncate standard view
        $result = array();
        if (is_array($optionValue)) {
            $_truncatedValue = implode("\n", $optionValue);
            $_truncatedValue = nl2br($_truncatedValue);
            return array('value' => $_truncatedValue);
        } else {
            $_truncatedValue = Mage::helper('core/string')->truncate($optionValue, 55, '');
            $_truncatedValue = nl2br($_truncatedValue);
        }

        $result = array('value' => $_truncatedValue);

        if (Mage::helper('core/string')->strlen($optionValue) > 55) {
            $result['value'] = $result['value'] . ' <a href="#" class="dots" onclick="return false">...</a>';
            $optionValue = nl2br($optionValue);
            $result = array_merge($result, array('full_view' => $optionValue));
        }

        return $result;
    }

}