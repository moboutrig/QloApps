<form id="openPaywall" action="{$paywallUrl|escape:'html'}" method="POST">
    <input type="hidden" name="payload" value='{$payload|escape:'html':'UTF-8'}' />
    <input type="hidden" name="signature" value="{$signature|escape:'html'}" />
</form>

<script type="text/javascript">
    document.getElementById("openPaywall").submit();
</script>
<p>{l s='Please wait, you will be redirected to Payzone shortly.' mod='payzone'}</p>
