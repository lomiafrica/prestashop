{*
* lomi. — module configuration notice
*}
<div class="alert alert-info">
<img src="{$lomi_module_logo|escape:'html'}" style="float:left; margin-right:15px;" height="60" alt="">
<p><strong>{l s='Accept card and mobile money payments with lomi. hosted checkout.' d='Modules.Lomi.Admin'}</strong></p>
<p>{l s='Configure a webhook in your lomi. dashboard using this URL and the same signing secret as below:' d='Modules.Lomi.Admin'}</p>
<p><code style="word-break:break-all;">{$lomi_webhook_url|escape:'html'}</code></p>
<p>{l s='Store currency must be EUR, USD, or XOF. Use test mode with sandbox API keys.' d='Modules.Lomi.Admin'}</p>
</div>
