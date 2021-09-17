<?php

namespace PWall\Helper;

class Constants{
  //API URLS
  const SANDBOX_URL = "https://sandbox.sipay.es/pwall/api/v1/actions";
  const LIVE_URL    = "https://live.sipay.es/pwall/api/v1/actions";
  const DEVELOP_URL = "https://develop.sipay.es/pwall/api/v1/actions";

  const ENVIROMENTS_URLS = [
    "sandbox" => self::SANDBOX_URL,
    "live"    => self::LIVE_URL,
    "develop" => self::DEVELOP_URL
  ];

  //API ACTIONS
  const PWALL_ACTION_SALE           = "pwall.sale";
  const PWALL_ACTION_GETEXTRADATA   = "pwall.getExtraData";

  //PSD2 
  const SCA_EXEMPTIONS_TRA_VALUE    = "TRA";
  const SCA_EXEMPTIONS_LWV_VALUE    = "LWV";
}