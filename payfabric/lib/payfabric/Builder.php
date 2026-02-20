<?php

class payFabric_Builder extends payFabric_RequestBase
{
    public $_data = array();
    public $merchantId;
    public $merchantKey;
    
    public function __construct($array)
    {
        if (strlen($array["merchantId"]) > 0) {
            $this->merchantId = $array["merchantId"];
        } else {
            throw new InvalidArgumentException("[PayFabric Class] Field 'merchantId' cannot be null.");
        }
        if (strlen($array["merchantKey"]) > 0) {
            $this->merchantKey = $array["merchantKey"];
        } else {
            throw new InvalidArgumentException("[PayFabric Class] Field 'merchantKey' cannot be null.");
        }
    }

protected function setToken()
{
    if (!empty($this->Audience)) {
        $this->_data["Audience"] = $this->Audience;
    } else {
        throw new InvalidArgumentException("[PayFabric Class] Field 'Audience' cannot be null.");
    }

    if (!empty($this->Subject)) {
        $this->_data["Subject"] = $this->Subject;
    } else {
        throw new InvalidArgumentException("[PayFabric Class] Field 'Subject' cannot be null.");
    }
}

protected function setRefund()
{
    if (!empty($this->type)) {
        $this->_data["Type"] = $this->type;
    } else {
        throw new InvalidArgumentException("[PayFabric Class] Field 'Type' cannot be null.");
    }

    if (isset($this->Amount) && is_numeric($this->Amount)) {
        $this->_data["Amount"] = $this->Amount;
    } else {
        throw new InvalidArgumentException("[PayFabric Class] Field 'Amount' is invalid.");
    }

    if (!empty($this->ReferenceKey)) {
        $this->_data["ReferenceKey"] = $this->ReferenceKey;
    } else {
        throw new InvalidArgumentException("[PayFabric Class] Field 'ReferenceKey' cannot be null.");
    }
}

protected function setOrder()
{
    if (!empty($this->type)) {
        $this->_data["Type"] = $this->type;
    } else {
        throw new InvalidArgumentException("[PayFabric Class] Field 'Type' cannot be null.");
    }

    if (!empty($this->id)) {
        $this->_data["TrxUserDefine1"] = $this->id;
    }

    if (isset($this->Amount) && is_numeric($this->Amount)) {
        $this->_data["Amount"] = $this->Amount;
    } else {
        throw new InvalidArgumentException("[PayFabric Class] Field 'Amount' is invalid.");
    }

    if (!empty($this->Currency)) {
        $this->_data["Currency"] = $this->Currency;
    } else {
        throw new InvalidArgumentException("[PayFabric Class] Field 'Currency' cannot be null.");
    }

    if (!empty($this->customerId)) {
        $this->bizuno_get_wallet_id();
        $this->_data["Customer"] = $this->customerId;
    }

    $this->setAddress();
}

protected function setAddress()
{
    if (!empty($this->billingCity)) {
        $this->_data['Document']["DefaultBillTo"]["City"] = $this->billingCity;
    }
    if (!empty($this->billingCountry)) {
        $this->_data['Document']["DefaultBillTo"]["Country"] = $this->billingCountry;
    }
    if (!empty($this->customerId)) {
        $this->_data['Document']["DefaultBillTo"]["Customer"] = $this->customerId;
    }
    if (!empty($this->billingEmail)) {
        $this->_data['Document']["DefaultBillTo"]["Email"] = $this->billingEmail;
    }
    if (!empty($this->billingAddress1)) {
        $this->_data['Document']["DefaultBillTo"]["Line1"] = $this->billingAddress1;
    }
    if (!empty($this->billingAddress2)) {
        $this->_data['Document']["DefaultBillTo"]["Line2"] = $this->billingAddress2;
    }
    if (!empty($this->billingPhone)) {
        $this->_data['Document']["DefaultBillTo"]["Phone"] = $this->billingPhone;
    }
    if (!empty($this->billingState)) {
        $this->_data['Document']["DefaultBillTo"]["State"] = $this->billingState;
    }
    if (!empty($this->billingPostalCode)) {
        $this->_data['Document']["DefaultBillTo"]["Zip"] = $this->billingPostalCode;
    }

    if (!empty($this->shippingCity)) {
        $this->_data["Shipto"]["City"] = $this->shippingCity;
    }
    if (!empty($this->shippingCountry)) {
        $this->_data["Shipto"]["Country"] = $this->shippingCountry;
    }
    if (!empty($this->customerId)) {
        $this->_data["Shipto"]["Customer"] = $this->customerId;
    }
    if (!empty($this->shippingEmail)) {
        $this->_data["Shipto"]["Email"] = $this->shippingEmail;
    }
    if (!empty($this->shippingAddress1)) {
        $this->_data["Shipto"]["Line1"] = $this->shippingAddress1;
    }
    if (!empty($this->shippingAddress2)) {
        $this->_data["Shipto"]["Line2"] = $this->shippingAddress2;
    }
    if (!empty($this->shippingPhone)) {
        $this->_data["Shipto"]["Phone"] = $this->shippingPhone;
    }
    if (!empty($this->shippingState)) {
        $this->_data["Shipto"]["State"] = $this->shippingState;
    }
    if (!empty($this->shippingPostalCode)) {
        $this->_data["Shipto"]["Zip"] = $this->shippingPostalCode;
    }
}
    /**
     * 2023-10-30 Added by PhreeSoft to link the WooCommerce Customer ID to the Bizuno Wallet (Customer) ID
     */
    private function bizuno_get_wallet_id()
    {
        $user_id = get_current_user_id();
        if ( empty($user_id)) { return; } // not logged in
        $wallet_id = get_user_meta($user_id, 'bizuno_wallet_id', true);
        if (!empty($wallet_id)) {
            $this->customerId = $wallet_id;
        }
    }


    protected function setItens()
    {
        //set level2
        $this->_data['Document']['Head'] = array(
            array('Name' => 'InvoiceNumber', 'Value' => $this->referenceNum),
            array('Name' => 'FreightAmount', 'Value' => $this->freightAmount),
            array('Name' => 'TaxAmount', 'Value' => $this->taxAmount),
        );
        //set level3
        $this->_data['Document']['Lines'] = array();
        if (!empty($this->lineItems)) {
            foreach ($this->lineItems as $item) {
                $this->_data['Document']['Lines'][]['Columns'] = array(
                    array('Name' => 'ItemProdCode', 'Value' => $item['product_code']),
                    array('Name' => 'ItemDesc', 'Value' => $item['product_description']),
                    array('Name' => 'ItemCost', 'Value' => $item['unit_cost']),
                    array('Name' => 'ItemQuantity', 'Value' => $item['quantity']),
                    array('Name' => 'ItemDiscount', 'Value' => $item['discount_amount']),
                    array('Name' => 'ItemTaxAmount', 'Value' => $item['tax_amount']),
                    array('Name' => 'ItemAmount', 'Value' => $item['item_amount']),
                );
            }
        }
        //Set UserDefined
        if (defined('BIZUNO_PAYMENTS_PAYFABRIC_NAME') > 0 && defined('BIZUNO_PAYMENTS_PAYFABRIC_VERSION') > 0) {
            $this->_data["Document"]["UserDefined"] = [
                ['Name' => 'PluginName',    "Value" => BIZUNO_PAYMENTS_PAYFABRIC_NAME],
                ['Name' => 'PluginVersion', "Value" => BIZUNO_PAYMENTS_PAYFABRIC_VERSION]
            ];
        }
    }

    protected function setParams()
    {
        if (strlen($this->Key) > 0) {
            $this->_data["Key"] = $this->Key;
        }
        $this->setOrder();
        //set level 2/3
        $this->setItens();
        unset($this->_data["Type"]);
    }

}