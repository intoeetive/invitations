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
 File: mod.invitations.php
-----------------------------------------------------
 Purpose: System to manage invitation codes
=====================================================
*/

if ( ! defined('BASEPATH'))
{
    exit('Invalid file request');
}


class Invitations {

    var $return_data	= ''; 	
    
    var $settings = array();
    
    var $perpage = 25;
    
    var $site_id		= 1;

    /** ----------------------------------------
    /**  Constructor
    /** ----------------------------------------*/

    function __construct()
    {        
    	$this->EE =& get_instance(); 
    	$this->site_id = $this->EE->config->item('site_id');
		$this->EE->lang->loadfile('invitations');  
    }
    /* END */
    
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
				'notify_on_requests'=>true,
                'invitation_required'=>false,
	            'destination_group_id'=>'0',
	            'default_credits_author'=>0,
	            'default_credits_user'=>0
    		);
        }
        
    }
    
    function generate()
    {
		if ($this->EE->session->userdata('member_id')==0) 
		{
			//$this->EE->output->show_user_error('general', lang('must_be_logged_in'));
			return $this->EE->TMPL->no_results();
		}
		
		$code = $this->_generate_code();
		
		$settings = $this->_get_settings();
		
		$data = array(
			'code'		=> $code,
			'author_id'	=> $this->EE->session->userdata('member_id'),
			'site_id'	=> $this->EE->config->item('site_id'),
			'created_date' => $this->EE->localize->now,
			'destination_group_id' => $settings['destination_group_id'],
			'credits_author'	=> $settings['default_credits_author'],
			'credits_user'	=> $settings['default_credits_user']
		);
		if ($this->EE->TMPL->fetch_param('email')!='')
		{
			$data['email'] = $this->EE->TMPL->fetch_param('email');

			$this->EE->db->select('member_id')
							->from('members')
							->where('email', $data['email']);
			$q = $this->EE->db->get();
			if ($q->num_rows()>0)
			{
				$this->EE->output->show_user_error('general', lang('email_registered'));
			}
		}
		
		if ($this->EE->TMPL->fetch_param('destination_group_id')!='')
		{
			$data['destination_group_id'] = $this->EE->TMPL->fetch_param('destination_group_id');

			if (in_array($data['destination_group_id'], array(1,2,3,4)))
			{
				$this->EE->output->show_user_error('general', lang('invalid_destination_group'));
			}
			$this->EE->db->select('group_id')
							->from('member_groups')
							->where('group_id', $data['destination_group_id']);
			$q = $this->EE->db->get();
			if ($q->num_rows()==0)
			{
				$this->EE->output->show_user_error('general', lang('invalid_destination_group'));
			}
		}
		
		if ($this->EE->TMPL->fetch_param('credits_author')!='')
		{
			$data['credits_author'] = $this->EE->TMPL->fetch_param('credits_author');
		}

		if ($this->EE->TMPL->fetch_param('credits_user')!='')
		{
			$data['credits_user'] = $this->EE->TMPL->fetch_param('credits_user');
		}
		if ($data['credits_author'] < 0 || $data['credits_user'] < 0)
		{
			$this->EE->output->show_user_error('general', lang('invalid_credits_amount'));
		}

		if ($this->EE->TMPL->fetch_param('expire_in')!='')
        {
            $data['expires_date'] = strtotime($this->EE->TMPL->fetch_param('expire_in'), $this->EE->localize->now);
        }
        
        if ($this->EE->TMPL->fetch_param('expire_on')!='')
        {
            if ($this->EE->config->item('app_version')>=260)
	        {
				$data['expires_date'] = $this->EE->localize->string_to_timestamp($this->EE->TMPL->fetch_param('expire_on'));
			}
			else
			{
				$data['expires_date'] = $this->_string_to_timestamp($this->EE->TMPL->fetch_param('expire_on'));
			}
            
        }
		
		$this->EE->db->insert('invitations_codes', $data);
        $data['code_id'] = $this->EE->db->insert_id();
        
        $this->EE->extensions->call('invitations_generate_end', $data);
		
		return $code;
    }
    
    
    
    function display()
    {
    	$mode = ($this->EE->TMPL->fetch_param('mode')!==false)?$this->EE->TMPL->fetch_param('mode'):'valid';//my/valid/all
    	
    	$start = 0;
        $paginate = ($this->EE->TMPL->fetch_param('paginate')=='top')?'top':(($this->EE->TMPL->fetch_param('paginate')=='both')?'both':'bottom');
        if ($this->EE->TMPL->fetch_param('limit')!='') $this->perpage = $this->EE->TMPL->fetch_param('limit');
        
        $basepath = $this->EE->functions->create_url($this->EE->uri->uri_string);
        $query_string = ($this->EE->uri->page_query_string != '') ? $this->EE->uri->page_query_string : $this->EE->uri->query_string;

		if (preg_match("#^P(\d+)|/P(\d+)#", $query_string, $match))
		{
			$start = (isset($match[2])) ? $match[2] : $match[1];
			$basepath = $this->EE->functions->remove_double_slashes(str_replace($match[0], '', $basepath));
		}
    	
    	switch ($mode)
    	{
    		case 'my':
    			$this->EE->db->where('author_id', $this->EE->session->userdata('member_id'));
    			break;
   			case 'all':
   				//
			   	break; 
   			case 'valid':
   			default:
   				$this->EE->db->where("(unlimited_usage = 'y' OR times_used < usage_limit) AND (expires_date = 0 OR expires_date > ".$this->EE->localize->now.")");
   				break;
    	}
        
        $total = $this->EE->db->count_all_results('exp_invitations_codes');

        switch ($mode)
    	{
    		case 'my':
    			$this->EE->db->select('exp_invitations_codes.*')
			    				->from('exp_invitations_codes');
   				$this->EE->db->where('author_id', $this->EE->session->userdata('member_id'));
    			break;
   			case 'all':
   			case 'valid':
   			default:
   				$this->EE->db->select('exp_invitations_codes.*, exp_members.username AS author_username, exp_members.screen_name AS author_screen_name')
		    				->from('exp_invitations_codes')
		    				->join('exp_members', 'exp_invitations_codes.author_id=exp_members.member_id', 'left');
   				if ($mode=='valid')
   				{
   					$this->EE->db->where("(unlimited_usage = 'y' OR times_used < usage_limit) AND (expires_date = 0 OR expires_date > ".$this->EE->localize->now.")");
   				}
   				break;
    	}
    	$this->EE->db->limit($this->perpage, $start);
        $query = $this->EE->db->get();
        
        if ($query->num_rows()==0)
        {
            return $this->EE->TMPL->no_results();
        }
        
        $tagdata_orig = $this->EE->TMPL->swap_var_single('total_results', $total, $this->EE->TMPL->tagdata);
        $paginate_tagdata = '';
        
        if ( preg_match_all("/".LD."paginate".RD."(.*?)".LD."\/paginate".RD."/s", $tagdata_orig, $tmp)!=0)
        {
            $paginate_tagdata = $tmp[1][0];
            $tagdata_orig = str_replace($tmp[0][0], '', $tagdata_orig);
        }

        $out = '';
        $i = 0;
        
        foreach ($query->result_array() as $row)
        {
            $i++;
            $cond = array();
            $cond['expired'] = ($row['expires_date'] != 0 && $row['expires_date'] < $this->EE->localize->now) ? true : false;
            $cond['valid'] = (($row['unlimited_usage'] == 'y' || $row['times_used'] < $row['usage_limit']) && ($row['expires_date'] == 0 || $row['expires_date'] > $this->EE->localize->now)) ? true : false;
            $cond['not_valid'] = !$cond['valid'];
            $cond['used'] = ($row['times_used'] > 0) ? true : false;

            $tagdata = $this->EE->functions->prep_conditionals($tagdata_orig, $cond);
            
            $tagdata = $this->EE->TMPL->swap_var_single('count', $i, $tagdata);
            $tagdata = $this->EE->TMPL->swap_var_single('absolute_count', $start+$i, $tagdata);
            
            $tagdata = $this->EE->TMPL->swap_var_single('code', $row['code'], $tagdata);
            if ($mode!='my')
            {
	            $tagdata = $this->EE->TMPL->swap_var_single('author_id', $row['author_id'], $tagdata);
	            $tagdata = $this->EE->TMPL->swap_var_single('author_username', $row['author_username'], $tagdata);
	            $tagdata = $this->EE->TMPL->swap_var_single('author_screen_name', $row['author_screen_name'], $tagdata);
            }
            else
            {
          	 	$tagdata = $this->EE->TMPL->swap_var_single('author_id', $this->EE->session->userdata('member_id'), $tagdata);
	            $tagdata = $this->EE->TMPL->swap_var_single('author_username', $this->EE->session->userdata('username'), $tagdata);
	            $tagdata = $this->EE->TMPL->swap_var_single('author_screen_name', $this->EE->session->userdata('screen_name'), $tagdata);
            }
            $tagdata = $this->EE->TMPL->swap_var_single('destination_group_id', $row['destination_group_id'], $tagdata);
            $tagdata = $this->EE->TMPL->swap_var_single('restricted_email', $row['email'], $tagdata);
            $tagdata = $this->EE->TMPL->swap_var_single('credits_author', $row['credits_author'], $tagdata);
            $tagdata = $this->EE->TMPL->swap_var_single('credits_user', $row['credits_user'], $tagdata);
            $tagdata = $this->EE->TMPL->swap_var_single('use_count', $row['times_used'], $tagdata);
            $use_left = ($row['unlimited_usage']=='y')?lang('unlimited'):($row['usage_limit']-$row['times_used']);
            $tagdata = $this->EE->TMPL->swap_var_single('use_left', $use_left, $tagdata);

            
            if (preg_match_all("#".LD."created_date format=[\"|'](.+?)[\"|']".RD."#", $tagdata, $matches))
    		{
                foreach ($matches['1'] as $match)
    			{
    				$tagdata = preg_replace("#".LD."created_date format=.+?".RD."#", $this->_format_date($match, $row['created_date']), $tagdata, true);
    			}
    		}
    		if (preg_match_all("#".LD."expires_date format=[\"|'](.+?)[\"|']".RD."#", $tagdata, $matches))
    		{
                foreach ($matches['1'] as $match)
    			{
    				$tagdata = preg_replace("#".LD."expires_date format=.+?".RD."#", $this->_format_date($match, $row['expires_date']), $tagdata, true);
    			}
    		}
    		
    		
    		
    		if ( preg_match_all("/".LD."users.*?(backspace=[\"|'](\d+?)[\"|'])?".RD."(.*?)".LD."\/users".RD."/s", $tagdata, $tmp)!=0)
	        {
	            $users_tagdata_orig = $tmp[3][0];
	            $users_out = '';
	            
	            if ($cond['used']==true)
	            {
					$this->EE->db->select('exp_invitations_uses.*, exp_members.username AS user_username, exp_members.screen_name AS user_screen_name')
			    				->from('exp_invitations_uses')
			    				->join('exp_members', 'exp_invitations_uses.member_id=exp_members.member_id', 'left')
			    				->where('code_id', $row['code_id']);
					$user_q = $this->EE->db->get();
		            
		            foreach ($user_q->result_array() as $user_row)
		            {
		                $users_tagdata = $users_tagdata_orig;
		                $users_tagdata = $this->EE->TMPL->swap_var_single('user_id', $user_row['member_id'], $users_tagdata);
		                $users_tagdata = $this->EE->TMPL->swap_var_single('user_username', $user_row['user_username'], $users_tagdata);
		                $users_tagdata = $this->EE->TMPL->swap_var_single('user_screen_name', $user_row['user_screen_name'], $users_tagdata);
		                if (preg_match_all("#".LD."used_date format=[\"|'](.+?)[\"|']".RD."#", $tagdata, $matches))
			    		{
			                foreach ($matches['1'] as $match)
			    			{
			    				$tagdata = preg_replace("#".LD."used_date format=.+?".RD."#", $this->_format_date($match, $user_row['used_date']), $tagdata, true);
			    			}
			    		}
		                $users_out .= $users_tagdata;
		            }
		            
		            $backspace_var = $tmp[2][0];
		            $users_out = trim($users_out);
		            $users_out	= substr($users_out, 0, strlen($users_out)-$backspace_var);
	            }
	            $tagdata = str_replace($tmp[0][0], $users_out, $tagdata);
	        }
    		
    		

            $out .= $tagdata;
        }
        
        $out = trim($out);
        
        if ($this->EE->TMPL->fetch_param('backspace')!='')
        {
            $backspace = intval($this->EE->TMPL->fetch_param('backspace'));
            $out = substr($out, 0, - $backspace);
        }
        
        $out = $this->_process_pagination($total, $this->perpage, $start, $basepath, $out, $paginate, $paginate_tagdata);
        
        return $out;
    	
    	
    }
    
    function _generate_code($length = 16, $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890')
	{
        // Length of character list
        $chars_length = (strlen($chars) - 1);
    
        // Start our string
        $string = $chars[rand(0, $chars_length)];
        
        // Generate random string
        for ($i = 1; $i < $length; $i++)
        {
            // Grab a random character from our list
            $r = $chars[rand(0, $chars_length)];
            
            // Make sure the same two characters don't appear next to each other
            //if ($r != $string{$i - 1}) $string .=  $r;
            $string .=  $r;
        }
        
        $q = $this->EE->db->query("SELECT code_id FROM exp_invitations_codes WHERE `code`='".$string."'");
        if ($q->num_rows>0)
        {
            $string = $this->_generate_code();
        }
        
        // Return the string
        return $string;
    }
    
    function check($code='', $email='')
    {
    	$data = array('success'=>false, 'error'=>'', 'code_object'=>false);
		//make sure request comes from same domain
		
		if ($code=='' && $this->EE->input->get_post('invitation')!==false)
		{
			$code = $this->EE->input->get_post('invitation');
		}
		if ($email=='' && $this->EE->input->get_post('email')!==false)
		{
			$email = $this->EE->input->get_post('email');
		}
		
		
		if ($code=='')
		{
			$data['success'] = false;
			$data['error'] = lang('code_not_provided');
			return $data;
		}
    	
    	$this->EE->db->select()
    				->from('invitations_codes')
    				->where('code', $code);
		$q = $this->EE->db->get();
		if ($q->num_rows()==0)
		{
			$data['success'] = false;
			$data['error'] = lang('invalid_invitation');
			return $data;
		}
		else
		{
			$data['success'] = true;
		}
		
		//email set and does not match?
		if ($q->row('email')!='' && $q->row('email')!=$email)
		{
			$data['success'] = false;
			$data['error'] = lang('invitation_not_allowed');
			return $data;
		}
		
		// over usage limit?
		if ($q->row('unlimited_usage')!='y' && $q->row('times_used') >= $q->row('usage_limit'))
		{
			$data['success'] = false;
			$data['error'] = lang('invitation_cannot_be_used_anymore');
			return $data;
		}
		
		// expired?
		if ($q->row('expires_date')!='0' && $this->EE->localize->now > $q->row('expires_date'))
		{
			$data['success'] = false;
			$data['error'] = lang('invitation_expired');
			return $data;
		}
		
		//valid for this site?
		if ($q->row('site_id')!='0' && $q->row('site_id')!=$this->EE->config->item('site_id'))
		{
			$data['success'] = false;
			$data['error'] = lang('invitation_not_for_this_site');
			return $data;
		}
		
		if ($data['success']==true) $data['code_object'] = $q;
		
		return $data;
    }
    
    function request()
    {

		if ($this->EE->TMPL->fetch_param('return')=='')
        {
            $return = $this->EE->functions->fetch_site_index();
        }
        else if ($this->EE->TMPL->fetch_param('return')=='SAME_PAGE')
        {
            $return = $this->EE->functions->fetch_current_uri();
        }
        else if (strpos($this->EE->TMPL->fetch_param('return'), "http://")!==FALSE || strpos($this->EE->TMPL->fetch_param('return'), "https://")!==FALSE)
        {
            $return = $this->EE->TMPL->fetch_param('return');
        }
        else
        {
            $return = $this->EE->functions->create_url($this->EE->TMPL->fetch_param('return'));
        }
        
        $data['hidden_fields']['ACT'] = $this->EE->functions->fetch_action_id('Invitations', 'process_invitation_request');
		$data['hidden_fields']['RET'] = $return;
        $data['hidden_fields']['PRV'] = $this->EE->functions->fetch_current_uri();
        
        if ($this->EE->TMPL->fetch_param('ajax')=='yes') $data['hidden_fields']['ajax'] = 'yes';
									      
        $data['id']		= ($this->EE->TMPL->fetch_param('id')!='') ? $this->EE->TMPL->fetch_param('id') : 'invitations_request_form';
        $data['name']		= ($this->EE->TMPL->fetch_param('name')!='') ? $this->EE->TMPL->fetch_param('name') : 'invitations_request_form';
        $data['class']		= ($this->EE->TMPL->fetch_param('class')!='') ? $this->EE->TMPL->fetch_param('class') : 'invitations_request_form';
		
		$tagdata = $this->EE->TMPL->tagdata;
		
        $out = $this->EE->functions->form_declaration($data).$tagdata."\n"."</form>";
        
        return $out;
    }
    
    
    function process_invitation_request()
    {
        
        //email submitted?
        if ($this->EE->input->post('email')=='')
        {
            $this->EE->output->show_user_error('submission', lang('email_is_required'));
        }
        
        //email valid?
        $this->EE->load->helper('email');
        
        if (!valid_email($this->EE->input->post('email')))
        {
            $this->EE->output->show_user_error('submission', lang('email_is_required'));
        }
        
        //email not in the db yet?
        $q = $this->EE->db->select('email')
                ->from('invitations_requests')
                ->where('email', $this->EE->input->post('email'))
                ->get();
        if ($q->num_rows()>0)
        {
            $this->EE->output->show_user_error('submission', lang('invitation_requested'));
        }
        
        $ins = array(
            'email'			    => $this->EE->input->post('email'),
			'comment'			=> $this->EE->input->post('comment'),
			'request_date'	    => $this->EE->localize->now
        );
        
        $this->EE->db->insert('invitations_requests', $ins);
        
        //send the notification
        $settings = $this->_get_settings();
        if ($settings['notify_on_requests']==true)
        {
            $query = $this->EE->db->select("data_title, template_data")
                    ->from('specialty_templates')
                    ->where('template_name', 'invitation_requested_email')
                    ->limit('1')
                    ->get();
                    
            $email_subject = $this->EE->functions->var_swap($query->row('data_title'), $ins);
    		$email_msg = $this->EE->functions->var_swap($query->row('template_data'), $ins);
            
            $this->EE->load->library('email');

			// Load the text helper
			$this->EE->load->helper('text');

			$this->EE->email->EE_initialize();
			$this->EE->email->wordwrap = FALSE;
			$this->EE->email->from($this->EE->config->item('webmaster_email'), $this->EE->config->item('webmaster_name'));
			$this->EE->email->to($this->EE->config->item('webmaster_email'));
			$this->EE->email->reply_to($this->EE->config->item('webmaster_email'));
			$this->EE->email->subject($email_subject);
			$this->EE->email->message(entities_to_ascii($email_msg));
			$this->EE->email->send();

        }
        
        $this->EE->functions->redirect($_POST['RET']);
        
    }
    
    
    function apply()
    {
    	if (in_array($this->EE->session->userdata('group_id'), array(0,1,2,3,4)))
    	{
    		return $this->EE->TMPL->no_results();
    	}
		if ($this->EE->TMPL->fetch_param('return')=='')
        {
            $return = $this->EE->functions->fetch_site_index();
        }
        else if ($this->EE->TMPL->fetch_param('return')=='SAME_PAGE')
        {
            $return = $this->EE->functions->fetch_current_uri();
        }
        else if (strpos($this->EE->TMPL->fetch_param('return'), "http://")!==FALSE || strpos($this->EE->TMPL->fetch_param('return'), "https://")!==FALSE)
        {
            $return = $this->EE->TMPL->fetch_param('return');
        }
        else
        {
            $return = $this->EE->functions->create_url($this->EE->TMPL->fetch_param('return'));
        }
        
        $data['hidden_fields']['ACT'] = $this->EE->functions->fetch_action_id('Invitations', 'process_apply');
		$data['hidden_fields']['RET'] = $return;
        $data['hidden_fields']['PRV'] = $this->EE->functions->fetch_current_uri();
        
        if ($this->EE->TMPL->fetch_param('ajax')=='yes') $data['hidden_fields']['ajax'] = 'yes';
									      
        $data['id']		= ($this->EE->TMPL->fetch_param('id')!='') ? $this->EE->TMPL->fetch_param('id') : 'invitations_form';
        $data['name']		= ($this->EE->TMPL->fetch_param('name')!='') ? $this->EE->TMPL->fetch_param('name') : 'invitations_form';
        $data['class']		= ($this->EE->TMPL->fetch_param('class')!='') ? $this->EE->TMPL->fetch_param('class') : 'invitations_form';
		
		$tagdata = $this->EE->TMPL->tagdata;
		
        $out = $this->EE->functions->form_declaration($data).$tagdata."\n"."</form>";
        
        return $out;
    }
    
    function process_apply()
    {
    	//is pending, or banned?
    	if (in_array($this->EE->session->userdata('group_id'), array(0,1,2,3,4)))
    	{
    		$this->EE->output->show_user_error('general', lang('unauthorized_access'));
    	}
    	
		//code valid?
    	$check = $this->check($this->EE->input->post('invitation'), $this->EE->session->userdata('email'));

    	if ($check['success']==false)
    	{
    		$this->EE->output->show_user_error('submission', $check['error']);
		}
		
		$q = $check['code_object'];
		
    	//is already in same group?
    	if ($q->row('destination_group_id') == $this->EE->session->userdata('group_id') || $q->row('destination_group_id') == 0)
    	{
    		$this->EE->output->show_user_error('general', lang('invitation_not_allowed'));
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
						if ($q->row('credits_author')<0)
						{
							$query = $this->EE->db->select('SUM(credits) as credits_total')->from('exp_credits')->where('member_id', $q->row('author_id'))->get();
							if ($query->num_rows()==0 || ($query->row('credits_total') < abs($q->row('credits_author'))))
							{
								$this->EE->output->show_user_error('general', lang('not_enough_credits_author'));
							}
						}
						
						$pData = array(
							'action_id'	=> $credits_action_q->row('action_id'),
							'credits'	=> $q->row('credits_author'),
							'receiver'	=> $q->row('author_id'),
							'item_id'	=> $this->EE->session->userdata('member_id'),
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
						if ($q->row('credits_author')<0)
						{
							$query = $this->EE->db->select('SUM(credits) as credits_total')->from('exp_credits')->where('member_id', $this->EE->session->userdata('member_id'))->get();
							if ($query->num_rows()==0 || ($query->row('credits_total') < abs($q->row('credits_user'))))
							{
								$this->EE->output->show_user_error('general', lang('not_enough_credits_user'));
							}
						}
						
						$pData = array(
							'action_id'	=> $credits_action_q->row('action_id'),
							'credits'	=> $q->row('credits_author'),
							'receiver'	=> $this->EE->session->userdata('member_id'),
							'item_id'	=> $this->EE->session->userdata('member_id'),
							'item_parent_id' => 0
						);
		
						$this->_save_credits($pData);
		    		}
    			}
	        }
       }
		
		
		//apply code

		$this->EE->db->query("UPDATE exp_members SET group_id = '".$q->row('destination_group_id')."' WHERE member_id = '".$this->EE->session->userdata('member_id')."'");
		$this->EE->stats->update_member_stats();
		
		
		//update our tables
		$this->EE->db->query("UPDATE exp_invitations_codes SET times_used=times_used+1 WHERE code_id='".$q->row('code_id')."'");
		$insert = array(
			'member_id' => $this->EE->session->userdata('member_id'),
			'code_id'	=> $q->row('code_id'),
			'ip_address'=> $this->EE->input->ip_address(),
			'used_date '=> $this->EE->localize->now
		);
		
		$this->EE->db->insert('invitations_uses', $insert);
		
		$this->EE->functions->redirect($_POST['RET']);
		
    }
    
    function referrals()
    {
    	$member_id = ($this->EE->TMPL->fetch_param('member_id')!==false)?$this->EE->TMPL->fetch_param('member_id'):$this->EE->session->userdata('member_id');
    	if ($member_id==0) return $this->EE->TMPL->no_results();
		$this->EE->db->select('exp_members.member_id, exp_members.username, exp_members.screen_name, exp_members.group_id')
    				->from('exp_invitations_uses')
    				->join('exp_invitations_codes', 'exp_invitations_codes.code_id=exp_invitations_uses.code_id', 'left')
    				->join('exp_members', 'exp_invitations_uses.member_id=exp_members.member_id', 'left')
    				->where('exp_invitations_codes.author_id', $member_id);
		$q = $this->EE->db->get();
		if ($q->num_rows()==0)
		{
			return $this->EE->TMPL->no_results();
		}
		
		$tagdata_orig = $this->EE->TMPL->tagdata;
		$out = '';
		$i = 0;
		
		foreach ($q->result_array() as $row)
		{
			$i++;
			$tagdata = $tagdata_orig;
			$tagdata = $this->EE->TMPL->swap_var_single('count', $i, $tagdata);
			
			$tagdata = $this->EE->TMPL->swap_var_single('member_id', $row['member_id'], $tagdata);
			$tagdata = $this->EE->TMPL->swap_var_single('username', $row['username'], $tagdata);
			$tagdata = $this->EE->TMPL->swap_var_single('screen_name', $row['screen_name'], $tagdata);
			$tagdata = $this->EE->TMPL->swap_var_single('group_id', $row['group_id'], $tagdata);
			$out .= $tagdata;
			
		}
		
		$out = $this->EE->TMPL->swap_var_single('total_results', $i, $out);
		
		
		if ($this->EE->TMPL->fetch_param('backspace')!='')
        {
            $backspace = intval($this->EE->TMPL->fetch_param('backspace'));
            $out = substr($out, 0, - $backspace);
        }
		
		
		return $out;
    }
    
    function referrer()
    {
    	$member_id = ($this->EE->TMPL->fetch_param('member_id')!==false)?$this->EE->TMPL->fetch_param('member_id'):$this->EE->session->userdata('member_id');
    	if ($member_id==0) return $this->EE->TMPL->no_results();
		$this->EE->db->select('exp_members.member_id, exp_members.username, exp_members.screen_name, exp_members.group_id')
    				->from('exp_invitations_uses')
    				->join('exp_invitations_codes', 'exp_invitations_codes.code_id=exp_invitations_uses.code_id', 'left')
    				->join('exp_members', 'exp_invitations_codes.author_id=exp_members.member_id', 'left')
    				->where('exp_invitations_uses.member_id', $member_id)
					->limit(1);
		$q = $this->EE->db->get();
		if ($q->num_rows()==0)
		{
			return $this->EE->TMPL->no_results();
		}
		
		$tagdata = $this->EE->TMPL->tagdata;
		$tagdata = $this->EE->TMPL->swap_var_single('member_id', $q->row('member_id'), $tagdata);
		$tagdata = $this->EE->TMPL->swap_var_single('username', $q->row('username'), $tagdata);
		$tagdata = $this->EE->TMPL->swap_var_single('screen_name', $q->row('screen_name'), $tagdata);
		$tagdata = $this->EE->TMPL->swap_var_single('group_id', $q->row('group_id'), $tagdata);
		
		return $tagdata;
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
			if ($pData['credits']>0)
			{
				$this->EE->db->set('credits', "( credits + {$pData['credits']} )", FALSE);
			}
			else
			{
				$this->EE->db->set('credits', "( credits ".$pData['credits'].")", FALSE);
			}
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
    
    
    
    function _format_date($datestr = '', $unixtime = '', $localize = TRUE)
    {
    	if ($this->EE->config->item('app_version')>=260)
        {
        	return $this->EE->localize->format_date($datestr, $unixtime, $localize);
        }
        else
        {
        	return $this->EE->localize->decode_date($datestr, $unixtime, $localize);
        }
    }
    
    
    function _string_to_timestamp($human_string, $localized = TRUE)
    {
        if ($this->EE->config->item('app_version')<260)
        {
            return $this->EE->localize->convert_human_date_to_gmt($human_string, $localized);
        }
        else
        {
            return $this->EE->localize->string_to_timestamp($human_string, $localized);
        }
    }
    
    
    function _process_pagination($total, $perpage, $start, $basepath='', $out='', $paginate='bottom', $paginate_tagdata='')
    {
        if ($this->EE->config->item('app_version') >= 240)
		{
	        $this->EE->load->library('pagination');
	        if ($this->EE->config->item('app_version') >= 260)
	        {
	        	$pagination = $this->EE->pagination->create(__CLASS__);
	        }
	        else
	        {
	        	$pagination = new Pagination_object(__CLASS__);
	        }
            if ($this->EE->config->item('app_version') >= 280)
            {
                $this->EE->TMPL->tagdata = $pagination->prepare($this->EE->TMPL->tagdata);
                $pagination->build($total, $perpage);
            }
            else
            {
                $pagination->get_template();
    	        $pagination->per_page = $perpage;
    	        $pagination->total_rows = $total;
    	        $pagination->offset = $start;
    	        $pagination->build($pagination->per_page);
            }
	        
	        $out = $pagination->render($out);
  		}
  		else
  		{
        
	        if ($total > $perpage)
	        {
	            $this->EE->load->library('pagination');
	
				$config['base_url']		= $basepath;
				$config['prefix']		= 'P';
				$config['total_rows'] 	= $total;
				$config['per_page']		= $perpage;
				$config['cur_page']		= $start;
				$config['first_link'] 	= $this->EE->lang->line('pag_first_link');
				$config['last_link'] 	= $this->EE->lang->line('pag_last_link');
	
				$this->EE->pagination->initialize($config);
				$pagination_links = $this->EE->pagination->create_links();	
	            $paginate_tagdata = $this->EE->TMPL->swap_var_single('pagination_links', $pagination_links, $paginate_tagdata);			
	        }
	        else
	        {
	            $paginate_tagdata = $this->EE->TMPL->swap_var_single('pagination_links', '', $paginate_tagdata);		
	        }
	        
	        switch ($paginate)
	        {
	            case 'top':
	                $out = $paginate_tagdata.$out;
	                break;
	            case 'both':
	                $out = $paginate_tagdata.$out.$paginate_tagdata;
	                break;
	            case 'bottom':
	            default:
	                $out = $out.$paginate_tagdata;
	        }
	        
    	}
        
        return $out;
    }    
    

}
/* END */
?>