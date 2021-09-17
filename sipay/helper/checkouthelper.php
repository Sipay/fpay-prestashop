<?php

class SipayCheckoutHelper {

    const EXCLUDED_SUSPECTED_FRAUD_METHODS = ["azon", "altp_bizum", "altp_bankia_transfer", "altp_bankia"];

    public function placeOrderFromResponse($requestJSON, $response)
    {
        return $this->convertQuoteToOrder($requestJSON, $response);
    }

    public function convertQuoteToOrder($request, $response)
    {
        $cart = Context::getContext()->cart;
        PrestaShopLogger::addLog("[EZENIT SIPAY] CONVERT TO QUOTE CUSTOMER " . $cart->id);
        $order_total = $cart->getOrderTotal(true, Cart::BOTH);
        $order_status = $response->isCreatePendingOrder() ? 
                            (int) Configuration::get('SIPAY_PENDING_PAYMENT') 
                            : ($this->detectSuspectedFraud($response, $order_total) ? 
                                    (int) Configuration::get('SIPAY_SUSPECTED_FRAUD')
                                    : (int) Configuration::get('PS_OS_PAYMENT'));
        $payment_module = Module::getInstanceByName('sipay');
        if($payment_module->validateOrder($cart->id, $order_status, $order_total, $payment_module->displayName, null, array(), null, false, $cart->secure_key)){
            return $cart->id;
        }else{
            return false;
        }
    }

    public function detectSuspectedFraud($response, $real_order_amount)
    {
        $responseAmount = $response->getPaidAmount();       
        PrestaShopLogger::addLog("[EZENIT SIPAY] " . $responseAmount);
        if(!$responseAmount){
            PrestaShopLogger::addLog("[EZENIT SIPAY] SUSPECTED FROUD DETECTED");
            return true;
        }else if ($responseAmount != $real_order_amount) {
            PrestaShopLogger::addLog("[EZENIT SIPAY] SUSPECTED FROUD DETECTED");
            return true;
        } else {
            return false;
        }
        return false;
    }

    private function flatten($array, $prefix = '')
    {
        $result = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = $result + $this->flatten($value, $prefix . $key . '.');
            } else {
                $result[$prefix . $key] = $value;
            }
        }
        return $result;
    }

    public function getCartTags($quote){
        $has_virtual   = false;
        $has_novirtual = false;

        if ($quote->getProducts()) {
            foreach ($quote->getProducts() as $item) {
                if ($item["is_virtual"] == "1") {
                    $has_virtual = true;
                } else {
                    $has_novirtual = true;
                }
            }
        }

        if ($has_virtual && $has_novirtual) {
            return "mixto";
        } else if ($has_virtual) {
            return "virtual";
        } else {
            return "fisico";
        }
    }

    public function setAddressAndCollectRates($response, $quote)
    {
        $response_address  = $response->getAddress();
        $response_customer = $response->getCustomerData();
        if ($response_address /*&& $response_customer*/) {
            $errors = [];
            //set address
            $customer = new Customer(Context::getContext()->customer->id);
            $customer_addresses = $customer->getAddresses(Context::getContext()->cookie->id_lang);
            $found_address = false;
            if(count($customer_addresses)){
                foreach($customer_addresses as $address){
                    if(
                        array_key_exists("name", $response_address) && $address['firstname'] == $response_address["name"] &&
                        $address['id_country'] == Country::getByIso($response_address["country_code"]) &&
                        $address['address1'] == $response_address["address"][0] &&
                        $address['city'] == $response_address["city"]
                    ){
                        $quote->updateAddressId($quote->id_address_delivery, $address["id_address"]);
                        $found_address = true;
                        break;
                    }
                }
            }
            // Check if we have found a matching address
            if(!$found_address){
                //create address and asign to cart
                $new_address = new Address(null);
                $this->setAddressInPrestashopAddress($response_address, $response_customer, $new_address);
                $new_address->add();
                $addresserror = $new_address->validateController(false);
                if (count($addresserror)) {
                    $errors[] = $addresserror[0];
                }
                $quote->updateAddressId($quote->id_address_delivery, $new_address->id);
            }
            $quote->id_customer = Context::getContext()->customer->id;
            $quote->update();
            if(!$quote->isVirtualCart()){
                $quote->getDeliveryOptionList(null, true);
                $delivery_option = $quote->getDeliveryOption(null, false, false);
                if (is_array($delivery_option) && count($delivery_option) && array_key_exists((int) $quote->id_address_delivery, $delivery_option)) {
                    $carrier = explode(',', $delivery_option[(int) $quote->id_address_delivery]);
                } else {
                    $carrier = [];
                }
                $has_shipping_method = false;
                foreach ($quote->getProducts() as $product) {
                    $carrier_list = Carrier::getAvailableCarrierList(new Product($product['id_product']), null, $quote->id_address_delivery);
                    foreach ($carrier as $id_carrier) {
                        if (!in_array($id_carrier, $carrier_list)) {
                            $has_shipping_method = false;
                        } else {
                            $quote->setDeliveryOption($delivery_option);
                            $quote->update();
                            $has_shipping_method = true;
                            break;
                        }
                    }
                    if ($has_shipping_method) {
                        break;
                    }
                }
                if (!$has_shipping_method) {
                    $errors[] = "It seems there are no recommended carriers for your country.";
                }
            }
            return $errors;
        }
    }

    private function setAddressInPrestashopAddress($address, $customer, &$prestashop_address)
    {
        if ($address && $prestashop_address) {
            $prestashop_address->alias = 'Sipay_Address';
            if (array_key_exists("name", $address) && $address["name"] && $address["name"] != "") {
                $prestashop_address->firstname = $address["name"];
            } else if ($customer && array_key_exists("name", $customer) && $customer["name"] && $customer["name"] != "") {
                $prestashop_address->firstname = $customer["name"];
            }else{
                $prestashop_address->firstname = "-";
            }
            $prestashop_address->lastname = "-";
            if (array_key_exists("address", $address) && $address["address"][0] && $address["address"][0] != "") {
                $concat_street = "";
                if (array_key_exists(1, $address["address"])) {
                    $concat_street = $address["address"][1];
                }
                if (array_key_exists(2, $address["address"])) {
                    $concat_street = $concat_street . " " . $address["address"][2];
                }
                $prestashop_address->address1 = $address["address"][0];
                $prestashop_address->address2 = $concat_street;
            }else{
                $prestashop_address->address1 = "-";
            }
            if(_PS_VERSION_ >= 1.7){
                $prestashop_address->dni = "00000000";
            }
            (array_key_exists("city", $address) && $address["city"] && $address["city"] != "") ? $prestashop_address->city = $address["city"] : null;
            (array_key_exists("country_code", $address) && $address["country_code"] && $address["country_code"] != "") ? $prestashop_address->id_country = Country::getByIso($address["country_code"]) : null;
            (array_key_exists("zip", $address) && $address["zip"] && $address["zip"] != "") ? $prestashop_address->id_state = $address["country_code"] == 'ES' ? $this->getRegionIdByPostcode($address["zip"]) : null : null;
            (array_key_exists("phone", $address) && $address["phone"] && $address["phone"] != "") ? $prestashop_address->phone_mobile = $address["phone"] : $prestashop_address->mobile_phone = "600000000";
            (array_key_exists("zip", $address) && $address["zip"] && $address["zip"] != "") ? $prestashop_address->postcode = $address["zip"] : null;
            if ($customer && !Context::getContext()->customer->id) {
                $guest_customer = new Customer();
                (array_key_exists("email", $customer) && $customer["email"] && $customer["email"] != "") ? $guest_customer->email = $customer["email"] : null;
                (array_key_exists("name", $customer) && $customer["name"] && $customer["name"] != "") ? $guest_customer->firstname = $customer["name"] : null;
                $guest_customer->lastname = "-";
                $guest_customer->is_guest = 1;
                $guest_customer->active = 1;
                $guest_customer->passwd = Tools::encrypt(Tools::passwdGen());
                $guest_customer->add();

                $prestashop_address->id_customer = $guest_customer->id;
                $this->updateContext($guest_customer);
            } else {
                $prestashop_address->id_customer = Context::getContext()->customer->id;
            }
        }
    }

    /**
     * Update context after customer creation
     * @param Customer $customer Created customer
     */
    protected function updateContext(Customer $customer)
    {
        Context::getContext()->customer = $customer;
        Context::getContext()->cookie->id_customer = (int) $customer->id;
        Context::getContext()->cookie->customer_lastname = $customer->lastname;
        Context::getContext()->cookie->customer_firstname = $customer->firstname;
        Context::getContext()->cookie->passwd = $customer->passwd;
        Context::getContext()->cookie->logged = 1;

        $customer->logged = 1;
        Context::getContext()->cookie->email = $customer->email;
        Context::getContext()->cookie->is_guest = 1;
        // Update cart address
        Context::getContext()->cart->secure_key = $customer->secure_key;
    }

    private function getRegionIdByPostcode($postcode)
    {
        return null;
    }


    public function getPaypalItemsInfo($quote){

        $totals     = array(
          "total"     => floatval(Context::getContext()->cart->getOrderTotal(true)),
          "shipping"  => floatval(Context::getContext()->cart->getOrderTotal(true,Cart::ONLY_SHIPPING)), //$quote->getShippingAddress()->getShippingAmount(),
          "tax"       => floatval(Context::getContext()->cart->getOrderTotal(true)-Context::getContext()->cart->getOrderTotal(false))       //$quote->getShippingAddress()->getTaxAmount()
        );
    
        $cart_items = [];
        foreach ($quote->getProducts() as $item) {
          $product = new Product($item["id_product"]);
          $unit_price   = floatval($item["price_with_reduction"]);
          $unit_tax     = floatval($item["price_with_reduction"] - $item["price_with_reduction_without_tax"]);
          $cart_items[] = array(
            "name"       => $item["name"],
            "sku"        => $product->reference,
            "qty"        => intval($item["quantity"]),
            "unit_price" => $unit_price,
            "unit_tax"   => $unit_tax,
            "is_digital" => $product->is_virtual
          );
        }
        $res = \PWall\Request::buildPaypalCartInfo(Context::getContext()->currency->iso_code,$cart_items,$totals);
        return $res;
        
      }

      public function setPSD2Params(&$request, $customer, $quote)
      {
        $info = [];
        $billing_address = new Address($quote->id_address_invoice);
        $shipping_address = new Address($quote->id_address_delivery);

        
        $info["account_modification_date"] = $customer->date_upd;
        $info["account_creation_date"]     = $customer->date_add;
        $info["account_purchase_number"]   = count(Order::getCustomerOrders((int)$customer->id));
        $info["account_age_date"]          = $customer->birthday != "0000-00-00" ? $customer->birthday : "";

        $info["billing_city"]      = $billing_address->city;
        $info["billing_country"]   = Country::getIsoById($billing_address->id_country);
        $info["billing_address_1"] = $billing_address->address1;
        $info["billing_address_2"] = $billing_address->address2;
        $info["billing_postcode"]  = $billing_address->postcode;

        if($quote->isVirtualCart()){
            $info["delivery_email_address"] = $customer->email;
        }
        
        $info["shipping_city"]      = $shipping_address->city;
        $info["shipping_country"]   = Country::getIsoById($shipping_address->id_country);
        $info["shipping_address_1"] = $shipping_address->address1;
        $info["shipping_address_2"] = $shipping_address->address2;
        $info["shipping_postcode"]  = $shipping_address->postcode;

        $request->setPSD2Info(
            boolval(Configuration::get('sipay_tra_enable')),
            floatval(Configuration::get('sipay_tra_high_amount')),
            boolval(Configuration::get('sipay_lwv_enable')),
            floatval(Configuration::get('sipay_lwv_low_amount')),
            floatval($quote->getOrderTotal(true)),
            $info
        );
      }

//     public function getPaypalItemsInfo($quote)
//   {
//     $cart_items = [];
//     $all_digital_products = true;
//     // items: [{
//     //                 "name": "Something nice and warm",
//     //                 "unit_amount": {
//     //                     "currency_code": "EUR",
//     //                     "value": "30.00"
//     //                 },
//     //                 "tax": {
//     //                     "currency_code": "EUR",
//     //                     "value": "0"
//     //                 },
//     //                 "quantity": "1",
//     //                 "sku": "#123asdf",
//     //                 "category": "DIGITAL_GOODS"
//     //             }]})
//     foreach ($quote->getProducts() as $item) {
//       $product = new Product($item["id_product"]);
//       $paypal_item = new \stdClass();
//       $paypal_item->name = $item["name"];
//       $unit_amount = new \stdClass();
//       $unit_amount->currency_code = Context::getContext()->currency->iso_code;
//       $line_total   = strval($item["price"]);
//       $unit_amount->value = $line_total;
//       $paypal_item->unit_amount = $unit_amount;
//       $tax = new \stdClass();
//       $tax->currency_code = Context::getContext()->currency->iso_code;
//       $tax->value = "0";
//       $paypal_item->tax = $tax;
//       $paypal_item->quantity = strval($item["quantity"]);
//       $paypal_item->sku = $product->reference;
//       $item_type_virtual = $product->is_virtual;
//       if ($item_type_virtual) {
//         $paypal_item->category = "DIGITAL_GOODS";
//       } else {
//         $paypal_item->category = "PHYSICAL_GOODS";
//         $all_digital_products = false;
//       }
//       $cart_items[] = $paypal_item;
//     }

//     return ["items" => $cart_items, "is_digital" => $all_digital_products];
//   }  

}
