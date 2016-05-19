<?php

/**
 * @author Hacko <amavrov@dotmedia.bg>
 * @license http://www.gnu.org/licenses/gpl-2.0.html
 * @version 0.0.1
 * @link https://github.com/simple-projects/fib-payment
 */

//we configure our variables

//development mode
define(PIB_DEVELOPMENT, TRUE);

//Path to the certificate
define(PKEY_PATH, 'cert.pem');

//If you use password for certificate put in file and write path to file. Otherwise leave it blank
define(PPASS_PATH, 'cert.conf');

//Path to development log
define('PELOG_PATH', 'mylog.log');

require './FibShell.php';

function renderTemplate() {
    ob_start();
    include 'template.php';
    $templateData = ob_get_clean();
    echo $templateData;
}

function fakeSQL($sql) {
    return TRUE;
}


if(filter_input(INPUT_POST, 'tobank')) {
    //In this case data comming from our form
    $fib = new FibShell();
    
    //get transactionID from bank
    try {
        //Here can safe order to your database and get order ID, end sum and currency
        //For this example we will use static data
        $sum = 2;
        $orderID = 100;
        
        //Get transaction id from payment gateway
        $tID = $fib->authorization($sum, $orderID);
        
        //Now we have transaction ID. We showd save in database.
        //For example wi use fake sunction with very simple sql query. Please do not use for real work!
        fakeSQL(sprintf('UPDATE orders SET fib_transaction_id = "%s" WHERE id=%d', addslashes($tID), $orderID));
        
        //Redirect to the payment gateway
        $fib->redirectToPaymentGateway($tID);
    } catch (Exception $e) {
        $error = "Възникнала е грешка: " . $e->getMessage();
    }
} elseif(filter_input(INPUT_POST, 'trans_id')) {
    //In this case the data come from bank gateway (probably!)
    
    $fib = new FibShell();
    if(filter_input(INPUT_POST, 'error')) {
        $error = 'Възникнала е грешка по време на плащане';
    } else {
        $status = $fib->getPaymentStatus(filter_input(INPUT_POST, 'trans_id'));
        
        //here you must write payment result in your database
        //For example wi use fake sunction with very simple sql query. Please do not use for real work!
        fakeSQL(sprintf('UPDATE orders SET fib_payment_status=%d, fib_payment_log="%s" WHERE fib_transaction_id = "%s"', $status[2], $status[1], filter_input(INPUT_POST, 'trans_id')));
    }
}
renderTemplate();