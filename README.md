# Universal Analytics for PHP 

This library provides a PHP interface for Google's Universal Analytics Measurement Protocol, with an interface modeled (loosely) after Google's `analytics.js`.

**NOTE** that this project is still _beta_; some features of the Measurement Protocol aren't fully represented, and new features will be added in the (hopefully) nearer future.

# Usage

For the most accurate data in your reports, establish a distinct User ID for each of your users, and integrate that ID on your front-end web tracking, as well as back-end tracking calls. This provides for a consistent, correct representation of user engagement, without skewing overall visit metrics (and others).  As of this writing, User ID is in closed beta (https://developers.google.com/analytics/devguides/collection/analyticsjs/user-id).

A simple example:

```php
<?php

require('UniversalAnalytics/Tracker.php');

$ua = new UniversalAnalytics_Tracker($accountId);

// Example event hit
$ua->setUserId(/* unique user ID */'990', true)   // Client ID will be extracted from the _ga cookie if it exists, or generated randomly
    ->setUserAgent($_SERVER['HTTP_USER_AGENT'])
    ->setValue('dimension1', 'pizza')
    ->setValues( /* hit properties */
        array(
            'eventCategory' => 'test events',
            'eventAction' => 'testing',
            'eventLabel' => '(test)'
        ))
    ->send(/* hit type */ 'event', true);

// Example transaction/item hits
$transaction = array(
    'transactionId' => /* unique ID */$transactionId,
    'transactionAffiliation' => 'affiliate',
    'transactionTotal' => 175.68,
    'transactionShipping' => 16.95,
    'transactionTax' => 4.73);

$items = array(
    array(
        'itemName'=>'Pink Hat',
        'itemPrice'=>69.20,
        'itemQuantity'=>1,
        'itemCode'=>'PH-1000',
        'itemCategory'=>'Hats'
    ),
    array(
        'itemName'=>'Blue Shoe',
        'itemPrice'=>107.00,
        'itemQuantity'=>1,
        'itemCode'=>'BS-2000',
        'itemCategory'=>'Shoes'
    )
);

$ua->initialize() // initialize clears data values on an existing tracker, but preserves any previously set 'global' UA data (Account ID, Client ID, User ID, User Agent).
    ->setValues($transaction)
    ->send('transaction', /* log the hit POST for dubugging */ true);
foreach($items as $item) {
    $ua->initialize()->setValues(array('transactionId' => $transactionId) + $item)->send('item', /* log the hit POST for dubugging */ true);
}

?>
```

Currently all tracking hits (using `send`) require an array (dictionary) of properties related to the hit type.
See the Measurement Protocol Parameter Reference (https://developers.google.com/analytics/devguides/collection/protocol/v1/parameters) for parameters for supported hit types.


# Features not implemented

* Throttling 
* GA Classic interface

# License

universal-analytics-php is licensed under the [BSD license](./LICENSE)
