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

namespace Hybula\WHMCS;

/**
 * This is a simple helper class with bare minimum functionality for PaymentIntents.
 * Subject to change e.g. add more functionality.
 */
class CoinifyHelper
{
    /**
     * @var string Coinify API key.
     */
    private string $apiKey;

    /**
     * @var string|array Last error stored.
     */
    public string|array $lastResponse;

    /**
     * @param  string  $apiKey Coinify API key starting with either production_ or sandbox_.
     */
    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * @param  float  $amount Amount for payment.
     * @param  string  $currency ISO currency code.
     * @param  string  $orderId Order ID (or we use invoice ID for this).
     * @param  string  $customerId Customer ID, not to be confused with User ID in WHMCS.
     * @param  string  $customerEmail Customer email address, this is used for potential refunds.
     * @param  string  $successUrl Success redirection URL, we set this automatically.
     * @param  string  $failureUrl Failure redirection URL, we set this automatically.
     * @return string Returns the paymentWindowUrl, which is the hosted payment checkout.
     * @throws \Exception
     */
    public function paymentIntent(float $amount, string $currency, string $orderId, string $customerId, string $customerEmail, string $successUrl, string $failureUrl): string
    {
        $postFields = [
            'amount' => $amount,
            'currency' => $currency,
            'orderId' => $orderId,
            'customerId' => $customerId,
            'customerEmail' => $customerEmail,
            'pluginIdentifier' => 'Hybula Coinfy WHMCS Integration',
            'successUrl' => $successUrl,
            'failureUrl' => $failureUrl
        ];
        if (str_contains($this->apiKey, 'production_')) {
            $curlHandle = curl_init('https://api.payment.coinify.com/v1/payment-intents');
        } else {
            $curlHandle = curl_init('https://api.payment.sandbox.coinify.com/v1/payment-intents');
        }
        curl_setopt($curlHandle, CURLOPT_POSTFIELDS, json_encode($postFields));
        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            'content-type: application/json',
            'X-API-KEY: '.$this->apiKey
        ]);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        $curlResponse = curl_exec($curlHandle);
        $curlCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        $curlResponseArray = json_decode($curlResponse, true);
        curl_close($curlHandle);
        $this->lastResponse = $curlResponseArray;
        if ($curlCode == 201 && isset($curlResponseArray['paymentWindowUrl'])) {
            return $curlResponseArray['paymentWindowUrl'];
        } else {
            throw new \Exception('There was an issue with the payment processor (Error code received).');
        }
    }

    /**
     * @param  string  $rawBody The raw POST body which is used to generate a HMAC hash.
     * @param  string  $sharedSecret Your secret UUID v4.
     * @param  string  $headerSignature Signature received from Coinify webhook.
     * @return true Returns true or an exception if signature is invalid.
     * @throws \Exception
     */
    public function validateSignature(string $rawBody, string $sharedSecret, string $headerSignature): bool
    {
        $generatedSignature = strtolower(hash_hmac('sha256', $rawBody, $sharedSecret, false));
        if ($generatedSignature !== $headerSignature) {
            throw new \Exception('Invalid signature received.');
        }
        return true;
    }
}
