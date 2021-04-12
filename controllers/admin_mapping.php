<?php
use QuickBooksOnline\API\DataService\DataService;
class AdminMapping extends QboIntegrationController{

    public function preAction()
    {
        parent::preAction();
          // Require login
          $this->requireLogin();
        // Restore structure view location of the admin portal
        $this->structure->setDefaultView(APPDIR);
        $this->structure->setView(null, $this->orig_structure_view);        
    }
    public function index(){
        
    }
    /**
     * 
     * This function will return customer mapping fields
     */
    public function customer(){

        $this->uses(['Clients']);
        $page = (isset($this->get[0]) ? (int)$this->get[0] : 1);
        $widget_type = (isset($this->get['type']) ? $this->get['type'] : '');
        $limit = 10;
        $start = $page * $limit - 10 + 1;
        /*
        $blestaCustomersData =  $this->Record->select(array("contacts.*","contact_numbers.*","clients.*","qbo_transaction.is_mapping","qbo_transaction.qbo_id","qbo_transaction.blesta_id","qbo_transaction.status"))
        ->from("clients")
        ->innerJoin("contacts", "contacts.client_id", "=", "clients.id", false)
        ->leftJoin("contact_numbers", "contact_numbers.contact_id", "=", "contacts.id", false)
        ->leftJoin("qbo_transaction", "qbo_transaction.blesta_id", "=", "clients.id", false)
        //->where('qbo_transaction.is_mapping','=','')
      //  ->where('qbo_transaction.is_mapping','!=','')
        ->group(array("contact_numbers.contact_id", "contacts.id"))
        ->like('qbo_transaction.type',"%customer%")
      //  ->limit($limit,$start)
        ->fetchAll();
        */

        $page = (isset($this->get[0]) ? (int)$this->get[0] : 1);
        $status = (isset($this->get[1]) ? $this->get[1] : 'active');
        $sort = (isset($this->get['sort']) ? $this->get['sort'] : 'id_code');
        $order = (isset($this->get['order']) ? $this->get['order'] : 'asc');
        $post_filters =  [];
        
       $blestaCustomersList = $this->Clients->getList($status, $page, [$sort => $order], $post_filters);
       $total_results = $this->Clients->getListCount();
      // echo $total_results;die;
       // $total_results = count($blestaCustomersList);
       if($total_results > 0){
       foreach($blestaCustomersList as $client_key=>$client_value){
           $qbo_transaction_data = $this->Record->select(array("qbo_transaction.qbo_id","qbo_transaction.is_mapping","qbo_transaction.status"))
           ->from("qbo_transaction")
           ->where('qbo_transaction.blesta_id','=',$client_value->id)
           ->like('qbo_transaction.type',"%customer%")
           ->fetch();
           if(!$qbo_transaction_data){
            $qbo_transaction_data = (object) [];
             $qbo_transaction_data->is_mapping = '';
             $qbo_transaction_data->qbo_id = '';
             $qbo_transaction_data->status = '';
        } 
        $blestaCustomersData[] = (object) array_merge(
             (array) $blestaCustomersList[$client_key], (array) $qbo_transaction_data);
    
       }
    }else{
        $blestaCustomersData = [];
    }
      //  echo "<pre>";print_r($blestaCustomersData);die;
       $blesta_unmapped_customer = $this->unmappedQbEntityData('customer');
       $settings = array_merge(
        Configure::get('Blesta.pagination'),
        [   
            'total_results' => $total_results,
            'uri' => $this->base_uri . 'plugin/qbo_integration/admin_mapping/customer/[p]',
         //   'results_per_page'=> 5 ,
            'params' => ['sort' => $sort, 'order' => $order],
        ]
    );
    //get mapped qb customer id 
    $qboIds = $this->Record->select("qbo_id")->from("qbo_transaction")
    ->where("is_mapping",'=',1)
    ->like('type','%customer%')
    ->fetchAll();
    if($qboIds){
        foreach($qboIds as $key => $value){
            $qboIdsArr[] =  $value->qbo_id;
        }
    }else{
        $qboIdsArr = [];
    }
    //echo "<pre>";print_r($qboIdsArr);die;
    $this->set('mapped_qbo_id',json_encode($qboIdsArr));
    $this->setPagination($this->get, $settings);
    $this->set('blesta_customer_data',$blestaCustomersData);
    $this->set('qbo_customers_data',$blesta_unmapped_customer);
 //   $data = $this->view->fetch('admin_mapping_customer');
      return $this->renderAjaxWidgetIfAsync(isset($this->get[0]));

    /*
    if($this->isAjax() && $widget_type ==''){
        return $this->renderAjaxWidgetIfAsync();
    }elseif($this->isAjax() && $widget_type){
       echo $data;
       return false;
    }
    */
    }
     /**
     * 
     * This function will return product mapping fields
     */
    public function product(){

        $this->uses(['Packages']);
        $page = (isset($this->get[0]) ? (int)$this->get[0] : 1);
        $widget_type = (isset($this->get['type']) ? $this->get['type'] : '');
        $limit = 10;
        $start = $page * $limit - 10 + 1;
        $post_filters = [];
        $blesta_package_list = $this->Packages->getList($page, ['id_code' => 'asc'], null, $post_filters);
         foreach($blesta_package_list as $pac_key => $pac_value){
            $qbo_transaction_data = $this->Record->select(array("qbo_transaction.is_mapping","qbo_transaction.qbo_id","qbo_transaction.status"))
            ->from("qbo_transaction")
            ->where('blesta_id','=',$pac_value->id)
         //   ->where("qbo_transaction.is_mapping",'!=',0)
            ->like('qbo_transaction.type',"%product%")
            ->fetch();
            if(!$qbo_transaction_data){
                $qbo_transaction_data = (object) [];
                 $qbo_transaction_data->is_mapping = '';
                 $qbo_transaction_data->qbo_id = '';
                 $qbo_transaction_data->status = '';
            } 
            $product_list[] = (object) array_merge(
                 (array) $blesta_package_list[$pac_key], (array) $qbo_transaction_data);
         }
        $blesta_unmapped_product = $this->unmappedQbEntityData('product');
        //echo "<pre>";print_R($product_list);die;
       $count_unmapped_product = count($blesta_package_list);
       $settings = array_merge(
        Configure::get('Blesta.pagination'),
        [   
            'total_results' => $count_unmapped_product,
            'uri' => $this->base_uri . 'plugin/qbo_integration/admin_mapping/product/[p]',
            'results_per_page'=> 10 ,
            'params'=>array('sort'=>'id','order'=>'DESC')
        ]
    );
    //get mapped qb customer id 
    $qboIds = $this->Record->select("qbo_id")->from("qbo_transaction")
    ->where("is_mapping",'=',1)
    ->like('type','%product%')
    ->fetchAll();
    if($qboIds){
        foreach($qboIds as $key => $value){
            $qboIdsArr[] =  $value->qbo_id;
        }
    }else{
        $qboIdsArr = [];
    }
    //echo "<pre>";print_r($product_list);die;
    $this->set('mapped_qbo_id',json_encode($qboIdsArr));
    $this->setPagination($this->get, $settings);
    $this->set('blesta_product_data',$product_list);
    $this->set('qbo_product_data',$blesta_unmapped_product);
 //   $data = $this->view->fetch('admin_mapping_customer');
    return $this->renderAjaxWidgetIfAsync();

    /*
    if($this->isAjax() && $widget_type ==''){
        return $this->renderAjaxWidgetIfAsync();
    }elseif($this->isAjax() && $widget_type){
       echo $data;
       return false;
    }
    */
    }
     /**
     * 
     * This function will return unmapped qb entity list along with blesta customer
     */
    public function unmappedQbEntityData($type){
        
        $qboEntityData = $this->Record->select(array("qbo_entities.*"))->from("qbo_entities")->like("type","%$type%")->fetch();
        $qboIds = $this->Record->select(array("qbo_transaction.qbo_id"))->from("qbo_transaction")->like("type","%$type%")->where('qbo_transaction.is_mapping','=','0')->fetchAll();
      //  echo "<pre>";print_r($qboEntityData);die;
        $qbo_helper = new QboIntegrationHelper();
        $qboCustomerData = $qbo_helper->getQboData($type,$qboEntityData,$qboIds);
        return $qboCustomerData;
    }
    
}