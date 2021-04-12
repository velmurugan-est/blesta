<?php
class AdminSettings extends QboIntegrationController{

    public function preAction()
    {
        parent::preAction();
        // Restore structure view location of the admin portal
        $this->structure->setDefaultView(APPDIR);
        $this->structure->setView(null, $this->orig_structure_view);    
        Loader::loadComponents($this, array("Record"));

    }

    public function index(){
            //get qb account details
            $qbo_acc_data = $this->Record->select()
            ->from('qbo_entities')
            ->like('type','%account%')
            ->fetch();
            $product_type_data = $this->Record->select(array("qbo_product_settings.*"))
            ->from("qbo_product_settings")
            ->fetch();
            //echo "<pre>";print_R($qbo_acc_data);die;
            $decoded_acc_data = isset($qbo_acc_data->qbo_data) ? json_decode($qbo_acc_data->qbo_data) : '';
            
           // echo "<pre>";print_r($decoded_acc_data);die;
            $this->set('account_data',$decoded_acc_data);
            $this->set('product_type_data',$product_type_data);
    }   
    /**
     * 
     * This function is to save the settings data
     */
    public function saveSettings(){
        $formData = $this->post;
       
        if($formData){
            $product_type = $formData['product_type'];
            unset($formData['product_type']);
            $product_settings_data = [
              'product_type'=>$product_type,
              'accounts'=>json_encode($formData),
            ];
            $date = date('Y-m-d H:i:s');
            $product_settings_data['created_at'] = $date;
          //  echo "<pre>";print_r($product_settings_data);die;
           $product_type_data = $this->Record->select(array("qbo_product_settings.*"))
           ->from("qbo_product_settings")
           ->fetch();

          if(!empty($product_type_data)){
            //update a record if exist 
            $id = $product_type_data->id;
            $product_settings_data['updated_at'] = $date;
            $this->Record->where("id", "=", $id)->update("qbo_product_settings", $product_settings_data);
          }else{
            $this->Record->insert("qbo_product_settings", $product_settings_data);
          }
          $this->flashMessage(
            'message',
            "Product settings saved successfully",
            null,
            false
        );
        }
        $this->redirect($this->base_uri."plugin/qbo_integration/admin_settings/");
    }
    /**
     * 
     * This function return product type value
     */
    public function getSettings(){
        $product_type = $_REQUEST['product_type'];
        $product_type_data = $this->Record->select(array("qbo_product_settings.*"))
        ->from("qbo_product_settings")
        ->like('product_type',"%$product_type%")
        ->fetch();
        $response = [];
        $product_settings_accounts = isset($product_type_data->accounts) ? json_decode($product_type_data->accounts) : '';
      //  echo "<pre>";print_r($product_settings_accounts);die;
        $income_account = isset($product_settings_accounts->income_account) ? $product_settings_accounts->income_account : 0;
        $expense_account = isset($product_settings_accounts->expense_account) ? $product_settings_accounts->expense_account : 0;
        $asset_account = isset($product_settings_accounts->asset_account) ? $product_settings_accounts->asset_account : 0;
        
        $response['income_account'] = $income_account;
        $response['expense_account'] = $expense_account;
        $response['asset_account']  = $asset_account;
        $this->outputAsJson($response);
        return false;
    }
}   