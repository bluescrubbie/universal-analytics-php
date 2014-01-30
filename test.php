<?php

require('UniversalAnalytics/Tracker.php');

$transactionId = '99'.time();   // makes for easier debugging
$accountId = 'UA-XXXXXXXX-Y';

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
