<?PHP

class InvoicesOnlineAPI {

    var $username = ''; 
    var $BusinessID = '';
    var $password = '';
    var $error = array();
    var $API_url = 'https://www.invoicesonline.co.za/api/';
    var $cache_for = 10; //10 minutes
    var $cache_file;
    var $cache_dir = '/io_api_cache/';
    var $data_dir = '/io_data/';

    public function clearCache($dir = false) {
        if (!$dir) {
            $dir = str_replace('//', '/', dirname(__FILE__) . '/') . $this->cache_dir;
        }
        $dh = opendir($dir);
        //echo $dh.'<br />';
        while (false !== ($obj = readdir($dh))) {
            //echo $obj.'<br />';
            if ($obj != '.' && $obj != '..') {
                unlink($dir . '/' . $obj);
            }
        }
        closedir($dh);
    }
    
    public function Setusername($username) {
        $this->username = $username;
    }

    public function Setpassword($password) {
        $this->password = $password;
    }

    public function GetErrors() {
        return $this->error;
    }

    private function _SetError($error) {
        $this->error[] = $error;
    }

    private function _callCurl($type, $request, $ClientID, $OrderID = false, $docType = false) {
        switch ($type) {
            case 'details';
                $file = 'getClientDetails_JSON.php';
                $this->cache_file = dirname(__FILE__) . $this->cache_dir . $ClientID . '.cDetails.json';
                break;
            case 'all_clients';
                $file = 'getClients_JSON.php';
                $this->cache_file = dirname(__FILE__) . $this->cache_dir . 'allClients.json';
                break;
            case 'history';
                $file = 'getClientHistory_JSON.php';
                $this->cache_file = dirname(__FILE__) . $this->cache_dir . $ClientID . '.cHistory.json';
                break;
            case 'documents_by_type';
                $file = 'getDocumentsByType_JSON.php';
                $this->cache_file = dirname(__FILE__) . $this->cache_dir . $ClientID . '.' . $docType . '.json';
                break;
            case 'order_invoice';
                $file = 'getClientOrderInvoice_JSON.php';
                $this->cache_file = dirname(__FILE__) . $this->cache_dir . $ClientID . '.' . $OrderID . '.coInvoice.json';
                break;
            default:
                $file = false;
                break;
        }
        if ($file && $request) {
            //echo $this->cache_file;
            $url = $this->API_url . $file;
            if (!file_exists($this->cache_file) || time() - filemtime($this->cache_file) > $this->cache_for) {
                $ch = curl_init();
                //var_dump($url);
                //var_dump($ch);
                //var_dump($request);
                curl_setopt($ch, CURLOPT_URL, $url); //set the url
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //return as a variable
                curl_setopt($ch, CURLOPT_POST, 1); //set POST method
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $request); //set the POST variables
                $response = curl_exec($ch); //run the whole process and return the response
                //var_dump($response);
                $response = json_decode($response, true);
                //var_dump($response);
                curl_close($ch); //close the curl handle*/
                if ($response['error']) {
                    $this->_SetError($response['error']);
                    return false;
                } else {
                    $fp = fopen($this->cache_file, 'w+'); // open or create cache
                    //var_dump($fp);
                    if ($fp) {
                        fwrite($fp, json_encode($response));
                        fclose($fp);
                    }
                    return $response;
                }
            } else {
                return json_decode(file_get_contents($this->cache_file), true);
            }
        } else {
            $this->_SetError('Invalid type or file');
        }
    }

    public function GetClientDetails($ClientID) {
        if (!is_numeric($ClientID) || $ClientID < 1) {
            $this->_SetError('method GetClientDetails :: Invalid ClientID');
            return false;
        }
        unset($param, $request);
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['ClientID'] = $ClientID;
        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, strlen($request) - 1);
        $response = $this->_callCurl('details', $request, $ClientID);
        return $response;
    }

    public function GetAllClients() {
        unset($param, $request);
        $request = '';
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, strlen($request) - 1);
        $response = $this->_callCurl('all_clients', $request, false);
        return $response;
    }

    //'recurring_pro-forma_invoices',$i,$ClientID,$res['item_code']
    public function RemoveItemByCode($type, $IID, $ClientID, $ItemCode) {
        unset($param, $request);
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['IID'] = $IID;

        $param['ClientID'] = $ClientID;
        $param['item_code'] = $ItemCode;

        switch (strtolower($type)) {
            case 'recurring_pro-forma_invoices':
                $param['type'] = strtolower($type);
                break;
        }


        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, strlen($request) - 1);


        $url = $this->API_url . 'RemoveItemByCode.php';
        //echo $url.'<br />';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url); //set the url
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //return as a variable
        curl_setopt($ch, CURLOPT_POST, 1); //set POST method
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request); //set the POST variables
        $result = curl_exec($ch); //run the whole process and return the response
        return $result;
    }

    //item is json_encoded array
    public function AddItemTo($type, $IID, $ClientID, $item) {
        unset($param, $request);
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['IID'] = $IID;

        $param['ClientID'] = $ClientID;
        $param['item'] = json_encode($item);

        switch (strtolower($type)) {
            case 'recurring_pro-forma_invoices':
                $param['type'] = strtolower($type);
                break;
        }


        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, strlen($request) - 1);


        $url = $this->API_url . 'AddItemTo.php';
        //echo $url.'<br />';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url); //set the url
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //return as a variable
        curl_setopt($ch, CURLOPT_POST, 1); //set POST method
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request); //set the POST variables
        $result = curl_exec($ch); //run the whole process and return the response
        return $result;
    }
    
    //Convert Pro-Forma invoice to Invoice
    public function ConvertProformaToInvoice($BID, $PINR) {
        unset($param, $request);
        $param['username'] = $this->username;
        $param['password'] = $this->password;

        $param['BusinessID'] = $BID;
        $param['ProFormaInvoiceNR'] = $PINR;

        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        
        $request = substr($request, 0, strlen($request) - 1);


        $url = $this->API_url . 'ConvertProFormaInvoiceToInvoice.php';
        //echo $url.'<br />';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url); //set the url
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //return as a variable
        curl_setopt($ch, CURLOPT_POST, 1); //set POST method
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request); //set the POST variables
        $result = curl_exec($ch); //run the whole process and return the response
        return $result;
    }

    public function GetAllDocumentsByType($type, $ClientID = 'all') {
        unset($param, $request);
        switch (strtolower($type)) {
            case 'recurring_pro-forma_invoices':
            case 'invoices':
            case 'quotes':
            case 'recurring_invoices':
            case 'credit_notes':
            case 'pro-forma_invoices':
            case 'delivery_notes':
                $type = strtolower($type);
                break;
            default:
                $this->_SetError('Invalid type');
                return false;
                break;
        }
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['ClientID'] = $ClientID;
        $param['type'] = $type;
        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, strlen($request) - 1);
        //var_dump($request);
        $response = $this->_callCurl('documents_by_type', $request, $ClientID, false, $type);
        return $response;
    }

    public function GetOrderInvoice($ClientID, $OrderID) {
        if (!is_numeric($ClientID) || $ClientID < 1) {
            $this->_SetError('method GetOrderInvoice :: Invalid ClientID');
            return false;
        }
        if (!is_numeric($OrderID) || $OrderID < 1) {
            $this->_SetError('method GetOrderInvoice :: Invalid OrderID');
            return false;
        }
        unset($param, $request);
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['ClientID'] = $ClientID;
        $param['OrderID'] = $OrderID;
        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, strlen($request) - 1);
        $response = $this->_callCurl('order_invoice', $request, $ClientID, $OrderID);
        return $response;
    }

    public function ClearClientCache($ClientID) {
        //echo 'ClientID: '.$ClientID.'<br />';
        unlink(dirname(__FILE__) . $this->cache_dir . $ClientID . '.cHistory.json');
        unlink(dirname(__FILE__) . $this->cache_dir . $ClientID . '.cDetails.json');
        //unlink(dirname(__FILE__).$this->cache_dir.$ClientID.'.coInvoice.json');
        //echo 'cache cleared: '.dirname(__FILE__).$this->cache_dir.$ClientID.'.coInvoice.json';
        $arr = dirList(dirname(__FILE__) . $this->cache_dir);
        //$arr now contains all files in this directory
        foreach ($arr as $index => $value) {
            $val = pathinfo($value);
            if (strstr($val['filename'], $ClientID . '.')) {
                //echo 'found _ in '.$val['filename'].'<br />';
                //only work with dynamically created images here
                //echo 'deleting: '.dirname(__FILE__).$this->cache_dir.$value.'<br />';
                unlink(dirname(__FILE__) . $this->cache_dir . $value);
            }
        }
    }

    public function GetClientHistory($ClientID, $from = false, $to = false, $Order = false) {
        if (!is_numeric($ClientID) || $ClientID < 1) {
            $this->_SetError('method GetClientHistory :: Invalid ClientID');
            return false;
        }
        unset($param, $request);
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['ClientID'] = $ClientID;
        if (strlen($from) == 10) {
            $param['StartDate'] = date('Y-m-d', strtotime($from));
        }
        if (strlen($to) == 10) {
            $param['EndDate'] = date('Y-m-d', strtotime($to));
        }
        if ($Order) {
            switch ($Order) {
                case 'ASC':
                    $param['Order'] = 'ASC';
                    break;
                case 'DESC':
                default:
                    $param['Order'] = 'DESC';
                    break;
            }
        }
        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, strlen($request) - 1);
        $response = $this->_callCurl('history', $request, $ClientID);
        return $response;
    }

    public function CreateNewClient($ClientParams) {
        unset($param, $request);
        $ClientID = 0;
        
        $request = '';
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['BID'] = $this->BusinessID;

        $param['client_invoice_name'] = $ClientParams['client_invoice_name'];
        $param['client_phone_nr'] = $ClientParams['client_phone_nr'];
        $param['client_phone_nr2'] = $ClientParams['client_phone_nr2'];
        $param['client_mobile_nr'] = $ClientParams['client_mobile_nr'];
        $param['client_email'] = $ClientParams['client_email'];
        $param['client_vat_nr'] = $ClientParams['client_vat_nr'];
        $param['client_fax_nr'] = $ClientParams['client_fax_nr'];
        $param['contact_name'] = $ClientParams['contact_name'];
        $param['contact_surname'] = $ClientParams['contact_surname'];
        $param['client_postal_address1'] = $ClientParams['client_postal_address1'];
        $param['client_postal_address2'] = $ClientParams['client_postal_address2'];
        $param['client_postal_address3'] = $ClientParams['client_postal_address3'];
        $param['client_postal_address4'] = $ClientParams['client_postal_address4'];
        $param['client_physical_address1'] = $ClientParams['client_physical_address1'];
        $param['client_physical_address2'] = $ClientParams['client_physical_address2'];
        $param['client_physical_address3'] = $ClientParams['client_physical_address3'];
        $param['client_physical_address4'] = $ClientParams['client_physical_address4'];

        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, strlen($request) - 1);

        $url = $this->API_url . 'NewClient.php';
        //echo $url.'<br />';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url); //set the url
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //return as a variable
        curl_setopt($ch, CURLOPT_POST, 1); //set POST method
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request); //set the POST variables
        $ClientID = curl_exec($ch); //run the whole process and return the response

        return $ClientID;
    }

    public function UpdateClient($BID, $ClientID, $ClientParams) {
        unset($param, $request);
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['BID'] = $BID;

        $param['ClientID'] = $ClientID;
        $param['client_invoice_name'] = $ClientParams['client_invoice_name'];
        $param['client_phone_nr'] = $ClientParams['client_phone_nr'];
        $param['client_phone_nr2'] = $ClientParams['client_phone_nr2'];
        $param['client_mobile_nr'] = $ClientParams['client_mobile_nr'];
        $param['client_email'] = $ClientParams['client_email'];
        $param['client_vat_nr'] = $ClientParams['client_vat_nr'];
        $param['client_fax_nr'] = $ClientParams['client_fax_nr'];
        $param['contact_name'] = $ClientParams['contact_name'];
        $param['contact_surname'] = $ClientParams['contact_surname'];
        $param['client_postal_address1'] = $ClientParams['client_postal_address1'];
        $param['client_postal_address2'] = $ClientParams['client_postal_address2'];
        $param['client_postal_address3'] = $ClientParams['client_postal_address3'];
        $param['client_postal_address4'] = $ClientParams['client_postal_address4'];
        $param['client_physical_address1'] = $ClientParams['client_physical_address1'];
        $param['client_physical_address2'] = $ClientParams['client_physical_address2'];
        $param['client_physical_address3'] = $ClientParams['client_physical_address3'];
        $param['client_physical_address4'] = $ClientParams['client_physical_address4'];

        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, strlen($request) - 1);

        $url = $this->API_url . 'UpdateClient.php';
        //echo $url.'<br />';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url); //set the url
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //return as a variable
        curl_setopt($ch, CURLOPT_POST, 1); //set POST method
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request); //set the POST variables
        $result = curl_exec($ch); //run the whole process and return the response
        return $result;
    }

    public function InvoiceBatch($BID, $data) {
        /*

         * data is array like this
         * invoice_nr :: incremented number - should be the same for all items on a single invoice
         * product_nr :: incremented number - each invoice line has it's own product_nr

         * data[invoice_nr][Products][] => array('code','desc','qty','currency','vat_percentage','item_amount','includes_vat','vat_applies');
         * data[invoice_nr][ClientID] = ClientID;
         */

        /**/
        unset($param, $request);
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['BID'] = $BID;

        $param['data'] = json_encode($data);

        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, -1);
        //echo $request.'<br />';
        $url = $this->API_url . 'GenerateInvoiceBatch.php';
        //echo $url.'<br />';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url); //set the url
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //return as a variable
        curl_setopt($ch, CURLOPT_POST, 1); //set POST method
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request); //set the POST variables
        $result = curl_exec($ch); //run the whole process and return the response
        //var_dump($result);
        //var_dump(curl_getinfo($ch));
        return $result;
    }

    public function CreateNewInvoice($BID, $ClientID, $OrderNR, $data, $opts = false) {
        /*

         * data is array like this
          $data[$i][0] = 'D_'.$value.'_'.date('n', time()).'_'.date('Y', time());
          $data[$i][1] = 1;
          $data[$i][2] = stripslashes($domres['child_name']).' '.stripslashes($domres['child_surname']).' Subscription for '.date('F', time()).' '.date('Y', time());
          $data[$i][3] = ($amount);
          $data[$i][4] = 'ZAR';
          $data[$i][5] = '1';
          $data[$i][6] = '14.00';
          $data[$i][7] = '1';
          $data[$i][8] = '';
          $data[$i][9] = '';
          $data[$i][10] = '';
         *
          data[0] - prod_code
          data[1] - qty
          data[2] - description
          data[3] - amount
          data[4] - currency
          data[5] - vat_applies
          data[6] - vat_percentage
          data[7] - amount_includes_vat
          data[8] - custom1
          data[9] - custom2
          data[10] - custom3
         */

        /**/
        if (!isset($opts['IncludesVat'])) {
            $opts['IncludesVat'] = 'true';
        }
        if (!isset($opts['VatApplies'])) {
            $opts['VatApplies'] = 'true';
        }
        if (!isset($opts['EmailToClient'])) {
            $opts['EmailToClient'] = 'true';
        }
        if (!isset($opts['Paid'])) {
            $opts['Paid'] = 'false';
        }
        if (!isset($opts['MarkAsPaid'])) {
            $opts['MarkAsPaid'] = 'false';
        }
        if (!isset($opts['DiscountPercentage'])) {
            $opts['DiscountPercentage'] = 0;
        }
        if (!isset($opts['DiscountAmount'])) {
            $opts['DiscountAmount'] = 0;
        }
        unset($param, $request);
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['BID'] = $BID;

        $param['ClientID'] = $ClientID;
        $param['ClientOrderNr'] = $OrderNR;
        $param['IncludesVat'] = $opts['IncludesVat'];
        $param['VatApplies'] = $opts['VatApplies'];
        $param['EmailToClient'] = $opts['EmailToClient'];
        $param['Paid'] = $opts['Paid'];
        $param['MarkAsPaid'] = $opts['MarkAsPaid'];
        $param['DiscountPercentage'] = $opts['DiscountPercentage'];
        $param['DiscountAmount'] = $opts['DiscountAmount'];
        $i = 0;
        foreach ($data as $val) {
            $param["data[$i][0]"] = $val[0];
            $param["data[$i][1]"] = $val[1];
            $param["data[$i][2]"] = $val[2];
            $param["data[$i][3]"] = $val[3];
            $param["data[$i][4]"] = $val[4];
            $param["data[$i][5]"] = $val[5];
            $param["data[$i][6]"] = $val[6];
            $param["data[$i][7]"] = $val[7];
            $i++;
        }

        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, -1);
        $url = $this->API_url . 'GenerateNewInvoice.php';
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url); //set the url
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //return as a variable
        curl_setopt($ch, CURLOPT_POST, 1); //set POST method
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request); //set the POST variables
        $result = curl_exec($ch); //run the whole process and return the response
        //var_dump($result);
        //var_dump(curl_getinfo($ch));
        return $result;
    }
    
    public function CreateNewProformaInvoice($BID, $ClientID, $OrderNR, $data, $opts = false) {
        /*

         * data is array like this
          $data[$i][0] = 'D_'.$value.'_'.date('n', time()).'_'.date('Y', time());
          $data[$i][1] = 1;
          $data[$i][2] = stripslashes($domres['child_name']).' '.stripslashes($domres['child_surname']).' Subscription for '.date('F', time()).' '.date('Y', time());
          $data[$i][3] = ($amount);
          $data[$i][4] = 'ZAR';
          $data[$i][5] = '1';
          $data[$i][6] = '14.00';
          $data[$i][7] = '1';
          $data[$i][8] = '';
          $data[$i][9] = '';
          $data[$i][10] = '';
         *
          data[0] - prod_code
          data[1] - qty
          data[2] - description
          data[3] - amount
          data[4] - currency
          data[5] - vat_applies
          data[6] - vat_percentage
          data[7] - amount_includes_vat
          data[8] - custom1
          data[9] - custom2
          data[10] - custom3
         */

        /**/
        if (!isset($opts['IncludesVat'])) {
            $opts['IncludesVat'] = 'true';
        }
        if (!isset($opts['VatApplies'])) {
            $opts['VatApplies'] = 'true';
        }
        if (!isset($opts['EmailToClient'])) {
            $opts['EmailToClient'] = 'true';
        }
        if (!isset($opts['Paid'])) {
            $opts['Paid'] = 'false';
        }
        if (!isset($opts['MarkAsPaid'])) {
            $opts['MarkAsPaid'] = 'false';
        }
        if (!isset($opts['DiscountPercentage'])) {
            $opts['DiscountPercentage'] = 0;
        }
        if (!isset($opts['DiscountAmount'])) {
            $opts['DiscountAmount'] = 0;
        }
        unset($param, $request);
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['BID'] = $BID;

        $param['ClientID'] = $ClientID;
        $param['ClientOrderNr'] = $OrderNR;
        $param['IncludesVat'] = $opts['IncludesVat'];
        $param['VatApplies'] = $opts['VatApplies'];
        $param['EmailToClient'] = $opts['EmailToClient'];
        $param['Paid'] = $opts['Paid'];
        $param['MarkAsPaid'] = $opts['MarkAsPaid'];
        $param['DiscountPercentage'] = $opts['DiscountPercentage'];
        $param['DiscountAmount'] = $opts['DiscountAmount'];
        $i = 0;
        foreach ($data as $val) {
            $param["data[$i][0]"] = $val[0];
            $param["data[$i][1]"] = $val[1];
            $param["data[$i][2]"] = $val[2];
            $param["data[$i][3]"] = $val[3];
            $param["data[$i][4]"] = $val[4];
            $param["data[$i][5]"] = $val[5];
            $param["data[$i][6]"] = $val[6];
            $param["data[$i][7]"] = $val[7];
            $i++;
        }

        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, -1);
        $url = $this->API_url . 'GenerateNewProFormaInvoice.php';
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url); //set the url
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //return as a variable
        curl_setopt($ch, CURLOPT_POST, 1); //set POST method
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request); //set the POST variables
        $result = curl_exec($ch); //run the whole process and return the response
        //var_dump($result);
        //var_dump(curl_getinfo($ch));
        return $result;
    }

    public function CreateNewPayment($BID, $ClientID, $opts = false) {
        /*

         * data is array like this
          $data[$i][0] = 'D_'.$value.'_'.date('n', time()).'_'.date('Y', time());
          $data[$i][1] = 1;
          $data[$i][2] = stripslashes($domres['child_name']).' '.stripslashes($domres['child_surname']).' Subscription for '.date('F', time()).' '.date('Y', time());
          $data[$i][3] = ($amount);
          $data[$i][4] = 'ZAR';
          $data[$i][5] = '1';
          $data[$i][6] = '14.00';
          $data[$i][7] = '1';
          $data[$i][8] = '';
          $data[$i][9] = '';
          $data[$i][10] = '';
         *
          data[0] - prod_code
          data[1] - qty
          data[2] - description
          data[3] - amount
          data[4] - currency
          data[5] - vat_applies
          data[6] - vat_percentage
          data[7] - amount_includes_vat
          data[8] - custom1
          data[9] - custom2
          data[10] - custom3
         */

        /**/
        if (!isset($opts['PaymentAmount'])) {
            return false;
        }
        if (!isset($opts['payment_method'])) {
            $opts['payment_method'] = 'EFT';
        }
        if (!isset($opts['payment_date'])) {
            $opts['payment_date'] = date('Y-m-d');
        }
        if (!isset($opts['reference_number'])) {
            $opts['reference_number'] = 'Payment ' . $opts['payment_date'];
        }

        unset($param, $request);
        $param['username'] = $this->username;
        $param['password'] = $this->password;
        $param['BID'] = $BID;

        $param['ClientID'] = $ClientID;
        $param['reference_number'] = $opts['reference_number'];
        $param['payment_method'] = $opts['payment_method'];
        $param['payment_date'] = $opts['payment_date'];
        $param['PaymentAmount'] = $opts['PaymentAmount'];
        $param['Description'] = $opts['Description'];

        foreach ($param as $key => $val) {
            $request.= $key . '=' . urlencode($val);
            $request.= '&';
        }
        $request = substr($request, 0, -1);
        //echo $request.'<br />';
        $url = $this->API_url . 'RecordPayment.php';
        //echo $url.'<br />';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url); //set the url
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); //return as a variable
        curl_setopt($ch, CURLOPT_POST, 1); //set POST method
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request); //set the POST variables
        $result = curl_exec($ch); //run the whole process and return the response
        //var_dump($result);
        //var_dump(curl_getinfo($ch));
        return $result;
    }

}

?>