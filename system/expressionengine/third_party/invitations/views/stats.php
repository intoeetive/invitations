

<?php 
$this->load->view('tabs');
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

