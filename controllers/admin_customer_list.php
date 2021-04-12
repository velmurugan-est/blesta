<?php
use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Facades\Customer;

class AdminCustomerList extends QboIntegrationController{
    public function preAction()
    {
        Loader::LoadComponents($this,['Record']);   
        parent::preAction();
        // Restore structure view location of the admin portal
        $this->structure->setDefaultView(APPDIR);
        $this->structure->setView(null, $this->orig_structure_view);        
    }
    public function index(){
        
        $this->uses(['QboIntegration.QboIntegrationTransaction','Clients']);
        $page = (isset($this->get[0]) ? (int)$this->get[0] : 1);
        $widget_type = (isset($this->get['type']) ? $this->get['type'] : '');
     //   echo $widget_type;die;
        $limit = 10;
        $start = $page * $limit - 10 + 1;
        $customer_list = $this->Record->select(array("clients.*","contacts.*","client_notes.description","contact_numbers.*","qbo_transaction.is_mapping","qbo_transaction.qbo_id","qbo_transaction.status"))
        ->from("clients")
        ->innerJoin("contacts", "contacts.client_id", "=", "clients.id", false)
        ->leftJoin("client_notes", "client_notes.client_id", "=", "clients.id", false)
        ->leftJoin("contact_numbers", "contact_numbers.contact_id", "=", "contacts.id", false)
        ->leftJoin("qbo_transaction", "qbo_transaction.blesta_id", "=", "clients.id", false)
        ->group(array("contact_numbers.contact_id", "contacts.id"))
        ->where("qbo_transaction.is_mapping",'=','0')
        ->like('qbo_transaction.type','%customer%')
        ->order(array("clients.id"=>"asc"))
        
        ->limit($limit,$start)
    //    ->where('clients.id','=',$blesta_client_id)
        ->fetchAll();
       // Set current page of results
       $status = (isset($this->get[0]) ? $this->get[0] : 'active');
       $page = (isset($this->get[1]) ? (int)$this->get[1] : 1);
       $sort = (isset($this->get['sort']) ? $this->get['sort'] : 'id_code');
       $order = (isset($this->get['order']) ? $this->get['order'] : 'desc');
       $post_filters =  [];
       // Set the number of clients of each type
       $status_count = [
           'active' => $this->Clients->getStatusCount('active', $post_filters),
           'inactive' => $this->Clients->getStatusCount('inactive', $post_filters),
           'fraud' => $this->Clients->getStatusCount('fraud', $post_filters)
       ];
       foreach($customer_list as $key=>$value){
           if(!$value->id){
               unset($customer_list[$key]);
           }
       }
       /*
       $customer_listObj = $this->Clients->getAll($status, $page, [$sort => $order], $post_filters);
       foreach($customer_listObj as $client_key => $client_value){
           $qbo_transaction_data = $this->Record->select(array("qbo_transaction.is_mapping","qbo_transaction.qbo_id","qbo_transaction.status"))
           ->from("qbo_transaction")
           ->where('blesta_id','=',$client_value->id)
           ->where("qbo_transaction.is_mapping",'=','0')
           ->fetch();
           if(!$qbo_transaction_data){
                $qbo_transaction_data = (object) [];
                $qbo_transaction_data->is_mapping = '';
                $qbo_transaction_data->qbo_id = '';
                $qbo_transaction_data->status = '';

           } 
           $customer_list[] = (object) array_merge(
                (array) $customer_listObj[$client_key], (array) $qbo_transaction_data);
        }
        */
     //   echo "<pre>";print_r( $customer_list);die;
        
      $qboEntityData = $this->Record->select()->from("qbo_entities")->like("type","%customer%")->fetch();
      $qbotransactionData = $this->Record->select()->from("qbo_transaction")->like("type","%customer%")->fetchAll();
      $decodedQboEntityData = isset($qboEntityData->qbo_data) ? json_decode($qboEntityData->qbo_data) : '';
    //  echo "<pre>";print_r($decodedQboEntityData);die;
      
      if($decodedQboEntityData ){
            if($customer_list){
          foreach($customer_list as $key=>$value){
             // echo "<pre>";print_R($value);di;
                if($value->is_mapping == 0){
                    $qbo_id = isset($value->qbo_id) ? $value->qbo_id : '';
                    $customer_list[$key]->qbo_customer_name = isset($decodedQboEntityData->Id->$qbo_id) ? $decodedQboEntityData->Id->$qbo_id : $value->first_name." ".$value->last_name;
                }else{
                    $customer_list[$key]->qbo_customer_name = '';
                }
          }
        }
      }   
       // echo "<pre>";print_r($customer_list);die;

         $total_results = count($customer_list);
        //get unmapped blesta customer 
        $blestaCustomersData =  $this->Record->select(array("contacts.*","contact_numbers.*","clients.*","qbo_transaction.is_mapping","qbo_transaction.qbo_id","qbo_transaction.blesta_id","qbo_transaction.status"))
        ->from("clients")
        ->innerJoin("contacts", "contacts.client_id", "=", "clients.id", false)
        ->leftJoin("contact_numbers", "contact_numbers.contact_id", "=", "contacts.id", false)
        ->leftJoin("qbo_transaction", "qbo_transaction.blesta_id", "=", "clients.id", false)
        //->where('qbo_transaction.is_mapping','=','')
      //  ->where('qbo_transaction.is_mapping','!=','')
        ->group(array("contact_numbers.contact_id", "contacts.id"))
        ->fetchAll();
        // echo "<pre>";print_r($blestaCustomersData);die;
        $settings = array_merge(
        Configure::get('Blesta.pagination'),
        [
            'total_results' => $total_results,
             'results_per_page'=> 4 ,
            'uri' => $this->base_uri . 'plugin/qbo_integration/admin_customer_list/index/[p]/',
            'params' => ['sort' => $sort, 'order' => $order],

        ]
    );
    $blesta_unmapped_customer = $this->unmappedQbCustomer();
  
    $count_unmapped_customer = count($blestaCustomersData);
    //pagination for unmapped customer
    $unmapped_customer_settings = array_merge(
        Configure::get('Blesta.pagination'),
        [
            'total_results' => $count_unmapped_customer,
            'uri' => $this->base_uri . 'plugin/qbo_integration/admin_customer_list/customer/[p]/',
        //    'params' => ['widget_type' => $widget_type],
            'results_per_page'=> 4 ,
        ],[
            'type'=>'customer_list',
        ]
    );
    // echo "<pre>";print_R($unmapped_customer_settings);die;
   //  $this->helpers(array("Pagination"=>array($this->get, $settings)));
    // $this->Pagination->setSettings(Configure::get("Blesta.pagination"));
     $this->setPagination($this->get, $settings);
     $this->set('customersArr',$customer_list);
     $this->set('blesta_customer_data',$blestaCustomersData);
     $this->set('qbo_customers_data',$blesta_unmapped_customer);
     $this->set('page',$page);
     $this->set('widget_type',$widget_type); 
   // $this->set('mapping',$this->partial('admin_mapping_customer',$vars));
    $data = $this->view->fetch('admin_customer_list');
      if($this->isAjax() && $widget_type){
          return $this->renderAjaxWidgetIfAsync();
      }elseif($this->isAjax() && $widget_type == ''){
          echo $data;
          return false;
      }
    }
     
    public function post_customer(){
         
        Loader::loadModels($this, ['QboIntegration.QboIntegrationTransaction','Clients']);
        $page = (isset($this->get[0]) ? (int)$this->get[0] : 1);
         $limit = 10;
        $start = $page * $limit - 10 + 1;
     //   $blesta_customer_list = $this->Clients->getList();
     //   echo "<pre>";print_r($blesta_customer_list);die;
     //    $config = $this->Record->select()->from('qbo_configuration')->fetchAll();
         $data = $this->post;
         $selected_clients = $data['selected_client_id'];
      //   echo "<pre>";print_r($selected_clients);die;
         for($i=0;$i<count($selected_clients);$i++){
            $blesta_customer_list[] = $this->Record->select(array("contacts.*","client_notes.*","contact_numbers.*","clients.*"))->from("contacts")
            ->innerJoin("clients", "contacts.client_id", "=", "clients.id", false)
            ->leftJoin("client_notes", "client_notes.client_id", "=", "clients.id", false)
            ->leftJoin("contact_numbers", "contact_numbers.contact_id", "=", "contacts.id", false)
            ->where('clients.id','=',$selected_clients[$i])
         //   ->limit($limit,$start)
            ->fetch();
         }
        // echo "<pre>";print_r(($blesta_customer_list));die;
         $blesta_customer_count = count($blesta_customer_list);
        // echo "<pre>";print_r($blesta_customer_list);die;
        //check if customer is already exist or not in qbo
        //if not create a new customer in qbo or else update a customer        
        // Add a customer
    if($blesta_customer_count !=0){
        $qbo_helper = new QboIntegrationHelper();
        for($i=0;$i<$blesta_customer_count;$i++){
            $blesta_id = $blesta_customer_list[$i]->id;
            $blesta_customer_data = (array)$blesta_customer_list[$i];
            $check_customer  = $this->Record->select()
            ->from("qbo_transaction")
            ->where('blesta_id','=',$blesta_id)
            ->like('type',"%customer%")

          //  ->where('is_mapping','=','0')
            ->fetch();
            if(empty($check_customer) || isset($check_customer->is_mapping) == 1){
                $resultingCustomerObj = $qbo_helper->newQboCustomer($blesta_customer_data);
                if($resultingCustomerObj){
                        if(isset($check_customer->is_mapping) == 1){
                           $id = $check_customer->id;
                           $date = date('Y-m-d H:i:s');
                            $createdQboData = [
                                'type'=>'customer',
                                'blesta_id'=>$blesta_id,
                                'qbo_id'=>$resultingCustomerObj->Id,
                                'is_mapping'=>'0',
                                'status'=>'created',
                                'created_at'=>$date
                            ];
                            $this->flashMessage(
                                'message',
                                "The client has been successfully created.",
                                null,
                                false
                            );
                         $transactionID  = $this->Record->where("id", "=", $id)->update("qbo_transaction", $createdQboData);
                            
                        }else{
                    $createdQboData = [
                        'type'=>'customer',
                        'blesta_id'=>$blesta_id,
                        'qbo_id'=>$resultingCustomerObj->Id,
                        'status'=>'created'
                    ];
                    $this->flashMessage(
                        'message',
                        "The client has been successfully created.",
                        null,
                        false
                    );
                $transactionID  = $this->QboIntegrationTransaction->add($createdQboData);
                }
            }
            }else{
                die("updated");
               $qboCustomerId = $check_customer->qbo_id;
               // echo "<pre>";print_r($qboCustomerId);die;
               $resultingCustomerObj = $qbo_helper->updateQboCustomer($blesta_customer_data,$qboCustomerId);
               $createdQboData = [
                  'type'=>'customer',
                  'blesta_id'=>$blesta_id,
                  'qbo_id'=>$resultingCustomerObj->Id,
                  'is_mapping'=>'0',
                  'status'=>'updated'
              ];
              $transactionID  = $this->QboIntegrationTransaction->update($createdQboData,$resultingCustomerObj->Id);

                $this->flashMessage(
                    'message',
                    "The client has been successfully updated.",
                    null,
                    false
                );
            }
        }
    }
    $this->redirect($this->base_uri."plugin/qbo_integration/admin_master/");
    }
    
    /**
     * 
     * This function will return unmapped qb customer list along with blesta customer
     */
    public function unmappedQbCustomer(){
        
        $type = 'customer';
        $qboEntityData = $this->Record->select()->from("qbo_entities")->like("type","%$type%")->fetch();
        $qboIds = $this->Record->select(array("qbo_transaction.qbo_id"))->from("qbo_transaction")->like("type","%$type%")->where('qbo_transaction.is_mapping','=','0')->fetchAll();
        $qbo_helper = new QboIntegrationHelper();
        $qboCustomerData = $qbo_helper->getQboData($type,$qboEntityData,$qboIds);
        return $qboCustomerData;
    }
    /**
     * 
     * This fuction for save customer mapping
     */
    public function saveMappingFields(){
        $data = $this->post;
      //  echo "<pre>";print_R($data);die;
        if($data){
            unset($data['selected_client_id']);
        // echo "<pre>";print_R($data);die;
        foreach($data as $blesta_id=>$qbo_id){
            $type = $data['module'];
            $old_qbo_id = $this->Record->select()->from("qbo_transaction")->where('blesta_id','=',$blesta_id)->fetch();
            if($blesta_id != 'module' && $qbo_id  || $old_qbo_id  ){
                $check_id = $this->Record->select(array("qbo_transaction.blesta_id"))
                ->from("qbo_transaction")
                ->where("qbo_transaction.blesta_id",'=',$blesta_id)
            //    ->where("qbo_transaction.is_mapping",'=','1')
                ->like('type',"%$type%")
                ->fetchAll();
                //check if qbo id is exist or not 
                //if exist set mapping field as true
                if($old_qbo_id){
                    if(empty($qbo_id)){
                        $qbo_id  = 0;
                    }
                    $this->Record->where("qbo_transaction.blesta_id", "=", $blesta_id)->like('type',"%$type%")->update("qbo_transaction", array("qbo_id"=>$qbo_id,'is_mapping'=>'1'));
                    $this->flashMessage(
                     'message',
                     "The Client has been mapped with quick book customer successfully.",
                     null,
                     false
                     );
                   }else{
                     if($check_id){  
                    if(empty($qbo_id)){
                      //  echo $blesta_id;die;
                        $qbo_id  = 0;             
                   }
                   // echo $qbo_id;die;
                   $this->Record->where("qbo_transaction.blesta_id", "=", $blesta_id)->like('type',"%$type%")->update("qbo_transaction", array("qbo_id"=>$qbo_id,'is_mapping'=>'1'));
                   $this->flashMessage(
                    'message',
                    "The Client has been mapped with quick book customer successfully.",
                    null,
                    false
                );
               }else{
                   
                     $this->Record->insert('qbo_transaction',['type'=>"$type",'blesta_id'=>$blesta_id,'qbo_id'=>$qbo_id,'is_mapping'=>1]);
                     $transaction_id = $this->Record->lastInsertId();
                     if($transaction_id){
                        $this->flashMessage(
                            'message',
                            "The Client has been mapped with quick book customer successfully.",
                            null,
                            false
                        );
                     }
                }
            } 
        }
        }
        $this->redirect($this->base_uri."plugin/qbo_integration/admin_master");
    }
    }
    public function post_payment(){
        $this->uses(['Transactions']);
        $qbo_helper = new QboIntegrationHelper();
        $blesta_transaction = $this->Transactions->getList();
        echo "<pre>";print_R($blesta_transaction);die;
        $payment = $qbo_helper->newQboPayment();
    }
}