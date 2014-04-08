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

class Invitations_ext {

	var $name	     	= INVITATIONS_ADDON_NAME;
	var $version 		= INVITATIONS_ADDON_VERSION;
	var $description	= 'System to manage invitation codes';
	var $settings_exist	= 'y';
	var $docs_url		= 'http://www.intoeetive.com/docs/invitations.html';
    
    var $settings 		= array();
    var $site_id		= 1;
    
	/**
	 * Constructor
	 *
	 * @param 	mixed	Settings array or empty string if none exist.
	 */
	function __construct($settings = '')
	{
		$this->EE =& get_instance();
		$this->site_id = $this->EE->config->item('site_id');
		$this->EE->lang->loadfile('invitations');  
	}
    
    /**
     * Activate Extension
     */
    function activate_extension()
    {
        
        $hooks = array(
    		//validate invitation
    		array(
    			'hook'		=> 'member_member_register_start',
    			'method'	=> 'check_code',
    			'priority'	=> 5
    		),
    		array(
    			'hook'		=> 'zoo_visitor_register_start',
    			'method'	=> 'check_code',
    			'priority'	=> 5
    		),
    		array(
    			'hook'		=> 'user_register_start',
    			'method'	=> 'check_code',
    			'priority'	=> 5
    		),
    		array(
    			'hook'		=> 'freeform_module_validate_begin',
    			'method'	=> 'freeform_check_code',
    			'priority'	=> 5
    		),
    		//create record
			array(
    			'hook'		=> 'member_member_register',
    			'method'	=> 'create_record',
    			'priority'	=> 5
    		),
    		array(
    			'hook'		=> 'zoo_visitor_register_end',
    			'method'	=> 'create_record',
    			'priority'	=> 5
    		),
    		array(
    			'hook'		=> 'user_register_end',
    			'method'	=> 'create_record',
    			'priority'	=> 5
    		),
    		array(
    			'hook'		=> 'freeform_module_insert_end',
    			'method'	=> 'freeform_create_record',
    			'priority'	=> 5
    		),
    		//move the user to group
    		array(
    			'hook'		=> 'member_register_validate_members',
    			'method'	=> 'finalize_self',
    			'priority'	=> 5
    		),
    		array(
    			'hook'		=> 'cp_members_validate_members',
    			'method'	=> 'finalize_cp',
    			'priority'	=> 5
    		)
    		
            
    	);
    	
        foreach ($hooks AS $hook)
    	{
    		$data = array(
        		'class'		=> __CLASS__,
        		'method'	=> $hook['method'],
        		'hook'		=> $hook['hook'],
        		'settings'	=> '',
        		'priority'	=> $hook['priority'],
        		'version'	=> $this->version,
        		'enabled'	=> 'y'
        	);
            $this->EE->db->insert('extensions', $data);
    	}	
        
    }
    
    /**
     * Update Extension
     */
    function update_extension($current = '')
    {
		
		if ($current == '' OR $current == $this->version)
    	{
    		return FALSE;
    	}
    	
    	$this->EE->db->where('class', __CLASS__);
    	$this->EE->db->update(
    				'extensions', 
    				array('version' => $this->version)
    	);
    }
    
    
    /**
     * Disable Extension
     */
    function disable_extension()
    {
    	$this->EE->db->where('class', __CLASS__);
    	$this->EE->db->delete('extensions');
    }
    
    
    
    function settings()
    {
		$this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=settings');
		return true;
    }
    
    
    function check_code($obj=false)
    {
    	$settings = $this->_get_settings();
    	
    	//is the code required
    	$invitation_required = (isset($settings['invitation_required']) && $settings['invitation_required']==true)?true:false;
    	if ($this->EE->input->get_post('invitation')=="")
    	{
    		if ($invitation_required==false)
    		{
    			return true;
    		}
   			$this->_show_error('submission', lang('invitation_code_is_required'));
    	}

    	//does the code exist?
    	$this->EE->db->select()
    				->from('invitations_codes')
    				->where('code', $this->EE->input->get_post('invitation'));
		$q = $this->EE->db->get();
		if ($q->num_rows()==0)
		{
			$this->_show_error('submission', lang('invalid_invitation'));
		}
		
		//email set and does not match?
		if ($q->row('email')!='' && $q->row('email')!=$this->EE->input->get_post('email'))
		{
			$this->_show_error('submission', lang('invitation_not_allowed'));
		}
		
		// over usage limit?
		if ($q->row('unlimited_usage')!='y' && $q->row('times_used') >= $q->row('usage_limit'))
		{
			$this->_show_error('submission', lang('invitation_cannot_be_used_anymore'));
		}
		
		// expired?
		if ($q->row('expires_date')!='0' && $this->EE->localize->now > $q->row('expires_date'))
		{
			$this->_show_error('submission', lang('invitation_expired'));
		}
		
		//valid for this site?
		if ($q->row('site_id')!='0' && $q->row('site_id')!=$this->EE->config->item('site_id'))
		{
			$this->_show_error('submission', lang('invitation_not_for_this_site'));
		}
		
		
		//proceed to creation of account
		
	}
    
    
    
    function freeform_check_code($errors, $obj)
    {
		
		if (REQ == 'CP')
		{
			return $errors;
		}
		
		if ($obj->edit == TRUE)
		{
			return $errors;
		}
		
		if ($obj->multipage == TRUE && $obj->last_page != TRUE)
		{
			return $errors;
		}
		
		$settings = $this->_get_settings();
    	
    	if (!isset($settings['invitation_required_freeform']) || !in_array($obj->params['form_id'], $settings['invitation_required_freeform']))
    	{
    		return $errors;
    	}
    	
    	if ($this->EE->input->get_post('invitation')=="")
    	{
    		$errors['invitations_error'] = lang('invitation_code_is_required');
			return $errors;
    	}

    	//does the code exist?
    	$this->EE->db->select()
    				->from('invitations_codes')
    				->where('code', $this->EE->input->get_post('invitation'));
		$q = $this->EE->db->get();
		if ($q->num_rows()==0)
		{
			$errors['invitations_error'] = lang('invalid_invitation');
			return $errors;
		}
		
		//email set and does not match?
		if ($q->row('email')!='' && $q->row('email')!=$this->EE->input->get_post('email'))
		{
			$errors['invitations_error'] = lang('invitation_not_allowed');
			return $errors;
		}
		
		// over usage limit?
		if ($q->row('unlimited_usage')!='y' && $q->row('times_used') >= $q->row('usage_limit'))
		{
			$errors['invitations_error'] = lang('invitation_cannot_be_used_anymore');
			return $errors;
		}
		
		// expired?
		if ($q->row('expires_date')!='0' && $this->EE->localize->now > $q->row('expires_date'))
		{
			$errors['invitations_error'] = lang('invitation_expired');
			return $errors;
		}
		
		//valid for this site?
		if ($q->row('site_id')!='0' && $q->row('site_id')!=$this->EE->config->item('site_id'))
		{
			$errors['invitations_error'] = lang('invitation_not_for_this_site');
			return $errors;
		}
		
		
		
		
		
		return $errors;
		
	}
	
	function freeform_create_record($field_input_data, $entry_id, $form_id, $obj)
	{
		$settings = $this->_get_settings();
    	
    	if (!isset($settings['invitation_required_freeform']) || !in_array($form_id, $settings['invitation_required_freeform']))
    	{
    		return;
    	}
    	
    	$this->create_record(array(), $this->EE->session->userdata('member_id'));
    	
    	
	}
    
	function create_record($data, $member_id)
	{
		$this->EE->db->select('code_id')
    				->from('invitations_codes')
    				->where('code', $this->EE->input->get_post('invitation'));
		$q = $this->EE->db->get();
		if ($q->num_rows()==0)
		{
			return false;
		}
		
		$insert = array(
			'member_id' => $member_id,
			'code_id'	=> $q->row('code_id'),
			'ip_address'=> $this->EE->input->ip_address()
		);
		$this->EE->db->select('member_id');
		$this->EE->db->where($insert);
		$q = $this->EE->db->get('invitations_uses');
		if ($q->num_rows()>0)
		{
			return true;
		}
		
		$insert['used_date '] = 0;
		
		$this->EE->db->insert('invitations_uses', $insert);
		
		$this->EE->db->query("UPDATE exp_invitations_codes SET times_used=times_used+1 WHERE code_id='".$insert['code_id']."'");
		
		if ($this->EE->config->item('req_mbr_activation') == 'manual' || $this->EE->config->item('req_mbr_activation') == 'email')
		{
			return true;
		}
		
		//if we don't need activation - finalize task
    	$this->finalize($insert, $member_id);
    	
    }
    
    function finalize_self($member_id)
    {
    	$this->finalize(false, $member_id);
    }
    
    function finalize_cp()
    {
    	foreach ($_POST['toggle'] as $key => $val)
    	{
			$this->finalize(false, $val);
		}
    }
    
    
    function finalize($data=false, $member_id=false)
    {
		if ($member_id===false)
		{
			$member_id = $this->EE->session->userdata('member_id');
		}
		
		if ($data==false)
    	{
    		$this->EE->db->select('code_id')
    				->from('invitations_uses')
    				->where('member_id', $member_id)
					->order_by('used_date', 'desc')
					->limit(1);
			$q = $this->EE->db->get();
			if ($q->num_rows()==0)
			{
				return false;
			}
			$data = array(
				'member_id' => $member_id,
				'code_id' => $q->row('code_id')
			);
    	}
    	
    	$this->EE->db->select()
    				->from('invitations_codes')
    				->where('code_id', $data['code_id']);
		$q = $this->EE->db->get();
		if ($q->num_rows()==0)
		{
			return false;
		}

		//set the group
		if ($q->row('destination_group_id')!=0 && ($data==false || $q->row('destination_group_id')!=$this->EE->session->userdata('group_id')))
		{
			$this->EE->db->query("UPDATE exp_members SET group_id = '".$q->row('destination_group_id')."' WHERE member_id = '".$data['member_id']."'");
			$this->EE->stats->update_member_stats();
			
			$zoo = $this->EE->db->select('module_id')->from('modules')->where('module_name', 'Zoo_visitor')->get(); 
	        if ($zoo->num_rows()>0)
	        {
	        	$this->EE->load->add_package_path(PATH_THIRD.'zoo_visitor/');
				$this->EE->load->library('zoo_visitor_cp');
				$this->EE->zoo_visitor_cp->sync_member_status($member_id);
				$this->EE->load->remove_package_path(PATH_THIRD.'zoo_visitor/');
	        }
			
		}

		//add credits
		if ($q->row('credits_author')!='0' || $q->row('credits_user')!='0')
		{
			$this->EE->db->select('module_id'); 
	        $credits_installed_q = $this->EE->db->get_where('modules', array('module_name' => 'Credits')); 
	        if ($credits_installed_q->num_rows()>0)
	        {            	
				if ($q->row('credits_author')!='0')
	        	{
					$this->EE->db->select('action_id, enabled')
								->where('action_name', 'invitations_redeemed_author');
					$credits_action_q = $this->EE->db->get('exp_credits_actions');
					if ($credits_action_q->num_rows()>0 && $credits_action_q->row('enabled')==1)
		        	{
						$pData = array(
							'action_id'	=> $credits_action_q->row('action_id'),
							'credits'	=> $q->row('credits_author'),
							'receiver'	=> $q->row('author_id'),
							'item_id'	=> $member_id,
							'item_parent_id' => 0
						);
		
						$this->_save_credits($pData);
		    		}
    			}
    			
    			if ($q->row('credits_user')!='0')
	        	{
					$this->EE->db->select('action_id, enabled')
								->where('action_name', 'invitations_redeemed_user');
					$credits_action_q = $this->EE->db->get('exp_credits_actions');
					if ($credits_action_q->num_rows()>0 && $credits_action_q->row('enabled')==1)
		        	{
						$pData = array(
							'action_id'	=> $credits_action_q->row('action_id'),
							'credits'	=> $q->row('credits_user'),
							'receiver'	=> $member_id,
							'item_id'	=> $member_id,
							'item_parent_id' => 0
						);
		
						$this->_save_credits($pData);
		    		}
    			}
	        }
       }
		
		//update our tables
		
		$this->EE->db->query("UPDATE exp_invitations_uses SET used_date='".$this->EE->localize->now."' WHERE member_id='".$data['member_id']."' AND  code_id='".$this->EE->db->escape_str($data['code_id'])."' AND used_date='0'");

    }
    
    
    function _get_settings()
    {
    	$query = $this->EE->db->query("SELECT settings FROM exp_modules WHERE module_name='Invitations' LIMIT 1");
        $settings = unserialize($query->row('settings')); 
        if (!empty($settings) && isset($settings[$this->site_id]))
        {
        	return $settings[$this->site_id];
        }
        else
        {
        	return array(
				'invitation_required'=>false,
	            'destination_group_id'=>'0',
	            'default_credits_author'=>0,
	            'default_credits_user'=>0
    		);
        }
        
    }
    
    
   	function _save_credits($pData=FALSE)
	{

		// Does the action stat already exist?
		$query = $this->EE->db->select('credit_id, credits')->from('exp_credits')->where('action_id', $pData['action_id'])->where('member_id',  $pData['receiver'])->where('site_id', $this->site_id)->limit(1)->get();

		// Do we need to update?
		$update = ( $query->num_rows() > 0 ) ? TRUE : FALSE;
		$credit_id = $query->row('credit_id');

		// Resources are not free
		$query->free_result();

		if ($update)
		{
			$this->EE->db->set('credits', "( credits + {$pData['credits']} )", FALSE);
			$this->EE->db->where('credit_id', $credit_id);
			$this->EE->db->update('exp_credits');
		}
		else
		{
			$this->EE->db->set('action_id',	$pData['action_id']);
			$this->EE->db->set('site_id',	$this->site_id);
			$this->EE->db->set('member_id', $pData['receiver']);
			$this->EE->db->set('credits',	$pData['credits']);
			$this->EE->db->insert('exp_credits');
		}

		// Log Credits!
		if (isset($pData['rule_id']) != FALSE && $pData['rule_id'] > 0)
		{
			$pData['date'] = $this->EE->localize->now + 10;
		}

		$this->EE->db->set('site_id',		$this->site_id);
		$this->EE->db->set('sender',		(isset($pData['sender']) ? $pData['sender'] : 0) );
		$this->EE->db->set('receiver',		(isset($pData['receiver']) ?  $pData['receiver'] : 0) );
		$this->EE->db->set('action_id',		(isset($pData['action_id']) ?  $pData['action_id'] : 0) );
		$this->EE->db->set('rule_id',		(isset($pData['rule_id']) ?  $pData['rule_id'] : 0) );
		$this->EE->db->set('date',			(isset($pData['date']) ?  $pData['date'] : $this->EE->localize->now) );
		$this->EE->db->set('credits',		(isset($pData['credits']) ?  $pData['credits'] : 0) );
		$this->EE->db->set('item_type',		(isset($pData['item_type']) ?  $pData['item_type'] : 0) );
		$this->EE->db->set('item_id',		(isset($pData['item_id']) ?  $pData['item_id'] : 0) );
		$this->EE->db->set('item_parent_id',(isset($pData['item_parent_id']) ?  $pData['item_parent_id'] : 0) );
		$this->EE->db->set('comments',		(isset($pData['comments']) ?  $pData['comments'] : '') );
		$this->EE->db->insert('exp_credits_log');

		return;
	}
    
    
    function _show_error($type='general', $message, $invitation_required=false)
    {
		$this->EE->output->show_user_error($type, $message);
    }     
    
  

}
// END CLASS
