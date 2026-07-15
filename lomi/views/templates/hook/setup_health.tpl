{*
* lomi. — Setup health panel
*}
<div class="panel">
  <h3><i class="icon-check"></i> {l s='Setup health' d='Modules.Lomi.Admin'}</h3>
  <table class="table">
    <thead>
      <tr>
        <th>{l s='Check' d='Modules.Lomi.Admin'}</th>
        <th>{l s='Status' d='Modules.Lomi.Admin'}</th>
        <th>{l s='Details' d='Modules.Lomi.Admin'}</th>
      </tr>
    </thead>
    <tbody>
      {foreach from=$lomi_setup_health item=check}
        <tr>
          <td>{$check.label|escape:'html'}</td>
          <td>
            {if $check.status == 'error'}
              <span class="label label-danger">{l s='Action required' d='Modules.Lomi.Admin'}</span>
            {elseif $check.status == 'warning'}
              <span class="label label-warning">{l s='Warning' d='Modules.Lomi.Admin'}</span>
            {elseif $check.status == 'info'}
              <span class="label label-default">{l s='Info' d='Modules.Lomi.Admin'}</span>
            {else}
              <span class="label label-success">{l s='OK' d='Modules.Lomi.Admin'}</span>
            {/if}
          </td>
          <td>{$check.message|escape:'html'}</td>
        </tr>
      {/foreach}
    </tbody>
  </table>
  <p class="help-block">
    {l s='Webhook events checklist: enable PAYMENT_SUCCEEDED and REFUND_COMPLETED in Dashboard → Webhooks.' d='Modules.Lomi.Admin'}
    <a href="https://docs.lomi.africa/build/ecommerce-extensions/prestashop" target="_blank" rel="noopener noreferrer">{l s='Setup guide' d='Modules.Lomi.Admin'}</a>
  </p>
</div>
