<?php
/**
 * Hybula Coinify WHMCS Gateway
 *
 * LICENSE: Licensed under the Apache License, Version 2.0 (the "License")
 * and the Commons Clause Restriction; you may not use this file except in
 * compliance with the License.
 *
 * @category   whmcs
 * @package    whmcs-coinify
 * @author     Hybula Development <development@hybula.com>
 * @copyright  2023 Hybula B.V.
 * @license    https://github.com/hybula/whmcs-coinify/blob/main/LICENSE.md
 * @link       https://github.com/hybula/whmcs-coinify
 * @see        https://coinify.readme.io/reference/payment-intent-resource#statereason
 * @see        https://coinify.readme.io/reference/payment-intent-api-webhook-notifications
 */

declare(strict_types=1);

use Hybula\WHMCS\CoinifyHelper;
use WHMCS\Database\Capsule;

require_once __DIR__.'/../../../init.php';
require_once __DIR__.'/../../../includes/gatewayfunctions.php';
require_once __DIR__.'/../../../includes/invoicefunctions.php';
require_once __DIR__.'/../hybula_coinify/CoinifyHelper.php';

const coinifyIpAddressList = [
    '34.240.144.187',
    '34.249.67.212',
    '34.251.158.97',
    '34.247.148.246',
    '34.247.148.57',
    '34.249.123.8',
];

if (isset($_SERVER['HTTP_X_COINIFY_WEBHOOK_SIGNATURE']) && strlen($_SERVER['HTTP_X_COINIFY_WEBHOOK_SIGNATURE']) == 64) {
    $gatewayParams = getGatewayVariables('hybula_coinify');
    if (!$gatewayParams['type']) {
        exit;
    }

    if ($gatewayParams['checkIpAddress'] && !in_array($_SERVER['REMOTE_ADDR'], coinifyIpAddressList)) {
        logTransaction('hybula_coinify', 'Unknown IP address from webhook: '.$_SERVER['REMOTE_ADDR'], 'Unsuccessful');
        exit;
    }

    try {
        $rawBody = file_get_contents('php://input');
        $bodyArray = json_decode($rawBody, true, 16, JSON_THROW_ON_ERROR);
        $coinify = new CoinifyHelper($gatewayParams['ApiKey']);
        $coinify->validateSignature($rawBody, $gatewayParams['SharedSecret'], $_SERVER['HTTP_X_COINIFY_WEBHOOK_SIGNATURE']);
        if ($gatewayParams['useInvoiceNum']) {
            $invoiceId = Capsule::table('tblinvoices')->where('invoicenum', $bodyArray['context']['orderId'])->value('id');
            $invoiceId = checkCbInvoiceID($invoiceId, 'hybula_coinify');
        } else {
            $invoiceId = checkCbInvoiceID($bodyArray['context']['orderId'], 'hybula_coinify');
        }
        checkCbTransID($bodyArray['id']);

        if ($bodyArray['event'] == 'payment-intent.completed' && $bodyArray['context']['state'] == 'completed' && $bodyArray['context']['stateReason'] == 'completed_overpaid') { // We should rely on stateReason, but there is no other way to detect overpayments;
            localAPI('SendAdminEmail', [
                'customsubject' => 'Coinify Overpayment',
                'custommessage' => 'The gateway could not process the payment for invoice ID '.$invoiceId.' because it received an overpayment of '.$bodyArray['context']['amount'].' '.$bodyArray['context']['currency'].'. Please check your gateway log for more information or log into your Coinify dashboard.',
                'type' => 'system'
            ]);
            throw new \Exception('Did not receive the exact amount, please check manually in your Coinify dashboard.');
        }

        if ($bodyArray['event'] == 'payment-intent.completed' && $bodyArray['context']['state'] == 'completed') {
            addInvoicePayment($invoiceId, $bodyArray['id'], '', 0, 'hybula_coinify');
            logTransaction('hybula_coinify', json_encode(['invoice' => $bodyArray['context']['orderId'], 'webhookIp' => $_SERVER['REMOTE_ADDR'], 'api' => $bodyArray]), 'Successful');
        }

        if ($bodyArray['event'] == 'payment-intent.failed') {
            throw new \Exception('PaymentIntent Failed ('.$bodyArray['context']['stateReason'].')');
        }

    } catch (\Exception $e) {
        logTransaction('hybula_coinify', json_encode(['invoice' => $bodyArray['context']['orderId'], 'webhookIp' => $_SERVER['REMOTE_ADDR'], 'api' => $bodyArray]), $e->getMessage());
    }
}
