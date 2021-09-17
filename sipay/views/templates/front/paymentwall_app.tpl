<form action="javascript:void(0);" class="payment-wall">
    <p id="sipay-terms-alert" class="alert alert-danger" role="alert" data-alert="danger">
          {l s='Please make sure you\'ve accepted the terms and conditions.' mod='sipay'}
    </p>
    <div id="sipay-app"></div>
    <script type="text/javascript">
        $(document).trigger('sipay_draw_payment_wall');
    </script>
</form>