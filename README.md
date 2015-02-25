StartMobile SMS
========

startmobile.ua api package for laravel 4

========

In app/config/app.php:

```php
'Yaro\StartSms\StartSmsServiceProvider',
//...
'StartSms' => 'Yaro\StartSms\Facades\StartSms',
```

========

**For single sms:**
```php
 // StartSms::send($phone, $message[, $sender])
$sms = StartSms::send('+380671234567', 'Oh hai');
if (!$sms->isOk()) {
    echo $sms->getError();
}

// $response = $sms->getRawResponse();
// $response = $sms->getResponse();
```