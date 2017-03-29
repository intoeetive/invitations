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
 File: mcp.invitations.php
-----------------------------------------------------
 Purpose: System to manage invitation codes
=====================================================
*/

if ( ! defined('BASEPATH'))
{
    exit('Invalid file request');
}

require_once PATH_THIRD.'invitations/config.php';

class Invitations_mcp {

    var $version = INVITATIONS_ADDON_VERSION;
    
    var $settings = array();
    
    var $perpage = 25;
    
    var $site_id = 1;
    
    function __construct() { 
        // Make a local reference to the ExpressionEngine super object 
        $this->EE =& get_instance(); 
        $this->site_id = $this->EE->config->item('site_id');
        
        if (version_compare(APP_VER, '2.6.0', '>='))
        {
        	$this->EE->view->cp_page_title = lang('invitations_module_name');
        }
        else
        {
        	$this->EE->cp->set_variable('cp_page_title', lang('invitations_module_name'));
        }
        
        $this->EE->cp->set_right_nav(array(
		            'generate' => BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=generate',
		            'generate_batch' => BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=generate_batch')
		        );
    } 
    
    
    function _format_date($datestr = '', $unixtime = '', $localize = TRUE)
    {
    	if (version_compare(APP_VER, '2.6.0', '>='))
        {
        	return $this->EE->localize->format_date($datestr, $unixtime, $localize);
        }
        else
        {
        	return $this->EE->localize->decode_date($datestr, $unixtime, $localize);
        }
    }
    
    function index()
    {
        $this->EE->load->helper('form');
    	$this->EE->load->library('table');  
        $this->EE->load->library('javascript');

    	$vars = array();
        
        if ($this->EE->input->get_post('perpage')!==false)
        {
        	$this->perpage = $this->EE->input->get_post('perpage');	
        }
        $vars['selected']['perpage'] = $this->perpage;
        
        $vars['selected']['show'] = 'all';
        if ($this->EE->input->get_post('show')!==false)
        {
			$vars['selected']['show'] = $this->EE->input->get_post('show');	
        }
        
        $vars['selected']['rownum']=($this->EE->input->get_post('rownum')!='')?$this->EE->input->get_post('rownum'):0;
        
        $this->EE->db->select()
        			->from('invitations_codes');
		switch ($vars['selected']['show'])
		{
			case 'expired':
				$this->EE->db->where("expires_date != 0 AND expires_date < ".$this->EE->localize->now);
				break;
			case 'used':
				$this->EE->db->where("times_used != 0");
				break;
			case 'available':
				$this->EE->db->where("(unlimited_usage = 'y' OR times_used < usage_limit) AND (expires_date = 0 OR expires_date > ".$this->EE->localize->now.")");
				break;
			case 'all':
			default:
				break;
		}
		
		if ($this->perpage!=0)
		{
        	$this->EE->db->limit($this->perpage, $vars['selected']['rownum']);
 		}

        $query = $this->EE->db->get();
        $vars['total_count'] = $query->num_rows();
        
        $date_fmt = ($this->EE->session->userdata('time_format') != '') ? $this->EE->session->userdata('time_format') : $this->EE->config->item('time_format');
        $date_format = ($date_fmt == 'us')? '%m/%d/%y %h:%i %a' : '%Y-%m-%d %H:%i';
        
        $member_groups = array();
		$member_groups[0] = lang('default_group');
        $this->EE->db->select('group_id, group_title');
        //$this->EE->db->where('group_id NOT IN (1,2,3,4)');
        $q = $this->EE->db->get('member_groups');
        foreach ($q->result() as $obj)
        {
            $member_groups[$obj->group_id] = $obj->group_title;
        }
        
        if ($this->EE->config->item('multiple_sites_enabled') == 'y')
        {
	        $sites = array();
			$this->EE->db->select('site_id, site_label');
			$sites_q = $this->EE->db->get('exp_sites');
			$sites[0] = lang('all_sites');
			foreach ($sites_q->result_array() as $row)
			{
				$sites[$row['site_id']] = $row['site_label'];
			}
		}
        
        $vars['table_headings'] = array(
                        form_checkbox('select_all', 'true', false, ' class="toggle_all"'),
                        $this->EE->lang->line('code'),
                        $this->EE->lang->line('destination_group_id')
        			);
		if ($this->EE->config->item('multiple_sites_enabled') == 'y')
		{
			$vars['table_headings'][] = $this->EE->lang->line('site');
		}
        $vars['table_headings'] = array_merge($vars['table_headings'], 
        			array(
                        $this->EE->lang->line('created_on'),
                        $this->EE->lang->line('times_used'),
                        $this->EE->lang->line('valid'),
                        $this->EE->lang->line('note'),
                        $this->EE->lang->line('stats')
                    ));      
					
		   
		$i = 0;
        foreach ($query->result() as $obj)
        {
           $vars['invitations'][$i]['code_id'] = form_checkbox('code_id[]', $obj->code_id, false, ' class="toggle"');
           $vars['invitations'][$i]['code'] = $obj->code;
           $vars['invitations'][$i]['destination_group_id'] = $member_groups[$obj->destination_group_id];
           if ($this->EE->config->item('multiple_sites_enabled') == 'y')
           {
           		$vars['invitations'][$i]['site_id'] = $sites[$obj->site_id];
		   }
           $vars['invitations'][$i]['created_on'] = $this->_format_date($date_format, $obj->created_date); 
           $vars['invitations'][$i]['times_used'] = $obj->times_used;    
           $vars['invitations'][$i]['valid'] = (($obj->unlimited_usage!='y' && $obj->times_used >= $obj->usage_limit) || ($obj->expires_date!='0' && $this->EE->localize->now > $obj->expires_date))?lang('no'):lang('yes');
           $vars['invitations'][$i]['note'] = $obj->note;
           $vars['invitations'][$i]['stats_link'] = "<a href=\"".BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=stats'.AMP.'code_id='.$obj->code_id."\" title=\"".$this->EE->lang->line('view_stats')."\"><img src=\"".$this->EE->config->slash_item('theme_folder_url')."third_party/invitations/stats.png\" alt=\"".$this->EE->lang->line('view_stats')."\"></a>";
           $i++;
 			
        }
        
        $this->EE->jquery->tablesorter('.mainTable', '{
			headers: {0: {sorter: false}, 7: {sorter: false}},
			widgets: ["zebra"]
		}');

		$this->EE->javascript->output('
			$(".toggle_all").toggle(
				function(){
					$(".mainTable tbody tr").addClass("selected");
					$("input.toggle").each(function() {
						this.checked = true;
					});
				}, function (){
					$(".mainTable tbody tr").removeClass("selected");
					$("input.toggle").each(function() {
						this.checked = false;
					});
				}
			);
		');

		if ($this->perpage==0)
		{
        	$total = $query->num_rows();
 		}
 		else
 		{
 			$this->EE->db->select('COUNT(*) AS count');
	        $this->EE->db->from('invitations_codes');
	        $q = $this->EE->db->get();
	        
	        switch ($vars['selected']['show'])
			{
				case 'expired':
					$this->EE->db->where("expires_date != 0 AND expires_date < ".$this->EE->localize->now);
					break;
				case 'used':
					$this->EE->db->where("times_used != 0");
					break;
				case 'available':
					$this->EE->db->where("(unlimited_usage = 'y' OR times_used < usage_limit) AND (expires_date = 0 OR expires_date > ".$this->EE->localize->now.")");
					break;
				case 'all':
				default:
					break;
			}
	        
	        $total = $q->row('count');
 		}

        $this->EE->load->library('pagination');

        $base_url = BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=index';
        $base_url .= AMP.'perpage='.$vars['selected']['perpage'];
        $base_url .= AMP.'show='.$vars['selected']['show'];

        $p_config = $this->_p_config($base_url, $total);

		$this->EE->pagination->initialize($p_config);
        
		$vars['pagination'] = $this->EE->pagination->create_links();
        
    	return $this->EE->load->view('index', $vars, TRUE);
	
    }    
    
    
    function requests()
    {
        $this->EE->load->helper('form');
    	$this->EE->load->library('table');  
        $this->EE->load->library('javascript');

    	$vars = array();
        
        if ($this->EE->input->get_post('perpage')!==false)
        {
        	$this->perpage = $this->EE->input->get_post('perpage');	
        }
        $vars['selected']['perpage'] = $this->perpage;

        
        $vars['selected']['rownum']=($this->EE->input->get_post('rownum')!='')?$this->EE->input->get_post('rownum'):0;
        
        $this->EE->db->select()
        			->from('invitations_requests');

		if ($this->perpage!=0)
		{
        	$this->EE->db->limit($this->perpage, $vars['selected']['rownum']);
 		}

        $query = $this->EE->db->get();
        $vars['total_count'] = $query->num_rows();
        
        $date_fmt = ($this->EE->session->userdata('time_format') != '') ? $this->EE->session->userdata('time_format') : $this->EE->config->item('time_format');
        $date_format = ($date_fmt == 'us')? '%m/%d/%y %h:%i %a' : '%Y-%m-%d %H:%i';
        
        $vars['table_headings'] = array(
                        $this->EE->lang->line('email'),
                        $this->EE->lang->line('comment'),
                        $this->EE->lang->line('invite')
        			); 
					
		   
		$i = 0;
        foreach ($query->result_array() as $row)
        {
           $vars['requests'][$i]['email'] = $row['email'];
           $vars['requests'][$i]['comment'] = $row['comment'];
           $vars['requests'][$i]['invite'] = "<a href=\"".BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=generate'.AMP.'request_id='.$row['request_id']."\">".$this->EE->lang->line('invite_user')."</a>";
           $i++;
 			
        }
        
        $this->EE->jquery->tablesorter('.mainTable', '{
			headers: {},
			widgets: ["zebra"]
		}');


		if ($this->perpage==0)
		{
        	$total = $query->num_rows();
 		}
 		else
 		{
 			$this->EE->db->select('COUNT(*) AS count');
	        $this->EE->db->from('invitations_requests');
	        $q = $this->EE->db->get();
	        
	        $total = $q->row('count');
 		}

        $this->EE->load->library('pagination');

        $base_url = BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=requests';
        $base_url .= AMP.'perpage='.$vars['selected']['perpage'];

        $p_config = $this->_p_config($base_url, $total);

		$this->EE->pagination->initialize($p_config);
        
		$vars['pagination'] = $this->EE->pagination->create_links();
        
    	return $this->EE->load->view('requests', $vars, TRUE);
	
    }    
    
    
    function generate()
    {
    	$this->EE->load->helper('form');
    	$this->EE->load->library('table');  
        $this->EE->load->library('javascript');
        
        $get_code_url = $this->EE->config->item('cp_url').'?&S=0&D=cp&C=addons_modules&M=show_module_cp&module=invitations&method=generate_code';
        $js = "
            ts = new Date();
            $('#get_code').click(function(){
                $(this).after('<img id=\"get_code_loader\" src=\"".$this->EE->config->item('theme_folder_url')."/cp_global_images/indicator.gif\" alt=\"please wait\" />');
                $.get('$get_code_url', {
                        'ts'        : ts.getTime()
                    }, function(msg) {
                        $('input[name=code]').val(msg);
                        $('#get_code_loader').remove();
                    }
                );
                return false;
            });
        ";
        
        $this->EE->javascript->output($js);
        $this->EE->cp->add_js_script('ui', 'datepicker'); 
        $this->EE->javascript->output('
			date_obj = new Date();
			date_obj_hours = date_obj.getHours();
			date_obj_mins = date_obj.getMinutes();

			if (date_obj_mins < 10) { date_obj_mins = "0" + date_obj_mins; }

			if (date_obj_hours > 11) {
				date_obj_hours = date_obj_hours - 12;
				date_obj_am_pm = " PM";
			} else {
				date_obj_am_pm = " AM";
			}

			date_obj_time = " \'"+date_obj_hours+":"+date_obj_mins+date_obj_am_pm+"\'";

			$.datepicker.setDefaults({dateFormat:$.datepicker.W3C+date_obj_time});
		');
        $this->EE->javascript->output(' $("input[name=expires_date]").datepicker(); ');
        $this->EE->javascript->compile(); 

    	$vars = array();
        
        $yesno = array(
                                    'y' => $this->EE->lang->line('yes'),
                                    'n' => $this->EE->lang->line('no')
                                );

        $settings = $this->_get_settings(); 

        $vars['data'] = array();
        $vars['data'][] = array(
            lang('code').NBS.lang('leave_blank'),//.' &nbsp; <a href="#" class="submit" id="get_code">('.lang('generate').')</a> &nbsp; ',
            form_input('code', '', 'style="width: 100%"')
		);
        
        if ($this->EE->config->item('multiple_sites_enabled') == 'y')
		{
			$sites = array();
			$this->EE->db->select('site_id, site_label');
			$sites_q = $this->EE->db->get('exp_sites');
			$sites[0] = lang('all_sites');
			foreach ($sites_q->result_array() as $row)
			{
				$sites[$row['site_id']] = $row['site_label'];
			}
			$vars['data'][] = array(lang('site'), form_dropdown('site_id', $sites, $this->site_id));
		}
		
		
		$member_groups = array();
		$member_groups[0] = lang('default_group');
        $this->EE->db->select('group_id, group_title');
        $this->EE->db->where('group_id NOT IN (1,2,3,4)');
        $q = $this->EE->db->get('member_groups');
        foreach ($q->result() as $obj)
        {
            $member_groups[$obj->group_id] = $obj->group_title;
        }

        $vars['data'][] = array(lang('destination_group_id'), form_dropdown('destination_group_id', $member_groups, isset($settings['destination_group_id'])?$settings['destination_group_id']:0));
        $email = '';
        if ($this->EE->input->get('request_id')!='')
        {
            $req_q = $this->EE->db->select('email')
                ->from('invitations_requests')
                ->where('request_id', $this->EE->input->get('request_id'))
                ->get();
            if ($req_q->num_rows()>0)
            {
                $email = $req_q->row('email');
            }
        }
        $vars['data'][] = array(lang('restrict_to_email'), form_input('email', $email));
        $vars['data'][] = array(lang('usage_limit'), form_input('usage_limit', '1'));
        $vars['data'][] = array(lang('unlimited_usage'), form_checkbox('unlimited_usage', 'y', false));
        $vars['data'][] = array(lang('expires_date'), form_input('expires_date', ''));
        $this->EE->db->select('module_id'); 
        $credits_installed_q = $this->EE->db->get_where('modules', array('module_name' => 'Credits')); 
        if ($credits_installed_q->num_rows()>0)
        {
	        $this->EE->db->select('action_id')
						->where('action_name', 'invitations_redeemed_author')
        				->or_where('action_name', 'invitations_redeemed_user');
			$credits_action_q = $this->EE->db->get('exp_credits_actions');
			if ($credits_action_q->num_rows()>0)
        	{
				$vars['data'][] = array(lang('credits_author'), form_input('credits_author', isset($settings['default_credits_author'])?$settings['default_credits_author']:0));
		        $vars['data'][] = array(lang('credits_user'), form_input('credits_user', isset($settings['default_credits_user'])?$settings['default_credits_user']:0));
    		}
        }
        $vars['data'][] = array(lang('note'), form_input('note', ''));
        $vars['data'][] = array(lang('email_user'), form_checkbox('email_user', 'y', ($email!='')?true:false));
        
    	return $this->EE->load->view('generate', $vars, TRUE);
		
    				
    }
    
    function create_and_save()
    {
    	if ($this->EE->input->post('code')!='')
		{
			$code = $this->EE->input->post('code');
			$q = $this->EE->db->query("SELECT code_id FROM exp_invitations_codes WHERE `code`='".$code."'");
	        if ($q->num_rows>0)
	        {
	            show_error(lang('code_exists'));
	        }
		}
		else
		{
			$code = $this->_generate_code();
		}
		$data = array(
			'code'				=> $code,
			'author_id'			=> $this->EE->session->userdata('member_id'),
			'site_id'			=> ($this->EE->input->post('site_id')!=0)?$this->EE->input->post('site_id'):$this->EE->config->item('site_id'),
			'email' 			=> $this->EE->input->post('email'),
			'destination_group_id' => $this->EE->input->post('destination_group_id'),
			'usage_limit'		=> $this->EE->input->post('usage_limit'),
        	'unlimited_usage'	=> ($this->EE->input->post('unlimited_usage')=='y')?'y':'n',
        	'created_date'		=> $this->EE->localize->now,
        	'expires_date'		=> ($this->EE->input->post('expires_date')=='')?0:$this->_string_to_timestamp($this->EE->input->post('expires_date')),
        	'credits_author'	=> $this->EE->input->post('credits_author'),
        	'credits_user'		=> $this->EE->input->post('credits_user'),
        	'note'				=> $this->EE->input->post('note')

			
		);
		
		$this->EE->db->insert('invitations_codes', $data);
        
        if ($this->EE->input->post('email_user')=='y' && $data['email']!='')
        {
            //email user
            $query = $this->EE->db->select("data_title, template_data")
                    ->from('specialty_templates')
                    ->where('template_name', 'invitation_generated_email')
                    ->limit('1')
                    ->get();
                    
            $email_subject = $this->EE->functions->var_swap($query->row('data_title'), $data);
    		$email_msg = $this->EE->functions->var_swap($query->row('template_data'), $data);
            
            $this->EE->load->library('email');

			// Load the text helper
			$this->EE->load->helper('text');

			$this->EE->email->EE_initialize();
			$this->EE->email->wordwrap = FALSE;
			$this->EE->email->from($this->EE->config->item('webmaster_email'), $this->EE->config->item('webmaster_name'));
			$this->EE->email->to($data['email']);
			$this->EE->email->reply_to($this->EE->config->item('webmaster_email'));
			$this->EE->email->subject($email_subject);
			$this->EE->email->message(entities_to_ascii($email_msg));
			$this->EE->email->send();
            
            //if there is pending request, remove it
            $this->EE->db->where('email', $data['email']);
            $this->EE->db->delete('invitations_requests');
                
        }

        $this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=index');

    }
    
    
    function generate_batch()
    {
    	$this->EE->load->helper('form');
    	$this->EE->load->library('table');  
        $this->EE->load->library('javascript');
        
        $this->EE->cp->add_js_script('ui', 'datepicker'); 
        $this->EE->javascript->output('
			date_obj = new Date();
			date_obj_hours = date_obj.getHours();
			date_obj_mins = date_obj.getMinutes();

			if (date_obj_mins < 10) { date_obj_mins = "0" + date_obj_mins; }

			if (date_obj_hours > 11) {
				date_obj_hours = date_obj_hours - 12;
				date_obj_am_pm = " PM";
			} else {
				date_obj_am_pm = " AM";
			}

			date_obj_time = " \'"+date_obj_hours+":"+date_obj_mins+date_obj_am_pm+"\'";

			$.datepicker.setDefaults({dateFormat:$.datepicker.W3C+date_obj_time});
		');
        $this->EE->javascript->output(' $("input[name=expires_date]").datepicker(); ');
        $this->EE->javascript->compile(); 

    	$vars = array();
        
        $yesno = array(
                                    'y' => $this->EE->lang->line('yes'),
                                    'n' => $this->EE->lang->line('no')
                                );
                                
        $settings = $this->_get_settings(); 

        $vars['data'] = array();
        $vars['data'][] = array(
            lang('number_of_codes'),
            form_input('number_of_codes', '5')
		);
        
        if ($this->EE->config->item('multiple_sites_enabled') == 'y')
		{
			$sites = array();
			$this->EE->db->select('site_id, site_label');
			$sites_q = $this->EE->db->get('exp_sites');
			$sites[0] = lang('all_sites');
			foreach ($sites_q->result_array() as $row)
			{
				$sites[$row['site_id']] = $row['site_label'];
			}
			$vars['data'][] = array(lang('site'), form_dropdown('site_id', $sites, $this->site_id));
		}
		
		
		$member_groups = array();
		$member_groups[0] = lang('default_group');
        $this->EE->db->select('group_id, group_title');
        $this->EE->db->where('group_id NOT IN (1,2,3,4)');
        $q = $this->EE->db->get('member_groups');
        foreach ($q->result() as $obj)
        {
            $member_groups[$obj->group_id] = $obj->group_title;
        }
        
        $vars['data'][] = array(lang('destination_group_id'), form_dropdown('destination_group_id', $member_groups, isset($settings['destination_group_id'])?$settings['destination_group_id']:0));
        $vars['data'][] = array(lang('usage_limit'), form_input('usage_limit', '1'));
        $vars['data'][] = array(lang('unlimited_usage'), form_checkbox('unlimited_usage', 'y', false));
        $vars['data'][] = array(lang('expires_date'), form_input('expires_date', ''));
        $this->EE->db->select('module_id'); 
        $credits_installed_q = $this->EE->db->get_where('modules', array('module_name' => 'Credits')); 
        if ($credits_installed_q->num_rows()>0)
        {
	        $this->EE->db->select('action_id')
						->where('action_name', 'invitations_redeemed_author')
        				->or_where('action_name', 'invitations_redeemed_user');
			$credits_action_q = $this->EE->db->get('exp_credits_actions');
			if ($credits_action_q->num_rows()>0)
        	{
				$vars['data'][] = array(lang('credits_author'), form_input('credits_author', isset($settings['default_credits_author'])?$settings['default_credits_author']:0));
		        $vars['data'][] = array(lang('credits_user'), form_input('credits_user', isset($settings['default_credits_user'])?$settings['default_credits_user']:0));
    		}
        }
        $vars['data'][] = array(lang('note'), form_input('note', ''));
        
    	return $this->EE->load->view('generate_batch', $vars, TRUE);
    }
    
    function create_and_save_batch()
    {
    	for ($i=1; $i<=$this->EE->input->post('number_of_codes'); $i++)
    	{
			$data = array(
				'code'				=> $this->_generate_code(),
				'author_id'			=> $this->EE->session->userdata('member_id'),
				'site_id'			=> ($this->EE->input->post('site_id')!=0)?$this->EE->input->post('site_id'):$this->EE->config->item('site_id'),
				'destination_group_id' => $this->EE->input->post('destination_group_id'),
				'usage_limit'		=> $this->EE->input->post('usage_limit'),
	        	'unlimited_usage'	=> ($this->EE->input->post('unlimited_usage')=='y')?'y':'n',
	        	'created_date'		=> $this->EE->localize->now,
	        	'expires_date'		=> ($this->EE->input->post('expires_date')=='')?0:$this->_string_to_timestamp($this->EE->input->post('expires_date')),
	        	'credits_author'	=> $this->EE->input->post('credits_author'),
	        	'credits_user'		=> $this->EE->input->post('credits_user'),
	        	'note'				=> $this->EE->input->post('note')
	
				
			);
			
			$this->EE->db->insert('invitations_codes', $data);
		}
		
		$this->EE->session->set_flashdata('message_success', str_replace("%x", $i, lang('invitations_created')));
        
        $this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=index');

    }

    
    function generate_code()
    {
    	echo $this->_generate_code();
    	exit();
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

    
    function delete()
    {
		if (empty($_POST['code_id']))
		{
			$this->EE->session->set_flashdata('message_failure', lang('provide_some_invitations_to_delete'));
		}
		else
		{
		
			$this->EE->db->where_in('code_id', $_POST['code_id']);
			$this->EE->db->delete('invitations_codes');
			
			$this->EE->db->where_in('code_id', $_POST['code_id']);
			$this->EE->db->delete('invitations_uses');
			
			$this->EE->session->set_flashdata('message_success', str_replace("%x", count($_POST['code_id']), lang('invitations_deleted')));
		}

        $this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=index');
    }
    
    function delete_confirm()
    {
		if (empty($_POST['code_id']))
		{
			$this->EE->session->set_flashdata('message_failure', lang('provide_some_invitations_to_delete'));
			$this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=index');
		}
		
		$vars = array();
		$vars['data'] = '';
		foreach ($_POST['code_id'] as $code_id)
		{
			$vars['data'] .= form_hidden('code_id[]', $code_id);
		}
        
    	return $this->EE->load->view('delete_confirm', $vars, TRUE);
    	
    }
    
    function stats()
    {
    	$this->EE->load->library('table');  
		
		if ($this->EE->input->get('code_id')===false)
    	{
    		show_error(lang('provide_valid_invitation'));
    	}
    	
    	$this->EE->db->select('exp_invitations_codes.*, exp_members.screen_name')
    				->from('exp_invitations_codes')
    				->join('exp_members', 'exp_invitations_codes.author_id=exp_members.member_id', 'left')
    				->where('code_id', $this->EE->input->get('code_id'));
		$codes_q = $this->EE->db->get();
		if ($codes_q->num_rows()==0)
		{
			show_error(lang('provide_valid_invitation'));
		}
		
		$this->EE->db->select()
    				->from('invitations_uses')
    				->join('exp_members', 'exp_invitations_uses.member_id=exp_members.member_id', 'left')
    				->where('code_id', $this->EE->input->get('code_id'));
		$uses_q = $this->EE->db->get();
		
		$vars = array();
		$vars['data'] = array();
		$vars['data']['code'] = $codes_q->row('code');
		if ($this->EE->config->item('multiple_sites_enabled') == 'y')
		{
			if ($codes_q->row('site_id') == 0)
			{
				$site = lang('all_sites');
			}
			else
			{
				$this->EE->db->select('site_label')
							->from('sites')
							->where('site_id', $codes_q->row('site_id'));
				$q = $this->EE->db->get();
				$site = $q->row('site_label');
			}
			$vars['data']['site'] = $site;
		}
		if ($codes_q->row('email') != '')
		{
			$vars['data']['restrict_to_email'] = $codes_q->row('email');
		}
		$vars['data']['author'] = "<a href=\"".BASE.AMP."C=myaccount&id=".$codes_q->row('author_id')."\">".$codes_q->row('screen_name')."</a>";
		if ($codes_q->row('destination_group_id') == 0)
		{
			$group = lang('default_group');
		}
		else
		{
			$this->EE->db->select('group_title')
						->from('member_groups')
						->where('group_id', $codes_q->row('destination_group_id'));
			$q = $this->EE->db->get();
			$group = $q->row('group_title');
		}
		$vars['data']['destination_group_id'] = $group;
		$vars['data']['usage_limit'] = ($codes_q->row('unlimited_usage')=='y')?lang('unlimited'):$codes_q->row('usage_limit');
		$date_fmt = ($this->EE->session->userdata('time_format') != '') ? $this->EE->session->userdata('time_format') : $this->EE->config->item('time_format');
        $date_format = ($date_fmt == 'us')? '%m/%d/%y %h:%i %a' : '%Y-%m-%d %H:%i';
		$vars['data']['created_on'] = $this->_format_date($date_format, $codes_q->row('created_date'));
		$vars['data']['expires_date'] = ($codes_q->row('expires_date')!=0)?$this->_format_date($date_format, $codes_q->row('expires_date')):lang('never');
		
		$credits_installed_q = $this->EE->db->get_where('modules', array('module_name' => 'Credits')); 
        if ($credits_installed_q->num_rows()>0)
        {
			$this->EE->db->select('action_id')
							->where('action_name', 'invitations_redeemed_author')
	        				->or_where('action_name', 'invitations_redeemed_user');
			$credits_action_q = $this->EE->db->get('exp_credits_actions');
			if ($credits_action_q->num_rows()>0)
	    	{
	    		$vars['data']['credits_author'] = $codes_q->row('credits_author');
	      		$vars['data']['credits_user'] = $codes_q->row('credits_user');
	   		}
 		}

		if ($uses_q->num_rows()==0)
		{
			$uses = lang('never');
		}
		else
		{
			$uses_a = array();
			foreach ($uses_q->result_array() as $row)
			{
				$uses_a[] = $this->_format_date($date_format, $row['used_date']).NBS.lang('by').NBS."<a href=\"".BASE.AMP."C=myaccount&id=".$row['member_id']."\">".$row['screen_name']."</a> <em>(".$row['ip_address'].")</em>";
			}
			$uses = implode(BR, $uses_a);
		}
		
		$vars['data']['used'] = $uses;
		$vars['data']['note'] = $codes_q->row('note');
        
    	return $this->EE->load->view('stats', $vars, TRUE);
    	
    }
    
    function settings()
    {
		$settings = $this->_get_settings();

        $this->EE->load->helper('form');
    	$this->EE->load->library('table');
    	
    	$member_groups = array();
    	$member_groups[0] = lang('use_system_setting');
        $this->EE->db->select('group_id, group_title');
        $this->EE->db->where('group_id NOT IN (1,2,3,4)');
        $q = $this->EE->db->get('member_groups');
        foreach ($q->result() as $obj)
        {
            $member_groups[$obj->group_id] = $obj->group_title;
        }
 
        $vars['settings'] = array(	
            'notify_on_requests'	=> form_checkbox('notify_on_requests', 'y', $settings['notify_on_requests']),
            'invitation_required'	=> form_checkbox('invitation_required', 'y', $settings['invitation_required']),
            'default_group_id'	=> form_dropdown('destination_group_id', $member_groups, $settings['destination_group_id'])
    		);
    	
		$this->EE->db->select('module_id'); 
        $freeform_installed_q = $this->EE->db->get_where('modules', array('module_name' => 'Freeform')); 
        if ($freeform_installed_q->num_rows()>0)
        {
        	$this->EE->db->select('form_id, form_label');
			$freeforms_q = $this->EE->db->get('freeform_forms');
			if ($freeforms_q->num_rows()>0)
        	{
				$forms = array();
				foreach ($freeforms_q->result_array() as $row)
				{
					$forms[$row['form_id']] = $row['form_label'];
				}
				$vars['settings']['invitation_required_freeform']	= form_multiselect('invitation_required_freeform[]', $forms, (isset($settings['invitation_required_freeform'])?$settings['invitation_required_freeform']:array()));
    		}
        }	
    		
		$this->EE->db->select('module_id'); 
        $credits_installed_q = $this->EE->db->get_where('modules', array('module_name' => 'Credits')); 
        if ($credits_installed_q->num_rows()>0)
        {
        	$this->EE->db->select('action_id')
						->where('action_name', 'invitations_redeemed_author')
        				->or_where('action_name', 'invitations_redeemed_user');
			$credits_action_q = $this->EE->db->get('exp_credits_actions');
			if ($credits_action_q->num_rows()>0)
        	{
				$vars['settings']['default_credits_author']	= form_input('default_credits_author', $settings['default_credits_author']);
	            $vars['settings']['default_credits_user']	= form_input('default_credits_user', $settings['default_credits_user']);
    		}
        }

    	return $this->EE->load->view('settings', $vars, TRUE);
	
    }    
    
    function save_settings()
    {
		
		$query = $this->EE->db->query("SELECT settings FROM exp_modules WHERE module_name='Invitations' LIMIT 1");
        $settings = unserialize($query->row('settings')); 
        
        $settings[$this->site_id]['notify_on_requests'] = (isset($_POST['notify_on_requests']) && $_POST['notify_on_requests']=='y')?true:false;
		
        $settings[$this->site_id]['invitation_required'] = (isset($_POST['invitation_required']) && $_POST['invitation_required']=='y')?true:false;
        $settings[$this->site_id]['invitation_required_freeform'] = (isset($_POST['invitation_required_freeform']))?$_POST['invitation_required_freeform']:array();
        $settings[$this->site_id]['destination_group_id'] = (isset($_POST['destination_group_id']))?$this->EE->input->post('destination_group_id'):'0';
        $settings[$this->site_id]['default_credits_author'] = (isset($_POST['default_credits_author']))?$this->EE->input->post('default_credits_author'):'0';
        $settings[$this->site_id]['default_credits_user'] = (isset($_POST['default_credits_user']))?$this->EE->input->post('default_credits_user'):'0';

        $this->EE->db->where('module_name', 'Invitations');
        $this->EE->db->update('modules', array('settings' => serialize($settings)));
        
        $this->EE->session->set_flashdata('message_success', $this->EE->lang->line('preferences_updated'));
        
        $this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=settings');
    }
    
    
    
    function email_templates()
    {

        $this->EE->load->helper('form');
    	$this->EE->load->library('table');
 
        $this->EE->db->select('template_name, data_title, template_data')
                    ->from('specialty_templates')
                    ->like('template_name', 'invitation', 'after');
        $query = $this->EE->db->get();
        foreach ($query->result_array() as $row)
        {
            $vars['data'][$row['template_name']] = array(	
                'data_title'	=> form_input("{$row['template_name']}"."[data_title]", $row['data_title'], 'style="width: 100%"'),
                'template_data'	=> form_textarea("{$row['template_name']}"."[template_data]", $row['template_data'])
        		);
    	}

    	return $this->EE->load->view('email_templates', $vars, TRUE);
	
    }    
    
    function save_email_templates()
    {
        
        $query = $this->EE->db->select('template_name')->like('template_name', 'invitation', 'after')->get('specialty_templates');

        foreach ($query->result_array() as $row)
        {
            $template = $row['template_name'];
            $data_title = (isset($_POST[$template]['data_title']))?$this->EE->security->xss_clean($_POST[$template]['data_title']):$this->EE->lang->line($template.'_subject');
            $template_data = (isset($_POST[$template]['template_data']))?$this->EE->security->xss_clean($_POST[$template]['template_data']):$this->EE->lang->line($template.'_message');
            
            $this->EE->db->where('template_name', $template);
            $this->EE->db->update('specialty_templates', array('data_title' => $data_title, 'template_data' => $template_data));
        }       

        $this->EE->session->set_flashdata('message_success', $this->EE->lang->line('preferences_updated'));
        
        $this->EE->functions->redirect(BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=email_templates');
    }
    
    
    
    function _p_config($base_url, $total_rows)
    {
        $p_config = array();
        $p_config['base_url'] = $base_url;
        $p_config['total_rows'] = $total_rows;
		$p_config['per_page'] = $this->perpage;
		$p_config['page_query_string'] = TRUE;
		$p_config['query_string_segment'] = 'rownum';
		$p_config['full_tag_open'] = '<p id="paginationLinks">';
		$p_config['full_tag_close'] = '</p>';
		$p_config['prev_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_prev_button.gif" width="13" height="13" alt="&lt;" />';
		$p_config['next_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_next_button.gif" width="13" height="13" alt="&gt;" />';
		$p_config['first_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_first_button.gif" width="13" height="13" alt="&lt; &lt;" />';
		$p_config['last_link'] = '<img src="'.$this->EE->cp->cp_theme_url.'images/pagination_last_button.gif" width="13" height="13" alt="&gt; &gt;" />';
        return $p_config;
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
				'notify_on_requests'=>true,
                'invitation_required'=>false,
	            'destination_group_id'=>'0',
	            'default_credits_author'=>0,
	            'default_credits_user'=>0
    		);
        }
        
    }
    
    
    function _string_to_timestamp($human_string, $localized = TRUE)
    {
        if (version_compare(APP_VER, '2.6.0', '<'))
        {
            return $this->EE->localize->convert_human_date_to_gmt($human_string, $localized);
        }
        else
        {
            return $this->EE->localize->string_to_timestamp($human_string, $localized);
        }
    }
  
  

}
/* END */
?>