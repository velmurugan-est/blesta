<?php
use Blesta\Core\Util\Events\Common\EventInterface;
require_once 'vendor/autoload.php';  
require_once 'qbo_integration_helper.php';

/**
 * Qbo Integration plugin handler
 *
 * @link https://www.expsoltech.com/ blesta
 */
class QboIntegrationPlugin extends Plugin
{
    public function __construct()
    {
        // Load components required by this plugin
        Loader::loadComponents($this, ['Input', 'Record']);

        Language::loadLang('qbo_integration_plugin', null, dirname(__FILE__) . DS . 'language' . DS);
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

    }

    /**
     * Performs any necessary bootstraping actions
     *
     * @param int $plugin_id The ID of the plugin being installed
     */
    public function install($plugin_id)
    {
    
    //Create table Quick books configuration table
     $this->Record->
     setField("id", array('type' => "int",'size' => 10,'unsigned' => true,'auto_increment' => true))->
     setField("client_id", array('type' => "varchar", 'size' => 255))->
     setField("client_secret", array('type' => "varchar", 'size' => 255))->
     setField("redirect_url", array('type' => "varchar", 'size' => 255))->
     setField("realmid", array('type' => "varchar", 'size' => 255, 'is_null' => true, 'default' => null))->
     setField("access_token", array('type' => "longtext",   'is_null' => true, 'default' => null))->
     setField("refresh_token", array('type' => "longtext", 'is_null' => true, 'default' => null))->
     setField("is_connected", array('type' => "int", 'size' => 10, 'is_null' => true, 'default' => 0))->
     setField("created_at", array('type' => "timestamp", 'is_null' => true, 'default' => null))->
     setField("updated_at", array('type' => "timestamp", 'is_null' => true, 'default' => null))->
     setKey(array("id"), "primary")->
     create("qbo_configuration");

     //Create table Quick books transaction
     $this->Record->
     setField("id", array('type' => "int",'size' => 10,'unsigned' => true,'auto_increment' => true))->
     setField("type", array('type' => "varchar", 'size' => 255))->
     setField("blesta_id", array('type' => "int", 'size' => 10))->
     setField("qbo_id", array('type' => "int", 'size' => 10))->
     setField("is_mapping", array('type' => "int", 'size' => 10,'default'=>0))->
     setField("status", array('type' => "varchar", 'size' => 255, 'is_null' => true,))->
     setField("created_at", array('type' => "timestamp", 'is_null' => true, 'default' => null))->
     setField("updated_at", array('type' => "timestamp", 'is_null' => true, 'default' => null))->
     setKey(array("id"), "primary")->
     create("qbo_transaction");

     //Create table to store qbo entity list
     $this->Record->
     setField("id", array('type' => "int",'size' => 10,'unsigned' => true,'auto_increment' => true))->
     setField("type", array('type' => "varchar", 'size' => 255))->
     setField("qbo_data", array('type' => "longtext"))->
     setField("created_at", array('type' => "timestamp", 'is_null' => true, 'default' => null))->
     setField("updated_at", array('type' => "timestamp", 'is_null' => true, 'default' => null))->
     setKey(array("id"), "primary")->
     create("qbo_entities");

      //Create table to store qbo product settings
      $this->Record->
      setField("id", array('type' => "int",'size' => 10,'unsigned' => true,'auto_increment' => true))->
      setField("product_type", array('type' => "varchar", 'size' => 255))->
      setField("accounts", array('type' => "longtext"))->
      setField("created_at", array('type' => "timestamp", 'is_null' => true, 'default' => null))->
      setField("updated_at", array('type' => "timestamp", 'is_null' => true, 'default' => null))->
      setKey(array("id"), "primary")->
      create("qbo_product_settings");
     

       ////        // Fetch all currently-installed languages for this company, for which email templates should be created for
////        $languages = $this->Languages->getAll(Configure::get('Blesta.company_id'));
////
////        // Add all email templates
////        $emails = Configure::get('QboIntegration.install.emails');
////        foreach ($emails as $email) {
////            $group = $this->EmailGroups->getByAction($email['action']);
////            if ($group) {
////                $group_id = $group->id;
////            } else {
////                $group_id = $this->EmailGroups->add([
////                    'action' => $email['action'],
////                    'type' => $email['type'],
////                    'plugin_dir' => $email['plugin_dir'],
////                    'tags' => $email['tags']
////                ]);
////            }
////
////            // Set from hostname to use that which is configured for the company
////            if (isset(Configure::get('Blesta.company')->hostname)) {
////                $email['from'] = str_replace(
////                    '@mydomain.com',
////                    '@' . Configure::get('Blesta.company')->hostname,
////                    $email['from']
////                );
////            }
////
////            // Add the email template for each language
////            foreach ($languages as $language) {
////                $this->Emails->add([
////                    'email_group_id' => $group_id,
////                    'company_id' => Configure::get('Blesta.company_id'),
////                    'lang' => $language->code,
////                    'from' => $email['from'],
////                    'from_name' => $email['from_name'],
////                    'subject' => $email['subject'],
////                    'text' => $email['text'],
////                    'html' => $email['html']
////                ]);
////            }
////        }
    }
    
 /**
     * Returns all events to be registered for this plugin
     * (invoked after install() or upgrade(), overwrites all existing events)
     *
     * @return array A numerically indexed array containing:
     *  - event The event to register for
     *  - callback A string or array representing a callback function or class/method.
     *      If a user (e.g. non-native PHP) function or class/method, the plugin must
     *      automatically define it when the plugin is loaded. To invoke an instance
     *      methods pass "this" instead of the class name as the 1st callback element.
     */
    public function getEvents()
    {
        return [
            [
                'event' => 'Clients.add',
                'callback' => ['this', 'getCustomerData']
            ],
            [
                'event' => 'Clients.edit',
                'callback' => ['this', 'getCustomerData']
            ],
            [
                'event' => 'Invoices.add',
                'callback' => ['this', 'getInvoiceData']
            ],
            [
                'event' => 'Invoices.edit',
                'callback' => ['this', 'getInvoiceData']
            ],
            [
                'event' => 'Packages.add',
                'callback' => ['this', 'getPackageData']
            ],
            [
                'event' => 'Packages.edit',
                'callback' => ['this', 'getPackageData']
            ],
            [
                'event' => 'Transactions.add',
                'callback' => ['this', 'getTransactionData']
            ],
        ];
    }
    
    public function getCustomerData(EventInterface $event){
      Loader::loadModels($this, ['QboIntegration.QboIntegrationTransaction','Clients',"Contacts"]);
        $params = $event->getParams();
        $return = $event->getReturnValue();
        $qbo_helper = new QboIntegrationHelper();
        if(isset($params['client_id'])){
            $blesta_client_id = $params['client_id'];
         //   $blesta_customer_data = $this->Clients->get($blesta_client_id);
         //   echo "<pre>";print_r($params['vars']);die;
            //echo $blesta_client_id;die;
             $qbo_customer_id =  $this->getQbId($blesta_client_id,'customer');
           
           /*  $blesta_customer_data = $this->Record->select(array("contacts.*","client_notes.*","contact_numbers.*","clients.*","qbo_transaction.*"))
             ->from("clients")
             ->innerJoin("contacts", "contacts.client_id", "=", "clients.id", false)
             ->leftJoin("client_notes", "client_notes.client_id", "=", "clients.id", false)
             ->leftJoin("contact_numbers", "contact_numbers.contact_id", "=", "contacts.id", false)
             ->leftJoin('qbo_transaction','qbo_transaction.blesta_id','=','clients.id',false)
             ->where('clients.id','=',$blesta_client_id)
             ->fetch();
             */
            $blesta_customer_data = $params['vars'];
            $blesta_client_notes= $this->Record->select()->from("client_notes")->where('client_id','=',$blesta_client_id)->fetch();
            $blesta_customer_data['notes'] = isset($blesta_client_notes->description) ? $blesta_client_notes->description : ''; 
         //   echo "<pre>";print_r($blesta_customer_data);die;
            if(empty($qbo_customer_id)){
                //  echo "<pre>";print_r($blesta_customer_data);die;
                $blesta_customer_data['number'] = '';
                 $resultingCustomerObj = $qbo_helper->newQboCustomer($blesta_customer_data);
                // echo "<pre>";print_R($resultingCustomerObj);die;
                 $createdQboData = [
                    'type'=>'customer',
                    'blesta_id'=>$blesta_client_id,
                    'qbo_id'=>$resultingCustomerObj->Id,
                    'status'=>'created'
                ];
            $transactionID  = $this->QboIntegrationTransaction->add($createdQboData);
               
            }else{
           // echo "<pre>";print_r($params['vars']);die;
                $qboCustomerId = $qbo_customer_id->qbo_id;
                $resultingCustomerObj = $qbo_helper->updateQboCustomer($blesta_customer_data,$qboCustomerId);
                $createdQboData = [
                   'type'=>'customer',
                   'blesta_id'=>$blesta_client_id,
                   'qbo_id'=>$resultingCustomerObj->Id,
                   'is_mapping'=>0,
                   'status'=>'updated'
               ];
            $transactionID  = $this->QboIntegrationTransaction->update($createdQboData,$resultingCustomerObj->Id);

            }
            return $transactionID;
        }
    }
    /**
     * 
     * get quick books id
     */
    public function getQbId($blesta_id,$type){
        $qbo_id = $this->Record->select()->from("qbo_transaction")
        ->where('blesta_id','=',$blesta_id)
        ->where('type','=',$type)
        ->fetch();
      return $qbo_id;
    }
    /**
     * 
     * This function return invoice data after add or edit
     */
    public function getInvoiceData(EventInterface $event){
      Loader::loadModels($this, ['QboIntegration.QboIntegrationTransaction','Invoices',"Services",'Packages']);
        $params = $event->getParams();
        $return = $event->getReturnValue();
        if(isset($params['invoice_id'])){
            $qbo_helper = new QboIntegrationHelper();
            $blesta_invoice_id = $params['invoice_id'];
            $qbo_invoice_id = $this->getQbId($blesta_invoice_id,'invoices','contacts');
            //get besta invoice data 
            
            $blesta_invoice_list = $this->Record->select(array("contacts.*","invoices.*","qbo_transaction.*"))->from("invoices")
            ->innerJoin("contacts",'invoices.client_id','=','contacts.client_id',false)
            ->leftJoin('qbo_transaction','qbo_transaction.blesta_id','=','invoices.client_id',false)
            ->where('invoices.id','=',$blesta_invoice_id)
            ->fetch();
            
         //   $blesta_invoice_list = $this->Invoices->get($blesta_invoice_id);
            $invoiceLineItems = $this->Invoices->getLineItems($blesta_invoice_id);
            $blesta_client_id = $blesta_invoice_list->client_id;
                for($i=0;$i<count($invoiceLineItems);$i++){
                    $service_id = $invoiceLineItems[$i]->service_id;
                    $blesta_service_data = $this->Services->get($service_id);
                    if($blesta_service_data){
                    $blesta_service_package_id = $blesta_service_data->package->id;
                    $blesta_package_id = $this->getQbId($blesta_service_package_id,'product');    
                    if(empty( $blesta_package_id)){
                        $blesta_package_data = $this->Packages->get($blesta_service_package_id);
                     //   echo "<pre>";print_r($blesta_package_data);die;
                        $qbo_item_id = 1;
                    }else{
                        $qbo_item_id = $blesta_package_id->qbo_id;
                    }
                    $invoiceLineItems[$i]->qbo_item_id = $qbo_item_id ;
                }else{
                    $invoiceLineItems[$i]->qbo_item_id = 1 ;
                }
            }
                 $invoiceLineItemsArr['line_items'] = $invoiceLineItems;
                    $blesta_invoice_data = (object) array_merge( 
                (array) $blesta_invoice_list, (array) $invoiceLineItemsArr); 
               
            // echo "<pre>";print_r($blesta_invoice_data);die;
                
            if(empty($qbo_invoice_id)){
                $resultingInvoiceObj = $qbo_helper->newQboInvoice($blesta_invoice_data);
                $createdQboData = [
                    'type'=>'invoices',
                    'blesta_id'=>$blesta_invoice_id,
                    'qbo_id'=>$resultingInvoiceObj->Id,
                    'status'=>'created'
                ];
            $transactionID  = $this->QboIntegrationTransaction->add($createdQboData);
            }else{

                $resultingInvoiceObj = $qbo_helper->updateQboInvoice($blesta_invoice_data,$qbo_invoice_id->qbo_id);
                $createdQboData = [
                    'type'=>'invoices',
                    'blesta_id'=>$blesta_invoice_id,
                    'qbo_id'=>$resultingInvoiceObj->Id,
                    'status'=>'updated'
                ];
            $transactionID  = $this->QboIntegrationTransaction->update($createdQboData,$resultingInvoiceObj->Id);
            }
            return $transactionID;
        }
       
    }
    /**
     * 
     * This function will return package data after add or edit
     */
    public function getPackageData(EventInterface $event){
      Loader::loadModels($this, ['QboIntegration.QboIntegrationTransaction']);
        $qbo_helper = new QboIntegrationHelper();
        $params = $event->getParams();
        $return = $event->getReturnValue();
        if(isset($params['package_id'])){
            $blesta_package_id = $params['package_id'];
            $blesta_package_data = $this->Record->select(array("packages.*","package_descriptions.*","pricings.*","package_names.*"))->from("packages")
            ->innerJoin('package_descriptions','package_descriptions.package_id','=','packages.id',false)
            ->leftJoin('pricings','pricings.id','=','packages.id',false)
            ->leftJoin('package_names','package_names.package_id','=','packages.id',false)
             ->where('packages.id','=',$blesta_package_id)
             ->fetch();
             //get package configuration 
             $package_configuration = $this->Record->select(array("qbo_product_settings.*"))->from("qbo_product_settings")->fetch();
             if($package_configuration){
                $qbo_product_accounts = json_decode($package_configuration->accounts);
                $qbo_product_accounts->product_type = $package_configuration->product_type;
            //  echo "<pre>";print_R($qbo_product_accounts);die;
            $qbo_item_id =  $this->getQbId($blesta_package_id,'product');
            
            if(empty($qbo_item_id)){
                $resultingItemObj = $qbo_helper->newQboItem($blesta_package_data,$qbo_product_accounts);
                $createdQboData = [
                    'type'=>'product',
                    'blesta_id'=>$blesta_package_id,
                    'qbo_id'=>$resultingItemObj->Id,
                    'status'=>'created'
                ];
             $transactionID  = $this->QboIntegrationTransaction->add($createdQboData);
            }else{
                $resultingItemObj = $qbo_helper->updateQboItem($blesta_package_data,$qbo_item_id,$qbo_product_accounts);
                $createdQboData = [
                    'type'=>'product',
                    'blesta_id'=>$blesta_package_id,
                    'qbo_id'=>$resultingItemObj->Id,
                    'status'=>'updated'
                ];
             $transactionID  = $this->QboIntegrationTransaction->update($createdQboData,$resultingItemObj->Id);
           
            }
        }
        }
    }
    /**
     * 
     * This function will return applied transaction
     */
    public function getTransactionData(EventInterface $event){
        Loader::loadModels($this, ['QboIntegration.QboIntegrationTransaction',]);
        $qbo_helper = new QboIntegrationHelper();
        $params = $event->getParams();
        $return = $event->getReturnValue();
        if(isset($params['transaction_id'])){
            $blesta_transaction_id = $params['transaction_id']; 
            if (!isset($this->Transactions)) {
                Loader::loadModels($this, array("Transactions"));
            }
            $blesta_transaction_data = $this->Transactions->get($blesta_transaction_id);
            $amounts = $blesta_transaction_data->amount;
            echo "<pre>";print_R($blesta_transaction_data);die;
           
        /*    $blesta_client_id = $blesta_transaction_data->client_id;
            $gateway_id = $blesta_transaction_data->gateway_id;
            $transactionID = $blesta_transaction_data->transaction_id;
            */
            
            $vars = [];
            $vars= ['amount'=>$amounts];
            $blesta_transaction_apply = $this->Transacions->apply($blesta_transaction_id,$vars);
          //  $blesta_transaction_apply = $this->QboIntegrationTransaction->newEvents($blesta_transaction_id);
       //     $blesta_transaction_dataArr = $this->Transactions->getApplied('55');
        
            echo "<pre>";print_R($blesta_transaction_apply);die;
        }
    }
    /**
     * Performs any necessary cleanup actions
     *
     * @param int $plugin_id The ID of the plugin being uninstalled
     * @param bool $last_instance True if $plugin_id is the last instance across
     *  all companies for this plugin, false otherwise
     */
    public function uninstall($plugin_id, $last_instance)
    {

        //drop table 
        $qbo_tables = ["qbo_configuration","qbo_transaction","qbo_entities","qbo_product_settings"];
        foreach($qbo_tables as $key=>$value){
            $this->Record->drop($value);
        }

        if ($last_instance) {
            
        }
////
////
////        Loader::loadModels($this, ['Emails', 'EmailGroups']);
////        Configure::load('qbo_integration', dirname(__FILE__) . DS . 'config' . DS);
////
////        $emails = Configure::get('QboIntegration.install.emails');
////        // Remove emails and email groups as necessary
////        foreach ($emails as $email) {
////            // Fetch the email template created by this plugin
////            $group = $this->EmailGroups->getByAction($email['action']);
////
////            // Delete all emails templates belonging to this plugin's email group and company
////            if ($group) {
////                $this->Emails->deleteAll($group->id, Configure::get('Blesta.company_id'));
////
////                if ($last_instance) {
////                    $this->EmailGroups->delete($group->id);
////                }
////            }
////        }
    }

    /**
     * Returns all permissions to be configured for this plugin (invoked after install(), upgrade(),
     *  and uninstall(), overwrites all existing permissions)
     *
     * @return array A numerically indexed array containing:
     *
     *  - group_alias The alias of the permission group this permission belongs to
     *  - name The name of this permission
     *  - alias The ACO alias for this permission (i.e. the Class name to apply to)
     *  - action The action this ACO may control (i.e. the Method name of the alias to control access for)
     */
    public function getPermissions()
    {
        return [
            [
                'group_alias' => 'qbo_integration.admin_main',
                'name' => Language::_('QboIntegrationPlugin.permission.invoice_list', true),
                'alias' => 'qbo_integration.invoice_list',
                'action' => '*'
            ],
        ];
        
    }

    /**
     * Returns all permission groups to be configured for this plugin (invoked after install(), upgrade(),
     *  and uninstall(), overwrites all existing permission groups)
     *
     * @return array A numerically indexed array containing:
     *
     *  - name The name of this permission group
     *  - level The level this permission group resides on (staff or client)
     *  - alias The ACO alias for this permission group (i.e. the Class name to apply to)
     */
    public function getPermissionGroups()
    {
        return [
            [
                'name' => Language::_('QboIntegrationPlugin.permission.invoice_list', true),
                'level' => 'staff',
                'alias' => 'qbo_integration.invoice_list'
            ]
        ];
    }
    public function getActions() {
       return   [
       // Staff Nav
       [
        'action' => 'nav_primary_staff',
        'uri' => 'plugin/qbo_integration/admin_main/',
        'name' => 'QboIntegrationPlugin.nav_primary_staff.qbo_integration',
        'options' => [
            'sub' => [
                [
                    'uri' => 'plugin/qbo_integration/admin_configuration/',
                    'name' => 'QboIntegrationPlugin.nav_primary_staff.configuration'
                ],
                [
                    'uri' => 'plugin/qbo_integration/admin_settings/',
                    'name' => 'QboIntegrationPlugin.nav_primary_staff.settings'
                ],
                [
                    'uri' => 'plugin/qbo_integration/admin_master/',
                    'name' => 'QboIntegrationPlugin.nav_primary_staff.master'
                ],
                /*
                [
                    'uri' => 'plugin/qbo_integration/admin_invoice_list/',
                    'name' => 'QboIntegrationPlugin.nav_primary_staff.invoice'
                ],
                [
                    'uri' => 'plugin/qbo_integration/admin_customer_list/',
                    'name' => 'QboIntegrationPlugin.nav_primary_staff.customer'
                ], 
                [
                    'uri' => 'plugin/qbo_integration/admin_payment_list/',
                    'name' => 'QboIntegrationPlugin.nav_primary_staff.payment'
                ],
                */
            ]
        ]
    ],
    ];
    }
}

