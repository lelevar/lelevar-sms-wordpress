# Lelevar SMS Package for Laravel

The **Lelevar SMS Package** is a Laravel package for sending SMS messages via the Lelevar SMS API (https://sms.lelevar.com). This package simplifies SMS sending functionality in your Laravel application.

## Features
- Send single SMS messages
- Send multiple SMS messages
- Easy integration with Laravel
- Support for API key configuration

## Installation

### Via Composer
Install the package via Composer by running the following command in your Laravel project directory:

```bash
composer require lelevar/lelevar-sms-package
```

### Service Provider
If you are using Laravel 5.5 or later, the package will automatically be discovered. If you are using an earlier version of Laravel, you need to manually add the service provider to your config/app.php:

```php
'providers' => [
    // Other service providers...
    Lelevar\Sms\SmsServiceProvider::class,
],
```

### Facades (Optional)
For easier access to the package's functionality, you can add facades to your config/app.php:

```php
'aliases' => [
    // Other aliases...
    'SmsService' => Lelevar\Sms\Facades\SmsService::class,
],
```

### Configuration
The package uses an API key for authentication. Set your API key in your .env file:

```dotenv
LELEVAR_SMS_API_KEY=your_api_key_here
LELEVAR_SMS_SENDER_NAME=your_sender_name_here
```
#### Usage
##### Sending a Single SMS
You can send a single SMS message using the LelevarSendSms helper function:

```php
use Lelevar\Sms\Facades\SmsService;

$response = LelevarSendSms([
    'mobile' => '**********',
    'content' => 'Hello World!',
    'sender_name' => '*******',
]);

dd($response);
```

## License
This package is licensed under the MIT License.

