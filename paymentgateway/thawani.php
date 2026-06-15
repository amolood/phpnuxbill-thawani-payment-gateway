<?php

/**
 * PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *
 * Thawani Payment Gateway (thawani.om)
 * by https://amolood.com
 *
 * NOTE ON AMOUNTS: Thawani charges in BAISA (1 OMR = 1000 baisa). PHPNuxBill
 * stores plan price as a decimal OMR string, so we convert OMR -> baisa
 * (price * 1000, integer) when creating the checkout session.
 */

define('THAWANI_VERSION', '2.0.0');

/** Smallest Thawani charge (100 baisa = 0.100 OMR). */
define('THAWANI_MIN_BAISA', 100);

function thawani_validate_config()
{
    global $config;
    if (
        empty($config['thawani_secret_key']) ||
        empty($config['thawani_publishable_key']) ||
        empty($config['thawani_testing_url']) ||
        empty($config['thawani_live_url'])
    ) {
        r2(U . 'order/package', 'w', Lang::T("Admin has not yet setup Thawani payment gateway, please tell admin"));
    }
}

function thawani_show_config()
{
    global $ui;
    $ui->assign('_title', 'Thawani - Payment Gateway');
    $ui->display('thawani.tpl');
}

/** Insert-or-update a single tbl_appconfig setting. */
function thawani_set_config($setting, $value)
{
    $d = ORM::for_table('tbl_appconfig')->where('setting', $setting)->find_one();
    if (!$d) {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = $setting;
    }
    $d->value = $value;
    $d->save();
}

function thawani_save_config()
{
    global $admin, $_L;

    thawani_set_config('thawani_publishable_key', _post('thawani_publishable_key'));
    thawani_set_config('thawani_secret_key', _post('thawani_secret_key'));
    thawani_set_config('thawani_live_url', _post('thawani_live_url'));
    thawani_set_config('thawani_testing_url', _post('thawani_testing_url'));
    thawani_set_config('thawani_stage', _post('thawani_stage'));

    _log('[' . $admin['username'] . ']: Thawani ' . $_L['Settings_Saved_Successfully'], 'Admin', $admin['id']);
    r2(U . 'paymentgateway/thawani', 's', $_L['Settings_Saved_Successfully']);
}

/** Convert a PHPNuxBill OMR price string to integer baisa. */
function thawani_to_baisa($price)
{
    return (int) round(floatval($price) * 1000);
}

function thawani_create_transaction($trx, $user)
{
    global $config;

    $amount = thawani_to_baisa($trx['price']);
    if ($amount < THAWANI_MIN_BAISA) {
        r2(U . 'order/view/' . $trx['id'], 'e', Lang::T("Amount is below the minimum accepted by Thawani."));
    }

    $json = [
        'client_reference_id' => (string) $user['id'],
        'mode' => 'payment',
        'products' => [
            [
                'name' => $trx['plan_name'],
                'quantity' => 1,
                'unit_amount' => $amount, // baisa
            ]
        ],
        'success_url' => U . 'order/view/' . $trx['id'] . '/check',
        'cancel_url' => U . 'order/view/' . $trx['id'] . '/check',
        'metadata' => [
            'Customer name' => $user['fullname'],
            'order id' => $trx['id'],
        ],
    ];

    $headers = [
        'thawani-api-key: ' . $config['thawani_secret_key'],
        'Content-Type: application/json',
    ];

    $response = Http::postJsonData(thawani_get_server() . '/checkout/session', $json, $headers);
    $result = json_decode($response, true);

    // 2004 = session created successfully
    if (!is_array($result) || ($result['code'] ?? null) != 2004 || empty($result['data']['session_id'])) {
        $why = is_array($result) && !empty($result['description']) ? $result['description'] : 'Failed to create transaction.';
        r2(U . 'order/view/' . $trx['id'], 'e', Lang::T($why));
    }

    $d = ORM::for_table('tbl_payment_gateway')
        ->where('username', $user['username'])
        ->where('status', 1)
        ->find_one();
    if (!$d) {
        r2(U . 'order/package', 'e', Lang::T("Pending transaction not found."));
    }

    $sessionId = $result['data']['session_id'];
    $d->gateway_trx_id = $sessionId;
    $d->pg_url_payment = thawani_checkout_url() . $sessionId . '?key=' . $config['thawani_publishable_key'];
    $d->pg_request = json_encode($result);
    if (!empty($result['data']['expiry_date'])) {
        $d->expired_date = date('Y-m-d H:i:s', strtotime($result['data']['expiry_date']));
    }
    $d->save();

    header('Location: ' . $d->pg_url_payment);
    exit();
}

function thawani_get_status($trx, $user)
{
    global $config;

    // Already settled — don't re-query.
    if ($trx['status'] == 2) {
        r2(U . "order/view/" . $trx['id'], 's', Lang::T("Transaction has been paid."));
    }

    $url = thawani_get_server() . '/checkout/session/' . $trx['gateway_trx_id'];
    $headers = [
        'thawani-api-key: ' . $config['thawani_secret_key'],
        'Content-Type: application/json',
    ];

    $result = json_decode(Http::getData($url, $headers), true);
    $status = $result['data']['payment_status'] ?? null;

    if ($status === null) {
        r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Could not reach Thawani, please try again."));
    }

    if ($status == 'paid') {
        if (!Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'], 'Thawani')) {
            r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Failed to activate your Package, try again later."));
        }
        thawani_mark($trx, $result, 2);
        r2(U . "order/view/" . $trx['id'], 's', Lang::T("Transaction has been paid."));
    } elseif ($status == 'cancelled') {
        thawani_mark($trx, $result, 4);
        r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Transaction is cancelled."));
    } elseif ($status == 'unpaid') {
        thawani_mark($trx, $result, 1);
        r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Transaction is still unpaid."));
    } else {
        thawani_mark($trx, $result, 3);
        r2(U . "order/view/" . $trx['id'], 'd', Lang::T($result['description'] ?? 'Transaction failed.'));
    }
}

/** Persist transaction status + the raw gateway response. */
function thawani_mark($trx, $result, $status)
{
    $trx->pg_paid_response = json_encode($result);
    $trx->payment_method = 'Thawani';
    $trx->payment_channel = 'Thawani';
    if ($status == 2 && !empty($result['data']['created_at'])) {
        $trx->paid_date = date('Y-m-d H:i:s', strtotime($result['data']['created_at']));
    }
    $trx->status = $status;
    $trx->save();
}

/**
 * Webhook callback. We never trust the POST body for the outcome: we look up
 * the transaction by the session id it carries, then RE-QUERY Thawani for the
 * authoritative status before granting anything.
 */
function thawani_payment_notification()
{
    global $config;
    header("Content-Type: application/json");

    $data = file_get_contents('php://input');
    if (empty($data)) {
        die(json_encode(['status' => 'no data received']));
    }

    $json = json_decode($data, true);
    if (empty($json['id'])) {
        die(json_encode(['status' => 'invalid payload']));
    }

    $trx = ORM::for_table('tbl_payment_gateway')->where('gateway_trx_id', $json['id'])->find_one();
    if (!$trx && !empty($json['external_id'])) {
        $trx = ORM::for_table('tbl_payment_gateway')->find_one($json['external_id']);
    }
    if (!$trx) {
        die(json_encode(['status' => 'error', 'message' => 'Transaction not found.']));
    }

    // Already settled — acknowledge without re-processing (idempotent).
    if ($trx['status'] == 2) {
        die(json_encode(['status' => 'ok', 'message' => 'already paid']));
    }

    $user = ORM::for_table('tbl_customers')->where('username', $trx['username'])->find_one();
    $result = json_decode(Http::getData(thawani_get_server() . '/checkout/session/' . $trx['gateway_trx_id'], [
        'thawani-api-key: ' . $config['thawani_secret_key'],
        'Content-Type: application/json',
    ]), true);

    $status = $result['data']['payment_status'] ?? null;
    $msg = '';

    if ($status == 'paid' && $user) {
        if (Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'], 'Thawani')) {
            thawani_mark($trx, $result, 2);
            $msg = 'paid';
        } else {
            thawani_mark($trx, $result, 3);
            $msg = 'Failed to activate package';
        }
    } elseif ($status == 'cancelled') {
        thawani_mark($trx, $result, 4);
        $msg = 'cancelled';
    } elseif ($status == 'unpaid') {
        thawani_mark($trx, $result, 1);
        $msg = 'unpaid';
    } else {
        $msg = 'status not actionable';
    }

    die(json_encode(['status' => 'ok', 'id' => $json['id'], 'message' => $msg]));
}

/** Resolve the API base URL for the configured stage (no global side effects). */
function thawani_get_server()
{
    global $config;
    $stage = $config['thawani_stage'] ?? 'Testing';
    return ($stage == 'Live') ? $config['thawani_live_url'] : $config['thawani_testing_url'];
}

/** Build the hosted-checkout URL from the API base URL. */
function thawani_checkout_url()
{
    return str_replace('/api/v1', '/pay/', thawani_get_server());
}
