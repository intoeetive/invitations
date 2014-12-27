
<ul class="tab_menu" id="tab_menu_tabs">
<li class="content_tab<?php if (in_array($this->input->get('method'), array('', 'index'))) echo ' current';?>"> <a href="<?=BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=index'?>"><?=lang('invitations')?></a>  </li> 
<li class="content_tab<?php if (in_array($this->input->get('method'), array('method'))) echo ' current';?>"> <a href="<?=BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=requests'?>"><?=lang('requests')?></a>  </li> 
<li class="content_tab<?php if (in_array($this->input->get('method'), array('settings'))) echo ' current';?>"> <a href="<?=BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=settings'?>"><?=lang('settings')?></a>  </li> 
<li class="content_tab<?php if (in_array($this->input->get('method'), array('email_templates'))) echo ' current';?>"> <a href="<?=BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=email_templates'?>"><?=lang('email_templates')?></a>  </li> 
</ul> 
<div class="clear_left shun"></div> 
