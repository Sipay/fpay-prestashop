<?php

declare(strict_types=1);

namespace PWall;

class Response
{
  protected $response;

  public function __construct(
    $jsonResponse
  ){
    $this->response = json_decode($jsonResponse, true);
  }

  /**
   * Returns the JSON response
   *
   * @return string JSON response
   */
  public function toJSON(){
    return json_encode($this->response);
  }

  /**
   * Returns error code if there was an error
   *
   * @return string error code if there was an error, null otherwise
   */
  public function getErrorCode(){
    if(array_key_exists("result", $this->response)
    && array_key_exists("code", $this->response["result"])
    && $this->response["result"]["code"] !== 0){
      return $this->response["result"]["code"];
    }

    if(array_key_exists("code", $this->response)
    && $this->response["code"] !== 0){
      return $this->response["code"];
    }

    return null;
  }
  
  /**
   * Returns error message if there was an error
   *
   * @return String error message if there was an error, null otherwise
   */
  public function getErrorMessage(){
    if(array_key_exists("result", $this->response)
    && array_key_exists("code", $this->response)
    && $this->response["result"]["code"] !== 0){
      return $this->response["result"]["description"];
    }

    if(array_key_exists("code", $this->response)
    && $this->response["code"] !== 0){
      return $this->response["description"];
    }

    return null;
  }
  /**
   * Return address if the response has address based on key (for express checkout)
   * $key: address | billing_address
   * 
   * @return array|null array with address if response has address, otherwise null
   */

  public function getAddressFromResponse($key){
    if(is_array($this->response)
    && array_key_exists("result", $this->response)
    && array_key_exists("payload", $this->response["result"]) 
    && array_key_exists("address", $this->response["result"]["payload"])){
      return $this->response["result"]["payload"][$key];
    }
    return null;
  }

  /**
   * Return address if the response has address (for express checkout)
   *
   * @return array|null array with address if response has address, otherwise null
   */
  public function getAddress(){
    return $this->getAddressFromResponse("address");
  }


   /**
   * Return billing address if the response has address (for express checkout)
   *
   * @return array|null array with billing address if response has address, otherwise null
   */
  public function getBillingAddress(){
    return $this->getAddressFromResponse("billing_address");
  }

  /**
   * Return customer data if the response has customer data (for express checkout)
   *
   * @return array|null array with customer data if response has customer data, otherwise null
   */
  public function getCustomerData(){
    if (
      is_array($this->response)
      && array_key_exists("result", $this->response)
      && array_key_exists("payload", $this->response["result"])
      && array_key_exists("customer", $this->response["result"]["payload"])
    ) {
      return $this->response["result"]["payload"]["customer"];
    }
    return null;
  }
  
  /**
   * Check if the response is valid
   *
   * @return boolean true if response is valid, otherwise false
   */
  public function isValid(){
    if(is_array($this->response)
    && array_key_exists("id", $this->response)
    && array_key_exists("result", $this->response)){
      return true;
    }
    return false;
  }

  /**
   * Check if request is create_order to create order in pending payment (redirect method)
   * 
   * @return boolean true if request is create order in pending payment, false otherwise
   */
  public function isCreatePendingOrder(){
    if(is_array($this->response)
    && array_key_exists("result", $this->response) && $this->response["result"] != null && array_key_exists("payload", $this->response["result"])
    && array_key_exists("payload", $this->response["result"])
    && array_key_exists("create_order", $this->response["result"]["payload"])
    && array_key_exists("express", $this->response["result"]["payload"])
    && $this->response["result"]["payload"]["create_order"] == true
    && $this->response["result"]["payload"]["express"] == false){
      return true;
    }
    return false;
  }

  /**
   * Check if the response has address (for express checkout)
   *
   * @return boolean true if response has address, otherwise false
   */
  public function hasAddress(){
    if(is_array($this->response)
    && array_key_exists("result", $this->response)
    && $this->response["result"] != null && array_key_exists("payload", $this->response["result"])
    && array_key_exists("address", $this->response["result"]["payload"])){
      return true;
    }
    return false;
  }

  /**
   * Check if the response has billing address (for express checkout)
   *
   * @return boolean true if response has address, otherwise false
   */
  public function hasBillingAddress(){
    if(is_array($this->response)
    && array_key_exists("result", $this->response)
    && $this->response["result"] != null && array_key_exists("payload", $this->response["result"])
    && array_key_exists("billing_address", $this->response["result"]["payload"])){
      return true;
    }
    return false;
  }

  /**
   * Check if the response has customer data (for express checkout)
   *
   * @return boolean true if response has customer data, otherwise false
   */
  public function hasCustomerData(){
    if(is_array($this->response)
    && array_key_exists("result", $this->response)
    && array_key_exists("payload", $this->response["result"])
    && array_key_exists("customer", $this->response["result"]["payload"])){
      return true;
    }
    return false;
  }

  /**
   * Check if the response has payload url for redirect
   *
   * @return boolean true if response has payload url, otherwise false
   */
  public function hasPayloadUrl()
  {
    if (
      is_array($this->response)
      && array_key_exists("result", $this->response)
      && array_key_exists("payload", $this->response["result"])
      && array_key_exists("url", $this->response["result"]["payload"])
    ) {
      return true;
    }
    return false;
  }

  /**
   * Set updated amount for express_checkout
   * @param  float $amount Amount with decimals that will be charged to customer
   * @return void
   */
  public function setUpdatedAmount($amount){
    $this->response["result"]["payload"]["amount"] = strval($amount * 100);
  }

  /**
   * Set updated amount for express_checkout
   * @param  string $message Error message to return
   * @return void
   */
  public function setError($message){
    //Response will be removed and only return this.
    $this->response = [];
    $this->response["error"] = $message;
  }

  /**
   * Check for update amount in response, to ignore address if any
   * @param  string $message Error message to return
   * @return void
   */
  public function hasUpdateAmount(){
    if (
      is_array($this->response)
      && array_key_exists("result", $this->response)
      && array_key_exists("payload", $this->response["result"])
      && array_key_exists("update_amount", $this->response["result"]["payload"])
    ) {
      return true;
    }
    return false;
  }

  /**
   * This response checks if the response is valid for place order
   *
   * @return boolean true if can place order, otherwise false
   */
  public function canPlaceOrder(){
    return false;
  }
  
  /**
   * Returns the paid amount if the response is for sale action
   *
   * @return float|null amount paid by customer, null if response is not for sale action
   */
  public function getPaidAmount(){
    return null;
  }

  /**
   * Returns the payment method used if the response is for sale action
   *
   * @return string|null payment method name used by customer, null if response is not for sale action
   */
  public function getPaymentMethod(){
    return null;
  }

  /**
   * Returns the payment info in a flattened array
   *
   * @return array|null payment info in a flatenned array, null if response is not for sale action
   */
  public function getPaymentInfo(){
    return null;
  }
}
