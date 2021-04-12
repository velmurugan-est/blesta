<?php
use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Core\Http\Serialization\XmlObjectSerializer;
use QuickBooksOnline\API\Facades\Payment;
use QuickBooksOnline\API\Facades\Invoice;

class AdminPaymentList extends QboIntegrationController{
    public function preAction()
    {
        parent::preAction();
        // Restore structure view location of the admin portal
        $this->structure->setDefaultView(APPDIR);
        $this->structure->setView(null, $this->orig_structure_view);        
    }
    public function index(){
        Loader::LoadComponents($this,['Record']);
        $transaction_list = $this->Record->select()->from("transactions")->fetchAll();
        foreach($transaction_list as $key=>$value){
            $transactionArr[] = (array)$value;
            $customersArr[] =  (array)$this->Record->select()->from("users")->where("users.id", "=", $value->client_id)->fetch();
        }
      //  echo "<pre>";print_r($transactionArr);die;
      
        $this->set('transactionArr',$transactionArr);
        $this->set('customersArr',$customersArr);
        return $this->renderAjaxWidgetIfAsync( );

    }
    public function post_payments(){

        $config = $this->Record->select()->from('qbo_configuration')->fetchAll();
        $company_id = $config[0]->realmid;
        $accessToken = $config[0]->access_token;
        $scope = 'com.intuit.quickbooks.accounting';
        $error = false;
        
        // Prep Data Services
       $dataService = DataService::Configure(array(
           'auth_mode'       => 'oauth2',
           'ClientID'        => $config[0]->client_id,
           'ClientSecret'    => $config[0]->client_secret,
           'accessTokenKey'  => $config[0]->access_token,
           'refreshTokenKey' => $config[0]->refresh_token,
           'QBORealmID'      =>$company_id,
           'baseUrl'         => "development"
       ));

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
  $theResourceObj = $dataService->FindbyId('payment', 11);
echo "<pre>";print_r($theResourceObj);die;
  $resultingObj = $dataService->void($theResourceObj);
  
  
  $error = $dataService->getLastError();
  if ($error) {
      echo "The Status code is: " . $error->getHttpStatusCode() . "\n";
      echo "The Helper message is: " . $error->getOAuthHelperError() . "\n";
      echo "The Response message is: " . $error->getResponseBody() . "\n";
  }
  else {
      echo "Created Id={$resultingObj->Id}. Reconstructed response body:\n\n";
      $xmlBody = XmlObjectSerializer::getPostXmlFromArbitraryEntity($resultingObj, $urlResource);
      echo $xmlBody . "\n";
  }
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
}