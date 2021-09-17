<?php

declare(strict_types=1);

namespace PWall;

class Request
{
  private $request;
  private $order_id         = null;
  private $currency         = null;
  private $group_id         = null;
  private $original_url     = null;
  private $notify           = null;
  //PSD2
  private $sca_exemptions   = null;
  private $emv3ds           = null;

  public function __construct(
    $jsonRequest,
    $isAdmin
  ){
    $this->request      = json_decode($jsonRequest, true);
    $this->order_id     = $isAdmin ? str_pad("0", 12, "0", STR_PAD_LEFT) : null;
    $this->currency     = $isAdmin ? "" : null;
    $this->amount       = $isAdmin ? "0" : null;
    $this->group_id     = $isAdmin ? 0 : null;
    $this->original_url = $isAdmin ? "" : null;
  }

  /**
   * Sets PSD2 parameters for authentication excemption
   * 
   *
   * @param  boolean $traEnabled  true if TRA validation is enabled 
   * @param  float $traValue      true if TRA validation is enabled 
   * @param  boolean $lwvEnabled  true if LWV validation is enabled 
   * @param  float $lwvValue      LWV 
   * @param  float $cartTotal     total amount to pay of cart
   * @param  mixed $customerData  customer data and cart information. Posible values (all values are not mandatory, but recommended):
   *            $customerData = [
   *              "account_additional_info",
   *              "account_age_indicator",
   *              "account_modification_date",
   *              "account_modification_indicator",
   *              "account_creation_date",
   *              "account_pw_change_date",
   *              "account_pw_change_indicator",
   *              "account_purchase_number",
   *              "transactions_day",
   *              "transactions_year",
   *              "account_age_date",
   *              "pay_account_creation_date",
   *              "pay_account_indicator",
   *              "address_first_use_date",
   *              "address_first_use_indicator",
   *              "shipment_name_indicator",
   *              "suspicious_activity_indicator",
   *              "billing_city",
   *              "billing_country",
   *              "billing_address_1",
   *              "billing_address_2",
   *              "billing_address_3",
   *              "billing_postcode",
   *              "cardholder_mobile_phone_prefix",
   *              "cardholder_mobile_phone_number",
   *              "cardholder_home_phone_prefix",
   *              "cardholder_home_phone_number",
   *              "cardholder_work_phone_prefix",
   *              "cardholder_work_phone_number",
   *              "delivery_email_address",
   *              "delivery_timeframe",
   *              "amount_in_giftcards",
   *              "amount_of_giftcards",
   *              "preorder_date",
   *              "preorder_availability_indicator",
   *              "reorder_indicator",
   *              "shipping_indicator",
   *              "shipping_city",
   *              "shipping_country",
   *              "shipping_address_1",
   *              "shipping_address_2",
   *              "shipping_address_3",
   *              "shipping_postcode"
   *            ]    
   * 
   * @return void
   */
  public function setPSD2Info($traEnabled, $traValue, $lwvEnabled, $lwvValue, $cartTotal, $customerData){
    if($traEnabled === true && $lwvEnabled === true){
      if($cartTotal <= $lwvValue){
        $this->sca_exemptions = \PWall\Helper\Constants::SCA_EXEMPTIONS_LWV_VALUE;
      }else if($cartTotal <= $traValue){
        $this->sca_exemptions = \PWall\Helper\Constants::SCA_EXEMPTIONS_TRA_VALUE;
      }
    } else if($lwvEnabled === true && ($cartTotal <= $lwvValue)) {
      $this->sca_exemptions = \PWall\Helper\Constants::SCA_EXEMPTIONS_LWV_VALUE;
    } else if($traEnabled === true && ($cartTotal <= $traValue)){
      $this->sca_exemptions = \PWall\Helper\Constants::SCA_EXEMPTIONS_TRA_VALUE;
    }
    if($this->sca_exemptions !== null){
      $this->setEmv3ds($customerData);
    }
  }

  /**
   * Builds Paypal Express Checkout Cart Info
   * 
   *
   * @param  string $currencyCode currency code of cart
   * @param  mixed  $items cart items  
   * @param  mixed  $currencyCode cart totals information 
   * @return mixed
   */
  public static function buildPaypalCartInfo($currencyCode,$items,$total){
    $cart_items = [];
    $all_digital_products = true;
    // prepare totals
    $totals                             = new \stdClass();
    $totals->total                      = new \stdClass();
    $totals->shipping                   = new \stdClass();
    $totals->tax_total                  = new \stdClass();
    $totals->discount                   = new \stdClass();
    $totals->item_total                 = new \stdClass();

    // set currency
    $totals->total->currency_code       = $currencyCode;
    $totals->shipping->currency_code    = $currencyCode;
    $totals->tax_total->currency_code   = $currencyCode;
    $totals->discount->currency_code    = $currencyCode;
    $totals->item_total->currency_code  = $currencyCode;

    // initialize amount
    $totals->total->value               = 0.0;
    $totals->shipping->value            = 0.0;
    $totals->tax_total->value           = 0.0;
    $totals->discount->value            = 0.0;

    foreach($items as $item){
      // calc per unit
      $price_per_unit                          = round((float)$item["unit_price"],2,PHP_ROUND_HALF_UP);

      // initialize
      $paypal_item                             = new \stdClass();
      $paypal_item->unit_amount                = new \stdClass();
      $paypal_item->name                       = $item["name"];
      $paypal_item->quantity                   = strval($item["qty"]);
      $paypal_item->sku                        = $item["sku"];
      $paypal_item->unit_amount->currency_code = $currencyCode;
      $paypal_item->unit_amount->value         = strval($price_per_unit);
      
      if($item["is_digital"]){
        $paypal_item->category    = "DIGITAL_GOODS";
      }else{
        $paypal_item->category    = "PHYSICAL_GOODS";
        $all_digital_products     = false;
      }
      // update totals:
      $totals->total->value      += $paypal_item->unit_amount->value * $item["qty"];
      $cart_items[] = $paypal_item;
    }

    $grand_total                  =  strval(round((float)$total["total"],2,PHP_ROUND_HALF_UP));
    $total_wo_shipping            =  strval(round((float)$total["total"] - $total["shipping"],2,PHP_ROUND_HALF_UP));
    $totals->shipping->value      =  strval(round((float)$total["shipping"],2,PHP_ROUND_HALF_UP));
    $totals->tax_total->value     =  round((float)$total["tax"],2,PHP_ROUND_HALF_UP);
    $discount                     =  round($totals->total->value + $totals->tax_total->value - $total_wo_shipping,2,PHP_ROUND_HALF_UP);
    if($discount >= 0){
      $totals->discount->value    = strval($discount);
    }else{
      $totals->discount->value    = strval(0);
      $totals->tax_total->value   += abs($discount);
    }
    $totals->total->value         =  strval($totals->total->value);
    $totals->tax_total->value     =  strval($totals->tax_total->value);
    $totals->item_total->value    =  strval($totals->total->value);

    return ["items" => $cart_items, "is_digital" => $all_digital_products, "total" => $grand_total, "breakdown"=>$totals ];
  }

  /**
   * Returns the required JSON to proxy a request to Sipay
   *
   * @return  JSON request ready to proxy to Sipay
   */
  public function toJSON(){
    // $json_request                               = $this->request;
    // $json_request["params"]["order"]            = $this->order_id;
    // $json_request["params"]["amount"]           = $this->amount;
    // $json_request["params"]["currency"]         = $this->currency;
    // $json_request["params"]["group_id"]         = $this->group_id;
    // $json_request["params"]["original_url"]     = $this->original_url;
    // $json_request["params"]["notify"]["result"] = $this->notify;
    // //PSD2 params
    // if($this->sca_exemptions !== null){
    //   $json_request["params"]["sca_exemptions"] = $this->sca_exemptions;
    // }
    // if($this->emv3ds !== null){
    //   $json_request["params"]["emv3ds"]         = $this->emv3ds;
    // }
    
    return json_encode($this->toArray());
  }

  /**
   * Returns the required Array to proxy a request to Sipay
   *
   * @return  Array request ready to proxy to Sipay
   */
  public function toArray()
  {
    $json_request                               = $this->request;
    $json_request["params"]["order"]            = $this->order_id;
    $json_request["params"]["amount"]           = $this->amount;
    $json_request["params"]["currency"]         = $this->currency;
    $json_request["params"]["group_id"]         = $this->group_id;
    $json_request["params"]["original_url"]     = $this->original_url;
    $json_request["params"]["notify"]["result"] = $this->notify;
    if ($this->sca_exemptions !== null) {
      $json_request["params"]["sca_exemptions"] = $this->sca_exemptions;
    }
    if ($this->emv3ds !== null) {
      $json_request["params"]["emv3ds"]         = $this->emv3ds;
    }
    return $json_request;
  }
  
  /**
   * Sets order id that will be sent to confirm payments
   * 
   *
   * @param  mixed $order_id Order id which the petition is built around 
   * @return void
   */
  public function setOrderId($order_id){
    if(strlen($order_id) > 12){
      throw new \PWall\Exception\InvalidArgumentException('Order id must not exceed 12 characters');
    }
    $this->order_id = str_pad(strval($order_id), 12, "0", STR_PAD_LEFT);
  }
  
    
  /**
   * Sets amount for the payment, defaults to 0 for backend operations
   *
   * @param  float $amount Amount with decimals that will be charged to customer
   * @return void
   */
  public function setAmount($amount){
    // if($amount <= floatval(0)){
    //   throw new \PWall\Exception\InvalidArgumentException('Order amount must be more than 0');
    // }
    $this->amount = strval($amount * 100);
  }


  /**
   * Sets currency of the sale operation
   *
   * @param   $currency ISO 4217 currency code
   * @return void
   */
  public function setCurrency($currency){
    $this->currency = $currency;
  }
  
  /**
   * Sets customer id for the request
   *
   * @param int $customer_id Customer id group
   * @return void
   */
  public function setGroupId($customer_id){
    $this->group_id = $customer_id;
  }

  /**
   * Sets original url for the request
   *
   * @param string $original_url Base url of commerce
   * @return void
   */
  public function setOriginalUrl($original_url){
    $parsed_url = parse_url($original_url);
    if (!$parsed_url) {
      throw new \PWall\Exception\InvalidArgumentException('Invalid url');
    }
    $this->original_url = $parsed_url["host"];
  }

  /**
   * Sets return url for redirect payment methods
   *
   * @param  $url url to redirect after payment gateway
   * @return void
   */
  public function setNotifyResult($url)
  {
    $this->notify = $url;
  }
    
  /**
   * Check if request is action sale, this can NOT be used to place order
   *
   * @return boolean true if request is for sale action, false otherwise
   */
  public function isActionSale(){
    if(is_array($this->request)
    && array_key_exists("action", $this->request)
    && $this->request["action"] === \PWall\Helper\Constants::PWALL_ACTION_SALE){
      return true;
    }

    return false;
  }

  /**
   * Check if request is and action of express checkbox
   * 
   * @return boolean true if request is for sale action, false otherwise
   */
  public function isActionExpressCheckout(){
    if(is_array($this->request)
    && array_key_exists("params", $this->request) 
    && array_key_exists("express_checkout", $this->request["params"])
    && $this->request["params"]["express_checkout"] == true){
      return true;
    }
    return false;
  }

  /**
   * Check if request is getExtraData action
   * 
   * @return boolean true if request is for sale action, false otherwise
   */
  public function isActionGetExtraData()
  {
    if (
      is_array($this->request)
      && array_key_exists("action", $this->request)
      && $this->request["action"] == true
    ) {
      return true;
    }
    return false;
  }

  /**
   * Check if request is get cart info action
   * 
   * @return boolean true if request is get cart info action, false otherwise
   */
  public function isEcCreateOrder(){
    if(
      is_array($this->request)
      && array_key_exists("params", $this->request)
      && array_key_exists("create_order", $this->request["params"])
      && $this->request["params"]["create_order"] == true
      // && array_key_exists("method", $this->request["params"])
      // && $this->request["params"]["method"] == "paypal"
      && array_key_exists("express", $this->request["params"])
      && $this->request["params"]["express"] == true
    ){
      return true;
    }
    return false;
  }

  /**
   * Check for update amount in response, to ignore address if any
   * @param  string $message Error message to return
   * @return void
   */
  public function hasUpdateAmount(){
    //$this->request["params"]
    if (
      is_array($this->request)
      && array_key_exists("params", $this->request)
      && array_key_exists("update_amount", $this->request["params"])
    ) {
      return true;
    }
    return false;
  }

/**
   * Set cart info to express checkout
   * @param  string $items      Cart info formatted as Paypal requierements
   * @param  string $is_digital true if all items in the cart are digital, false otherwise
   * @param  string $breakdown  Cart breakdown totals
   * @return void
   */
  public function setEcCartInfo($items, $is_digital, $breakdown)
  {
    $this->request["params"]["is_digital"]  = $is_digital;
    if(
      array_key_exists("params", $this->request)
      && array_key_exists("method", $this->request["params"])
      && $this->request["params"]["method"] == "paypal"){
        $this->request["params"]["items"]       = $items;
        $this->request["params"]["breakdown"]   = $breakdown;
      }

  }

  private function setEmv3ds($customerData){
    $this->emv3ds = [];
    $this->checkPsd2Param($customerData, "account_additional_info")     ? $this->emv3ds["account_additional_info"] = $customerData["account_additional_info"] : null;
    $this->checkPsd2Param($customerData, "account_age_indicator")       ? $this->emv3ds["account_info"]["account_age_indicator"] = $customerData["account_age_indicator"] : null;
    $this->checkPsd2Param($customerData, "account_modification_date")   ? $this->emv3ds["account_info"]["account_modification_date"] = $this->psd2FormatDate($customerData["account_modification_date"]) : null;
    $this->checkPsd2Param($customerData, "account_modification_indicator")   ? $this->emv3ds["account_info"]["account_modification_indicator"] = $customerData["account_modification_indicator"] : null;
    $this->checkPsd2Param($customerData, "account_creation_date")   ? $this->emv3ds["account_info"]["account_creation_date"] = $this->psd2FormatDate($customerData["account_creation_date"]) : null;
    $this->checkPsd2Param($customerData, "account_pw_change_date")   ? $this->emv3ds["account_info"]["account_pw_change_date"] = $this->psd2FormatDate($customerData["account_pw_change_date"]) : null;
    $this->checkPsd2Param($customerData, "account_pw_change_indicator")   ? $this->emv3ds["account_info"]["account_pw_change_indicator"] = $customerData["account_pw_change_indicator"] : null;
    $this->checkPsd2Param($customerData, "account_purchase_number")   ? $this->emv3ds["account_info"]["account_purchase_number"] = $customerData["account_purchase_number"] : null;
    $this->checkPsd2Param($customerData, "transactions_day")   ? $this->emv3ds["account_info"]["transactions_day"] = $customerData["transactions_day"] : null;
    $this->checkPsd2Param($customerData, "transactions_year")   ? $this->emv3ds["account_info"]["transactions_year"] = $customerData["transactions_year"] : null;
    $this->checkPsd2Param($customerData, "account_age_date")   ? $this->emv3ds["account_info"]["account_age_date"] = $this->psd2FormatDate($customerData["account_age_date"]) : null;
    $this->checkPsd2Param($customerData, "pay_account_creation_date")   ? $this->emv3ds["account_info"]["pay_account_creation_date"] = $this->psd2FormatDate($customerData["pay_account_creation_date"]) : null;
    $this->checkPsd2Param($customerData, "pay_account_indicator")   ? $this->emv3ds["account_info"]["pay_account_indicator"] = $customerData["pay_account_indicator"] : null;
    $this->checkPsd2Param($customerData, "address_first_use_date")   ? $this->emv3ds["account_info"]["address_first_use_date"] = $customerData["address_first_use_date"] : null;
    $this->checkPsd2Param($customerData, "address_first_use_indicator")   ? $this->emv3ds["account_info"]["address_first_use_indicator"] = $customerData["address_first_use_indicator"] : null;
    $this->checkPsd2Param($customerData, "shipment_name_indicator")   ? $this->emv3ds["account_info"]["shipment_name_indicator"] = $customerData["shipment_name_indicator"] : null;
    $this->checkPsd2Param($customerData, "suspicious_activity_indicator")   ? $this->emv3ds["account_info"]["suspicious_activity_indicator"] = $customerData["suspicious_activity_indicator"] : null;
    $this->checkPsd2Param($customerData, "cardholder_mobile_phone_prefix")   ? $this->emv3ds["cardholder_mobile_phone"]["prefix"] = $customerData["cardholder_mobile_phone_prefix"] : null;
    $this->checkPsd2Param($customerData, "cardholder_mobile_phone_number")   ? $this->emv3ds["cardholder_mobile_phone"]["number"] = $customerData["cardholder_mobile_phone_number"] : null;
    $this->checkPsd2Param($customerData, "cardholder_home_phone_prefix")   ? $this->emv3ds["cardholder_home_phone"]["prefix"] = $customerData["cardholder_home_phone_prefix"] : null;
    $this->checkPsd2Param($customerData, "cardholder_home_phone_number")   ? $this->emv3ds["cardholder_home_phone"]["number"] = $customerData["cardholder_home_phone_number"] : null;
    $this->checkPsd2Param($customerData, "cardholder_work_phone_prefix")   ? $this->emv3ds["cardholder_work_phone"]["prefix"] = $customerData["cardholder_work_phone_prefix"] : null;
    $this->checkPsd2Param($customerData, "cardholder_work_phone_number")   ? $this->emv3ds["cardholder_work_phone"]["number"] = $customerData["cardholder_work_phone_number"] : null;
    $this->checkPsd2Param($customerData, "delivery_email_address")   ? $this->emv3ds["merchant_risk_indicator"]["delivery_email_address"] = $customerData["delivery_email_address"] : null;
    $this->checkPsd2Param($customerData, "delivery_timeframe")   ? $this->emv3ds["merchant_risk_indicator"]["delivery_timeframe"] = $customerData["delivery_timeframe"] : null;
    $this->checkPsd2Param($customerData, "amount_in_giftcards")   ? $this->emv3ds["merchant_risk_indicator"]["amount_in_giftcards"] = $customerData["amount_in_giftcards"] : null;
    $this->checkPsd2Param($customerData, "amount_of_giftcards")   ? $this->emv3ds["merchant_risk_indicator"]["amount_of_giftcards"] = $customerData["amount_of_giftcards"] : null;
    $this->checkPsd2Param($customerData, "preorder_date")   ? $this->emv3ds["merchant_risk_indicator"]["preorder_date"] = $this->psd2FormatDate($customerData["preorder_date"]) : null;
    $this->checkPsd2Param($customerData, "preorder_availability_indicator")   ? $this->emv3ds["merchant_risk_indicator"]["preorder_availability_indicator"] = $customerData["preorder_availability_indicator"] : null;
    $this->checkPsd2Param($customerData, "reorder_indicator")   ? $this->emv3ds["merchant_risk_indicator"]["reorder_indicator"] = $customerData["reorder_indicator"] : null;
    $this->checkPsd2Param($customerData, "shipping_indicator")   ? $this->emv3ds["merchant_risk_indicator"]["shipping_indicator"] = $customerData["shipping_indicator"] : null;
    //Billing address, shipping address and adressmatch
    $this->checkAddressesMatch($customerData);
  }

  private function checkAddressesMatch($customerData){
    $billing_address  = [];
    $shipping_address = [];

    $this->checkPsd2Param($customerData, "billing_city")        ? $this->emv3ds["billing_city"]       = $billing_address[] = $this->toAlphaNumericString($customerData["billing_city"],50) : null;
    $this->checkPsd2Param($customerData, "billing_country")     ? $this->emv3ds["billing_country"]    = $billing_address[] = $this->iso3166_2To3166_1($customerData["billing_country"]) : null;
    $this->checkPsd2Param($customerData, "billing_address_1")   ? $this->emv3ds["billing_address_1"]  = $billing_address[] = $this->toAlphaNumericString($customerData["billing_address_1"],50) : null;
    $this->checkPsd2Param($customerData, "billing_address_2")   ? $this->emv3ds["billing_address_2"]  = $billing_address[] = $this->toAlphaNumericString($customerData["billing_address_2"],50) : null;
    $this->checkPsd2Param($customerData, "billing_address_3")   ? $this->emv3ds["billing_address_3"]  = $billing_address[] = $this->toAlphaNumericString($customerData["billing_address_3"],50) : null;
    $this->checkPsd2Param($customerData, "billing_postcode")    ? $this->emv3ds["billing_postcode"]   = $billing_address[] = $this->toAlphaNumericString($customerData["billing_postcode"],16) : null;

    $this->checkPsd2Param($customerData, "shipping_city")       ? $this->emv3ds["shipping_city"]      = $shipping_address[] = $this->toAlphaNumericString($customerData["shipping_city"],50) : null;
    $this->checkPsd2Param($customerData, "shipping_country")    ? $this->emv3ds["shipping_country"]   = $shipping_address[] = $this->iso3166_2To3166_1($customerData["shipping_country"]) : null;
    $this->checkPsd2Param($customerData, "shipping_address_1")  ? $this->emv3ds["shipping_address_1"] = $shipping_address[] = $this->toAlphaNumericString($customerData["shipping_address_1"],50) : null;
    $this->checkPsd2Param($customerData, "shipping_address_2")  ? $this->emv3ds["shipping_address_2"] = $shipping_address[] = $this->toAlphaNumericString($customerData["shipping_address_2"],50) : null;
    $this->checkPsd2Param($customerData, "shipping_address_3")  ? $this->emv3ds["shipping_address_3"] = $shipping_address[] = $this->toAlphaNumericString($customerData["shipping_address_3"],50) : null;
    $this->checkPsd2Param($customerData, "shipping_postcode")   ? $this->emv3ds["shipping_postcode"]  = $shipping_address[] = $this->toAlphaNumericString($customerData["shipping_postcode"],16) : null;
    if(count($billing_address) == count($shipping_address) && array_diff($billing_address, $shipping_address) === array_diff($shipping_address, $billing_address)){
      $this->emv3ds["addresses_match"] = "Y";
    }else{
      $this->emv3ds["addresses_match"] = "N";
    }
  }

  private function toAlphaNumericString($string, $length = null){
    if($length){
      $string = substr($string,0,$length);
    }
    return preg_replace("/[^A-Za-z0-9 ]/", '', $string);
  }

  private function iso3166_2To3166_1($countryCode){
    $countryData = (new \League\ISO3166\ISO3166)->alpha2($countryCode);
    return $countryData["numeric"];
  }

  private function checkPsd2Param($array, $param){
    return is_array($array) && array_key_exists($param, $array) && is_string($array[$param]) && $array[$param] != "";
  }

  private function psd2FormatDate($date_string){
    if($date_string){
      $date = new \DateTime($date_string);
      return date_format($date, 'Ymd');
    }else{
      return "";
    }
  }

}
