(function ($) {
    var sipayExpressCheckoutState = function () {
      this.profiles           = {};
      this.currentProfile     = null;
      this.lastProfile        = null;
      this.config             = null;
      this.element            = null;
      this.elementParent      = null;
      this.hasLoadedStyle     = false;

    //   $.fn.serializeArrayAll = function () {
    //   var rCRLF = /\r?\n/g;
    //   return this.map(function () {
    //     return this.elements ? jQuery.makeArray(this.elements) : this;
    //   }).map(function (i, elem) {
    //     var val = jQuery(this).val();
    //     if (val == null) {
    //       return val == null
    //       //next 2 lines of code look if it is a checkbox and set the value to blank 
    //       //if it is unchecked
    //     } else if (this.type == "checkbox" && this.checked == false) {
    //       return { name: this.name, value: this.checked ? this.value : '' }
    //       //next lines are kept from default jQuery implementation and 
    //       //default to all checkboxes = on
    //     } else {
    //       return jQuery.isArray(val) ?
    //         jQuery.map(val, function (val, i) {
    //           return { name: elem.name, value: val.replace(rCRLF, "\r\n") };
    //         }) :
    //         { name: elem.name, value: val.replace(rCRLF, "\r\n") };
    //     }
    //   }).get();
    // };

      this.addProfile = function(config, element){
        this.profiles[config.profile] = {config:config, element: element, elementParent: $(element).parent()};
      }

      this.setCurrentProfile = function(config, element){
        this.addProfile(config, element);
        this.lastProfile = this.currentProfile;
        this.removeFieldset(this.element);
        this.currentProfile = config.profile;
        this.config = config;
        this.element = element;
        this.elementParent = this.profiles[config.profile].elementParent;
        this.initExpressCheckout()
      }

      this.revertProfile = function(){
        if(!this.lastProfile){
          this.currentProfile = null;
          this.config = null;
          this.element = null;
          return;
        }
        this.removeFieldset(this.element);
        this.currentProfile = this.lastProfile;
        this.lastProfile = null;
        this.config = this.profiles[this.currentProfile].config;
        this.element = this.profiles[this.currentProfile].element;
        this.elementParent = this.profiles[this.currentProfile].elementParent;
        this.initExpressCheckout();
      }

      this._init = function(config, element, quote) {
        const client = new PWall(config.enviroment, false);
        var express_checkout = client.expresscheckout();

        express_checkout.backendUrl(config.backendUrl)
        express_checkout.appendTo(element)
        express_checkout.setProfile(config.profile)
        express_checkout.setTags(quote.tags)
        express_checkout.setLogoUrl(config.storeLogoUrl)
        express_checkout.on("paymentOk", this.redirectToCheckoutSuccess.bind(this))
        express_checkout.on("paymentKo", () => { console.log("PAYMENT KO") })
        express_checkout.on("validationFunc", this.validateFunction.bind(this))
        express_checkout.currency(quote.currency)
        express_checkout.groupId(quote.group_id)
        express_checkout.amount(quote.amount)
      };

      this.redirectToCheckoutSuccess = function() {
        console.log("PAYMENT OK,  REDIRECTING TO ONEPAGE SUCCESS");
        var url_encoded = this.getCookie("success_redirect");
        this.deleteCookie("success_redirect");
        window.location.replace(decodeURIComponent(url_encoded));
      };
      /**
         * Get the value of a cookie
         * Source: https://gist.github.com/wpsmith/6cf23551dd140fb72ae7
         * @param  {String} name  The name of the cookie
         * @return {String}       The cookie value
         */
      this.getCookie = function(name) {
        var value = "; " + document.cookie;
        var parts = value.split("; " + name + "=");
        if (parts.length == 2) return parts.pop().split(";").shift();
      };
      this.deleteCookie = function(name) {
        document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:01 GMT;';
      };

      this.initExpressCheckout = function() {
        //if first #app, save configuration for later
        this.containerPosition();
        $.ajax({
          url: this.config.quoteInfoUrl,
          dataType: "json",
          data: {},
          timeout: 30000,
          success: function (data) {
            this._init(this.config, this.element, data);
          }.bind(this)
        });
      };

      this.containerPosition = function(){
        var app_element = this.element;
        var insert = "";
        $('#app').remove();
        if (Object.keys(this.config.positionConfig).length > 0) {
          $('#sipay_ec_app').remove();
          //Prepare container and insert in position selected
          var app_container = "<div id=sipay_ec_app></div>";
          this.insertInPosition(this.config.positionConfig, this.element, app_container);
          app_element = "#sipay_ec_app";
          if (this.config.positionStyleConfig) {
            $(app_element).css(this.config.positionStyleConfig);
          }
          this.element = app_element;
          insert = this.config.positionConfig.insertion;
        }
        //CREATE CONTAINER WITH LEGEND AND ALL THAT HAPPY STUFF
        if (Object.keys(this.config.containerStyle).length > 0) {
          if (this.config.containerStyle.header_title != null) {
            var fieldset = "<fieldset id=sipay_ec_container><legend style='padding: 0px 16px;width:auto' align=center>" + this.config.containerStyle.header_title + "</legend ></fieldset>";
            
          } else {
            var fieldset = "<fieldset id=sipay_ec_container><fieldset>";
          }
          if (this.config.positionStyleConfig) {
            $("#sipay_ec_container").css(this.config.positionStyleConfig);
          }
          if (insert === "into"){
            this.insertInPosition(this.config.positionConfig, $(this.element).parent(), fieldset);
          }else{
            this.insertInPosition(this.config.positionConfig, this.element, fieldset);
          }
          if (this.config.containerStyle.descriptive_text != null && this.config.containerStyle.descriptive_text != ""){
            $("fieldset#sipay_ec_container > legend").bind({
              mousemove: this.changeTooltipPosition,
              mouseenter: this.showTooltip.bind(this),
              mouseleave: this.hideTooltip
            });
          }
          var elementj = $(app_element).detach();
          if (this.config.containerStyle.color != "#") {
            $('#sipay_ec_container').css('border', '1px solid ' + this.config.containerStyle.color);
          } else {
            $('#sipay_ec_container').css('border', '1px solid ' + this.config.containerStyle.custom_color);
          }
          if (this.config.containerStyle.header_title_typo != null) {
            $('#sipay_ec_container').css('font-family', this.config.containerStyle.header_title_typo);
          }
          $('#sipay_ec_container').append(elementj);
        }
      }

      this.removeFieldset = function(app_element){
        var $fieldset = $('#sipay_ec_container');
        if ($fieldset.length && !this.config.positionConfig.length){
          var $app_element = $(app_element).detach();
          this.elementParent.append($app_element);
        }
        $fieldset.remove();
      }

      this.validateFunction = function() {
        var $form = $('#buy_block');
        var $form17 = $('#add-to-cart-or-refresh');
        if ($form.length > 0) {
           this.ajaxSyncSubmit();
          return true;
        }else if($form17.length > 0){
          this.ajaxSyncSubmit17();
          return true;
        }
        return true;
      };

      this.ajaxSyncSubmit17 = function() {
        var $form = $('#add-to-cart-or-refresh');
        var data = $form.serialize() + "&add=1&action=update";
        var url = $form.attr("action");
        $.ajax({
          type: 'POST',
          url: url,
          data: data,
          dataType: "json",
          async: false,
          success: function(data){
            prestashop.emit("updateCart", {
              reason: {
                idProduct: data.id_product,
                idProductAttribute: data.id_product_attribute,
                idCustomization: data.id_customization,
                linkAction: "add-to-cart",
                cart: data.cart
              },
              resp: data
            });
          }
        });
      };

      this.ajaxSyncSubmit = function() {
        //add: function(idProduct, idCombination, addedFromProductPage, callerElement, quantity, whishlist) {
        //ajaxCart.add($('#product_page_product_id').val(), $('#idCombination').val(), true, null, $('#quantity_wanted').val(), null);
        var idProduct = $('#product_page_product_id').val();
        var idCombination = $('#idCombination').val();
        var addedFromProductPage = true;
        var callerElement = null;
        var quantity = $('#quantity_wanted').val();
        var whishlist = null;   
        
        $.ajax({
          type: 'POST',
          headers: { "cache-control": "no-cache" },
          url: baseUri + '?rand=' + new Date().getTime(),
          async: false,
          cache: false,
          dataType: "json",
          data: 'controller=cart&add=1&ajax=true&qty=' + ((quantity && quantity != null) ? quantity : '1') + '&id_product=' + idProduct + '&token=' + static_token + ((parseInt(idCombination) && idCombination != null) ? '&ipa=' + parseInt(idCombination) : '' + '&id_customization=' + ((typeof customizationId !== 'undefined') ? customizationId : 0)),
          success: function (jsonData, textStatus, jqXHR) {
            // add appliance to whishlist module
            if (whishlist && !jsonData.errors)
              WishlistAddProductCart(whishlist[0], idProduct, idCombination, whishlist[1]);

            if (!jsonData.hasError) {
              if (contentOnly)
                window.parent.ajaxCart.updateCartInformation(jsonData, addedFromProductPage);
              else
                ajaxCart.updateCartInformation(jsonData, addedFromProductPage);

              if (jsonData.crossSelling)
                $('.crossseling').html(jsonData.crossSelling);

              if (idCombination)
                $(jsonData.products).each(function () {
                  if (this.id != undefined && this.id == parseInt(idProduct) && this.idCombination == parseInt(idCombination))
                    if (contentOnly)
                      window.parent.ajaxCart.updateLayer(this);
                    else
                      ajaxCart.updateLayer(this);
                });
              else
                $(jsonData.products).each(function () {
                  if (this.id != undefined && this.id == parseInt(idProduct))
                    if (contentOnly)
                      window.parent.ajaxCart.updateLayer(this);
                    else
                      ajaxCart.updateLayer(this);
                });
              if (contentOnly)
                parent.$.fancybox.close();
            }
            else {
              if (contentOnly)
                window.parent.ajaxCart.updateCart(jsonData);
              else
                ajaxCart.updateCart(jsonData);
              if (addedFromProductPage)
                $('#add_to_cart button').removeProp('disabled').removeClass('disabled');
              else
                $(callerElement).removeProp('disabled');
            }

            emptyCustomizations();

          }
        })
      };

      this.insertInPosition = function(configPosition, element, app_container) {
        if (configPosition.insertion === "into") {
          $(element).append(app_container);
          //$(app_container).appendTo(element);
        } else if (configPosition.insertion === "before") {
          $(app_container).insertBefore(element);
        } else {
          $(app_container).insertAfter(element);
        }
      };

      this.showTooltip = function (event) {
        $('div.tooltip').remove();
        $('<div class="tooltip">'+this.config.containerStyle.descriptive_text+'</div>')
          .appendTo('body').css({
            "margin": "8px",
            "padding": "8px",
            "border": "1px solid grey",
            "position": "absolute",
            "z-index": "100"
          });
        this.changeTooltipPosition(event);
      };

      this.changeTooltipPosition = function (event) {
        var tooltipX = event.pageX - 8;
        var tooltipY = event.pageY + 8;
        $('div.tooltip').css({ top: tooltipY, left: tooltipX });
      };

      this.hideTooltip = function () {
        $('div.tooltip').remove();
      };
    }
    window.sipayExpressCheckoutState = new sipayExpressCheckoutState();
  })(jQuery);