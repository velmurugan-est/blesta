<?php
class QboIntegrationTransaction extends QboIntegrationModel{
    
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();  

        Language::loadLang('qbo_integration', null, PLUGINDIR . 'qbo_integration' . DS . 'language' . DS);
    }
     /**
     * 
     * Add Record
     */
    public function add(array $vars)
    {
        $date = date('Y-m-d H:i:s');
            $fields = [
               'type','blesta_id','status','qbo_id','created_at','updated_at'
            ];
            $vars['created_at'] = $date;
            $vars['updated_at'] = $date;
            $this->Record->insert('qbo_transaction', $vars, $fields);
            return $this->Record->lastInsertId();
    }
    /**
     * 
     * Update Record
     */
    public function update(array $vars,$qbo_id){
        $date = date('Y-m-d H:i:s');
        $fields = [
           'type','blesta_id','status','qbo_id','updated_at','is_mapping'
        ];
        $vars['updated_at'] = $date;
        $this->Record->where("qbo_id", "=", $qbo_id)->update('qbo_transaction', $vars, $fields);
        return $qbo_id;
    }
    public function newEvents($blesta_transaction_id)
    {
        // Register the 'EventName' and have it trigger our 'callbackMethod'
        $eventFactory = $this->getFromContainer('util.events');
        $eventListener = $eventFactory->listener();
        $eventListener->register('TransactionSupplies', [$this, 'callbackMethod']);
        $eventListener->trigger($eventFactory->event('TransactionSupplies', ['transaction_id'=>$blesta_transaction_id]));
        return false; // don't render a view
    }
 
    public function callbackMethod($event)
    {   
        $params = $event->getParams();
        $blesta_transaction_id = $params['transaction_id'];
        echo $blesta_transaction_id;
        die("sds");
    }
}