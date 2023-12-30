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
 */

declare(strict_types=1);

use Hybula\WHMCS\CoinifyHelper;
use WHMCS\Config\Setting;
use WHMCS\Database\Capsule;

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

require_once __DIR__.'/hybula_coinify/CoinifyHelper.php';

function hybula_coinify_MetaData()
{
    return array(
        'DisplayName' => 'Hybula Coinify Gateway',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

function hybula_coinify_config(): array
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'Hybula Coinify Gateway'
        ],
        'ApiKey' => [
            'FriendlyName' => 'API Key',
            'Type' => 'text',
            'Size' => '64',
            'Default' => 'production_ or sandbox_...',
            'Description' => '<br>Request API keys from support as mentioned <a href="https://coinify.readme.io/docs/authentication" target="_blank">here</a>.'
        ],
        'SharedSecret' => [
            'FriendlyName' => 'Shared Secret',
            'Type' => 'text',
            'Size' => '64',
            'Default' => sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0xffff)
            ),
            'Description' => '<br>Self generated UUID v4 as mentioned <a href="https://coinify.readme.io/docs/webhooks" target="_blank">here</a>. You may use the generated UUID from this input field.'
        ],
        'WebhookUrl' => [
            'FriendlyName' => 'Webhook URL<script>document.addEventListener("DOMContentLoaded", function(){ document.querySelector(\'[name="field[WebhookUrl]"]\').disabled = true; });</script>',
            'Type' => 'text',
            'Size' => '64',
            'Default' => Setting::getValue('SystemURL').'/modules/gateways/callback/hybula_coinify.php',
            'Description' => '<br>This is your webhook URL, copy this and provide it to Coinify support (this is not a setting).'
        ],
        'useInvoiceNum' => [
            'FriendlyName' => 'Invoice Conversion',
            'Type' => 'yesno',
            'Default' => false,
            'Description' => 'Convert Invoice ID to Number',
        ],
        'checkIpAddress' => [
            'FriendlyName' => 'Webhook IP Check',
            'Type' => 'yesno',
            'Default' => true,
            'Description' => 'Allow Coinify IPs only',
        ]
    ];
}

function hybula_coinify_link($params): string
{
    if (empty($params['ApiKey'])) {
        return '<div class="alert alert-danger" role="alert">There was an issue with the payment processor (API Key not set).</div>';
    }
    if (empty($params['SharedSecret'])) {
        return '<div class="alert alert-danger" role="alert">There was an issue with the payment processor (Shared Secret not set).</div>';
    }

    if (isset($_GET['hybula_coinify_status'])) {
        if ($_GET['hybula_coinify_status'] == 'success') {
            return '<div class="alert alert-success" role="alert">Payment process succeeded. It may take some time until this is fully processed.</div>';
        }
        if ($_GET['hybula_coinify_status'] == 'failure') {
            return '<div class="alert alert-danger" role="alert">Something went wrong with your payment, contact our support if this is unexpected. <a href="'.$params['returnurl'].'">Click here</a> to try again.</div>';
        }
    }

    if ($params['amount'] < 10.00) {
        return '<div class="alert alert-warning" role="alert">This gateway only supports payments larger than <strong>10.00</strong>.</div>';
    }

    if ($params['amount'] > 15000.00) {
        return '<div class="alert alert-warning" role="alert">This gateway only supports payments up to <strong>15,000.00</strong>.</div>';
    }

    $invoice = (string)$params['invoiceid'];
    if ($params['useInvoiceNum']) {
        $findInvoice = Capsule::table('tblinvoices')->where('id', $params['invoiceid'])->value('invoicenum');
        if ($findInvoice) {
            $invoice = (string)$findInvoice;
        }
    }

    try {
        $coinify = new CoinifyHelper($params['ApiKey']);
        $paymentUrl = $coinify->paymentIntent(
            (float)$params['amount'],
            $params['currency'],
            $invoice,
            (string)$params['clientdetails']['owner_user_id'],
            $params['clientdetails']['email'],
            $params['returnurl'].'&hybula_coinify_status=success',
            $params['returnurl'].'&hybula_coinify_status=failure'
        );
        logTransaction('hybula_coinify', json_encode(['invoice' => $invoice, 'api' => $coinify->lastResponse]), 'Successful');
    } catch (\Exception $e) {
        logTransaction('hybula_coinify', json_encode(['invoice' => $invoice, 'api' => $coinify->lastResponse]), 'Unsuccessful');
        return $e->getMessage();
    }

    return '<form method="GET" action="'.$paymentUrl.'"><input type="submit" class="btn btn-primary" value="Pay Now"></form>';
}
