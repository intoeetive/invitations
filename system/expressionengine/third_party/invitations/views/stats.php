
<ul class="tab_menu" id="tab_menu_tabs">
<li class="content_tab current"> <a href="<?=BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=index'?>"><?=lang('invitations')?></a>  </li> 
<li class="content_tab"> <a href="<?=BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=settings'?>"><?=lang('settings')?></a>  </li> 
</ul> 
<div class="clear_left shun"></div> 


<?php 
$this->table->set_template($cp_pad_table_template);
$this->table->set_heading(
    array('data' => '', 'style' => 'width:40%;'),
    array('data' => '', 'style' => 'width:60%;')
);


foreach ($data as $key=>$val)
{
	$this->table->add_row(lang($key), $val);
}

echo $this->table->generate();

?>
<?php $this->table->clear()?>

