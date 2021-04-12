<?php
use Blesta\Core\Util\Input\Fields\InputFields;
use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Core\Http\Serialization\XmlObjectSerializer;
use QuickBooksOnline\API\Facades\Invoice;
use QuickBooksOnline\API\Facades\Item;
class AdminInvoiceList extends QboIntegrationController{
    
      /**
     * Setup
     */
    public function preAction()
    {
        parent::preAction();
        // Restore structure view location of the admin portal
        $this->structure->setDefaultView(APPDIR);
        $this->structure->setView(null, $this->orig_structure_view);        
    }

    public function index(){
      $page = (isset($this->get[0]) ? (int)$this->get[0] : 1);
      $limit = 10;
      $start = $page * $limit - 10 + 1;
    //  echo $start;die;
      Loader::LoadComponents($this,['Record']);
       $this->uses(['QboIntegration.QboIntegrationTransaction','Invoices',"Transactions"]);
        
        $invoice_list = $this->Record->select(array("invoices.*","clients.id_value","qbo_transaction.status"))
        ->from("invoices")
      //  ->innerJoin("contacts",'invoices.client_id','=','contacts.client_id',false)
        ->leftJoin('qbo_transaction','qbo_transaction.blesta_id','=','invoices.id',false)
        ->leftJoin('clients','clients.id','=','invoices.client_id',false)
      //  ->like('qbo_transaction.type','%invoices%')
        ->limit($limit,$start)
        ->fetchAll();
        
        $page = (isset($this->get[0]) ? (int) $this->get[0] : 1);
        $status = (isset($this->get['status']) ? $this->get['status'] : 'open');
        $sort = (isset($this->get['sort']) ? $this->get['sort'] : 'date_billed');
        $order = (isset($this->get['order']) ? $this->get['order'] : 'desc');
        $post_filters = [];
        $invoice_list = $this->Invoices->getList(null, $status, $page, [$sort => $order], $post_filters);
        
        $total_results = count($invoice_list);
        //echo "<pre>";print_r($total_results);die;
        // Overwrite default pagination settings
         $settings = array_merge(
          Configure::get('Blesta.pagination'),
          [
                'total_results' => $total_results,
                'uri' => $this->base_uri . 'plugin/qbo_integration/admin_invoice_list/index/[p]/',
                'results_per_page'=> 10 ,
                'params' => ['sort' => $sort, 'order' => $order],
                
          ]
      );
       //get posted blesta invoice status 
       foreach($invoice_list as $key=>$value){
        $qbo_transaction_data =  $this->Record->select(array("qbo_transaction.is_mapping","qbo_transaction.blesta_id","qbo_transaction.status","qbo_transaction.type"))
        ->from("qbo_transaction")
        ->where("qbo_transaction.blesta_id",'=',$value->id)
        ->where('qbo_transaction.is_mapping','=',0)
        ->like("type",'%invoices%')
        ->fetch();
         if($qbo_transaction_data){
        // echo "<pre>";print_R($qbo_transaction_data);die;
         if($qbo_transaction_data->blesta_id == $value->id){

            $invoice_list[$key]->status = $qbo_transaction_data->status;
         }else{
           $invoice_list[$key]->status = '-';
         }
       }else{
           $invoice_list[$key]->status = '-';
       }
      }
      //  echo "<pre>";print_R($invoice_list);die;
        $invoiceArr  = [];
        foreach($invoice_list as $key=>$value){
            $invoiceArr[] = (array)$value;
          //   $customersArr[] =  (array)$this->Record->select()->from("users")->where("users.id", "=", $value->client_id)->fetch();
        }
        
        $this->setPagination($this->get,$settings);
     //   echo "<pre>";print_r($settings);die;
       // $this->helpers(array("Pagination"=>array($this->get, $settings)));
      //  $this->setPagination($this->get, $settings);
       // $this->Pagination->setSettings( Configure::get('Blesta.pagination_ajax'));
        $this->set('invoicesArr',$invoiceArr);
      //  $this->set('customersArr',$customersArr);
        $this->set('page',$page);
        $data = $this->view->fetch('admin_invoice_list');
        $widget_type = (isset($this->get['type']) ? $this->get['type'] : '');
       // echo $widget_type;die;
       if($this->isAjax() && $widget_type){
           return $this->renderAjaxWidgetIfAsync();
       }elseif($this->isAjax() && $widget_type == ''){
           echo $data;
           return false;
       }
      //  return $this->renderAjaxWidgetIfAsync(isset($this->get[0]) || isset($this->get['sort']));

    }
    public function post_invoices(){
       $this->uses(['QboIntegration.QboIntegrationTransaction','Invoices',"Services"]);
      $page = (isset($this->get[0]) ? (int)$this->get[0] : 1);
      $limit = 10;
      $start = $page * $limit - 10 + 1;
        
        $data = $this->post;
        $selected_invoices = $data['selected_invoice_id'];
      //  print_R($selected_invoices);die;
        for($i=0;$i<count($selected_invoices);$i++){
        $blesta_invoice_list[] = $this->Record->select(array("contacts.first_name","contacts.last_name","contacts.email","invoices.*","qbo_transaction.qbo_id","qbo_transaction.blesta_id"))
        ->from("invoices")
        ->innerJoin("contacts",'invoices.client_id','=','contacts.client_id',false)
        ->leftJoin('qbo_transaction','qbo_transaction.blesta_id','=','invoices.id',false)
        ->where('invoices.id','=',$selected_invoices[$i]) 
        //->like('qbo_transaction.type','%invoices%')
      //  ->limit($limit,$start)
        ->fetch();
        }
  //   echo "<pre>";print_r($blesta_invoice_list);die;  
       //Add a new Invoice
  if(count($blesta_invoice_list) !=0){
    //check if invoices is exist or not in qbo 
    $qbo_helper = new QboIntegrationHelper();
  for($i=0;$i<count($blesta_invoice_list);$i++){
    $blesta_invoice_id = $blesta_invoice_list[$i]->id;
    $invoiceLineItems = $this->Invoices->getLineItems($blesta_invoice_id);
    $qbo_invoice_doc_number = "Blesta-".$i;
    $blesta_invoice_list[$i]->qbo_invoice_doc_number = $qbo_invoice_doc_number;

   // echo "<pre>";print_R($invoiceLineItems);die;
                for($j=0;$j<count($invoiceLineItems);$j++){
                  $service_id = $invoiceLineItems[$j]->service_id;
                  $blesta_service_data = $this->Services->get($service_id);
                  if($blesta_service_data){
                  $blesta_service_package_id = isset($blesta_service_data->package->id) ? $blesta_service_data->package->id : '' ;
                  $blesta_package_id = $this->getQbId($blesta_service_package_id,'product');    
                  if(empty( $blesta_package_id)){
                      $qbo_item_id = 1;
                  }else{
                      $qbo_item_id = $blesta_package_id->qbo_id;
                  }
                  $invoiceLineItems[$j]->qbo_item_id = $qbo_item_id ;
              }else{
                  $invoiceLineItems[$j]->qbo_item_id = 1 ;
              }
                }
                 $invoiceLineItemsArr['line_items'] = $invoiceLineItems;
                
                    $blesta_invoice_data = (object) array_merge( 
                (array) $blesta_invoice_list[$i], (array) $invoiceLineItemsArr); 
                //echo "<pre>";print_r($blesta_invoice_data);die;
    $check_invoice  = $this->Record->select()->from("qbo_transaction")->where('blesta_id','=',$blesta_invoice_id)->like('type','%invoices%')->fetch();
    
    if(empty($check_invoice)){
        //create a new invoice
        $resultingInvoiceObj = $qbo_helper->newQboInvoice($blesta_invoice_data);
        if($resultingInvoiceObj){ 
        $createdQboData = [
            'type'=>'invoices',
            'blesta_id'=>$blesta_invoice_id,
            'qbo_id'=>$resultingInvoiceObj->Id,
            'status'=>'created'
        ];
        $this->QboIntegrationTransaction->add($createdQboData);
        $this->flashMessage(
          'message',
          "Invoice Posted Successfully",
          null,
          false
      );
    }
    }else{
      //update quick books invoice
      $this->flashMessage(
        'message',
        "Invoice Posted Successfully",
        null,
        false
    );
    }
}
}
$this->redirect($this->base_uri."plugin/qbo_integration/admin_master");
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
   * This function return quick books item id
   */
  public function getQbId($blesta_package_id,$type){
    $qbo_id = $this->Record->select(array("qbo_transaction.*"))->from("qbo_transaction")->where('blesta_id','=',$blesta_package_id)->fetchAll();
    //echo "<pre>";print_R($query);die; 
    if($qbo_id){
    return $query->qbo_id;
  }
}
}