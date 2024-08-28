<?php

namespace Lelevar\Sms\Facades;

use Illuminate\Support\Facades\Facade;

class SmsService extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'sms';
    }
}
