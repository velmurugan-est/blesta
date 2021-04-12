<?php
/**
 * Support Manager Admin Main controller
 *
 * @package blesta
 * @subpackage blesta.plugins.support_manager
 * @copyright Copyright (c) 2010, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 */
class AdminMaster extends QboIntegrationController
{
    /**
     * Redirect to the AdminTickets controller
     */
    public function preAction()
    {
        parent::preAction();
        // Restore structure view location of the admin portal
        $this->structure->setDefaultView(APPDIR);
        $this->structure->setView(null, $this->orig_structure_view);        
    }
    public function index()
    {      
        //echo $this->render('admin_customer_list');     

    }
}
