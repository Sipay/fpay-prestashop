<?php

class SipayQuoteEcModuleFrontController extends ModuleFrontController
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
        $response = [];

        $response["tags"]        = "express";
        $response["currency"]    = Context::getContext()->currency->iso_code;
        $response["amount"]      = Context::getContext()->cart->id == null ? 0 : floatval(Context::getContext()->cart->getOrderTotal(true));
        $response["group_id"]     = strval(Context::getContext()->customer->id ? Context::getContext()->customer->id : "0");

        return $this->ajaxDie(Tools::jsonEncode($response));
    }
}
