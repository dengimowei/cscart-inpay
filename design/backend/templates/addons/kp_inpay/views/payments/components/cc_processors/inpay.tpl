<div class="control-group">
    <label class="control-label" for="apikey">{__("apikey")}:</label>
    <div class="controls">
        <input type="text" name="payment_data[processor_params][apikey]" id="apikey" value="{$processor_params.apikey}" class="input-text" />
    </div>
</div>
<div class="control-group">
    <label class="control-label" for="secretApiKey">{__("secretApiKey")}:</label>
    <div class="controls">
        <input type="text" name="payment_data[processor_params][secretApiKey]" id="secretApiKey" value="{$processor_params.secretApiKey}" class="input-text" />
    </div>
</div>
<div class="control-group">
    <label class="control-label" for="currency">{__("currency")}:</label>
    <div class="controls">
        <select name="payment_data[processor_params][currency]" id="currency" value="{$processor_params.currency}">
            <option value="PLN" {if $processor_params.currency=='PLN'}selected{/if}>PLN</option>
            <option value="EUR" {if $processor_params.currency=='EUR'}selected{/if}>EUR</option>
            <option value="USD" {if $processor_params.currency=='USD'}selected{/if}>USD</option>
        </select>
    </div>
</div>
<div class="control-group">
    <label class="control-label" for="mode">{__("mode")}:</label>
    <div class="controls">
        <select name="payment_data[processor_params][mode]" id="mode" value="{$processor_params.mode}">
            <option value="test" {if $processor_params.currency=='test'}selected{/if}>Test</option>
            <option value="live" {if $processor_params.currency=='live'}selected{/if}>Live</option>
        </select>
    </div>
</div>
