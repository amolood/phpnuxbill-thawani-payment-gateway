{include file="sections/header.tpl"}

<form class="form-horizontal" method="post" autocomplete="off" role="form" action="{$_url}paymentgateway/thawani">
    <div class="row">
        <div class="col-sm-12 col-md-12">
            <div class="panel panel-primary panel-hovered panel-stacked mb30">
                <div class="panel-heading">Thawani Payment Gateway Settings</div>
                <div class="panel-body">
{*                    create dropdownmenu for prodection and testing *}
                    <div class="form-group">
                        <label class="col-md-2 control-label">Thawani Stage</label>
                        <div class="col-md-6">
                            <select class="form-control" id="thawani_stage" name="thawani_stage">
                                <option value="Live" {if $_c['thawani_stage'] == 'Live'}selected{/if}>Live</option>
                                <option value="Testing" {if $_c['thawani_stage'] == 'Testing'}selected{/if}>Testing</option>
                            </select>
                        </div>

                    </div>

                    <div class="form-group">
                        <label class="col-md-2 control-label">Publishable Key</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="thawani_publishable_key" name="thawani_publishable_key"
                                   value="{$_c['thawani_publishable_key']}">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="col-md-2 control-label">Secret Key</label>
                        <div class="col-md-6">
                            <input type="password" class="form-control" id="thawani_secret_key" name="thawani_secret_key"
                                   value="{$_c['thawani_secret_key']}">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-md-2 control-label">Thawani live url</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="thawani_live_url" name="thawani_live_url"
                                   placeholder="https://checkout.thawani.om/api/v1"
                                   value="{$_c['thawani_live_url']}">
                            <small class="form-text text-muted">Production API base, e.g. https://checkout.thawani.om/api/v1</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="col-md-2 control-label">Thawani testing url</label>
                        <div class="col-md-6">
                            <input type="text" class="form-control" id="thawani_testing_url" name="thawani_testing_url"
                                   placeholder="https://uatcheckout.thawani.om/api/v1"
                                   value="{$_c['thawani_testing_url']}">
                            <small class="form-text text-muted">UAT/sandbox API base, e.g. https://uatcheckout.thawani.om/api/v1</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <div class="col-lg-offset-2 col-lg-10">
                            <button class="btn btn-primary waves-effect waves-light" type="submit">Save</button>
                        </div>
                    </div>

                    <label class="control-label">Webhook / Callback URL</label>
                    <pre>{$app_url}/system/paymentgateway/thawani.php</pre>
                    <small class="form-text text-muted">If you enable Thawani webhooks, point them here.</small>

                    <label class="control-label" style="margin-top:10px">Mikrotik Walled Garden</label>
                    <pre>/ip hotspot walled-garden
add dst-host=thawani.om
add dst-host=*.thawani.om</pre>
                    <small class="form-text text-muted">
                        Amounts are charged in Omani Rial; Thawani uses baisa internally (1 OMR = 1000 baisa).
                        Set a Telegram bot in Settings to receive error notifications.
                    </small>
                </div>
            </div>

        </div>
    </div>
</form>

{include file="sections/footer.tpl"}
