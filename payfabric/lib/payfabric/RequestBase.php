<?php

class payFabric_RequestBase
{
    protected $timeout = 60;
    protected static $sslVerifyPeer = 0;
    protected static $sslVerifyHost = 2;
    public static $logger;
    public static $loggerSev;
    public static $debug;
    public $endpoint;
    public $type;
    // Environment variables created dynamically throwing error in php 8.2+
    public ?string $Key = null;
    public ?string $id = null;
    public ?string $referenceNum = null;
    public ?string $Amount = null;
    public ?string $Currency = null;
    public ?string $pluginName = null;
    public ?string $pluginVersion = null;
    
    public ?string $shippingCity = null;
    public ?string $shippingCountry = null;
    public ?string $customerId = null;
    public ?string $shippingEmail = null;
    public ?string $shippingAddress1 = null;
    public ?string $shippingAddress2 = null;
    public ?string $shippingPhone = null;
    public ?string $shippingState = null;
    public ?string $shippingPostalCode = null;
    
    public ?string $billingFirstName = null;
    public ?string $billingLastName = null;
    public ?string $billingCompany = null;
    public ?string $billingAddress1 = null;
    public ?string $billingAddress2 = null;
    public ?string $billingCity = null;
    public ?string $billingState = null;
    public ?string $billingPostalCode = null;
    public ?string $billingCountry = null;
    public ?string $billingEmail = null;
    public ?string $billingPhone = null;
    
    public ?string $freightAmount = null;
    public ?string $taxAmount = null;
    public ?array $lineItems = null;  // assuming array
    public ?string $allowOriginUrl = null;
    public ?string $merchantNotificationUrl = null;
    public ?string $userAgent = null;
    public ?string $customerIPAddress = null;
    
    
    public function setEndpoint($param)
    {
        try {
            if (!$param) {
                throw new BadMethodCallException('[PayFabric Class] INTERNAL ERROR on ' . __METHOD__ . ' method: no Endpoint defined');
            }
            $this->endpoint = $param;
            if (is_object(payFabric_RequestBase::$logger)) {
                payFabric_RequestBase::$logger->logDebug('Setting endpoint to "' . $param . '"');
            }
        } catch (Exception $e) {
            if (is_object(self::$logger)) {
                self::$logger->logCrit($e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            }
            throw $e;
        }
    }

    public function setTransactionType($param)
    {
        try {
            if (!$param) {
                throw new BadMethodCallException('[PayFabric Class] INTERNAL ERROR on ' . __METHOD__ . ' method: no Transaction Type defined');
            }
            $this->type = $param;
        } catch (Exception $e) {
            if (is_object(self::$logger)) {
                self::$logger->logCrit($e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            }
            throw $e;
        }
    }

    public function setVars($array)
    {
        try {
            if (!$array) {
                throw new BadMethodCallException('[PayFabric Class] INTERNAL ERROR on ' . __METHOD__ . ' method: no array to format.', 400);
            }
            foreach ($array as $k => $v) {
                $this->$k = $v;
            }
            if (is_object(self::$logger)) {
                if (self::$loggerSev != 'DEBUG') {
                    $array = self::clearForLog($array);
                }
                self::$logger->logNotice('Parameters sent', $array);
            }
            $this->validateCall();
        } catch (Exception $e) {
            if (is_object(self::$logger)) {
                self::$logger->logCrit($e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            }
            throw $e;
        }
    }

    public static function setSslVerify($param)
    {
        self::$sslVerifyHost = $param;
        self::$sslVerifyPeer = $param;
    }

    public static function setLogger($path, $severity = 'INFO')
    {
        switch ($severity) {
            case "EMERG":
                self::$logger = new KLogger($path, KLogger::EMERG);
                break;
            case "ALERT":
                self::$logger = new KLogger($path, KLogger::ALERT);
                break;
            case "CRIT":
                self::$logger = new KLogger($path, KLogger::CRIT);
                break;
            case "ERR":
                self::$logger = new KLogger($path, KLogger::ERR);
                break;
            case "WARN":
                self::$logger = new KLogger($path, KLogger::WARN);
                break;
            case "NOTICE":
                self::$logger = new KLogger($path, KLogger::NOTICE);
                break;
            case "INFO": // Severities INFO and up are safe to use in Production as Credit Card info are NOT logged
                self::$logger = new KLogger($path, KLogger::INFO);
                break;
            case "DEBUG": // Do NOT use 'DEBUG' for Production environment as Credit Card info WILL BE LOGGED
                self::$logger = new KLogger($path, KLogger::DEBUG);
                break;
        }
        if (self::$logger->_logStatus == 1) {
            self::$loggerSev = $severity;
        } else {
            self::$logger = null;
        }
    }

    public static function clearForLog($text)
    {
        if ((!isset($text)) || (self::$loggerSev == 'DEBUG')) {
            return $text;
        } elseif (is_array($text)) {
            isset($text["cvvNumber"]) && $text["cvvNumber"] = str_ireplace($text["cvvNumber"], str_repeat("*", strlen($text["cvvNumber"])), $text["cvvNumber"]);
            isset($text["shippingEmail"]) && $text["shippingEmail"] = str_ireplace($text["shippingEmail"], substr_replace($text["shippingEmail"], str_repeat("*", 3), 1, 3), $text["shippingEmail"]);
            if (isset($text["number"]) && payFabric_ServiceBase::checkCreditCard($text["number"])) {
                $text["number"] = str_ireplace($text["number"], substr_replace($text["number"], str_repeat('*', strlen($text["number"]) - 4), '4'), $text["number"]);
            }
            return $text;
        } elseif (strlen($text) >= 8) {
            return substr_replace($text, str_repeat('*', strlen($text) - 4), '4');
        } else {
            return substr_replace($text, str_repeat('*', strlen($text) - 2), '2');
        }
    }

    private function validateCall()
    {
        try {
            // Preferred - clear separation
            if ($this->number === null || $this->number === '') {
                throw new InvalidArgumentException("[PayFabric Class] Field 'number' is required.");
            }
            if (!ctype_digit((string)$this->number)) {
                throw new InvalidArgumentException("[PayFabric Class] Field 'number' accepts only numerical values.");
            }
            // Credit card expiration month (both property names)
            if ($this->expMonth !== null && $this->expMonth !== '') {
                $month = (string) $this->expMonth;
                if (strlen($month) !== 2 || !ctype_digit($month)) {
                    throw new InvalidArgumentException(
                        "[PayFabric Class] Credit card expiration month must be exactly 2 digits (01-12)."
                    );
                }
            }
            if ($this->expirationMonth !== null && $this->expirationMonth !== '') {
                $month = (string) $this->expirationMonth;
                if (strlen($month) !== 2 || !ctype_digit($month)) {
                    throw new InvalidArgumentException(
                        "[PayFabric Class] Credit card expiration month must be exactly 2 digits (01-12)."
                    );
                }
            }

            // Credit card expiration year
            if ($this->expYear !== null && $this->expYear !== '') {
                $year = (string) $this->expYear;
                if (strlen($year) !== 4 || !ctype_digit($year)) {
                    throw new InvalidArgumentException(
                        "[PayFabric Class] Credit card expiration year must be exactly 4 digits."
                    );
                }
            }
            if ($this->expirationYear !== null && $this->expirationYear !== '') {
                $year = (string) $this->expirationYear;
                if (strlen($year) < 2 || !ctype_digit($year)) {  // note: your original allows 2+ digits here
                    throw new InvalidArgumentException(
                        "[PayFabric Class] Credit card expiration year must have at least 2 digits."
                    );
                }
            }

            // Number of installments
            if ($this->numberOfInstallments !== null && $this->numberOfInstallments !== '') {
                if (!ctype_digit((string) $this->numberOfInstallments)) {
                    throw new InvalidArgumentException(
                        "[PayFabric Class] Field 'numberOfInstallments' accepts only numerical values."
                    );
                }
            }

            // Charge interest (Y/N flag)
            if ($this->chargeInterest !== null && $this->chargeInterest !== '') {
                $value = strtoupper((string) $this->chargeInterest);
                if (!in_array($value, ['Y', 'N'], true)) {
                    throw new InvalidArgumentException(
                        "[PayFabric Class] Field 'chargeInterest' only accepts 'Y' or 'N'."
                    );
                }
            }

            // Boleto expiration date (future check)
            if ($this->expirationDate !== null && $this->expirationDate !== '') {
                $expDate = gmdate('Ymd', strtotime($this->expirationDate));
                $today   = gmdate('Ymd');
                if ($expDate < $today) {
                    throw new InvalidArgumentException(
                        "[PayFabric Class] Boleto expiration date can only be set in the future."
                    );
                }
            }

            // Boleto instructions length
            if ($this->instructions !== null && $this->instructions !== '') {
                if (strlen((string) $this->instructions) > 350) {
                    throw new InvalidArgumentException(
                        "[PayFabric Class] Boleto instructions cannot be longer than 350 characters."
                    );
                }
            }
        } catch (Exception $e) {
            if (is_object(self::$logger)) {
                self::$logger->logCrit($e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            }
            throw $e;
        }
    }

    public function processRequest()
    {
        try {
            switch (strtolower($this->type)) {
                case "token":
                    $this->setToken();
                    break;
                case "authorization":
                case "sale":
                    $this->setOrder();
                    //set level 2/3
                    $this->setItens();
                    break;
                case "refund":
                    $this->setRefund();
                    break;
                case "update":
                    $this->setParams();
                    break;
                default:
                    break;
            }
            return $this->sendXml();
        } catch (Exception $e) {
            if (is_object(self::$logger)) {
                self::$logger->logCrit($e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            }
            throw $e;
        }
    }

    /**
     * @return mixed
     */
    public function __get($var)
    {
        return null;
    }
}
