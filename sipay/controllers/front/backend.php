<?php

include(_PS_MODULE_DIR_ . 'sipay' . DIRECTORY_SEPARATOR . 'helper' . DIRECTORY_SEPARATOR . "checkouthelper.php");
include(_PS_MODULE_DIR_ . 'sipay/sdk/autoload.php');

class SipayBackendModuleFrontController extends ModuleFrontController
{
    protected $helper;

    public function initContent()
    {
        $this->ajax             = true;
        parent::initContent();
    }

    public function addOrderInfo(&$pwall_request, $quote)
    {
        $customer = new Customer((int) (Context::getContext()->customer->id));

        $pwall_request->setOrderId($quote->id == null ? "000000" : strval($quote->id));
        $pwall_request->setAmount($quote->id == null ? 0 : floatval($quote->getOrderTotal(true)));
        $pwall_request->setCurrency(Context::getContext()->currency->iso_code);
        $pwall_request->setOriginalUrl(_PS_BASE_URL_);
        $pwall_request->setGroupId(strval($customer->is_guest ? "0" : $customer->id));
    }

    public function postProcess(){
        $this->client           = new \PWall\Client();
        $this->checkout_helper  = new SipayCheckoutHelper();
        $jsonRequest = Tools::jsonDecode(Tools::file_get_contents('php://input'), -true);
        PrestaShopLogger::addLog("[EZENIT SIPAY] ON BACKEND EXECUTE: " . json_encode($jsonRequest));

        $this->client->setEnvironment(Configuration::get('sipay_environment'));
        $this->client->setKey(Configuration::get('sipay_key'));
        $this->client->setResource(Configuration::get('sipay_resource'));
        $this->client->setSecret(Configuration::get('sipay_secret'));
        $this->client->setBackendUrl(Context::getContext()->link->getModuleLink('sipay', 'review', [], Configuration::get('PS_SSL_ENABLED')));
        $debug_path = Configuration::get('sipay_debug_path');
        if ($debug_path && $debug_path != '') {
            $this->client->setDebugFile($debug_path);
        }else{
            $this->client->setDebugFile(false);
        }
        $quote = Context::getContext()->cart;
        if($quote == null){
            $request = new \PWall\Request(json_encode($jsonRequest), true);
        }else{
            $request = new \PWall\Request(json_encode($jsonRequest), false);
        }
        $this->addOrderInfo($request, $quote);

        if ($request->isEcCreateOrder()||$request->hasUpdateAmount()) {
            $cart_info = $this->checkout_helper->getPaypalItemsInfo($quote);
            $request->setEcCartInfo($cart_info["items"], $cart_info["is_digital"], $cart_info["breakdown"]);
            $request->setAmount($cart_info["total"]);
        }

        $this->checkout_helper->setPSD2Params($request, Context::getContext()->customer, $quote);

        $response = $this->client->proxy($request);

        if ($response->hasAddress() && !$response->hasUpdateAmount()) {
            //Set address to quote, set shipping method, collect rates
            try {
                $quote = Context::getContext()->cart;
                $error = $this->checkout_helper->setAddressAndCollectRates($response, $quote);
                if ($error) {
                    $response->setError(json_encode($error[0]));
                } else {
                    $response->setUpdatedAmount(floatval(Context::getContext()->cart->getOrderTotal(true)));
                }
            } catch (\Exception $e) {
                $response->setError($e->getMessage());
            }
        }

        if ($response->canPlaceOrder()) {
            $order_id = null;
            $this->checkout_helper->placeOrderFromResponse($jsonRequest, $response);
            $cart = Context::getContext()->cart;
            $customer = Context::getContext()->customer;
            $order_id = $this->module->currentOrder;
            setcookie("success_redirect", Context::getContext()->link->getPageLink('order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int) $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key), time() + 10, "/");
            Db::getInstance()->insert('sipay_order_extradata', array(
                'id_order' => (int) $order_id,
                'data'      => pSQL(json_encode($response->getPaymentInfo())),
            ));
        }
        
        $this->ajaxDie($response->toJSON());
    }

}