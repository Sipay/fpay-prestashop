(function ($) {
    $.getScript(sipay_js_sdk,() => {
        const client = new PWall(sipay_enviroment, true);
        const checkout = client.checkout();
        initialize();

        function initialize() {
            //check if we hace to redirect to payment step
            var autoClickSipay = false;

            //var searchParams = new URLSearchParams(window.location.search);
            if (client.parseUrlParams('request_id') && client.parseUrlParams('method')) {
                if (parseFloat(ps_version) >= 1.7){
                    window.location.hash = PS_17_PAYMENT_STEP_HASH;
                    autoClickSipay = true;
                }else{
                    if (osc_checkout === "0" && !client.parseUrlParams('step')){
                        window.location.hash = PS_16_OSC_PAYMENT_STEP_HASH;
                        window.location.search = window.location.search + "&" + PS_16_PAYMENT_STEP;
                    }else{
                        window.location.hash = PS_16_OSC_PAYMENT_STEP_HASH;
                    }
                }
            }
            addListeners();
            $(document).ready(function(){
                if (autoClickSipay) {
                    //trigger click on payment method and accept terms (should be accepted if payment wall is trying to validate payment)
                    var regex = new RegExp(/payment-option-[0-9]*/);
                    var parent_element = $('.payment-wall').parent();
                    parent_element.show();
                    var payment_option = parent_element[0].id;
                    var match_option = payment_option.match(regex);
                    if (match_option) {
                        $('label[for=' + match_option + ']').trigger('click');
                    }
                    $('.js-terms').trigger('click');
                }
            }.bind(this));
            renderPaymentWall();
        }

        function updatePaymentWallDataset() {
            log("UPDATE");
            $.ajax({
                url: sipay_quote_rest,
                async: false,
                cache: false
            }).done(function (amount) {
                client.amount(amount);
                $('#sipay-terms-alert').hide();
            }).fail(function (data) {
                log("UpdateAmount failed")
            });

        }

        function renderPaymentWall() {
            log("RENDERING PAYMENT WALL");
            checkout.appendTo("#sipay-app")
                .backendUrl(sipay_backend_url)
                .validateForm(function(){return true})
                .on("paymentOk", redirectToCheckoutSuccess.bind(this))
                .currency(sipay_currency)
                .groupId(sipay_customerId === null ? 0 : sipay_customerId)
            updatePaymentWallDataset();
        }

        function redirectToCheckoutSuccess(){
            console.log("PAYMENT OK,  REDIRECTING TO ONEPAGE SUCCESS");
            var url_encoded = getCookie("success_redirect");
            deleteCookie("success_redirect");
            window.location.replace(decodeURIComponent(url_encoded));
        }

        function addListeners() {
            $(document).on('sipay_draw_payment_wall', function () {
                $('#sipay-terms-alert').hide();
                updatePaymentWallDataset();
            });
            if ($('#uniform-cgv span').hasClass("checked")) {
                $('#sipay-terms-alert').hide();
                updatePaymentWallDataset();
            }
            $(document).on('change', '.payment-options', function () {
                if ($('#payment-confirmation').length) {
                    if ($('#app').is(':visible')) {
                        $('#payment-confirmation').hide();
                        if (!$('#conditions-to-approve input').length || $('#conditions-to-approve input').is(':checked')) {
                            $('#sipay-terms-alert').hide();
                            console.log("payment-options");
                            updatePaymentWallDataset();
                        } else {
                            $("#app").empty();
                            $("#app").removeAttr("class");
                            $('#sipay-terms-alert').show();
                        }
                    } else {
                        $('#payment-confirmation').show();
                    }
                }
            });
            $(document).on('change', '#conditions-to-approve', function () {
                if ($('#payment-confirmation').length) {
                    if ($('#app').is(':visible')) {
                        $('#payment-confirmation').hide();
                        if ($('#conditions-to-approve input').is(':checked')) {
                            $('#sipay-terms-alert').hide();
                            console.log("conditions-to-approve");
                            updatePaymentWallDataset();
                        } else {
                            $("#app").empty();
                            $("#app").removeAttr("class");
                            $('#sipay-terms-alert').show();
                        }
                    } else {
                        $('#payment-confirmation').show();
                    }
                }
            });
        }

        /**
         * Get the value of a cookie
         * Source: https://gist.github.com/wpsmith/6cf23551dd140fb72ae7
         * @param  {String} name  The name of the cookie
         * @return {String}       The cookie value
         */
        function getCookie(name) {
            var value = "; " + document.cookie;
            var parts = value.split("; " + name + "=");
            if (parts.length == 2) return parts.pop().split(";").shift();
        };

        function deleteCookie(name, path, domain) {
            if (getCookie(name)) {
                document.cookie = name + "=" +
                    ((path) ? ";path=" + path : "") +
                    ((domain) ? ";domain=" + domain : "") +
                    ";expires=Thu, 01 Jan 1970 00:00:01 GMT";
            }
        }

        function log() {
            var args = Array.prototype.slice.call(arguments, 0);
            args.unshift("[SIPAY DEBUG]");
            console.log.apply(console, args);
        }
    });
})(jQuery);
