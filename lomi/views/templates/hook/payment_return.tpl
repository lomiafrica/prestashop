{if $status == 'ok'}
    <p>
      {l s='Your order on %s is complete.' sprintf=[$shop_name] d='Modules.Lomi.Shop'}
    </p>
    <p>
      {l s='We have also sent you this information by e-mail.' d='Modules.Lomi.Shop'}
    </p>
    <p>
      {l s='Reference:' d='Modules.Lomi.Shop'} <strong>{$reference|escape:'html'}</strong>
    </p>
    <p>
      <a href="{$contact_url|escape:'html'}">{l s='Contact customer support' d='Modules.Lomi.Shop'}</a>
    </p>
{else}
    <p class="warning">
      {l s='We noticed a problem with your order.' d='Modules.Lomi.Shop'}
      <a href="{$contact_url|escape:'html'}">{l s='Contact customer support' d='Modules.Lomi.Shop'}</a>
    </p>
{/if}
