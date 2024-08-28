<?php

use Lelevar\Sms\SmsService;

if (!function_exists('LelevarSendSms')) {
    /**
     * Send SMS using the Lelevar SMS service.
     *
     * @param array $params
     * @return array
     */
    function LelevarSendSms(array $params)
    {
        // Resolve the SmsService from the Laravel service container
        $smsService = app(SmsService::class);
        // Send the SMS and return the response
        return $smsService->sendSms($params);
    }
}
