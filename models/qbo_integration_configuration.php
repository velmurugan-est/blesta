<?php

class QboIntegrationConfiguration extends QboIntegrationModel{

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
     * Add configuration
     */
    public function add(array $vars)
    {
        $date = date('Y-m-d H:i:s');
        $this->Input->setRules($this->getRules($vars));
        if ($this->Input->validates($vars)) {
            $fields = [
               'client_id','client_secret','redirect_url','created_at','updated_at'
            ];
            $vars['created_at'] = $date;
            $vars['updated_at'] = $date;
            $this->Record->insert('qbo_configuration', $vars, $fields);
            return $this->Record->lastInsertId();
        }
    }
    /**
     * 
     * update Configuration
     */
    public function update($record_id,array $vars){
            $fields = [
             'access_token','refresh_token','realmid','is_connected','created_at','updated_at'
            ];
            $this->Record->where('id', '=', $record_id)->
            update('qbo_configuration', $vars, $fields);
            return $record_id;  
    }
    /**
     * 
     * Edit configuration
     */
    public function edit($record_id,array $vars){
         
        $this->Input->setRules($this->getRules($vars));
        if ($this->Input->validates($vars)) {
            $fields = [
               'client_id','client_secret','redirect_url'
            ];
            $this->Record->where('id', '=', $record_id)->
            update('qbo_configuration', $vars, $fields);
        }
            return $record_id;
    }
    public function getRules(array $vars){
         
        $rules = [
            'client_id' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' =>"Client Id should not be empty"
                ]
            ],
            'client_secret' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' =>"Client Secret should not be empty"
                ]
            ],
            'redirect_url' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' =>"Redirect url should not be empty"
                ]
            ],
        ];

        return $rules;
    }
}