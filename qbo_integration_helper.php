<?php 
use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Facades\Customer;
use QuickBooksOnline\API\Facades\Invoice;
use QuickBooksOnline\API\Facades\Item;
use QuickBooksOnline\API\Facades\Payment;
use QuickBooksOnline\API\Core\Http\Serialization\XmlObjectSerializer;

class QboIntegrationHelper {

    public function __construct($dataService= ''){
        if(empty($dataService)){
           Loader::loadComponents($this, array("Record"));
            Loader::loadModels($this, ['QboIntegration.QboIntegrationTransaction']);
            $dataService = $this->buildNewDataService();
        }
        $this->dataService = $dataService;
    }
     /**
     * 
     * This function will build a new dataservice
     */
    public function buildNewDataService()
    {
        
        $config = $this->Record->select()->from('qbo_configuration')->fetchAll();
        if(!$config){
            return false;
        }
        $client_id = $config[0]->client_id;
        $client_secret = $config[0]->client_secret;
        $accessToken = $config[0]->access_token;
        $refreshToken = $config[0]->refresh_token;
        $realmId =$config[0]->realmid;
        $error = false;
        $dataService = DataService::Configure([
         'auth_mode' => 'oauth2',
          'ClientID' => $client_id,
         'ClientSecret' => $client_secret,
         'accessTokenKey' => $accessToken,
         'refreshTokenKey' => $refreshToken,
         'QBORealmID' => $realmId,
         'baseUrl' => "development"
        ]);
        try {
            $OAuth2LoginHelper = $dataService->getOAuth2LoginHelper();
            $accessToken = $OAuth2LoginHelper->refreshToken();

            $newAccTok = $accessToken->getAccessToken();
            $newRefTok = $accessToken->getRefreshToken();
         
             $this->updateQBOTokens($newAccTok, $newRefTok, $config);
             
            $TokError = $OAuth2LoginHelper->getLastError();
            
            if ($TokError) {
                $accessToken = $OAuth2LoginHelper->refreshAccessTokenWithRefreshToken($refreshToken);

                $newAccTok = $accessToken->getRefreshToken();
                $newRefTok = $accessToken->getRefreshToken();

                $this->updateQBOTokens($newAccTok, $newRefTok, $config);
            }
        } catch (Exception $error) {
            $error = true;
        }
        $errorqbo = $OAuth2LoginHelper->getLastError();

        if ($errorqbo || $accessToken == '') {
            $error = true;
        }

        if ($error) {
            return false;
            throw new Exception('Please try after some times');
        }

        $dataService->updateOAuth2Token($accessToken);
       
        return $dataService;
    }
    /**
     * 
     * Update a new access and refresh token for particular company
     */
    public function updateQBOTokens($newAccTok, $newRefTok, $config){
        $record_id = $config[0]->id;
        $date = date('Y-m-d H:i:s');
        Loader::loadModels($this, ['QboIntegration.QboIntegrationConfiguration']);
        $updateData = [
            'access_token'=>$newAccTok,
            'refresh_token'=>$newRefTok,
            'realmid'=>$config[0]->realmid,
            'is_connected'=>1,
            'created_at'=>$date,
            'updated_at'=>$date,
        ];
        
        $id = $this->QboIntegrationConfiguration->update($record_id,$updateData);
        return $id;
    }
    /**
     * 
     * create a new customer in quick books 
     */
    public function newQboCustomer($customer_data){
      //   echo "<pre>";print_r([$customer_data]);die;
         
          $phone_numbers = isset($customer_data['numbers']) ? $customer_data['numbers'] : '';
      //   echo "<pre>";print_r($phone_numbers);die;
          $customer_data['notes'] = isset( $customer_data['notes']) ?  $customer_data['notes'] : "";
          if(is_array($phone_numbers)){
            if(isset($phone_numbers['type'])){
                $type = $phone_numbers['type'];
                $location = $phone_numbers['location'];
                $number = $phone_numbers['number'];
                for($i=0;$i<count($type);$i++){
                    if($type[$i] == 'phone' && $location[$i] =='mobile'){
                        //get number 
                        $phone_number['mobile'] = $number[$i];
                    }
                    elseif($type[$i] =='phone' && $location[$i] == 'home'){
                        $phone_number['home'] = $number[$i];
                    }
                    elseif($type[$i] =='phone' && $location[$i] == 'work'){
                        $phone_number['work'] = $number[$i];
                    }elseif($type[$i] == 'fax'){
                        $phone_number['fax']= $number[$i];
                    }
                }
            }else{

          for($i=0;$i<count($phone_numbers);$i++){
              if($phone_numbers[$i]['type'] == 'phone' && $phone_numbers[$i]['location'] =='mobile'){
                  //get number if
                  $phone_number['mobile'] = $phone_numbers[$i]['number'];
              }
              elseif($phone_numbers[$i]['type'] == 'phone' && $phone_numbers[$i]['location'] =='home'){
                  $phone_number['home'] = $phone_numbers[$i]['number'];
              }
              elseif($phone_numbers[$i]['type'] == 'phone' && $phone_numbers[$i]['location'] =='work'){
                  $phone_number['work'] = $phone_numbers[$i]['number'];
              }elseif($phone_numbers[$i]['type'] == 'fax'){
                  $phone_number['fax']= $phone_numbers[$i]['number'];
              }
          }
        }

        }
     //   echo "<pre>";print_r($customer_data);die;
        
        $customerObj= Customer::create([
            "BillAddr" => [
               "Line1"=>  $customer_data['address1'],
               "City"=>  $customer_data['city'],
               "Country"=>  $customer_data['country'],
               "CountrySubDivisionCode"=>  $customer_data['country'],
               "PostalCode"=>   $customer_data['zip']
           ],
           "Notes" =>  $customer_data['notes'],
           "Title"=>   $customer_data['title'],
           "GivenName"=> $customer_data['first_name'],
           "MiddleName"=>  $customer_data['last_name'],
           "FamilyName"=>  "Family",
           "Suffix"=>  "Jr",
           "FullyQualifiedName"=>  $customer_data['first_name'] . ' ' . $customer_data['last_name'],
           "CompanyName"=>   $customer_data['company'],
           "DisplayName"=>  $customer_data['first_name'] . ' ' . $customer_data['last_name'],
           "PrimaryPhone"=>  [
            "FreeFormNumber"=> isset($phone_number['home']) ? $phone_number['home'] : $customer_data['number']
          ],
          "Mobile"=>[
           "FreeFormNumber"=>isset($phone_number['mobile']) ? $phone_number['mobile'] : $customer_data['number']
          ],
          "AlternatePhone"=>[
           "FreeFormNumber"=>isset($phone_number['work']) ? $phone_number['work'] : $customer_data['number']
          ],
          "Fax"=>[
            "FreeFormNumber"=>isset($phone_number['fax']) ? $phone_number['fax'] : ''
          ],
           "PrimaryEmailAddr"=>  [
               "Address" =>  $customer_data['email']
           ]
          ]);
       //   echo "<pre>";print_R($customerObj);die;
          $resultingCustomerObj = $this->dataService->Add($customerObj);
          $error = $this->dataService->getLastError();
          if ($error) {
              echo "The Status code is: " . $error->getHttpStatusCode() . "\n";
              echo "The Helper message is: " . $error->getOAuthHelperError() . "\n";
              echo "The Response message is: " . $error->getResponseBody() . "\n";
            //  $this->setMessage('error', $error->getResponseBody() , false, null, false);
            return ;
          } else {
            return $resultingCustomerObj;
          }
    }
    /**
     * 
     * Update a quick books customer
     */
    public function updateQboCustomer($customer_data,$qboCustomerId){
     
        if($qboCustomerId){
        //get quickbooks id 
        $entities = $this->dataService->Query("SELECT * FROM Customer where Id='$qboCustomerId'");
       //Get the first element
        $theCustomer = reset($entities);
        $phone_numbers = isset($customer_data['numbers']) ? $customer_data['numbers'] : '';
        $customer_data['notes'] = isset( $customer_data['notes']) ?  $customer_data['notes'] : "";
        if(is_array($phone_numbers)){
        $type = $phone_numbers['type'];
        $location = $phone_numbers['location'];
        $number = $phone_numbers['number'];
        for($i=0;$i<count($type);$i++){
            if($type[$i] == 'phone' && $location[$i] =='mobile'){
                //get number 
                $phone_number['mobile'] = $number[$i];
            }
            elseif($type[$i] =='phone' && $location[$i] == 'home'){
                $phone_number['home'] = $number[$i];
            }
            elseif($type[$i] =='phone' && $location[$i] == 'work'){
                $phone_number['work'] = $number[$i];
            }elseif($type[$i] == 'fax'){
                $phone_number['fax']= $number[$i];
            }
        }
    }else{
        if($customer_data['type'] == 'phone' && $customer_data['location'] == 'home'){
            $phone_number['home'] = $customer_data['number'];
        }elseif($customer_data['type'] == 'phone' && $customer_data['location']  =='mobile'){
            //get number 
            $phone_number['mobile'] = $customer_data['number'];
        }elseif($customer_data['type'] == 'phone' && $customer_data['location']  =='mobile'){
            //get number 
            $phone_number['work'] = $customer_data['number'];
        }
      //  echo "<pre>";print_R($phone_number);

    }
    //   echo "<pre>";print_r([$phone_numbers,$phone_number]);die;
        $updateCustomer= Customer::update($theCustomer,[
            "BillAddr" => [
               "Line1"=>  $customer_data['address1'],
               "City"=>  $customer_data['city'],
            //   "Line2"=>$customer_data['state'],
               "Country"=>  $customer_data['country'],
               "CountrySubDivisionCode"=>  $customer_data['country'],
               "PostalCode"=>   $customer_data['zip']
           ],
           "Notes" =>  $customer_data['notes'],
           "Title"=>   $customer_data['title'],
           "GivenName"=> $customer_data['first_name'],
           "MiddleName"=>  $customer_data['last_name'],
           "FamilyName"=>  "Family",
           "Suffix"=>  "Jr",
           "FullyQualifiedName"=>  $customer_data['first_name'] . ' ' . $customer_data['last_name'],
           "CompanyName"=>   $customer_data['company'],
           "DisplayName"=>  $customer_data['first_name'] . ' ' . $customer_data['last_name'],
           "PrimaryPhone"=>  [
             "FreeFormNumber"=> isset($phone_number['home']) ? $phone_number['home'] : ''
           ],
           "Mobile"=>[
            "FreeFormNumber"=>isset($phone_number['mobile']) ? $phone_number['mobile'] : ''
           ],
           "AlternatePhone"=>[
            "FreeFormNumber"=>isset($phone_number['work']) ? $phone_number['work'] : ''
           ],
           "Fax"=>[
             "FreeFormNumber"=>isset($phone_number['fax']) ? $phone_number['fax'] : ''
           ],
           "PrimaryEmailAddr"=>  [
               "Address" =>  $customer_data['email']
           ]
          ]);
          $resultingCustomerUpdatedObj = $this->dataService->Update($updateCustomer);
       //  echo "<pre>";print_r($resultingCustomerUpdatedObj);die;

          $error = $this->dataService->getLastError();
          if ($error) {
              echo "The Status code is: " . $error->getHttpStatusCode() . "\n";
              echo "The Helper message is: " . $error->getOAuthHelperError() . "\n";
              echo "The Response message is: " . $error->getResponseBody() . "\n";
            //  $this->setMessage('error', $error->getResponseBody() , false, null, false);
              die;
          } else {
                return $resultingCustomerUpdatedObj;
          }
        }
    }
     /**
     * 
     * create a new invoices in quick books 
     */
    public function newQboInvoice($blesta_invoice_data){

            $qbo_customer_id  =  $blesta_invoice_data->qbo_id;
            $blesta_client_id = $blesta_invoice_data->client_id;
            $qbo_exist_customer_id = $this->checkQboCustomer($qbo_customer_id,$blesta_client_id);
          //  echo "<pre>";print_r($qbo_exist_customer_id);die;

            if($qbo_exist_customer_id){
            $qbo_invoice_doc_number = $blesta_invoice_data->qbo_invoice_doc_number;
            $lineItems = $blesta_invoice_data->line_items;
            for($i=0;$i<count($lineItems);$i++){
                $lineItemsArr[] = [
                    "Amount" => $lineItems[$i]->total,
                    "DetailType" => "SalesItemLineDetail",
                    "SalesItemLineDetail" => [
                      "ItemRef" => [
                        "value" => $lineItems[$i]->qbo_item_id,
                        "name" => $lineItems[$i]->description,
                      ],
                      'Qty'=>$lineItems[$i]->qty,
                     ]
                ];
            }
        //    echo "<pre>";print_r($lineItemsArr);die;
            $theResourceObj = Invoice::create([ 
                "DocNumber" => $qbo_invoice_doc_number,
                "Line" => $lineItemsArr,
            "CustomerRef"=> [
             "value"=> $qbo_exist_customer_id
            ],
                 "BillEmail" => [
                       "Address" =>$blesta_invoice_data->email
                 ],
            ]);
        }
//         echo "<pre>";print_r($qbo_exist_customer_id);die;

        $resultingInvoiceObj = $this->dataService->Add($theResourceObj);
        $error = $this->dataService->getLastError();
        if ($error) {
            echo "The Status code is: " . $error->getHttpStatusCode() . "\n";
            echo "The Helper message is: " . $error->getOAuthHelperError() . "\n";
            echo "The Response message is: " . $error->getResponseBody() . "\n";
           // $this->setMessage('error', $error->getResponseBody() , false, null, false);
        } else {
            return $resultingInvoiceObj;
        }
    }
    /**
     * 
     * update a quick books invoice
     */
    public function updateQboInvoice($blesta_invoice_data,$qboInvoiceId){
            //
            // echo "<pre>";print_r($blesta_invoice_data);die;
            if($qboInvoiceId){
             $entities = $this->dataService->Query("SELECT * FROM Invoice where Id='$qboInvoiceId'");
            $qbo_customer_id  =  $blesta_invoice_data->qbo_id;
            $blesta_client_id = $blesta_invoice_data->client_id;
            $qbo_exist_customer_id = $this->checkQboCustomer($qbo_customer_id,$blesta_client_id);

           //  echo "<pre>";print_r($entities);die;
             //Get the first element
               $theInvoice = reset($entities);
                if($theInvoice && $qbo_exist_customer_id){
                    $lineItems = $blesta_invoice_data->line_items;
                    for($i=0;$i<count($lineItems);$i++){
                        $lineItemsArr[] = [
                            "Amount" => $lineItems[$i]->total,
                            "DetailType" => "SalesItemLineDetail",
                            "SalesItemLineDetail" => [
                              "ItemRef" => [
                                "value" => 1,
                                "name" => $lineItems[$i]->description,
                              ],
                              'Qty'=>$lineItems[$i]->qty,
                             ]
                        ];
                    }
                    $updateResourceObj = Invoice::update($theInvoice,[ 
                        "Line" => $lineItemsArr,
                          "CustomerRef"=> [
                           "value"=> $qbo_exist_customer_id
                          ],
                        "BillEmail" => [
                            "Address" =>$blesta_invoice_data->email
                        ],
                    ]);
                    $resultingInvoiceUpdatedObj = $this->dataService->Update($updateResourceObj);
                    $error = $this->dataService->getLastError();
                    if ($error) {
                        echo "The Status code is: " . $error->getHttpStatusCode() . "\n";
                        echo "The Helper message is: " . $error->getOAuthHelperError() . "\n";
                        echo "The Response message is: " . $error->getResponseBody() . "\n";
                      //  $this->setMessage('error', $error->getResponseBody() , false, null, false);
                    } else {
                            return $resultingInvoiceUpdatedObj;
                    }
                }
            }
    }
    /**
     * 
     * Create a new item in quick books
     */
    public function newQboItem($blesta_item_data,$qbo_product_accounts){
        
      //  echo "<pre>";print_r($blesta_item_data);die;
        $blesta_package_name = $blesta_item_data->name;
        $qbo_income_account = isset($qbo_product_accounts->income_account) ? $qbo_product_accounts->income_account : 1;
        $qbo_expense_account = isset($qbo_product_accounts->expense_account) ? $qbo_product_accounts->expense_account : 1;
        $qbo_asset_account = isset($qbo_product_accounts->asset_account) ? $qbo_product_accounts->asset_account : 1;
        $qbo_product_type = $qbo_product_accounts->product_type;
        //echo $qbo_product_type;die;
        if($qbo_product_type != 'Inventory'){
            $TrackQtyOnHand = false;
            $taxable = false;
        }else{
            $TrackQtyOnHand =  true;
            $taxable = true;

        }
//        echo "<pre>";print_r($qbo_product_accounts);die;
        //check if product is exist or not in qbo
    //    $check_product = $this->checkQboItem($blesta_package_name);
            $dateTime = new \DateTime('NOW');
            $Item = Item::create([
                  "Name" => $blesta_package_name,
                  "Description" => $blesta_item_data->text,
                  "Active" => true,
                  "FullyQualifiedName" => "Office Supplies",
                  "Taxable" => $taxable,
                  "UnitPrice" => $blesta_item_data->price,
                  "Type" => $qbo_product_type,
                  "IncomeAccountRef"=> [
                    "value"=> $qbo_income_account,
                 //   "name" => "Landscaping Services:Job Materials:Fountains and Garden  "
                  ],
                  "PurchaseDesc"=> "This is the purchasing description.",
                  "PurchaseCost"=> $blesta_item_data->price,
                  "ExpenseAccountRef"=> [
                    "value"=> $qbo_expense_account,
                   // "name"=> "Cost of Goods Sold"
                  ],
                  "AssetAccountRef"=> [
                    "value"=> $qbo_asset_account,
                   // "name"=> "Inventory Asset"
                  ],
                  
                  "TrackQtyOnHand" => $TrackQtyOnHand,
                  "QtyOnHand"=> $blesta_item_data->qty ? $blesta_item_data->qty : 1,
                  "InvStartDate"=> $dateTime
            ]);
           // echo "<pre>";print_r($Item);die;
            $resultingItemObj = $this->dataService->Add($Item);
            $error = $this->dataService->getLastError();
            if ($error) {
                echo "The Status code is: " . $error->getHttpStatusCode() . "\n";
                echo "The Helper message is: " . $error->getOAuthHelperError() . "\n";
                echo "The Response message is: " . $error->getResponseBody() . "\n";
                die;
            } else {
                    return $resultingItemObj;
            }
    }
    /**
     * 
     * update qbo item 
     */
    public function updateQboItem($blesta_item_data,$qboItemId,$qbo_product_accounts){
        $qboItemId = $qboItemId->qbo_id;
        $blesta_package_name = $blesta_item_data->name;
        $entities = $this->dataService->Query("SELECT * FROM Item where Id='$qboItemId'");
        $theItem = reset($entities);
        $qbo_income_account = isset($qbo_product_accounts->income_account) ? $qbo_product_accounts->income_account : 1;
        $qbo_expense_account = isset($qbo_product_accounts->expense_account) ? $qbo_product_accounts->expense_account : 1;
        $qbo_asset_account = isset($qbo_product_accounts->asset_account) ? $qbo_product_accounts->asset_account : 1;
        $qbo_product_type = $qbo_product_accounts->product_type;
        //echo $qbo_product_type;die;
        if($qbo_product_type != 'Inventory'){
            $TrackQtyOnHand = false;
            $taxable = false;
        }else{
            $TrackQtyOnHand =  true;
            $taxable = true;

        }
        if($theItem){
            $dateTime = new \DateTime('NOW');
            $Item = Item::update($theItem,[
                "Name" => $blesta_package_name,
                "Description" => $blesta_item_data->text,
                "Active" => true,
                "FullyQualifiedName" => "Office Supplies",
                "Taxable" => $taxable,
                "UnitPrice" => $blesta_item_data->price,
                "Type" => $qbo_product_type,
                "IncomeAccountRef"=> [
                  "value"=> $qbo_income_account,
               //   "name" => "Landscaping Services:Job Materials:Fountains and Garden  "
                ],
                "PurchaseDesc"=> "This is the purchasing description.",
                "PurchaseCost"=> $blesta_item_data->price,
                "ExpenseAccountRef"=> [
                  "value"=> $qbo_expense_account,
                 // "name"=> "Cost of Goods Sold"
                ],
                "AssetAccountRef"=> [
                  "value"=> $qbo_asset_account,
                 // "name"=> "Inventory Asset"
                ],
                
                "TrackQtyOnHand" => $TrackQtyOnHand,
                "QtyOnHand"=> $blesta_item_data->qty ? $blesta_item_data->qty : 1,
                "InvStartDate"=> $dateTime
            ]);
            $resultingItemObj = $this->dataService->update($Item);
            $error = $this->dataService->getLastError();
            if ($error) {
                echo "The Status code is: " . $error->getHttpStatusCode() . "\n";
                echo "The Helper message is: " . $error->getOAuthHelperError() . "\n";
                echo "The Response message is: " . $error->getResponseBody() . "\n";
                die;
            } else {
                    return $resultingItemObj;
            }
        }

    }
    /**
     * 
     * Check if customer is exist or not in qbo 
     * if not create a new customer
     */
    public function checkQboCustomer($qboCustomerId,$blesta_client_id){
     //   echo $blesta_client_id;die;
        //get qbo customer id 
        $qbo_transaction_data = $this->Record->select(array("qbo_transaction.qbo_id"))
        ->from("qbo_transaction")
        ->where('qbo_transaction.blesta_id','=',$blesta_client_id)
        ->where('qbo_transaction.is_mapping','=',0)
        ->like('qbo_transaction.type','%customer%')
        ->fetch();
       // echo "<pre>";print_r($qbo_transaction_data);die;
        $qboCustomerId = isset($qbo_transaction_data->qbo_id) ? $qbo_transaction_data->qbo_id : '';
     //   $entities = $this->dataService->Query("SELECT * FROM Customer where Id='$qboCustomerId'");
        $i =0 ;
        if(empty($qbo_transaction_data)){
           //create a new customer if not exist in qbo 
            Loader::LoadModels($this,['Clients','QboIntegrationTransaction']);
            $blesta_customer_data = $this->Record->select(array("contacts.*","client_notes.*","contact_numbers.*","clients.*",))
            ->from("clients")
            ->innerJoin("contacts", "contacts.client_id", "=", "clients.id", false)
            ->leftJoin("client_notes", "client_notes.client_id", "=", "clients.id", false)
            ->leftJoin("contact_numbers", "contact_numbers.contact_id", "=", "contacts.id", false)
            ->where('clients.id','=',$blesta_client_id)
            ->fetch();
            $blesta_customer_dataArr = (array)$blesta_customer_data;
        //    echo "<pre>";print_R($blesta_customer_dataArr);die;
            $resultingCustomerObj = $this->newQboCustomer($blesta_customer_dataArr);
            $createdQboData = [
                'type'=>'customer',
                'blesta_id'=>$blesta_client_id,
                'qbo_id'=>$resultingCustomerObj->Id,
                'status'=>'created'
            ];
            $transactionID  = $this->QboIntegrationTransaction->add($createdQboData);
            $qbo_exist_customer_id = $resultingCustomerObj->Id;

        }else{
           // die("sds");
            $entities = $this->dataService->Query("SELECT * FROM Customer where Id='$qboCustomerId'");
            $qbo_exist_customer_id = $entities[$i]->Id;
           // echo $qbo_exist_customer_id;die;
        }
        return $qbo_exist_customer_id;
    }
    /**
     * 
     * check if invoices is exist or  not in qbo
     * if not create a new invoice
     */
    public function checkQboInvoice($qboInvoiceId){

    }
    /**
     * 
     * Check if item is exist or not in quickbooks
     */
    public function checkQboItem($qbo_item_name){
        $entities = $this->dataService->Query("select * from Item where name = '$qbo_item_name'");
        $i =0 ;
        if($entities){
            $qbo_exist_item_id = $entities[$i]->Id;
            return $qbo_exist_item_id;
        } 
    }
    /**
     * 
     * This function will return quick books customer 
     */
    public function getEntitiesList($entityName){
         
        if($entityName == 'product'){
            $entityName = 'item';
        }
         $qboEntitiesData = $this->dataService->Query("select * from $entityName");
        // echo "<pre>";print_r($qboEntitiesData);die;
        if($qboEntitiesData){
            return $qboEntitiesData;
        }
    }
    /**
     * 
     * get qbo id which are created in qbo with blesta data
     */
    public function getQboData($type,$qboEntityData,$qboIds){
      
        $decodedQboEntityData = isset($qboEntityData->qbo_data) ? json_decode($qboEntityData->qbo_data) : [];
     //   echo "<pre>";print_r($decodedQboEntityData);die;
        if(count($qboIds) > 0 && $decodedQboEntityData){
             foreach($qboIds as $key=>$value){
                 $qboIdsArr[] = $value->qbo_id;
             }       
            
            //skip entity data that are already created in qb
            foreach($decodedQboEntityData->Id as $entity_key=>$entity_value){
                    if(in_array($entity_key,$qboIdsArr)){
                       unset($decodedQboEntityData->Id->$entity_key);
                    }
            }
        }
        return $decodedQboEntityData;
      }
      public function newQboPayment(){
        $PaymentObj = Payment::create([
                "TotalAmt"=> 75.00, 
                "Line"=> [
                  [
                    "Amount"=> 25, 
                    "LinkedTxn"=> [
                      [
                        "TxnId"=> "485", 
                        "TxnType"=> "Invoice"
                      ]
                    ]
                  ]
                ], 
                "CustomerRef"=> [
                  "value"=> "5"
        ]
        ]);
        $purchaseObjConfirmation = $this->dataService->Add($PaymentObj);
        echo "<pre>";print_r($purchaseObjConfirmation);die;
      }
}
