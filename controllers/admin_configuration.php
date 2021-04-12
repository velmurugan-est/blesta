<?php
use QuickBooksOnline\API\DataService\DataService;
class AdminConfiguration extends QboIntegrationController{
    public function preAction()
    {
        parent::preAction();
        // Restore structure view location of the admin portal
     //   $this->client_id = 'ABD7YXdrBZYd3Td1BQPGLoIzRpuhJ9ImMzPuOmugksGexQNTkc';
     //   $this->client_secret = '6OEOtCE4GSFesLMmb1ZvjakvWQpY50LcnKuvAZlv';
        $this->scope = 'com.intuit.quickbooks.accounting';
        $this->structure->setDefaultView(APPDIR);
        $this->structure->setView(null, $this->orig_structure_view);        
    }
    public function index(){
        
         $configData = $this->Record->select()->from("qbo_configuration")->fetchAll();
         $entityData = $this->Record->select()->from("qbo_entities")->fetchAll();
         if($entityData){
             foreach($entityData as $ety_key=>$ety_value){
                $date = strtotime(isset($ety_value->updated_at) ? $ety_value->updated_at : $ety_value->created_at);
                $entityDataArr[$ety_value->type] =  date("M d Y H:i:sa",$date);
             }  
             $this->set("entity_data",$entityDataArr);
         }
         $count = $this->Record->select()->from("qbo_configuration")->numResults();
         $action = isset($this->get['action']) ? $this->get['action'] : '';
        if($action == 'edit'){
            $retrived_data = $this->fetchRecord($configData);
            $this->set( 'vars',$retrived_data['vars']  );
            $this->set( 'is_connected',0 );
            return ;
        }
         if($count !=0 ){
            $retrived_data = $this->fetchRecord($configData);
             $this->set( 'auth_url',$retrived_data['authUrl'] );
             $this->set( 'vars',$retrived_data['vars']  );
             $this->set('count',$count);
             $this->set( 'is_connected',$configData[0]->is_connected );
         }
    }
    public function add(){

         //save configuration
         Loader::loadModels($this, ['QboIntegration.QboIntegrationConfiguration']);
         $data = $this->post;
         if(!empty($data)){
           $data['oauth_scope'] = 'com.intuit.quickbooks.accounting';
          $vars = [
              'client_id' => $data['client_id'],
              'client_secret'=>$data['client_secret'],
              'redirect_url'=>$data['redirect_url'],
          ];
         //count of configuration 
         $configData = $this->Record->select()->from("qbo_configuration")->fetchAll();
         $count = $this->Record->select()->from("qbo_configuration")->numResults();
         if($count !=0){
             //update a configuration data
             $retrived_data = $this->fetchRecord($configData);
             $this->set( 'auth_url',$retrived_data['authUrl'] );
             $this->set( 'vars',$retrived_data['vars']  );
             $this->set( 'is_connected',$configData[0]->is_connected );
         }else{
             $add = $this->QboIntegrationConfiguration->add($vars);
             if (($errors = $this->QboIntegrationConfiguration->errors())) {
                 // Error, reset vars
                 $vars = (array)$this->post;
                 $this->setMessage('error', $errors, false, null, false);
             } else {
                 // Success, add this configuration
                  $redirect_url = $data['redirect_url'].'plugin/qbo_integration/admin_configuration/getToken';
                  $OAuth2LoginHelper = $this->setupDataService($redirect_url);
                  // Get the Authorization URL from the SDK
                  $authUrl = $OAuth2LoginHelper->getAuthorizationCodeURL();
                 $this->set('auth_url',$authUrl);
                 $this->set('vars',$vars);
             }            
         }
       }
       $this->redirect($this->base_uri.'plugin/qbo_integration/admin_configuration');
    }
    /**
     * 
     * This function is for update configuration
     */
    public function edit(){
        Loader::loadModels($this, ['QboIntegration.QboIntegrationConfiguration']);
        $configData = $this->Record->select()->from("qbo_configuration")->fetch();
        $count = $this->Record->select()->from("qbo_configuration")->numResults();
        $data = $this->post;
    //    print_r($data);die;
        if($count !=0){
        $id = $configData->id;
        $updatedConfig = $this->QboIntegrationConfiguration->edit($id,$data);
       // echo $updatedConfig;die;
       $this->redirect($this->base_uri.'plugin/qbo_integration/admin_configuration');
        }
    }
    /**
     * 
     * Retrieve record 
     */
    public function fetchRecord($configData){
        $record_id = $configData[0]->id;
        $redirect_url = $configData[0]->redirect_url.'plugin/qbo_integration/admin_configuration/getToken';
        $OAuth2LoginHelper = $this->setupDataService($redirect_url);
        // Get the Authorization URL from the SDK
        $authUrl = $OAuth2LoginHelper->getAuthorizationCodeURL();  
        $vars = [
            'client_id'=>$configData[0]->client_id,
            'client_secret'=>$configData[0]->client_secret,
            'redirect_url'=>$configData[0]->redirect_url,
        ];
        $retrived_data = [];
        $retrived_data['authUrl'] = $authUrl;
        $retrived_data['vars'] = $vars;
        return $retrived_data;
    }
    public function setupDataService($redirect_url = ''){
         
        $configData = $this->Record->select()->from("qbo_configuration")->fetchAll();
        $dataService = DataService::Configure(array(
            'auth_mode' => 'oauth2',
            'ClientID' => $configData[0]->client_id,
            'ClientSecret' =>  $configData[0]->client_secret,
            'RedirectURI' => $redirect_url,
            'scope' => $this->scope,
            'baseUrl' => "development"
             ));
             $OAuth2LoginHelper = $dataService->getOAuth2LoginHelper();
             return $OAuth2LoginHelper;
    }
    public function getToken(){
        $configData = $this->Record->select()->from("qbo_configuration")->fetchAll();
        $record_id = $configData[0]->id;
        
        $redirect_url = $configData[0]->redirect_url.'plugin/qbo_integration/admin_configuration/getToken';
        $access_denied = isset($this->get['error']) ? $this->get['error'] : "";
        if($access_denied == 'access_denied'){
            echo "<h1>Access Denied</h1>";
            return false;
        }
        $parseUrl = $this->parseAuthRedirectUrl($_SERVER['QUERY_STRING']);
        $OAuth2LoginHelper = $this->setupDataService($redirect_url);
        
        $accessTokenObj = $OAuth2LoginHelper->exchangeAuthorizationCodeForToken($parseUrl['code'], $parseUrl['realmId']);
        $accessTokenValue = $accessTokenObj->getAccessToken();
        $refreshTokenValue = $accessTokenObj->getRefreshToken();
        $TokError = $OAuth2LoginHelper->getLastError();
          
        if ($TokError) {
            $accessToken = $OAuth2LoginHelper->refreshAccessTokenWithRefreshToken($refreshTokenValue);
            $newAccTok = $accessToken->getRefreshToken();
            $newRefTok = $accessToken->getRefreshToken();
        }
        if($accessTokenValue){
        $date = date('Y-m-d H:i:s');
        $updateData = [
            'access_token'=>$accessTokenValue,
            'refresh_token'=>$refreshTokenValue,
            'realmid'=>$parseUrl['realmId'],
            'is_connected'=>1,
            'created_at'=>$date,
            'updated_at'=>$date,
        ];
        Loader::loadModels($this, ['QboIntegration.QboIntegrationConfiguration']);
        $updated_id = $this->QboIntegrationConfiguration->update($record_id,$updateData);
    }
    }
    public function parseAuthRedirectUrl($url)
    {
        parse_str($url,$qsArray);
        return array(
            'code' => $qsArray['code'],
            'realmId' => $qsArray['realmId']
        );
    }
    /**
     * 
     * Qbo disconnection
     */
    public function disconnect(){

        $configData = $this->Record->select()->from("qbo_configuration")->fetchAll();
        $count = $this->Record->select()->from("qbo_configuration")->numResults();
        $company_id = $configData[0]->realmid;
        if($company_id !=''){
            $dataService = $this->buildNewDataService($company_id);
            if ($dataService) {
                $OAuth2LoginHelper = $dataService->getOAuth2LoginHelper();
                $revokeResult = $OAuth2LoginHelper->revokeToken($configData[0]->refresh_token);
                $this->flashMessage(
                    'message',
                    "Disconnected Successfully",
                    null,
                    false
                );
            }else{
                $error = "API credentials are wrong..";
                $this->flashMessage('error', $error,null,false);
            }
        }
        $this->redirect($this->base_uri . 'plugin/qbo_integration/admin_configuration');    
    }
    /**
     * 
     * This function will build a new dataservice
     */
    public function buildNewDataService($comp_id = '')
    {
       
        $config = $this->Record->select()->from('qbo_configuration')->fetchAll();
        
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
       
            $this->updateQBOTokens($newAccTok, $newRefTok,$config);

            $TokError = $OAuth2LoginHelper->getLastError();

            if ($TokError) {
                $accessToken = $OAuth2LoginHelper->refreshAccessTokenWithRefreshToken($refreshToken);

                $newAccTok = $accessToken->getRefreshToken();
                $newRefTok = $accessToken->getRefreshToken();

                $this->updateQBOTokens($newAccTok, $newRefTok,$config);
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
            'is_connected'=>0,
            'created_at'=>$date,
            'updated_at'=>$date,
        ];
        $id = $this->QboIntegrationConfiguration->update($record_id,$updateData);
        return $id;
    }
    /**
     * 
     * This function will trigger get qbo entities list
     */
    public function getList(){
         $type = isset($this->get['type']) ? $this->get['type'] : "";
         if($type){
            $qbo_helper = new QboIntegrationHelper();
            $qboEntitiesData = $qbo_helper->getEntitiesList($type);
            foreach($qboEntitiesData as $qb_key=>$qb_value){
                if($type == 'customer' || $type == 'product'){
                $qboEntitiesDataArr['Id'][$qb_value->Id] = $qb_value->FullyQualifiedName;
                }elseif($type == 'account'){
                 $qboEntitiesDataArr[$qb_key]['Id']= $qb_value->Id;
                 $qboEntitiesDataArr[$qb_key]['Name']= $qb_value->Name;
                 $qboEntitiesDataArr[$qb_key]['AccountType'] = $qb_value->AccountType;
                 $qboEntitiesDataArr[$qb_key]['AccountSubType'] = $qb_value->AccountSubType;

                }elseif($type == 'invoice'){
                    echo "<pre>";print_R($qb_value);die;
                }
            }
         //   echo "<pre>";print_r([$qboEntitiesData,$qboEntitiesDataArr]);die;
            //check if entity data is exist or not in qbo_entities table
            $entity_exist = $this->Record->select()->from("qbo_entities")->like("type","%$type%")->fetchAll();
            $date = date('Y-m-d H:i:s');
            if(count($entity_exist) > 0){
                $updatedEntityData = [
                    'type'=>$type,
                    'qbo_data'=>json_encode($qboEntitiesDataArr),
                    'updated_at'=>$date
                ];
                $entity_exist_id = $entity_exist[0]->id;
                $this->Record->where("id", "=", $entity_exist_id)->update("qbo_entities", $updatedEntityData);
                if($entity_exist_id){
                    $reponse = [];
                    $response['status'] = true;
                    $response['type'] = ucfirst($type);
                    $response['last_sync_date'] = date("M d Y H:i:sa",strtotime($date));
                    echo json_encode($response);
                    die;
                }
            }else{         
                $entityData = [
                    'type'=>$type,
                    'qbo_data'=>json_encode($qboEntitiesDataArr),
                    'created_at'=>$date
                ];
            $newEntity =  $this->Record->insert("qbo_entities",$entityData);
            $entityId = $this->Record->lastInsertId();
            if($entityId){
                $reponse = [];
                    $response['status'] = true;
                    $response['type'] = ucfirst($type);
                    $response['last_sync_date'] = date("M d Y H:i:sa",strtotime($date));
                    echo json_encode($response);
                    die;
            }
        }
         }
        // $this->redirect($this->base_uri."plugin/qbo_integration/admin_configuration");
    }
    
}