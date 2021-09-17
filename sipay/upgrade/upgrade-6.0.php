<?php

function upgrade_module_6_0($module) {
    $result = true;

    $result = $result && createOrderExtraDataTable();
    $result = $result && addOrderState();
    
    return $result;
}

function createOrderExtraDataTable()
{
    Db::getInstance()->execute(
        '
        CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'sipay_order_extradata` (
            `id_order` INT UNSIGNED NOT NULL,
            `data` LONGTEXT NULL,
            PRIMARY KEY (`id_order`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' CHARACTER SET utf8 COLLATE utf8_general_ci;'
    );
    return true;
}

function addOrderState(){
  $payment_module = new Sipay(); 
  if (!Configuration::get('SIPAY_PENDING_PAYMENT')) {
      // create new order state
      $order_state = new OrderState();
      $order_state->send_email = false;
      $order_state->color = '#cdcdcd';
      $order_state->hidden = false;
      $order_state->delivery = false;
      $order_state->logable = false;
      $order_state->invoice = false;
      $languages = Language::getLanguages(false);
      foreach ($languages as $language)
          $order_state->name[$language['id_lang']] = $payment_module->l('Pending payment');

      // Update object
      $order_state->add();
      Configuration::updateValue('SIPAY_PENDING_PAYMENT', (int) $order_state->id);
  }

  if (!Configuration::get('SIPAY_SUSPECTED_FRAUD')) {
      // create new order state
      $order_state = new OrderState();
      $order_state->send_email = false;
      $order_state->color = '#FFA500';
      $order_state->hidden = false;
      $order_state->delivery = false;
      $order_state->logable = false;
      $order_state->invoice = false;
      $languages = Language::getLanguages(false);
      foreach ($languages as $language)
          $order_state->name[$language['id_lang']] = $payment_module->l('Suspected fraud');

      // Update object
      $order_state->add();
      Configuration::updateValue('SIPAY_SUSPECTED_FRAUD', (int) $order_state->id);
  }

  return true;
}