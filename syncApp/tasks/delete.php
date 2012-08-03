<?php

/**
*
* @class                task_item
* @brief                Task to reimport ical/webcal feeds
*
*/
class task_item
{
        /**
         * Object that stores the parent task manager class
         *
         * @var         $class
         */
        protected $class;

        /**
         * Array that stores the task data
         *
         * @var         $task
         */
        protected $task = array();

        /**
         * Registry Object Shortcuts
         *
         * @var         $registry
         * @var         $DB
         * @var         $lang
         */
        protected $registry;
        protected $DB;
        protected $lang;

        /**
         * Constructor
         *
         * @param       object          $registry               Registry object
         * @param       object          $class                  Task manager class object
         * @param       array           $task                   Array with the task data
         * @return      @e void
         */
        public function __construct( ipsRegistry $registry, $class, $task )
        {
                /* Make registry objects */
                $this->registry = $registry;
                $this->DB               = $registry->DB();
                $this->settings = ipsRegistry::fetchSettings();
                $this->lang             = $this->registry->getClass('class_localization');

                $this->class    = $class;
                $this->task             = $task;

                $this->registry->dbFunctions()->setDB( 'mysql', 'appSyncWoWqqDB', array(
                          'sql_database'                  => $this->settings['syncapp_realm_database'],
                          'sql_user'                      => $this->settings['syncapp_mysql_user'],
                          'sql_pass'                      => $this->settings['syncapp_mysql_password'],
                          'sql_host'                      => $this->settings['syncapp_mysql_ip'],
                        )
                    );
        }
        public function ExecuteSoapCommand($command)
        {
            try
            {
                $cliente = new SoapClient(NULL, array(
                    "location" => $this->settings['syncapp_soap_ip'], //"http://127.0.0.1:7878/",
                    "uri"   => "urn:TC",
                    "style" => SOAP_RPC,
                    "login" => $this->settings['syncapp_soap_user'],
                    "password" => $this->settings['syncapp_soap_password']));

            $result = $cliente->executeCommand(new SoapParam($command, "command"));

            }
            catch(Exception $e)
            {
                return array('sent' => false, 'message' => $e->getMessage());
            }

            return array('sent' => true, 'message' => $result);
        }

        /**
         * Run this task
         *
         * @return      @e void
         */
        public function runTask()
        {
                //-----------------------------------------
                // Here is where you perform your task
                //-----------------------------------------

            // TODO:
            //* Add deleted users to log
                $members = array();
                ipsRegistry::DB()->build(array('select' => '*', 'from' => 'syncapp_members', 'where' => "deleted='1'"));
                $memdb =  ipsRegistry::DB()->execute();
                while( $mems = ipsRegistry::DB()->fetch($memdb))
                    {
                        $members[] = $mems['account_id'];
                    }
                    ipsRegistry::DB('appSyncWoWqqDB')->freeResult($memdb);
                 if(count($members)>0)
                    {
                        $account = array();
                        ipsRegistry::DB('appSyncWoWqqDB')->build(array('select' => 'username', 'from' => 'account', 'where' => "id IN('".implode("','", $members)."')"));
                        $acctdb =  ipsRegistry::DB('appSyncWoWqqDB')->execute();

                    while( $accts = ipsRegistry::DB('appSyncWoWqqDB')->fetch($acctdb))
                        {
                            $account[] = $accts['username'];
                        }
                    }
                    ipsRegistry::DB('appSyncWoWqqDB')->freeResult($acctdb);

                    if(count($account)>0)
                    {
                        foreach($account as $m)
                        {
                            //do stuff with $m
                            $cmdLineToSend = 'account delete '.$m;
                            $soap_command = $this->ExecuteSoapCommand($cmdLineToSend);

                            if(!$soap_command['sent'])
                            {
                                $pass = 'Delete failed';
                            }
                            else
                            {
                                ipsRegistry::DB()->delete('syncapp_members',  "deleted=1");
                                $pass = 'Old accounts deleted!';
                            }
                        }
                    }




                //-----------------------------------------
                // Save task log
                //-----------------------------------------

                $this->class->appendTaskLog( $this->task, $pass );

                //-----------------------------------------
                // Unlock Task: REQUIRED!
                //-----------------------------------------

                $this->class->unlockTask( $this->task );
        }
}