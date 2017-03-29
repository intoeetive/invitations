<?php

if ( ! defined('INVITATIONS_ADDON_NAME'))
{
	define('INVITATIONS_ADDON_NAME',         'Invitations');
	define('INVITATIONS_ADDON_VERSION',      '1.3.1');
}

$config['name']=INVITATIONS_ADDON_NAME;
$config['version']=INVITATIONS_ADDON_VERSION;

$config['nsm_addon_updater']['versions_xml']='http://www.intoeetive.com/index.php/update.rss/89';