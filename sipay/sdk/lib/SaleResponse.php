<?php

declare(strict_types=1);

namespace PWall;

class SaleResponse extends \PWall\Response
{

  public function __construct(
    $jsonResponse
  ){
    parent::__construct($jsonResponse);
  }

  /**
   * {@inheritdoc} 
   */
  public function canPlaceOrder(){
    if (is_array($this->response)
      &&  array_key_exists("result",  $this->response)
      &&  array_key_exists("code",  $this->response["result"])
      &&  intval($this->response["result"]["code"]) === 0
    ){
      //CHECK IF result.payload.code exists and result.payload.code !== 198
      if (is_array($this->response)
        &&  array_key_exists("result",  $this->response)
        &&  array_key_exists("payload",  $this->response["result"])
        &&  array_key_exists("code", $this->response["result"]["payload"])
        &&  intval($this->response["result"]["payload"]["code"]) !== 0
      ){
        return false;
      }else if(is_array($this->response)
        &&  array_key_exists("result",  $this->response)
        &&  array_key_exists("payload",  $this->response["result"])
        &&  array_key_exists("url", $this->response["result"]["payload"])
      ){
        return false;
      }else{
        return true;
      }
    }
    return false;
  }

  /**
   * {@inheritdoc} 
   */
  public function getPaidAmount(){
    if($this->canPlaceOrder()
    && is_array($this->response)
    && array_key_exists("result", $this->response)
    && array_key_exists("payload", $this->response["result"])
    && array_key_exists("amount", $this->response["result"]["payload"])){
      return floatval($this->response["result"]["payload"]["amount"]/100);
    }
    return null;
  }

  /**
   * {@inheritdoc} 
   */
  public function getPaymentMethod(){
    if ($this->canPlaceOrder()
      && is_array($this->response)
      && array_key_exists("params", $this->response)
      && array_key_exists("method", $this->response["params"])
    ) {
      return $this->response["params"]["method"];
    }
    return null;
  }

  /**
   * {@inheritdoc} 
   */
  public function getPaymentInfo(){
    if($this->canPlaceOrder()){
      return $this->flatten($this->response);
    }
    return null;
  }

  
  private function flatten($array) {
    $result = array();
    foreach($array as $key=>$value) {
        if(is_array($value)) {
            $result = $result + $this->flatten($value, $key . '.');
        }
        else {
            $result[$key] = $value;
        }
    }
    return $result;
  }
}
