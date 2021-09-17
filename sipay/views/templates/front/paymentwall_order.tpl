<h4>{l s='Extra Details'}</h4>
<table class="sipay_payment_info">
{foreach from=$sipay_order_extradata item=row key=key}
  <tr>
    <th><strong>{$key}</strong></th>
    <th>{$row}</th>
  </tr>
{/foreach}
</table>
