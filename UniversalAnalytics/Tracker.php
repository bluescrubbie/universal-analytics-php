<?php
/**
 * A lightweight server-side Universal Analytics tracking using the Google Analytics Measurement Protocol (https://developers.google.com/analytics/devguides/collection/protocol/v1/).
 * Uses socket posts to ignore responses (and increase speed)
 *
 * This is a heavily modified fork of Universal Analytics for PHP (Copyright (c) 2013, Analytics Pros) (https://github.com/analytics-pros/universal-analytics-php/blob/master/universal-analytics.php)
 *
 * This project is free software, distributed under the BSD license.
 */

/**
 * Class UniversalAnalytics_Tracker
 * Constructs and Sends server-side Google Universal Analytics hits
 */
class UniversalAnalytics_Tracker {
    const VERSION = 1;
    private $accountId = null;
	private $clientId = null;
	private $userId = null;
    private $userAgent = null;
	private $ignoreInvalidParams = true;
    private $validKeys = null;
	private $state = null;
    private $endpoint = 'https://www.google-analytics.com/collect';
    private $currentUrl = null;

    /**
     * @param $accountId
     * @param bool $ignoreInvalidParams
     */
    public function __construct($accountId, $ignoreInvalidParams = true) {
        $this->accountId = $accountId;
	    $this->ignoreInvalidParams = $ignoreInvalidParams;

        $this->validKeys = array_flip(self::$nameMap) + self::$nameMap;

	    $this->initialize();
        return $this;
    }

    /**
     * @return string Account ID
     */
    public function getAccountId() {
        return $this->accountId;
    }

    /**
     * @param $clientId
     * @param bool $hashId  hash a non-null ID to a UUID
     * @return $this
     */
    public function setClientId($clientId, $hashId = true) {
        if($hashId) {
            $this->clientId = $this->hash_uuid($clientId);
        } else {
            $this->clientId = $clientId;
        }
        return $this;
    }

    /**
     * @return string Client ID
     */
    public function getClientId() {
        if(is_null($this->clientId)) {
            if (isset($_COOKIE['_ga'])) {
                list($version, $domainDepth, $cid1, $cid2) = explode('.', $_COOKIE["_ga"],4);
                $this->clientId = $cid1.'.'.$cid2;
            } else {
                $this->clientId = $this->generateUUID4();
            }
        }
        return $this->clientId;
    }

    /**
     * @param $userId
     * @param bool $hashId  hash a non-null ID to a UUID
     * @return $this
     */
    public function setUserId($userId, $hashId = true){
        $this->userId = is_null($userId) ?
            null :
            ($hashId ?
                $this->hash_uuid($userId) :
                $this->userId = $userId);

        return $this;
    }

    /**
     * @return string User ID
     */
    public function getUserId() {
        return $this->userId;
    }

    /**
     * @param $userAgent
     * @return $this
     */
    public function setUserAgent($userAgent) {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * @param array() $items
     * @return UniversalAnalytics_Tracker $this
     */
    public function setValues($items) {
        foreach($items as $name => $value) {
            $this->setValue($name, $value);
        }
        return $this;
    }

    /**
     * @param string $name
     * @param string $value
     * @return UniversalAnalytics_Tracker $this
     */
    public function setValue($name, $value) {
        $this->state[ $name ] = $value;
        return $this;
    }

    /**
     * @param string $name
     * @return string value
     */
    public function getValue($name) {
        if(array_key_exists($name, $this->state)) {
            return $this->state[ $name ];
        } else {
            return null;
        }
    }

    /**
     * (Re)initializes object, clearing data values, but preserving any 'global' UA data (Account ID, Client ID, User ID, and User Agent).
     * @return $this
     */
    public function initialize() {
	    $this->state = array();
        return $this;
    }

    /**
     * @param null $hitType
     * @param bool $log
     * @param bool $waitForResponse
     * @return bool
     */
    public function send($hitType = null, $log = false, $waitForResponse = false) {
        if(in_array($hitType, self::$hitTypes)) {
            $this->state = array(
                    't' => $hitType,
                    'v' => self::VERSION,
                    'tid' => $this->getAccountId(),
                    'cid' => $this->getClientId(),
                    'uid' => $this->getUserId()
                ) + $this->state;
            $this->socketPost($log, $waitForResponse);
        } else {
            error_log(__CLASS__.'::'.__METHOD__.' - Invalid Hit Type: '.$hitType, 3);
            return false;
        }
    }

    /**
     * Constructs a name/value array of hit parameters, translating from 'friendly' names to url param names.
     *
     * @return array Params
     */
    protected function getParams() {
        $resultData = array();
        $resultKeysIn = $this->ignoreInvalidParams ? array_keys(array_intersect_key($this->validKeys, $this->state)) : array_keys($this->state);
        $resultKeys = str_replace(array_keys(self::$nameMap), array_values(self::$nameMap), $resultKeysIn);
        $resultKeys = preg_replace(array_keys(self::$nameMapRe), array_values(self::$nameMapRe), $resultKeys);
        for($i = 0; $i < count($resultKeysIn); $i++){
            $resultData[ $resultKeys[ $i ] ] = $this->state[ $resultKeysIn[ $i ] ];
        }
	    // add location if not present
	    if(!array_key_exists('dl', $resultData)) {
		    $resultData[ 'dl' ] = $this->getUrl();
	    }
	    // add referrer if not present
	    if(!array_key_exists('dr', $resultData)) {
		    $resultData[ 'dr' ] = array_key_exists('HTTP_REFERER', $_SERVER) ? $_SERVER['HTTP_REFERER'] : $this->getUrl();
	    }
	    // add cache buster param
	    $resultData[ 'z' ] = mt_rand();
        return $resultData;
    }

    protected function getUrl() {
        if(!$this->currentUrl) {
            $this->currentUrl  = @( $_SERVER["HTTPS"] != 'on' ) ? 'http://'.$_SERVER["SERVER_NAME"] :  'https://'.$_SERVER["SERVER_NAME"];
            $this->currentUrl .= ( $_SERVER["SERVER_PORT"] !== 80 ) ? ":".$_SERVER["SERVER_PORT"] : "";
            $this->currentUrl .= $_SERVER["REQUEST_URI"];
        }
      return $this->currentUrl;
    }

    /**
     * HTTP post directly via socket.  Allows for immediate close, disregarding response
     *
     * @param bool $log
     * @param bool $waitForResponse
     * @return bool
     */
    protected function socketPost($log = false, $waitForResponse = false)
    {
        // create POST string
        $params = $this->getParams();
        $post_params = array();
        foreach ($params as $key => &$val)
        {
            if(!empty($val)){
                $post_params[] = $key . '=' . urlencode($val);
            }
        }
        $post_string = implode('&', $post_params);

        // get URL segments
        $parts = parse_url($this->endpoint);

        // workout port and open socket
        $port = isset($parts['port']) ? $parts['port'] : 80;
        $success = $fp = fsockopen($parts['host'], $port, $errno, $errstr, 30);
        if($fp) {
            // create output string
            $output  = "POST " . $parts['path'] . " HTTP/1.1\r\n";
            if(is_string($this->userAgent)) $output .= "User-Agent: " . $this->userAgent . "\r\n";
            $output .= "Host: " . $parts['host'] . "\r\n";
            $output .= "Content-Type: application/x-www-form-urlencoded\r\n";
            $output .= "Content-Length: " . strlen($post_string) . "\r\n";
            $output .= "Connection: Close\r\n\r\n";
            $output .= isset($post_string) ? $post_string : '';

            // send output to endpoint handle
            $success = fwrite($fp, $output);

            if($waitForResponse) {
                $response = "";
                //Wait for the web server to send the full response. On every line returned we append it onto the $contents
                //variable which will store the whole returned request once completed.
                while (!feof($fp)) {
                    $response .= fgets($fp, 4096);
                }
            }

            fclose($fp);
        }

        if($waitForResponse) {
            if(!empty($response)){
                $output = "\n\nResponse:\n".$response;
            }
        }

        if($log) {
            error_log(__CLASS__.'::'.__METHOD__." - UA Tracking Post:\n".$output);
        }

        return $success ? true : false;
    }

    /**
     * Supported Hit Types
     * @var array
     */
    public static $hitTypes = array(
        'pageview',
        'appview',
        'event',
        'transaction',
        'item',
        'social',
        'exception',
        'timing');

    /**
     * Maps friendly names to Google Analytics Measurement Protocol parameters.  Note - multiple friendly names may map to the same parameter for legacy reasons
     * See: https://developers.google.com/analytics/devguides/collection/protocol/v1/devguide
     * @var array
     */
    public static $nameMap = array(
        'hitType' => 't',           // The type of hit.  Set in send.  See $hitTypes.
        'trackingId' => 'tid',      // The tracking ID / web property ID / Account ID. Set in Constructor. The format is UA-XXXX-Y. All collected data is associated by this ID.
        'webPropertyId' => 'tid',
        'AccountId' => 'tid',
	    'clientId' => 'cid',        // The Client ID.  If not given will be generated from _ga cookie or randomly
	    'userId' => 'uid',          // The User ID.

        /* pageview */
        'hostname' => 'dh',
        'documentHostname' => 'dh',
        'page' => 'dp',
        'path' => 'dp',
        'documentPath' => 'dp',
        'title' => 'dt',
        'documentTitle' => 'dt',
        'location' => 'dl',
        'documentLocation' => 'dl',
        'referrer' => 'dr',
        'documentReferrer' => 'dr',

        /* event */
        'eventCategory' => 'ec',
        'eventAction' => 'ea',
        'eventLabel' => 'el',
        'eventValue' => 'ev',

        /* transaction */
        'transactionAffiliation' => 'ta',
        'transactionId' => 'ti',
        'transactionRevenue' => 'tr',
        'transactionTotal' => 'tr',
        'transactionShipping' => 'ts',
        'transactionTax' => 'tt',
        'transactionCurrency' => 'cu',

        /* item */
	    'name' => 'in',
	    'itemName' => 'in',
	    'price' => 'ip',
	    'itemPrice' => 'ip',
	    'quantity' => 'iq',
	    'itemQuantity' => 'iq',
	    'sku' => 'ic',
	    'itemCode' => 'ic',
	    'category' => 'iv',
	    'itemVariation' => 'iv',
        'itemCategory' => 'iv',

        /* social */
        'socialAction' => 'sa',
        'socialNetwork' => 'sn',
        'socialTarget' => 'st',

        /* exception */
        'exceptionDescription' => 'exd',
        'exceptionFatal' => 'exf',

        /* timing */
        'timingCategory' => 'utc',
        'timingVariable' => 'utv',
        'timingTime' => 'utt',
        'timingLabel' => 'utl',
        'timingDNS' => 'dns',
        'timingPageLoad' => 'pdt',
        'timingRedirect' => 'rrt',
        'timingTCPConnect' => 'tcp',
        'timingServerResponse' => 'srt',

        /* mobile app / screen tracking */
        'appName' => 'an',
        'appVersion' => 'av',
        'contentDescription' => 'cd',

        'campaignName' => 'cn',
        'campaignSource' => 'cs',
        'campaignMedium' => 'cm',
        'campaignKeyword' => 'ck',
        'campaignContent' => 'cc',
        'campaignId' => 'ci',

	    'anonymizeIp' => 'aip',
	    'flashVersion' => 'fl',
	    'javaEnabled' => 'je',
	    'nonInteraction' => 'ni',
	    'nonInteractive' => 'ni',
	    'sessionControl' => 'sc',
	    'queueTime' => 'qt',
	    'screenResolution' => 'sr',
	    'viewportSize' => 'vp',
	    'documentEncoding' => 'de',
	    'screenColors' => 'sd',
	    'userLanguage' => 'ul',

	    'displayAdsId' => 'dclid',  // Specifies the Google Display Ads Id
	    'adwordsID' => 'gclid',     // Specifies the Google AdWords Id
	    'linkid' => 'linkid',       // The ID of a clicked DOM element, used to disambiguate multiple links to the same URL in In-Page Analytics reports when Enhanced Link Attribution is enabled for the property.
	    'pageLoadTime' => 'plt',    // Specifies the time it took for a page to load. The value is in milliseconds
	    'expId' => 'xid',           // This parameter specifies that this visitor has been exposed to an experiment with the given ID. It should be sent in conjunction with the Experiment Variant parameter.
	    'expVar' => 'xvar',         // This parameter specifies that this visitor has been exposed to a particular variation of an experiment. It should be sent in conjunction with the Experiment ID parameter.
	    'version' => 'v',           // The Protocol version. Set in constructor. The current value is '1'. This will only change when there are changes made that are not backwards compatible.
	    'cacheBuster' => 'z'        // Cache Buster
    );

    public static $nameMapRe = array(
        '@^dimension([0-9]+)$@' => 'cd$1',
        '@^metric([0-9]+)$@' => 'cm$1'
    );

	/**
	 * Return an MD5 checksum spaced in UUD4-format
	 *
	 * @param string arbitrary value
	 * @return string UUID
	 */
	protected function hash_uuid($value) {
		$checksum = md5($value);
		return sprintf('%8s-%4s-%4s-%4s-%12s',
			substr($checksum, 0, 8),
			substr($checksum, 8, 4),
			substr($checksum, 12, 4),
			substr($checksum, 16, 4),
			substr($checksum, 20, 12)
		);
	}

	/**
     * Creates a random UUID
     *
     * @return string UUID
     */
    protected function generateUUID4() {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

            // 16 bits for "time_mid"
            mt_rand( 0, 0xffff ),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand( 0, 0x0fff ) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand( 0, 0x3fff ) | 0x8000,

            // 48 bits for "node"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }

}
