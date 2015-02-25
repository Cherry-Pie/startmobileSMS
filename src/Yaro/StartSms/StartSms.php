<?php

namespace Yaro\StartSms;

use Illuminate\Support\Facades\Config;
use Yaro\StartSms\Exceptions\StartSmsException;


class StartSms 
{

    const ACCEPTED_STATE      = 'Accepted'; // сообщение принято IP2SMS-платформой, но попытка доставки еще не предпринималось
    const ENROUTE_STATE       = 'Enroute'; // предпринимаются попытки доставить сообщение, однако, оно еще не доставлено
    const DELIVERED_STATE     = 'Delivered'; // сообщение доставлено получателю
    const EXPIRED_STATE       = 'Expired'; // исчерпан лимит времени на попытки доставить сообщение; последующие попытки предприниматься не будут
    const DELETED_STATE       = 'Deleted'; // сообщение принудительно удалено из системы администратором
    const UNDELIVERABLE_STATE = 'Undeliverable'; // сообщение по тем или иным причинам не может быть доставлено получателю (например, попытка доставить на несуществующий телефонный номер)
    const REJECTED_STATE      = 'Rejected'; // сообщение отвергнуто из-за ошибок в нем (нарушение формата, попытка отправить сообщение пределы украинских операторов и т.п.)
    const UNKNOWN_STATE       = 'Unknown'; // состояние сообщения неизвестно.

    private $apiUrl   = ' http://bulk.startmobile.com.ua/clients.php';
    private $login    = '';
    private $password = '';
    private $sender   = '';
    
    private $rawReponse;
    private $response;
    private $status;
    private $lastError;
    
    public function __construct()
    {
        $this->login    = Config::get('start-sms::login');
        $this->password = Config::get('start-sms::password');
        $this->sender   = Config::get('start-sms::sender');
    } // end __construct

    public function send($phone, $message, $sender = false)
    {
        $xml = new \SimpleXMLElement('<message/>');
        $service = $xml->addChild('service');
        $service->addAttribute('id', 'single');
        $service->addAttribute('source', $this->getSender($sender));
        
        $xml->addChild('to', $this->onPhoneCheck($phone));
        
        $body = $xml->addChild('body', $message);
        $body->addAttribute('content-type', 'text/plain');
        
        $this->rawResponse = $this->doSendXML($xml->asXML());
        $this->response    = $this->doPrepareSingleResponse($response);
        
        return $this;
    } // end send
    
    private function getSender($sender)
    {
        if ($sender) {
            return $sender;
        }
        
        if (!$this->sender) {
            throw new StartSmsException('StartSms: sender name is required.');
        }
        
        return $this->sender;
    } // end getSender
    
    public function isOk()
    {
        $status = $this->response['status']['state'];
        if (isset($this->response['status']['state']['$'])) {
            $status = $this->response['status']['state']['$'];
        }
        
        return $status === self::ACCEPTED_STATE
            || $status === self::ENROUTE_STATE
            || $status === self::DELIVERED_STATE;
    } // end isOk
    
    public function getError()
    {
        $error = 'No error provided';
        if (isset($this->response['status']['state']['@error'])) {
            $error = $this->response['status']['state']['@error'];
        }
        
        return $error;
    } // end getError
    
    public function getRawResponse()
    {
        return $this->rawResponse;
    } // end getRawResponse
    
    public function getResponse()
    {
        return $this->response;
    } // end getResponse
    
    private function doPrepareSingleResponse($response)
    {
        $xml = simplexml_load_string($response);
        return $this->doConvertXmlToArray($xml);
    } // end doPrepareSingleResponse
    
    public function doConvertXmlToArray($xml)
    {
        $options = array();
        $defaults = array(
            'namespaceSeparator' => ':',//you may want this to be something other than a colon
            'attributePrefix' => '@',   //to distinguish between attributes and nodes with the same name
            'alwaysArray' => array(),   //array of xml tag names which should always become arrays
            'autoArray' => true,        //only create arrays for tags which appear more than once
            'textContent' => '$',       //key used for the text content of elements
            'autoText' => true,         //skip textContent key if node has no attributes or child nodes
            'keySearch' => false,       //optional search and replace on tag and attribute names
            'keyReplace' => false       //replace values for above search values (as passed to str_replace())
        );
        $options = array_merge($defaults, $options);
        $namespaces = $xml->getDocNamespaces();
        $namespaces[''] = null; //add base (empty) namespace
     
        //get attributes from all namespaces
        $attributesArray = array();
        foreach ($namespaces as $prefix => $namespace) {
            foreach ($xml->attributes($namespace) as $attributeName => $attribute) {
                //replace characters in attribute name
                if ($options['keySearch']) $attributeName =
                        str_replace($options['keySearch'], $options['keyReplace'], $attributeName);
                $attributeKey = $options['attributePrefix']
                        . ($prefix ? $prefix . $options['namespaceSeparator'] : '')
                        . $attributeName;
                $attributesArray[$attributeKey] = (string)$attribute;
            }
        }
     
        //get child nodes from all namespaces
        $tagsArray = array();
        foreach ($namespaces as $prefix => $namespace) {
            foreach ($xml->children($namespace) as $childXml) {
                //recurse into child nodes
                $childArray = $this->doConvertXmlToArray($childXml, $options);
                list($childTagName, $childProperties) = each($childArray);
     
                //replace characters in tag name
                if ($options['keySearch']) $childTagName =
                        str_replace($options['keySearch'], $options['keyReplace'], $childTagName);
                //add namespace prefix, if any
                if ($prefix) $childTagName = $prefix . $options['namespaceSeparator'] . $childTagName;
     
                if (!isset($tagsArray[$childTagName])) {
                    //only entry with this key
                    //test if tags of this type should always be arrays, no matter the element count
                    $tagsArray[$childTagName] =
                            in_array($childTagName, $options['alwaysArray']) || !$options['autoArray']
                            ? array($childProperties) : $childProperties;
                } elseif (
                    is_array($tagsArray[$childTagName]) && array_keys($tagsArray[$childTagName])
                    === range(0, count($tagsArray[$childTagName]) - 1)
                ) {
                    //key already exists and is integer indexed array
                    $tagsArray[$childTagName][] = $childProperties;
                } else {
                    //key exists so convert to integer indexed array with previous value in position 0
                    $tagsArray[$childTagName] = array($tagsArray[$childTagName], $childProperties);
                }
            }
        }
     
        //get text content of node
        $textContentArray = array();
        $plainText = trim((string)$xml);
        if ($plainText !== '') $textContentArray[$options['textContent']] = $plainText;
     
        //stick it all together
        $propertiesArray = !$options['autoText'] || $attributesArray || $tagsArray || ($plainText === '')
                ? array_merge($attributesArray, $tagsArray, $textContentArray) : $plainText;
     
        //return node as array
        return array(
            $xml->getName() => $propertiesArray
        );
    } // end doConvertXmlToArray
    
    private function doSendXML($xml)
    {
        $credentials = sprintf(
            'Authorization: Basic %s', 
            base64_encode($this->login .":". $this->password)
        );
        $params = array(
            'http' => array(
                'method'  => 'POST',
                'content' => $xml, 
                'header'  => $credentials
            )
        );
        $context = stream_context_create($params);
        
        // suppress warning to handle it
        $fp = @fopen($this->apiUrl, 'rb', false, $context);
        if ($fp) {
            $response = stream_get_contents($fp);
        } else {
            throw new StartSmsException('StartSMS: unable to connect.');
        }
    
        return $response;
    } // end doSendXML
    
    private function onPhoneCheck($phone)
    {
        if (!preg_match('~^\+38\d{10}$~', $phone)) {
            throw new StartSmsException('StartSMS: invalid phone format.');
        }
        
        return $phone;
    } // end onPhoneCheck
    
}

