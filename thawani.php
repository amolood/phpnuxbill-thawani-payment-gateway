<?php

/**
 * PHP Mikrotik Billing (https://github.com/hotspotbilling/phpnuxbill/)
 *
 * Payment Gateway thawani.om
 * by https://amolood.com
 **/

function thawani_validate_config()
{
    global $config;
    if (empty($config['thawani_publishable_key']) || empty($config['thawani_secret_key']) || empty($config['thawani_testing_url']) || empty($config['thawani_live_url'])) {
        r2(U . 'order/package', 'w', Lang::T("Admin has not yet setup Thawani payment gateway, please tell admin"));
    }
}

function thawani_show_config()
{
    global $ui, $config;
    $ui->assign('_title', 'Thawani - Payment Gateway');
    $ui->display('thawani.tpl');
}

function thawani_save_config()
{
    global $admin, $_L;
    $thawani_publishable_key = _post('thawani_publishable_key');
    $thawani_secret_key = _post('thawani_secret_key');
    $thawani_live_url = _post('thawani_live_url');
    $thawani_testing_url = _post('thawani_testing_url');
    $thawani_stage = _post('thawani_stage');

    // Save thawani_publishable_key
    $d = ORM::for_table('tbl_appconfig')->where('setting', 'thawani_publishable_key')->find_one();
    if ($d) {
        $d->value = $thawani_publishable_key;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'thawani_publishable_key';
        $d->value = $thawani_publishable_key;
        $d->save();
    }

    // Save thawani_secret_key
    $d = ORM::for_table('tbl_appconfig')->where('setting', 'thawani_secret_key')->find_one();
    if ($d) {
        $d->value = $thawani_secret_key;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'thawani_secret_key';
        $d->value = $thawani_secret_key;
        $d->save();
    }

    // Save thawani_live_url
    $d = ORM::for_table('tbl_appconfig')->where('setting', 'thawani_live_url')->find_one();
    if ($d) {
        $d->value = $thawani_live_url;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'thawani_live_url';
        $d->value = $thawani_live_url;
        $d->save();
    }
    $d = ORM::for_table('tbl_appconfig')->where('setting', 'thawani_stage')->find_one();
    if ($d) {
        $d->value = $thawani_stage;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'thawani_stage';
        $d->value = $thawani_stage;
        $d->save();
    }

    // Save thawani_testing_url
    $d = ORM::for_table('tbl_appconfig')->where('setting', 'thawani_testing_url')->find_one();
    if ($d) {
        $d->value = $thawani_testing_url;
        $d->save();
    } else {
        $d = ORM::for_table('tbl_appconfig')->create();
        $d->setting = 'thawani_testing_url';
        $d->value = $thawani_testing_url;
        $d->save();
    }

    _log('[' . $admin['username'] . ']: Thawani ' . $_L['Settings_Saved_Successfully'], 'Admin', $admin['id']);
    r2(U . 'paymentgateway/thawani', 's', $_L['Settings_Saved_Successfully']);
}


function thawani_create_transaction($trx, $user)
{
    global $config;
    $json = [
        'client_reference_id' => $user['id'],
        "mode" => "payment",
        "products" => [
            [
                "name" => $trx['plan_name'],
                "quantity" => 1,
                "unit_amount" => $trx['price']
            ]
        ],
        "success_url" => U . 'order/view/' . $trx['id'] . '/check',
        "cancel_url" => U . 'order/view/' . $trx['id'] . '/check',
        "metadata" => [
            "Customer name" => $user['fullname'],
            "order id" => $trx['id']
        ]
    ];

    $headers = [
        'thawani-api-key: ' . $config['thawani_secret_key'],
        'Content-Type: application/json'
    ];

    $response = Http::postJsonData(thawani_get_server() . '/checkout/session', $json, $headers);
    $result = json_decode($response, true);

    if (!isset($result['code']) || $result['code'] != 2004) {
        r2(U . 'order/package', 'e', Lang::T("Failed to create transaction."));
    }

    $d = ORM::for_table('tbl_payment_gateway')
        ->where('username', $user['username'])
        ->where('status', 1)
        ->find_one();
    $d->gateway_trx_id = $result['data']['session_id'];
    $d->pg_url_payment = thawani_checkout_url() . $result['data']['session_id'] . '?key=' . $config['thawani_publishable_key'];
    $d->pg_request = json_encode($result);
    $d->expired_date = date('Y-m-d H:i:s', strtotime($result['data']['expiry_date']));
    $d->save();

    header('Location: ' . $d->pg_url_payment);
    exit();
}

function thawani_get_status($trx, $user)
{
    global $config;
    $url = thawani_get_server() . '/checkout/session/' . $trx['gateway_trx_id'];

    $headers = [
        'thawani-api-key: ' . $config['thawani_secret_key'],
        'Content-Type: application/json'
    ];

    $response = Http::getData($url, $headers);
    $result = json_decode($response, true);

    if ($trx['status'] == 2) {
        r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Transaction has been paid."));
    } elseif ($result['data']['payment_status'] == 'paid') {
        if (!Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'], 'Thawani')) {
            r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Failed to activate your Package, try again later."));
        }

        $trx->pg_paid_response = json_encode($result);
        $trx->payment_method = 'Thawani';
        $trx->payment_channel = 'Thawani';
        $trx->paid_date = date('Y-m-d H:i:s', strtotime($result['data']['created_at']));
        $trx->status = 2;
        $trx->save();

        r2(U . "order/view/" . $trx['id'], 's', Lang::T("Transaction has been paid."));
    } elseif ($result['data']['payment_status'] == 'unpaid') {
        $trx->pg_paid_response = json_encode($result);
        $trx->status = 1;
        $trx->save();
        r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Transaction expired."));
    } elseif ($result['data']['payment_status'] == 'cancelled') {
        $trx->pg_paid_response = json_encode($result);
        $trx->status = 4;
        $trx->save();
        r2(U . "order/view/" . $trx['id'], 'd', Lang::T("Transaction is cancelled."));
    } else {
        $trx->pg_paid_response = json_encode($result);
        $trx->status = 3;
        $trx->save();
        r2(U . "order/view/" . $trx['id'], 'd', Lang::T($result['description']));
    }
}

// Callback
function thawani_payment_notification()
{
    global $config;
    $data = file_get_contents('php://input');
    header("Content-Type: application/json");

    if (!empty($data)) {
        $json = json_decode($data, true);
        $msg = '';

        if (!empty($json['id'])) {
            $trx = ORM::for_table('tbl_payment_gateway')
                ->where('gateway_trx_id', $json['id'])
                ->find_one();

            if (!$trx) {
                $trx = ORM::for_table('tbl_payment_gateway')->find_one($json['external_id']);
            }

            if ($trx) {
                $user = ORM::for_table('tbl_customers')->where('username', $trx['username'])->find_one();
                $result = json_decode(Http::getData(thawani_get_server() . '/checkout/session/' . $trx['gateway_trx_id'], [
                    'thawani-api-key: ' . $config['thawani_secret_key'],
                    'Content-Type: application/json'
                ]), true);
                // 1 unpaid 2 paid 3 failed 4 canceled
                if ($result['data']['payment_status'] == 'paid') {
                    if (Package::rechargeUser($user['id'], $trx['routers'], $trx['plan_id'], $trx['gateway'], 'Thawani')) {
                        $trx->pg_paid_response = json_encode($result);
                        $trx->payment_method = 'Thawani';
                        $trx->payment_channel = 'Thawani';
                        $trx->paid_date = date('Y-m-d H:i:s', strtotime($result['data']['created_at']));
                        $trx->status = 2;
                        $trx->save();
                    }elseif ($result['data']['payment_status'] == 'unpaid'){
                        $trx->pg_paid_response = json_encode($result);
                        $trx->payment_method = 'Thawani';
                        $trx->payment_channel = 'Thawani';
                        $trx->paid_date = date('Y-m-d H:i:s', strtotime($result['data']['created_at']));
                        $trx->status = 1;
                        $trx->save();
                    }elseif ($result['data']['payment_status'] == 'cancelled'){
                        $trx->pg_paid_response = json_encode($result);
                        $trx->payment_method = 'Thawani';
                        $trx->payment_channel = 'Thawani';
                        $trx->paid_date = date('Y-m-d H:i:s', strtotime($result['data']['created_at']));
                        $trx->status = 4;
                        $trx->save();
                    } else {
                        $trx->pg_paid_response = json_encode($result);
                        $trx->payment_method = 'Thawani';
                        $trx->payment_channel = 'Thawani';
                        $trx->paid_date = date('Y-m-d H:i:s', strtotime($result['data']['created_at']));
                        $trx->status = 3;
                        $trx->save();
                        $msg = 'Failed to activate package';
                    }
                } else {
                    $msg = 'Status not paid';
                }
            } else {
                $msg = 'Transaction not found.';
            }
        }
        die(json_encode(['status' => $json['status'], 'id' => $json['id'], 'message' => $msg]));
    } else {
        die(json_encode(['status' => 'no data received']));
    }
}

function thawani_get_server()
{
    global $_app_stage, $config;
    $stage = orm::for_table('tbl_appconfig')->where('setting', 'thawani_stage')->find_one();
    $_app_stage = $stage->value;
    if ($_app_stage == 'Live') {
        return $config['thawani_live_url'];
    } else {
        return $config['thawani_testing_url'];
    }
}

function thawani_checkout_url()
{
    return str_replace('/api/v1', '/pay/', thawani_get_server());

}

