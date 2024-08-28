<?php

namespace Lelevar\Sms;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class SmsService
{
    protected $apiUrl = "https://hub.sms.lelevar.com/api";
    protected $apiKey;
    protected $apiSenderName;

    public function __construct($apiKey = null)
    {
        $this->apiKey = $apiKey ?: getenv('LELEVAR_SMS_API_KEY');
        $this->apiSenderName = getenv('LELEVAR_SMS_SENDER_NAME') ?: null;
    }

    public function sendSms($params)
    {
        $client = new Client();
        try {
            $response = $client->post($this->apiUrl . "/sms/compose/new", [
                'json' => [
                    'compose_type' => $params['compose_type'] ?? 1,
                    'content' => $params['content'],
                    'mobile' => $params['mobile'],
                    'sender_name' => $params['sender_name'] ?? $this->apiSenderName,
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ],
            ]);
            return (object) ['success' => true, 'data' => json_decode($response->getBody()->getContents(), true)];
        } catch (RequestException $e) {
            return (object) ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
