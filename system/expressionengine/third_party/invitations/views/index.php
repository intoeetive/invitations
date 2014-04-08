
<ul class="tab_menu" id="tab_menu_tabs">
<li class="content_tab current"> <a href="<?=BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=index'?>"><?=lang('invitations')?></a>  </li> 
<li class="content_tab"> <a href="<?=BASE.AMP.'C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=settings'?>"><?=lang('settings')?></a>  </li> 
</ul> 
<div class="clear_left shun"></div> 

<div id="filterMenu">
	<fieldset>
		<legend><?=lang('refine_results')?></legend>

	<?=form_open('C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=index', array('id'=>'invitations_filter_form'));?>

		<div class="group">
            <?php
			
			$show = array(
				'all' => lang('all'),
				'expired' => lang('expired'),
				'used' => lang('used'),
				'available' => lang('available')
			);
			
			echo lang('show').NBS.form_dropdown('show', $show, $selected['show']).NBS;
			
			$perpage = array(
				'25' => '25',
				'50' => '50',
				'100' => '100',
				'0' => lang('all')
			);
			
			echo lang('invitations').NBS.form_dropdown('perpage', $perpage, $selected['perpage']).NBS.lang('per_page');
            
            echo NBS.NBS.form_submit('submit', lang('show'), 'class="submit" id="search_button"');
            
            ?>
		</div>

	<?=form_close()?>
	</fieldset>
</div>


<div style="padding: 10px;">

<?php if ($total_count == 0):?>
	<div class="tableFooter">
		<p class="notice"><?=lang('no_records')?></p>
	</div>
<?php else:?>


	<?php
	
	echo form_open('C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=delete_confirm', array('id'=>'invitations_action_form'));
	
		$this->table->set_template($cp_table_template);
		$this->table->set_heading($table_headings); 

		echo $this->table->generate($invitations);
	?>



<div class="tableSubmit">

	<?=form_submit('submit', lang('delete'), 'class="submit"'); ?>
</div>
<?=form_close()?>

<span class="pagination"><?=$pagination?></span>


<?php endif; /* if $total_count > 0*/?>

</div>


