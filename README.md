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
 // StartSms::send($phone, $message[, $start, $validity, $sender])
$sms = StartSms::send('+380671234567', 'Oh hai');
if (!$sms->isOk()) {
    throw new Exception($sms->getError());
}

echo $sms->getID(); // 280323869812345468728

$xmlResponse = $sms->getRawResponse();
$arrayResponse = $sms->getResponse();

print_r($arrayResponse);
/*
Array
(
    [status] => Array
        (
            [@date] => Thu, 26 Feb 2015 09:42:53 +0200
            [id] => 280323869812345468728
            [state] => Accepted
        )

)
*/

```