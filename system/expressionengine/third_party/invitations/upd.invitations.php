<?php

/*
=====================================================
 Invitations
-----------------------------------------------------
 http://www.intoeetive.com/
-----------------------------------------------------
 Copyright (c) 2012 Yuri Salimovskiy
=====================================================
 This software is intended for usage with
 ExpressionEngine CMS, version 2.0 or higher
=====================================================
 File: ext.invitations.php
-----------------------------------------------------
 Purpose: System to manage invitation codes
=====================================================
*/

if ( ! defined('BASEPATH'))
{
    exit('Invalid file request');
}

require_once PATH_THIRD.'invitations/config.php';

class Invitations_upd {

    var $version = INVITATIONS_ADDON_VERSION;
    
    function __construct() { 
        // Make a local reference to the ExpressionEngine super object 
        $this->EE =& get_instance(); 
    } 
    
    function install() { 
  
        $this->EE->lang->loadfile('invitations');  
		
		$this->EE->load->dbforge(); 
        
        //----------------------------------------
		// EXP_MODULES
		// The settings column, Ellislab should have put this one in long ago.
		// No need for a seperate preferences table for each module.
		//----------------------------------------
		if ($this->EE->db->field_exists('settings', 'modules') == FALSE)
		{
			$this->EE->dbforge->add_column('modules', array('settings' => array('type' => 'TEXT') ) );
		}
        
        $settings = array();

        $data = array( 'module_name' => 'Invitations' , 'module_version' => $this->version, 'has_cp_backend' => 'y', 'has_publish_fields' => 'n', 'settings'=> serialize($settings) ); 
        $this->EE->db->insert('modules', $data); 
        
        $data = array( 'class' => 'Invitations' , 'method' => 'check' ); 
        $this->EE->db->insert('actions', $data); 
        
        $data = array( 'class' => 'Invitations' , 'method' => 'process_apply' ); 
        $this->EE->db->insert('actions', $data); 
        
        $this->EE->db->query("CREATE TABLE IF NOT EXISTS `exp_invitations_codes` (
              `code_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
              `code` VARCHAR(100) NOT NULL,
              `site_id` INT( 10 ) NOT NULL DEFAULT '0',
              `author_id` INT( 10 ) NOT NULL DEFAULT '0',
              `destination_group_id` INT( 10 ) NOT NULL DEFAULT '0',
              `email` VARCHAR(255) NOT NULL DEFAULT '',
              `note` VARCHAR(255) NOT NULL DEFAULT '',
              `credits_author` DECIMAL(10,2) NOT NULL DEFAULT '0',
              `credits_user` DECIMAL(10,2) NOT NULL DEFAULT '0',
              `usage_limit` INT NOT NULL DEFAULT '1',
              `unlimited_usage` ENUM(  'y',  'n' ) NOT NULL default 'n',
              `times_used` INT NOT NULL DEFAULT '0',
              `created_date` INT( 10 ) NOT NULL DEFAULT '0',
              `expires_date` INT( 10 ) NOT NULL DEFAULT '0',
              KEY `site_id` (`site_id`),
              KEY `author_id` (`author_id`)
            )");
        
        $this->EE->db->query("CREATE TABLE IF NOT EXISTS `exp_invitations_uses` (
          `member_id` INT( 10 ) NOT NULL DEFAULT '0',
          `code_id` INT( 10 ) NOT NULL DEFAULT '0',
          `ip_address` VARCHAR(128) NOT NULL,
          `used_date` INT( 10 ) NOT NULL DEFAULT '0',
          KEY `member_id` (`member_id`),
          KEY `code_id` (`code_id`)
        )");

        //credits
        
        $this->EE->db->select('module_id'); 
        $credits_installed_q = $this->EE->db->get_where('modules', array('module_name' => 'Credits')); 
        if ($credits_installed_q->num_rows()>0)
        {
        	$data = array(
				'action_name' => 'invitations_redeemed_author',
				'action_title' => $this->EE->lang->line('invitations_redeemed_author'),
				'action_credits' => 0,
				'enabled' => 1
			);
			$this->EE->db->insert('exp_credits_actions', $data);
			
			$data = array(
				'action_name' => 'invitations_redeemed_user',
				'action_title' => $this->EE->lang->line('invitations_redeemed_user'),
				'action_credits' => 0,
				'enabled' => 1
			);
			$this->EE->db->insert('exp_credits_actions', $data);
        }
        
        return TRUE; 
        
    } 
    
    function uninstall() { 

        $this->EE->db->select('module_id'); 
        $query = $this->EE->db->get_where('modules', array('module_name' => 'Invitations')); 
        
        $this->EE->db->where('module_id', $query->row('module_id')); 
        $this->EE->db->delete('module_member_groups'); 
        
        $this->EE->db->where('module_name', 'Invitations'); 
        $this->EE->db->delete('modules'); 
        
        $this->EE->db->where('class', 'Invitations'); 
        $this->EE->db->delete('actions'); 
        
        $this->EE->db->query("DROP TABLE exp_invitations_codes");
        $this->EE->db->query("DROP TABLE exp_invitations_uses");
        
        $this->EE->db->select('module_id'); 
        $credits_installed_q = $this->EE->db->get_where('modules', array('module_name' => 'Credits')); 
        if ($credits_installed_q->num_rows()>0)
        {
        	$this->EE->db->where('action_name', 'invitations_redeemed_author')
        				->or_where('action_name', 'invitations_redeemed_user');
			$this->EE->db->delete('exp_credits_actions');
        }
        
        return TRUE; 
    } 
    
    function update($current='') 
	{ 
        if ($current < 1.1) { 
            $this->EE->load->dbforge(); 
   			$this->EE->dbforge->add_column('invitations_codes', array('note' => array('type' => 'VARCHAR', 'constraint' => '255', 'default'=>'') ) );
        } 
        
        if ($current < 1.2)
        {

    		$data = array(
        		'class'		=> 'Invitations_ext',
        		'method'	=> 'freeform_check_code',
        		'hook'		=> 'freeform_module_validate_begin',
        		'settings'	=> '',
        		'priority'	=> 5,
        		'version'	=> $this->version,
        		'enabled'	=> 'y'
        	);
            $this->EE->db->insert('extensions', $data);
            
            $data = array(
        		'class'		=> 'Invitations_ext',
        		'method'	=> 'freeform_create_record',
        		'hook'		=> 'freeform_module_insert_end',
        		'settings'	=> '',
        		'priority'	=> 5,
        		'version'	=> $this->version,
        		'enabled'	=> 'y'
        	);
            $this->EE->db->insert('extensions', $data);

        }
        return TRUE; 
    } 
	

}
/* END */
?>