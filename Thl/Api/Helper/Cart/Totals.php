<?php

class Thl_Api_Helper_Cart_Totals extends Mage_Core_Helper_Abstract
{
	protected $_totalRenderers;
    protected $_defaultRenderer = 'checkout/total_default';
    protected $_totals = null;

    public function getTotals()
    {
        return $this->_totals;
    }

    public function setTotals($value)
    {
        $this->_totals = $value;
        return $this;
    }

    protected function _getTotalRenderer($code)
    {
        $block = $this->_defaultRenderer;
        $config = Mage::getConfig()->getNode("global/sales/quote/totals/{$code}/renderer");
        if ($config) {
            $block = (string) $config;
        }

        $block = $this->getBlock($block);
       
        $block->setTotals($this->getTotals());
        return $block;
    }

    public function renderTotal($total, $area = null, $colspan = 1)
    {
        $code = $total->getCode();
        if ($total->getAs()) {
            $code = $total->getAs();
        }
        return $this->_getTotalRenderer($code)
            ->setTotal($total)
            ->setColspan($colspan)
            ->setRenderingArea(is_null($area) ? -1 : $area)
            ->toHtml();
    }

    /**
     * Render totals html for specific totals area (footer, body)
     *
     * @param   null|string $area
     * @param   int $colspan
     * @return  string
     */
    public function renderTotals($area = null, $colspan = 1)
    {
        $html = '';
        foreach($this->getTotals() as $total) {
            if ($total->getArea() != $area && $area != -1) {
                continue;
            }
            $html .= $this->renderTotal($total, $area, $colspan);
        }
        return $html;
    }

    protected function getBlock($type)
    {
        $className = Mage::getConfig()->getBlockClassName($type);

        return new $className();
    }
}