<?php
/**
 * Qbo Integration parent controller
 *
 * @link https://www.expsoltech.com/ blesta
 */
require_once 'vendor/autoload.php';  
require_once 'qbo_integration_helper.php';
class QboIntegrationController extends AppController
{
    /**
     * Require admin to be login and setup the view
     */
    public function preAction()
    {
        $this->structure->setDefaultView(APPDIR);
        parent::preAction();
        // Load config
        Configure::load('qbo_integration', dirname(__FILE__) . DS . 'config' . DS);
        Loader::loadComponents($this, array("Form","Record"));
            
        // Override default view directory
        $this->view->view = 'default';
        $this->orig_structure_view = $this->structure->view;
        $this->structure->view = 'default';

    }
}
