<?php
class Gebork_Ajax_Block_Ajaxcheckoutcart extends Mage_Checkout_Block_Cart
{
	public function chooseTemplate()
    {
        $itemsCount = $this->getItemsCount() ? $this->getItemsCount() : $this->getQuote()->getItemsCount();
        if ($itemsCount) {
            $path = 'ajax/checkout/cart.phtml';
            $this->setTemplate($path);
        } else {
            $this->setTemplate($this->getEmptyTemplate());
        }
    }
}