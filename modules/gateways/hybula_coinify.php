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
 * @copyright  1997-2005 The PHP Group
 * @license    https://github.com/hybula/whmcs-coinify/blob/main/LICENSE.md
 * @link       https://github.com/hybula/whmcs-coinify
 */


declare(strict_types=1);

use Hybula\WHMCS\CoinifyHelper;
use WHMCS\Config\Setting;

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
            'Description' => '<br>Request API keys from support as mentioned <a href="https://coinify.readme.io/docs/authentication" target="_blank">here</a>.',
        ],
        'SharedSecret' => [
            'FriendlyName' => 'Shared Secret',
            'Type' => 'text',
            'Size' => '64',
            'Default' =>  sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)),
            'Description' => '<br>Self generated UUID v4 as mentioned <a href="https://coinify.readme.io/docs/webhooks" target="_blank">here</a>. You may use the generated UUID from this input field.'
        ],
        'WebhookUrl' => [
            'FriendlyName' => 'Webhook URL',
            'Type' => 'text',
            'Size' => '64',
            'Default' =>  Setting::getValue('SystemURL').'/modules/gateways/callback/hybula_coinify.php',
            'Description' => '<br>This is your webhook URL, copy this and provide it to Coinify support (this is not a setting).'
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

    if (!empty($_POST['hybula_coinify_pay'])) {
        try {
            $coinify = new CoinifyHelper($params['ApiKey']);
            $paymentUrl = $coinify->paymentIntent(
                (float)$params['amount'],
                $params['currency'],
                (string)$params['invoiceid'],
                (string)$params['clientdetails']['owner_user_id'],
                $params['clientdetails']['email'],
                $params['returnurl'].'&hybula_coinify_status=success',
                $params['returnurl'].'&hybula_coinify_status=failure'
            );
            header('Location: '.$paymentUrl);
            exit;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    return '<form method="POST"><input type="submit" name="hybula_coinify_pay" class="btn btn-primary" value="Pay Now"></form>';
}
