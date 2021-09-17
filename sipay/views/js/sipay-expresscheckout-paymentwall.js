(function ($) {
  $(document).ready(function () {
    if(sipay_ec_config){
     
      sipayExpressCheckoutState.setCurrentProfile(sipay_ec_config, sipay_ec_config.element);

      // $(document.body).on('updated_cart_totals', function () {
      //   sipayExpressCheckoutState.setCurrentProfile(sipay_ec_config, sipay_ec_config.element);
      // });
    }
    $(document).on('express_checkout_render', function(){
      sipayExpressCheckoutState.setCurrentProfile(sipay_ec_config, sipay_ec_config.element);
    })
    // if(sipay_ec_minicart_config){
    //   $('.cart-contents').mouseenter(function () {
    //     window.sipayExpressCheckoutState.setCurrentProfile(sipay_ec_minicart_config, sipay_ec_minicart_config.element);
    //   });
    //   $('.site-header-cart').mouseleave(function () {
    //     window.sipayExpressCheckoutState.revertProfile();
    //   });
    // }
  });
})(jQuery)