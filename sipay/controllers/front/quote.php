<?php

class SipayQuoteModuleFrontController extends ModuleFrontController
{
    protected $helper;
    protected $checkout_helper;


    public function initContent()
    {
        $this->ajax             = true;
        parent::initContent();
    }

    public function postProcess()
    {
        //$jsonResponse["amount"]  = Context::getContext()->cart->getOrderTotal(true) * 100;
        exit(Tools::jsonEncode(Context::getContext()->cart->getOrderTotal(true)));
    }
}
