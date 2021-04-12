<?php 
use QuickBooksOnline\API\DataService\DataService;
use QuickBooksOnline\API\Facades\product;

class AdminProductList extends QboIntegrationController{
    public function preAction()
    {
        Loader::LoadComponents($this,['Record']);   
        parent::preAction();
        // Restore structure view location of the admin portal
        $this->structure->setDefaultView(APPDIR);
        $this->structure->setView(null, $this->orig_structure_view);        
    }
    public function index(){

        $this->uses(['Packages']);
        $blesta_package_list = $this->Packages->getList();
        foreach($blesta_package_list as $pac_key => $pac_value){
            $qbo_transaction_data = $this->Record->select(array("qbo_transaction.is_mapping","qbo_transaction.qbo_id","qbo_transaction.status"))
            ->from("qbo_transaction")
            ->where('blesta_id','=',$pac_value->id)
            ->where("qbo_transaction.is_mapping",'=','0')
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
        // echo "<pre>";print_r($product_list);die;
         $this->set('product_list',$product_list);
         $data = $this->view->fetch('admin_product_list');
         $widget_type = (isset($this->get['type']) ? $this->get['type'] : '');
        
        if($this->isAjax() && $widget_type){
            return $this->renderAjaxWidgetIfAsync();
        }elseif($this->isAjax() && $widget_type == ''){
            echo $data;
            return false;
        }
      }
      public function post_product(){
         
        $this->uses(['Packages','QboIntegration.QboIntegrationTransaction']);
        $page = (isset($this->get[0]) ? (int)$this->get[0] : 1);
        $limit = 10;
        $start = $page * $limit - 10 + 1;
     //   $blesta_product_list = $this->products->getList();
     //   echo "<pre>";print_r($blesta_product_list);die;
     //    $config = $this->Record->select()->from('qbo_configuration')->fetchAll();
         $data = $this->post;
         $selected_products = $data['selected_product_id'];
        // echo "<pre>";print_r($data);die;
         for($i=0;$i<count($selected_products);$i++){
            $blesta_product_list[] =  $blesta_package_data = $this->Record->select(array("packages.*","package_descriptions.*","pricings.*","package_names.*"))->from("packages")
            ->innerJoin('package_descriptions','package_descriptions.package_id','=','packages.id',false)
            ->leftJoin('pricings','pricings.id','=','packages.id',false)
            ->leftJoin('package_names','package_names.package_id','=','packages.id',false)
             ->where('packages.id','=',$selected_products[$i])
             ->fetch();
         }
        // echo "<pre>";print_r(($blesta_product_list));die;
         $blesta_product_count = count($blesta_product_list);
        // echo "<pre>";print_r($blesta_product_list);die;
        //check if product is already exist or not in qbo
        //if not create a new product in qbo or else update a product        
        // Add a product
    if($blesta_product_count !=0){
        $qbo_helper = new QboIntegrationHelper();
        for($i=0;$i<$blesta_product_count;$i++){
            $blesta_id = $blesta_product_list[$i]->id;
            $blesta_product_data =  $blesta_product_list[$i];
         //   echo "<pre>";print_r([$blesta_id,$blesta_product_data]);die;
            $package_configuration = $this->Record->select(array("qbo_product_settings.*"))->from("qbo_product_settings")->fetch();
            if($package_configuration){
            $qbo_product_accounts = json_decode($package_configuration->accounts);
            $qbo_product_accounts->product_type = $package_configuration->product_type;
            }else{
                $error = "Please configure package to post quick books";
                $this->flashMessage('error', $error,null,false);
                $this->redirect($this->base_uri."plugin/qbo_integration/admin_master/");
            }
            $check_product  = $this->Record->select()
            ->from("qbo_transaction")
            ->where('blesta_id','=',$blesta_id)
            ->like('type',"%product%")
          //  ->where("is_mapping",'!=',1)
            ->fetch();
         //   echo "<pre>";print_r($check_product);die;
            if(empty($check_product) || isset($check_product->is_mapping) == 1){
                $resultingproductObj = $qbo_helper->newQboItem($blesta_product_data,$qbo_product_accounts);
                if($resultingproductObj){
                        if(isset($check_product->is_mapping) == 1){
                            $id = $check_product->id;
                           $date = date('Y-m-d H:i:s');
                            $createdQboData = [
                                'type'=>'product',
                                'blesta_id'=>$blesta_id,
                                'qbo_id'=>$resultingproductObj->Id,
                                'is_mapping'=>'0',
                                'status'=>'created',
                                'created_at'=>$date

                            ];
                            $this->flashMessage(
                                'message',
                                "The product has been successfully created.",
                                null,
                                false
                            );
                            $transactionID  = $this->Record->where("id", "=", $id)->update("qbo_transaction", $createdQboData);
                        }else{
                            $createdQboData = [
                                'type'=>'product',
                                'blesta_id'=>$blesta_id,
                                'qbo_id'=>$resultingproductObj->Id,
                                'status'=>'created'
                            ];
                            $this->flashMessage(
                                'message',
                                "The product has been successfully created.",
                                null,
                                false
                            );
                        $transactionID  = $this->QboIntegrationTransaction->add($createdQboData);
                        }
                }
            }else{
               $qboproductId = $check_product->qbo_id;
               // echo "<pre>";print_r($qboproductId);die;
               $resultingproductObj = $qbo_helper->updateQboItem($blesta_product_data,$qboproductId);
               $createdQboData = [
                  'type'=>'product',
                  'blesta_id'=>$blesta_id,
                  'qbo_id'=>$resultingproductObj->Id,
                  'is_mapping'=>'0',
                  'status'=>'updated'
              ];
              $transactionID  = $this->QboIntegrationTransaction->update($createdQboData,$resultingproductObj->Id);

                $this->flashMessage(
                    'message',
                    "The product has been successfully updated.",
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
     * This fuction for save customer mapping
     */
    public function saveproductMappingFields(){
        $data = $this->post;
        if($data){
            unset($data['selected_product_id']);
          //  echo "<pre>";print_r($data);die;
        foreach($data as $blesta_id=>$qbo_id){
            $type = $data['module'];
            $old_qbo_id = $this->Record->select(array("qbo_transaction.*"))
            ->from("qbo_transaction")
            ->where('blesta_id','=',$blesta_id)
            ->like('type','%product%')
            ->fetch();
           // echo $old_qbo_id;die;
           if($old_qbo_id){
            if(empty($qbo_id)){
                $qbo_id  = 0;
            }
            $this->Record->where("qbo_transaction.blesta_id", "=", $blesta_id)->like('type',"%$type%")->update("qbo_transaction", array("qbo_id"=>$qbo_id,'is_mapping'=>'1'));
            $this->flashMessage(
             'message',
             "The Product has been mapped with qb product successfully.",
             null,
             false
             );
           }else{
            if($blesta_id != 'module' && $qbo_id){
                $check_id = $this->Record->select(array("qbo_transaction.blesta_id"))
                ->from("qbo_transaction")
                ->where("qbo_transaction.blesta_id",'=',$blesta_id)
                ->like('type',"%$type%")
                ->fetchAll();
             //  echo "<pre>";print_R($check_id);die;    
                //check if qbo id is exist or not 
                //if exist set mapping field as true
                if($check_id){
                    if(empty($qbo_id)){
                        $qbo_id  = 0;
                    }
                   $this->Record->where("qbo_transaction.blesta_id", "=", $blesta_id)->like('type',"%$type%")->update("qbo_transaction", array("qbo_id"=>$qbo_id,'is_mapping'=>'1'));
                   $this->flashMessage(
                    'message',
                    "The Product has been mapped with quick book product successfully.",
                    null,
                    false
                );
               }else{
                   
                     $this->Record->insert('qbo_transaction',['type'=>"$type",'blesta_id'=>$blesta_id,'qbo_id'=>$qbo_id,'is_mapping'=>'1']);
                     $transaction_id = $this->Record->lastInsertId();
                     if($transaction_id){
                        $this->flashMessage(
                            'message',
                            "The Product has been mapped with quick book customer successfully.",
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
    }