<?=form_open('C=addons_modules'.AMP.'M=show_module_cp'.AMP.'module=invitations'.AMP.'method=create_and_save_batch', array('id'=>'invitations_generate_form'));?>



<?php 
$this->load->view('tabs');
$this->table->set_template($cp_pad_table_template);
$this->table->set_heading(
    array('data' => '', 'style' => 'width:40%;'),
    array('data' => '', 'style' => 'width:60%;')
);


foreach ($data as $item)
{
	$this->table->add_row($item[0], $item[1]);
}

echo $this->table->generate();

?>
<?php $this->table->clear()?>

<p><?=form_submit('submit', lang('create_and_save'), 'class="submit"')?></p>

<?php
form_close();

