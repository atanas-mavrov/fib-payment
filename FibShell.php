<?php

/**
 * @author Hacko <amavrov@dotmedia.bg>
 * @license http://www.gnu.org/licenses/gpl-2.0.html
 * @version 0.0.1
 * @link https://github.com/simple-projects/fib-payment
 */

/**
 * 0 - production mode
 * 1 - development mode
 */
if (!defined('PIB_DEVELOPMENT')) {
    define('PIB_DEVELOPMENT', 1);
}

/**
 * Path to the bank certificate in pem format.
 * Please put it in the directory where the web server has not access
 */
if (!defined('PKEY_PATH')) {
    define('PKEY_PATH', 'mycertificate.pem');
}
/**
 * Paht to the file containts certificate password
 * If certificate has not password, leave it blank
 * Please put it in the directory where the web server has not access
 */
if (!defined('PPASS_PATH')) {
    define('PPASS_PATH', 'myCrtPass.conf');
}
//Path to the testing log
if (!defined('PELOG_PATH')) {
    define('PELOG_PATH', 'mylog.log');
}


/**
 * Base class for comunication with FIB ECOMM server
 */
class FibShell {
    /**
     *
     * @var string URL to merchang service
     */
    private $merchantURL;
    /**
     *
     * @var string URL to client service
     */
    private $clientURL;
    
    /**
     *
     * @var boolan Flag for checking bank certificate
     */
    private $checkCertificate;
    
    /**
     *
     * @var string Bank transaction ID
     */
    private $transactionID = null;
    
    /**
     *
     * @var int Number of attempts in pending payment
     */
    public $numOfAttemptRequests = 3;
    
    /**
     *
     * @var array Array of status codes
     */
    private $stasuses = [
        'OK'=> 'sucessful',
        'FAILED' => 'failed',
        'CREATED' => 'unfinished',
        'PENDING' => 'unfinished',
        'DECLINED' => 'filed',
        'REVERSED' => 'canceled',
        'AUTOREVERSED' => 'filed',
        'TIMEOUT' => 'unfinished'
    ];
    
    
    public function __construct() {
        $this->merchantURL = 'https://mdpay.fibank.bg:9443/ecomm/MerchantHandler';
        $this->clientURL = 'https://mdpay.fibank.bg/ecomm/ClientHandler?trans_id=';
        $this->checkCertificate = true;
        if(PIB_DEVELOPMENT) {
            $this->merchantURL = 'https://mdpay-test.fibank.bg:9443/ecomm/MerchantHandler';
            $this->clientURL = 'https://mdpay-test.fibank.bg/ecomm/ClientHandler?trans_id=';
            $this->checkCertificate = false;
        }
    }

    /**
     * 
     * @param float $sum Order sum
     * @param string $description Order description
     * @param int $currency Currency code
     * @return string TransationID
     */
    public function authorization($sum, $description, $currency = 975) {
        if(!is_numeric($sum) || ($sum) <= 0) {
            throw new Exception('Invalid sum');
        }
        $formatedSum = sprintf("%0.2f",$sum)*100;
        $encodedDescription = urlencode($description);
        $remoteAddr = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
        $transactionData = $this->connection("command=V&amount=$formatedSum&currency=$currency&client_ip_addr=$remoteAddr&desc=$encodedDescription&msg_type=SMS");
        if (substr($transactionData,0,14)=="TRANSACTION_ID") {
            $this->transactionID=substr($transactionData,16,28);
        }
        return $this->transactionID;
    }
    
    /**
     * 
     * @param string $transactionID
     * @return string
     * @throws Exception
     */
    private function encodeTransactionID($transactionID) {
        if(!$transactionID && $this->transactionID) {
            $transactionID = $this->transactionID;
        }
        if(!$transactionID) {
            throw new Exception('No transaction ID');
        }
        return urlencode($transactionID);
    }

    /**
     * Function to redirect to the bank URL
     * 
     * @param string $transactionID
     * @param boolean $onlyURL
     * @return string
     */
    public function redirectToPaymentGateway($transactionID = null, $onlyURL = false) {
        $transactionID = $this->encodeTransactionID($transactionID);
        if($onlyURL) {
            return $this->clientURL . $transactionID;
        }
        header("Location: {$this->clientURL}$transactionID");
    }

    /**
     * 
     * @param string $transactionID
     * @return string
     */
    private function sendRequestForPaymentStatus($transactionID) {
        return $this->connection("command=C&trans_id=$transactionID&client_ip_addr={$_SERVER['REMOTE_ADDR']}");
    }

    /**
     * 
     * @param string $data
     * @return string
     */
    private function parseResultCode($data) {
        $splitData = explode('RESULT_CODE', $data);
        return trim(explode(' ', $splitData[0])[1]);
    }

    /**
     * Function get payment status. Return array. First element is payment result (it is returned from bank), second element is parsed payment status.
     * 
     * @param string $transactionID
     * @return array 0 - raw data, 1 - status code
     */
    public function getPaymentStatus($transactionID = null) {
        $transactionID = $this->encodeTransactionID($transactionID);
        $attempt = 1;
        $status = 3;
        do {
            $paymentResult = $this->sendRequestForPaymentStatus($transactionID);
            $resultCode = $this->parseResultCode($paymentResult);

            if($this->stasuses[$resultCode] == 'sucessful') {
                $status = 1;
            } elseif ($this->stasuses[$resultCode] == 'unfinished') {
                $attempt++;
                if($attempt < 30) {
                    sleep($attempt);
                } else {
                    sleep(30);   
                }
                continue;
            } elseif ($this->stasuses[$resultCode] == 'canceled') {
                $status = 2;
            }
            return [$paymentResult, $status];
        } while ($attempt >= $this->numOfAttemptRequests);
        return [$paymentResult, 3];
    }

    /**
     * Connection to bank gateway.
     * 
     * @param string $dataToSend
     * @return string
     */
    private function connection($dataToSend) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->merchantURL);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataToSend);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->checkCertificate);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->checkCertificate);
        curl_setopt($ch, CURLOPT_SSLCERT, PKEY_PATH);
        if(PPASS_PATH) {
            curl_setopt($ch, CURLOPT_SSLCERTPASSWD, file_get_contents(PPASS_PATH));
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        if(PIB_DEVELOPMENT) {
            $fileHandle = fopen(PELOG_PATH,"w+");
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_STDERR, $fileHandle);
        }
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}