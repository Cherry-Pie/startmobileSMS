<?php 

namespace Yaro\StartSms\Facades;
 
use Illuminate\Support\Facades\Facade;

 
class StartSms extends Facade 
{
 
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor() 
    {
        return 'startsms'; 
    } // end getFacadeAccessor
 
}