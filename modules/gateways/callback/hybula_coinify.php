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

require_once __DIR__.'/../../../init.php';
require_once __DIR__.'/../../../includes/gatewayfunctions.php';
require_once __DIR__.'/../../../includes/invoicefunctions.php';
require_once __DIR__.'/../hybula_coinify/CoinifyHelper.php';

if (isset($_SERVER['HTTP_X_COINIFY_WEBHOOK_SIGNATURE']) && strlen($_SERVER['HTTP_X_COINIFY_WEBHOOK_SIGNATURE']) == 64) {
    $gatewayParams = getGatewayVariables('hybula_coinify');
    if (!$gatewayParams['type']) {
        exit;
    }
    $rawBody = file_get_contents('php://input');
    $bodyArray = json_decode($rawBody, true);
    try {
        $coinify = new CoinifyHelper($gatewayParams['ApiKey']);
        $coinify->validateSignature($rawBody, $gatewayParams['SharedSecret'], $_SERVER['HTTP_X_COINIFY_WEBHOOK_SIGNATURE']);
        $invoiceId = checkCbInvoiceID($bodyArray['context']['orderId'], 'hybula_coinify');
        if ($bodyArray['context']['stateReason'] != 'completed_exact_amount') {
            localAPI('SendAdminEmail', [
                'customsubject' => 'Coinify Payment Failed',
                'custommessage' => 'The gateway could not process this payment because it did not match the "completed_exact_amount" state. Please check your gateway log for more information.',
                'type' => 'system'
            ]);
            throw new \Exception('Did not receive the exact amount, please check manually in your Coinify dashboard.');
        }
        addInvoicePayment($invoiceId, $bodyArray['id'], '', 0, 'hybula_coinify');
        logTransaction('hybula_coinify', $rawBody, 'Successful');
    } catch (\Exception $e) {
        logTransaction('hybula_coinify', $e->getMessage().' '.$rawBody, 'Unsuccessful');
    }
}
