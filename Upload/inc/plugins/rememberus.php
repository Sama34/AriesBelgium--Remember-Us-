<?php
/**
 * Remember Us?
 * Copyright 2011 Aries-Belgium
 *
 * $Id$
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

define('REMEMBERUS_PLUGIN_VERSION', '1100');

$version = $cache->read("version");
if($version['version_code'] < 1600)
{
	define('_MODULE_SEPARATOR', '/');
}
else
{
	define('_MODULE_SEPARATOR', '-');
}

$plugins->add_hook('admin_user_menu','rememberus_admin_user_menu');
$plugins->add_hook('admin_user_action_handler','rememberus_admin_user_action_handler');
$plugins->add_hook('admin_load','rememberus_admin_load');
$plugins->add_hook('usercp_start','rememberus_usercp_start');
$plugins->add_hook('redirect','rememberus_redirect');

/**
 * Info function for MyBB plugin system
 */
function rememberus_info()
{
	global $lang;
	
	rememberus__lang_load("",false,true);
	
	$donate_button = 
'<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=RQNL345SN45DS" style="float:right;margin-top:-8px;padding:4px;" target="_blank"><img src="https://www.paypalobjects.com/WEBSCR-640-20110306-1/en_US/i/btn/btn_donate_SM.gif" /></a>';

	return array(
		"name"			=> $lang->rememberus_plugin_name,
		"description"	=> $donate_button.$lang->rememberus_description,
		"website"		=> "",
		"author"		=> "Aries-Belgium",
		"authorsite"	=> "mailto:aries.belgium@gmail.com",
		"version"		=> "1.4",
		"guid" 			=> "3816f5d3af65050d62d32eb2bc22775c",
		"compatibility" => "14*,16*"
	);
}

/**
 * The install function for the plugin system
 */
function rememberus_install()
{
	global $db, $lang;
	
	rememberus__lang_load("",false,true);

	// create the tables
	$db->query(
		"CREATE TABLE IF NOT EXISTS ".TABLE_PREFIX."rememberus ("
		. "`rid` INT( 10 ) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY ,"
		. "`name` VARCHAR( 30 ) NOT NULL ,"
		. "`subject` VARCHAR( 78 ) NOT NULL ,"
		. "`message_html` TEXT NOT NULL,"
		. "`message_txt` TEXT NOT NULL,"
		. "`priority` SMALLINT NOT NULL ,"
		. "`conditions` TEXT NOT NULL,"
		. "`active` TINYINT(1) NOT NULL,"
		. "`perpage` SMALLINT( 4 ) UNSIGNED NOT NULL,"
		. "`interval` VARCHAR( 30 ) NOT NULL"
		. ")"
		.$db->build_create_table_collation()
	);
	
	$db->query(
		"CREATE TABLE IF NOT EXISTS ".TABLE_PREFIX."rememberus_log ("
		."`rid` int(10) unsigned NOT NULL,"
		."`uid` int(10) unsigned NOT NULL,"
		."`dateline` int(10) unsigned NOT NULL,"
		."KEY `rid` (`rid`),"
		."KEY `uid` (`uid`)"
		.")"
		.$db->build_create_table_collation()
	);
	
	// add the task if not exists
	$query = $db->simple_select("tasks", "tid", "file='rememberus'");
	if($db->num_rows($query) == 0)
	{
		$db->insert_query("tasks",array(
			'title' => $lang->rememberus_plugin_name,
			'description' => $lang->rememberus_task_description,
			'file' => "rememberus",
			'minute' => "10,25,40,55",
			'hour' => "*",
			'day' => "*",
			'month' => "*",
			'weekday' => "*",
			'nextrun' => strtotime("+15 minutes"),
			'lastrun' => time(),
			'enabled' => 1,
			'logging' => 1,
			'locked' => 0
		));
	}
}

/**
 * The is_installed function for the plugin system
 */
function rememberus_is_installed()
{
	global $db;
	
	// does the tables exists?
	$table_rememberus_exists = $db->table_exists('rememberus');
	$table_rememberus_log_exists = $db->table_exists('rememberus_log');
	
	// check if the task exists
	$query = $db->simple_select("tasks", "tid", "file='rememberus'");
	$task_exists = $db->num_rows($query) == 1;
	
	return $table_rememberus_exists && $table_rememberus_log_exists && $task_exists;
}

/**
 * The uninstall function for the plugin system
 */
function rememberus_uninstall()
{
	global $db;
	
	// remove the rememberus table
	$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."rememberus");
	$db->query("DROP TABLE IF EXISTS ".TABLE_PREFIX."rememberus_log");
	
	// remove the task from the database
	$db->delete_query("tasks", "file='rememberus'");
}

/**
 * The upgrade function
 */
function rememberus_upgrade()
{
	global $db;
	
	// new in version 1.1
	$db->query(
		"CREATE TABLE IF NOT EXISTS ".TABLE_PREFIX."rememberus_log ("
		."`rid` int(10) unsigned NOT NULL,"
		."`uid` int(10) unsigned NOT NULL,"
		."`dateline` int(10) unsigned NOT NULL,"
		."KEY `rid` (`rid`),"
		."KEY `uid` (`uid`)"
		.")"
		.$db->build_create_table_collation()
	);
	
	$query = $db->query("SHOW COLUMNS FROM ".TABLE_PREFIX."rememberus LIKE 'active'");
	if($db->num_rows($query) == 0)
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."rememberus ADD `active` TINYINT(1) NOT NULL;");
	}
	
	$query = $db->query("SHOW COLUMNS FROM ".TABLE_PREFIX."rememberus LIKE 'perpage'");
	if($db->num_rows($query) == 0)
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."rememberus ADD `perpage` SMALLINT( 4 ) UNSIGNED NOT NULL;");
	}
	
	$query = $db->query("SHOW COLUMNS FROM ".TABLE_PREFIX."rememberus LIKE 'interval'");
	if($db->num_rows($query) == 0)
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."rememberus ADD `interval` VARCHAR( 30 ) NOT NULL;");
	}
	
	$query = $db->query("SHOW COLUMNS FROM ".TABLE_PREFIX."users LIKE 'rememberus_time'");
	if($db->num_rows($query) == 1)
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."users DROP `rememberus_time`");
	}
	
	// fix character encoding, fixed in 1.2
	$db->query("ALTER TABLE ".TABLE_PREFIX."rememberus DEFAULT".$db->build_create_table_collation());
	$db->query("ALTER TABLE ".TABLE_PREFIX."rememberus_log DEFAULT".$db->build_create_table_collation());
	$db->query("ALTER TABLE  ".TABLE_PREFIX."rememberus "
		."CHANGE  `name`  `name` VARCHAR( 30 ) ".$db->build_create_table_collation()." NOT NULL ,"
		."CHANGE  `subject`  `subject` VARCHAR( 78 ) ".$db->build_create_table_collation()." NOT NULL ,"
		."CHANGE  `message_html`  `message_html` TEXT ".$db->build_create_table_collation()." NOT NULL ,"
		."CHANGE  `message_txt`  `message_txt` TEXT ".$db->build_create_table_collation()." NOT NULL ,"
		."CHANGE  `conditions`  `conditions` TEXT ".$db->build_create_table_collation()." NOT NULL ,"
		."CHANGE  `interval`  `interval` VARCHAR( 30 ) ".$db->build_create_table_collation()." NOT NULL"
	);
}

/**
 * The activation function for the plugin system
 */
function rememberus_activate()
{
	global $db;
	
	// do upgrades
	rememberus_upgrade();
	
	// enable the task
	$db->update_query("tasks", array('enabled'=>1), "file='rememberus'");
}

/**
 * The activation function for the plugin system
 */
function rememberus_deactivate()
{
	global $db;
	
	// disable the task
	$db->update_query("tasks", array('enabled'=>0), "file='rememberus'");
}

/**
 * Implementation of the admin_user_menu hook
 *
 * Add the submenu to the user tab
 */
function rememberus_admin_user_menu($submenu)
{
	global $lang;
	
	rememberus__lang_load("",false,true);
	
	$submenu[] = array(
		'id' => 'rememberus',
		'title' => $lang->rememberus_menu,
		'link' => 'index.php?module=user'._MODULE_SEPARATOR.'rememberus'
	);
}

/**
 * Implementation of the admin_user_action_handler hook
 *
 * Fix to set the menu to active
 */
function rememberus_admin_user_action_handler($actions)
{
	$actions['rememberus'] = array('active' => 'rememberus', 'file' => '');
}

/**
 * Implementation of the admin_load hook
 *
 * The actual Membership Reminder page
 */
function rememberus_admin_load()
{
	global $mybb, $page, $db, $lang, $admin_options;
	
	if($mybb->input['module'] == "user"._MODULE_SEPARATOR."rememberus")
	{
		rememberus__lang_load("",false,true);
		
		$sub_tabs = array(
			"reminders" => array(
				'title' => $lang->rememberus_reminders,
				'link' => 'index.php?module=user'._MODULE_SEPARATOR.'rememberus&amp;action=reminders',
				'description' => $lang->rememberus_reminders_description
			),
			"add" => array(
				'title'=> $lang->rememberus_add_reminder,
				'link' => 'index.php?module=user'._MODULE_SEPARATOR.'rememberus&amp;action=add',
				'description' => $lang->rememberus_add_reminder_description
			),
			"log" => array(
				'title'=> $lang->rememberus_view_log,
				'link' => 'index.php?module=user'._MODULE_SEPARATOR.'rememberus&amp;action=log',
				'description' => $lang->rememberus_view_log_descripion
			),
			"donate" => array(
				'title'=> "Donate",
				'link' => 'index.php?module=user'._MODULE_SEPARATOR.'rememberus&amp;action=donate',
				'description' => "Support the further development of this plugin."
			),
		);
		
		$rid = intval($mybb->input['rid']);
		$edit_sub_tabs = array(
			"edit" => array(
				'title' => $lang->rememberus_edit_reminder,
				'link' => 'index.php?module=user'._MODULE_SEPARATOR.'rememberus&amp;action=edit&amp;rid='.$rid,
				'description' => $lang->rememberus_edit_reminder_description
			),
			"delete" => array(
				'title'=> $lang->rememberus_delete_reminder,
				'link' => 'index.php?module=user'._MODULE_SEPARATOR.'rememberus&amp;action=delete&amp;rid='.$rid,
				'description' => $lang->rememberus_delete_reminder_description
			),
			"testmail" => array(
				'title'=> $lang->rememberus_testmail_reminder,
				'link' => 'index.php?module=user'._MODULE_SEPARATOR.'rememberus&amp;action=testmail&amp;rid='.$rid,
				'description' => $lang->rememberus_testmail_reminder_description
			),
		);
		
		$page->add_breadcrumb_item($lang->rememberus_reminders, "index.php?module=user"._MODULE_SEPARATOR."rememberus");
		
		switch($mybb->input['action'])
		{
			case 'donate':
				$page->add_breadcrumb_item("Donate", "index.php?module=user"._MODULE_SEPARATOR."rememberus&action=donate");
				
				$page->output_header("Support this plugin");
				
				$page->output_nav_tabs($sub_tabs,'donate');
				
				print '<div style="font-weight:bold;">This plugin will always be free but a donation to support the further development of this plugin and my other plugins is always welcome and much appreciated. You are completely free in doing so and not doing it wouldn\'t restrict you or the functionality of the plugin in any way. Thank you!</div>';
				print '<div style="text-align:center; padding: 10px;"><a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=RQNL345SN45DS" target="_blank"><img src="https://www.paypalobjects.com/WEBSCR-640-20110306-1/en_US/i/btn/btn_donate_SM.gif" /></a></div>';
				
				$page->output_footer();
				break;
			case 'add':
				// set the default values
				$name = '';
				$subject = '';
				$perpage = '25';
				$priority = '';
				$message_html = '';
				$message_txt = '';
				$conditions = array();
				$active = '0';
				$interval = 'once';
				$txt_display = '';
				$other_display = '';
				$txt_preview = false;
				
				$submit_btn_text = $lang->rememberus_add_reminder;
				
				if($mybb->request_method == 'post')
				{
					verify_post_check($mybb->input['my_post_key']);
					
					$name = $mybb->input['name'];
					$subject = $mybb->input['subject'];
					$perpage = $mybb->input['perpage'];
					$active = $mybb->input['active'];
					$interval = $mybb->input['interval'];
					$message_html = $mybb->input['message_html'];
					$priority = floatval($mybb->input['priority']);
					if(isset($mybb->input['conditions_serial']))
					{
						$conditions = $mybb->input['conditions_serial'];
					}
					else
					{
						$conditions = serialize(rememberus_build_condition_array());
					}
					
					if(isset($mybb->input['auto_txt']) && $mybb->input['auto_txt'] == "on")
					{
						include_once MYBB_ROOT."/inc/functions_massmail.php";
						
						$txt_preview = true;
						$txt_display = '';
						$other_display = 'display:none';
						$message_txt = create_text_message($message_html);
						$submit_btn_text = $lang->rememberus_continue;
						flash_message($lang->rememberus_review_text, 'success');
					}
					else
					{					
						$message_txt = $mybb->input['message_txt'];
					}
					
					if(!$txt_preview)
					{
						$db->insert_query("rememberus", array(
							'name' => $db->escape_string($name),
							'priority' => floatval($priority),
							'subject' => $db->escape_string($subject),
							'message_html' => $db->escape_string($message_html),
							'message_txt' => $db->escape_string($message_txt),
							'conditions' => $db->escape_string($conditions),
							'perpage' => intval($perpage),
							'active' => intval($active),
							'interval' => $db->escape_string($interval)
						));
						
						flash_message($lang->rememberus_add_success, 'success');
						admin_redirect("index.php?module=user"._MODULE_SEPARATOR."rememberus");
					}
				}
				
				$page->extra_header .= '
				<link type="text/css" href="'.$mybb->settings['bburl'].'/inc/plugins/rememberus/style/rememberus.css?v='.REMEMBERUS_PLUGIN_VERSION.'" rel="stylesheet" />
				<script type="text/javascript" src="../jscripts/scriptaculous.js?load=effects"></script>
				<script type="text/javascript" src="'.$mybb->settings['bburl'].'/inc/plugins/rememberus/jscripts/rememberus.js?v='.REMEMBERUS_PLUGIN_VERSION.'"></script>
				<script type="text/javascript">
					'.rememberus_inserttext().'
				</script>';
				
				$page->add_breadcrumb_item($lang->rememberus_add_reminder, "index.php?module=user"._MODULE_SEPARATOR."rememberus&action=add");
				
				$page->output_header($lang->rememberus_add_reminder);
				
				$page->output_nav_tabs($sub_tabs,'add');
				
				$form = new Form("index.php?module=user"._MODULE_SEPARATOR."rememberus&amp;action=add","POST", "reminder_form");
				
				echo '<div id="reminder_info" style="'.$other_display.'">';
				$form_container = new FormContainer($lang->rememberus_reminder);
				$form_container->output_row(
					$lang->rememberus_name,
					$lang->rememberus_name_description,
					$form->generate_text_box('name', $name, array('id' => 'name')),
					'name'
				);
				
				$form_container->output_row(
					$lang->rememberus_priority,
					$lang->rememberus_priority_description,
					$form->generate_text_box('priority', $priority, array('id' => 'priority')),
					'priority'
				);
				
				$form_container->output_row(
					$lang->rememberus_subject,
					$lang->rememberus_subject_description,
					$form->generate_text_box('subject', $subject, array('id' => 'subject')),
					'subject'
				);
				
				$form_container->output_row(
					$lang->rememberus_interval,
					$lang->rememberus_interval_description,
					$form->generate_select_box('interval', rememberus_interval_select(), $interval, array('id' => 'interval')),
					'interval'
				);
				
				$form_container->output_row(
					$lang->rememberus_perpage,
					$lang->rememberus_perpage_description,
					$form->generate_text_box('perpage', $perpage, array('id' => 'perpage')),
					'perpage'
				);
				
				$form_container->output_row(
					$lang->rememberus_active,
					"",
					$form->generate_yes_no_radio('active', $active),
					'active'
				);
				
				$form_container->end();
				echo '</div>';
				
				echo '<div id="reminder_html" style="'.$other_display.'">';
				$form_container = new FormContainer($lang->rememberus_message_html);
				$form_container->output_row(
					$lang->rememberus_message_html_description,
					$lang->rememberus_ph . " " .rememberus_placeholders('message_html'),
					$form->generate_text_area('message_html', $message_html, array('id' => 'message_html', 'class' => '', 'style' => 'width: 100%; height: 300px;')).
					(!$txt_preview ? 
					'<br />'
					.$form->generate_check_box('auto_txt', "on", $lang->rememberus_auto_txt_description, array('id' => 'auto_txt')) : ""),
					'message_html'
				);
				$form_container->end();
				echo '</div>';
				
				echo '<div id="reminder_txt" style="'.$txt_display.'">';
				$form_container = new FormContainer($lang->rememberus_message_txt);
				$form_container->output_row(
					$lang->rememberus_message_txt_description,
					$lang->rememberus_ph . " " .rememberus_placeholders('message_txt'),
					$form->generate_text_area('message_txt', $message_txt, array('id' => 'message_txt', 'class' => '', 'style' => 'width: 100%; height: 300px;')),
					'message_txt'
				);
				
				$form_container->end();
				echo '</div>';
				
				echo '<div id="reminder_conditions_help" class="rememberus_help" style="display:block">';
				$form_container = new FormContainer('<a name="help">'.$lang->rememberus_help.'</a><span style="float:right">[<a href="#" class="rememberus_help_close" >'.$lang->rememberus_help_close.'</a>]</span>');
				
				$form_container->output_cell($lang->rememberus_help_conditions);
				$form_container->construct_row();
				
				$form_container->end();
				echo '</div>';
				
				echo '<div id="reminder_conditions" style="'.$other_display.'">';
				$form_container = new FormContainer($lang->rememberus_conditions.'<a href="#help" class="rememberus_btn_help" rel="reminder_conditions_help" >&nbsp;</a>');
				
				$form_container->output_row_header($lang->rememberus_field);
				$form_container->output_row_header($lang->rememberus_test);
				$form_container->output_row_header($lang->rememberus_value);
				$form_container->output_row_header("&nbsp;",array("class" => "align_center","style" => "width: 2%"));
				
				$fields = rememberus_fields();
				$field_select = array(''=>'');
				foreach($fields as $key => $field)
				{
					$field_select[$key] = $field['title'];
				}
				
				$form_container->output_cell(
					$form->generate_select_box('field[]', $field_select, array(), array('class'=>"rememberus_field"))
				);
				
				$form_container->output_cell(
					$form->generate_select_box('test[]', rememberus_test_select(), array(), array('class'=>"rememberus_test"))
				);
				
				$form_container->output_cell(
					$form->generate_text_box('value[]', "", array('class'=>"rememberus_value"))
				);
				
				$form_container->output_cell(
					'<a href="#" class="rememberus_btn_add" id="rememberus_btn_add">&nbsp;</a>',
					array('style' => "text-align:center")
				);
				
				$form_container->construct_row();
				
				$form_container->end();
				if($txt_preview)
				{
					
					echo $form->generate_hidden_field(
						'conditions_serial',
						$conditions
					);
						
				}
				echo '</div>';
				
				$buttons[] = $form->generate_submit_button($submit_btn_text);
				$form->output_submit_wrapper($buttons);
				
				$form->end();
				
				$page->output_footer();
				break;
			case 'edit':
				// get information from the database
				$rid = intval($mybb->input['rid']);
				$query = $db->simple_select("rememberus", "*", "rid={$rid}");
				$reminder = $db->fetch_array($query);
				
				// set the default values
				$name = $reminder['name'];
				$subject = $reminder['subject'];
				$perpage = $reminder['perpage'];
				$priority = floatval($reminder['priority']);
				$active = intval($reminder['active']);
				$interval = $reminder['interval'];
				$message_html = $reminder['message_html'];
				$message_txt = $reminder['message_txt'];
				$conditions = unserialize($reminder['conditions']);
				$txt_display = '';
				$other_display = '';
				$txt_preview = false;
				
				$submit_btn_text = $lang->rememberus_edit_reminder;
				
				if($mybb->request_method == 'post')
				{
					verify_post_check($mybb->input['my_post_key']);
					
					$name = $mybb->input['name'];
					$subject = $mybb->input['subject'];
					$message_html = $mybb->input['message_html'];
					$priority = floatval($mybb->input['priority']);
					$perpage = $mybb->input['perpage'];
					$active = $mybb->input['active'];
					$interval = $mybb->input['interval'];
					if(isset($mybb->input['conditions_serial']))
					{
						$conditions = $mybb->input['conditions_serial'];
					}
					else
					{
						$conditions = serialize(rememberus_build_condition_array());
					}
					
					if(isset($mybb->input['auto_txt']) && $mybb->input['auto_txt'] == "on")
					{
						include_once MYBB_ROOT."/inc/functions_massmail.php";
						
						$txt_preview = true;
						$txt_display = '';
						$other_display = 'display:none';
						$message_txt = create_text_message($message_html);
						$submit_btn_text = $lang->rememberus_continue;
						flash_message($lang->rememberus_review_text, 'success');
					}
					else
					{					
						$message_txt = $mybb->input['message_txt'];
					}
					
					if(!$txt_preview)
					{
						$db->update_query("rememberus", array(
							'name' => $db->escape_string($name),
							'priority' => floatval($priority),
							'subject' => $db->escape_string($subject),
							'message_html' => $db->escape_string($message_html),
							'message_txt' => $db->escape_string($message_txt),
							'conditions' => $db->escape_string($conditions),
							'perpage' => intval($perpage),
							'active' => intval($active),
							'interval' => $db->escape_string($interval)
						),'rid='.$rid);
						
						flash_message($lang->rememberus_edit_success, 'success');
						admin_redirect("index.php?module=user"._MODULE_SEPARATOR."rememberus");
					}
				}
				
				$page->extra_header .= '
				<link type="text/css" href="'.$mybb->settings['bburl'].'/inc/plugins/rememberus/style/rememberus.css?v='.REMEMBERUS_PLUGIN_VERSION.'" rel="stylesheet" />
				<script type="text/javascript" src="../jscripts/scriptaculous.js?load=effects"></script>
				<script type="text/javascript" src="'.$mybb->settings['bburl'].'/inc/plugins/rememberus/jscripts/rememberus.js?v='.REMEMBERUS_PLUGIN_VERSION.'"></script>
				<script type="text/javascript">
					'.rememberus_inserttext().'
				</script>';
				
				$page->add_breadcrumb_item($lang->rememberus_edit_reminder, "index.php?module=user"._MODULE_SEPARATOR."rememberus&action=edit&rid=".$rid);
				
				$page->output_header($lang->rememberus_edit_reminder);
				
				$page->output_nav_tabs($edit_sub_tabs,'edit');
				
				$form = new Form("index.php?module=user"._MODULE_SEPARATOR."rememberus&amp;action=edit&rid=".$rid,"POST", "reminder_form");
				
				echo '<div id="reminder_info" style="'.$other_display.'">';
				$form_container = new FormContainer($lang->rememberus_reminder);
				$form_container->output_row(
					$lang->rememberus_name,
					$lang->rememberus_name_description,
					$form->generate_text_box('name', htmlspecialchars_uni($name), array('id' => 'name')),
					'name'
				);
				
				$form_container->output_row(
					$lang->rememberus_priority,
					$lang->rememberus_priority_description,
					$form->generate_text_box('priority', floatval($priority), array('id' => 'priority')),
					'priority'
				);
				
				$form_container->output_row(
					$lang->rememberus_subject,
					$lang->rememberus_subject_description,
					$form->generate_text_box('subject', htmlspecialchars_uni($subject), array('id' => 'subject')),
					'subject'
				);
				
				$form_container->output_row(
					$lang->rememberus_interval,
					$lang->rememberus_interval_description,
					$form->generate_select_box('interval', rememberus_interval_select(), $interval, array('id' => 'interval')),
					'interval'
				);
				
				$form_container->output_row(
					$lang->rememberus_perpage,
					$lang->rememberus_perpage_description,
					$form->generate_text_box('perpage', $perpage, array('id' => 'perpage')),
					'perpage'
				);
				
				$form_container->output_row(
					$lang->rememberus_active,
					"",
					$form->generate_yes_no_radio('active', $active),
					'active'
				);
				
				$form_container->end();
				echo '</div>';
				
				echo '<div id="reminder_html" style="'.$other_display.'">';
				$form_container = new FormContainer($lang->rememberus_message_html);
				$form_container->output_row(
					$lang->rememberus_message_html_description,
					$lang->rememberus_ph . " " . rememberus_placeholders('message_html'),
					$form->generate_text_area('message_html', $message_html, array('id' => 'message_html', 'class' => '', 'style' => 'width: 100%; height: 300px;')).
					(!$txt_preview ? 
					'<br />'
					.$form->generate_check_box('auto_txt', "on", $lang->rememberus_auto_txt_description, array('id' => 'auto_txt')) : ""),
					'message_html'
				);
				$form_container->end();
				echo '</div>';
				
				echo '<div id="reminder_txt" style="'.$txt_display.'">';
				$form_container = new FormContainer($lang->rememberus_message_txt);
				$form_container->output_row(
					$lang->rememberus_message_txt_description,
					$lang->rememberus_ph . " " .rememberus_placeholders('message_txt'),
					$form->generate_text_area('message_txt', htmlspecialchars_uni($message_txt), array('id' => 'message_txt', 'class' => '', 'style' => 'width: 100%; height: 300px;')),
					'message_txt'
				);
				
				$form_container->end();
				echo '</div>';
				
				echo '<div id="reminder_conditions_help" class="rememberus_help" style="'.$other_display.'">';
				$form_container = new FormContainer('<a name="help">'.$lang->rememberus_help.'</a><span style="float:right">[<a href="#" class="rememberus_help_close" >'.$lang->rememberus_help_close.'</a>]</span>');
				
				$form_container->output_cell($lang->rememberus_help_conditions);
				$form_container->construct_row();
				
				$form_container->end();
				echo '</div>';
				
				echo '<div id="reminder_conditions" style="'.$other_display.'">';
				$form_container = new FormContainer($lang->rememberus_conditions.'<a href="#help" class="rememberus_btn_help" rel="reminder_conditions_help" >&nbsp;</a>');
				
				$form_container->output_row_header($lang->rememberus_field);
				$form_container->output_row_header($lang->rememberus_test);
				$form_container->output_row_header($lang->rememberus_value);
				$form_container->output_row_header("&nbsp;",array("class" => "align_center","style" => "width: 2%"));
				
				$fields = rememberus_fields();
				$field_select = array(''=>'');
				foreach($fields as $key => $field)
				{
					$field_select[$key] = $field['title'];
				}
				
				if(is_array($conditions))
				{
					foreach($conditions as $condition)
					{
						$form_container->output_cell(
							$form->generate_select_box('field[]', $field_select, array($condition['field']), array('class'=>"rememberus_field"))
						);
						
						$form_container->output_cell(
							$form->generate_select_box('test[]', rememberus_test_select(), array($condition['test']), array('class'=>"rememberus_test"))
						);
						
						$form_container->output_cell(
							$form->generate_text_box('value[]', $condition['value'], array('class'=>"rememberus_value"))
						);
						
						$form_container->output_cell(
							'<a href="#" class="rememberus_btn_delete">&nbsp;</a>',
							array('style' => "text-align:center")
						);
						
						$form_container->construct_row();
					}
				}
				
				$form_container->output_cell(
					$form->generate_select_box('field[]', $field_select, array(), array('class'=>"rememberus_field"))
				);
				
				$form_container->output_cell(
					$form->generate_select_box('test[]', rememberus_test_select(), array(), array('class'=>"rememberus_test"))
				);
				
				$form_container->output_cell(
					$form->generate_text_box('value[]', "", array('class'=>"rememberus_value"))
				);
				
				$form_container->output_cell(
					'<a href="#" class="rememberus_btn_add" id="rememberus_btn_add">&nbsp;</a>',
					array('style' => "text-align:center")
				);
				
				$form_container->construct_row();
				
				$form_container->end();
				if($txt_preview)
				{
					
					echo $form->generate_hidden_field(
						'conditions_serial',
						$conditions
					);
						
				}
				echo '</div>';
				
				$buttons[] = $form->generate_submit_button($submit_btn_text);
				$form->output_submit_wrapper($buttons);
				
				$form->end();
				
				$page->output_footer();
			
				break;
			case 'delete':
				$rid = intval($mybb->input['rid']);
				
				if($mybb->request_method == 'post')
				{
					verify_post_check($mybb->input['my_post_key']);
					
					if(isset($mybb->input['delete_confirm']) && $mybb->input['delete_confirm'] == "on")
					{
						$db->delete_query("rememberus_log", "rid=".$rid);
						$db->delete_query("rememberus", "rid=".$rid);
						
						flash_message($lang->rememberus_delete_success, 'success');
					}
					
					admin_redirect("index.php?module=user"._MODULE_SEPARATOR."rememberus");
				}
				
				$page->add_breadcrumb_item($lang->rememberus_delete_reminder, "index.php?module=user"._MODULE_SEPARATOR."rememberus&action=delete&rid=".$rid);
				
				$page->output_header($lang->rememberus_delete_reminder);
				
				$page->output_nav_tabs($edit_sub_tabs,'delete');
				
				$form = new Form("index.php?module=user"._MODULE_SEPARATOR."rememberus&amp;action=delete&rid=".$rid,"POST", "reminder_form");
				
				$table = new DefaultTable();
				
				$table->construct_cell(
					$form->generate_check_box('delete_confirm', "on", $lang->delete)
				);
				
				$table->construct_cell(
					$lang->rememberus_delete_confirm
				);
				
				$table->construct_row();
				
				$table->output($lang->rememberus_delete_reminder);
				
				$buttons[] = $form->generate_submit_button($lang->rememberus_delete_reminder);
				$form->output_submit_wrapper($buttons);
				
				$form->end();
				
				$page->output_footer();
				
				break;
			case 'log':
				// some default values
				$rid = '-1';
				$rid_selected = '-1';
				$username = '';
				$filter_query = "";
				$filter_sql = "";
				
				// read the filter and set the query and sql parameters
				if(isset($mybb->input['rid']) && $mybb->input['rid'] != '-1')
				{
					$rid = $mybb->input['rid'];
					$rid_selected = $rid == 0 ? 'unsubscribe' : $rid;
					$filter_query .= "rid=".(int)$rid."&amp;";
					$filter_sql .= "l.rid=".(int)$rid." AND ";
				}
				
				if(isset($mybb->input['username']) && !empty($mybb->input['username']))
				{
					$username = $mybb->input['username'];
					$query = $db->simple_select("users", "uid", "username='".$db->escape_string($username)."'");
					$uid = (int)$db->fetch_field($query, "uid");
					$filter_query .= "username=".urlencode($username)."&amp;";
					
					if($uid > 0)
					{
						$filter_sql .= "l.uid={$uid} AND ";
					}
				}
				
				if(isset($mybb->input['perpage']) && !empty($mybb->input['perpage']))
				{
					$filter_query .= "perpage=".intval($mybb->input['perpage'])."&amp;";
				}
				
				// pagination
				$per_page = isset($mybb->input['perpage']) ? intval($mybb->input['perpage']) : 20;
				$current_page = isset($mybb->input['page']) ? $mybb->input['page'] : 1;
				$query = $db->query("SELECT COUNT(rid) As log_count FROM ".TABLE_PREFIX."rememberus_log l WHERE {$filter_sql} 1=1");
				$log_count = $db->fetch_field($query, 'log_count');
				$pagination = draw_admin_pagination($current_page, $per_page, $log_count, "index.php?module=user"._MODULE_SEPARATOR."rememberus&action=log&amp;{$filter_query}page={page}");
				
				$page->add_breadcrumb_item($lang->rememberus_view_log, "index.php?module=user"._MODULE_SEPARATOR."rememberus&action=log");
				
				$page->output_header($lang->rememberus_view_log);
				
				$page->output_nav_tabs($sub_tabs,'log');
				
				$table = new Table;
				$table->construct_header($lang->rememberus_reminder, array('width' => '33%'));
				$table->construct_header($lang->rememberus_username, array('width' => '33%'));
				$table->construct_header($lang->rememberus_date, array('width' => '33%'));
				
				$offset = $per_page * ($current_page-1);
				$query = $db->query(
					"SELECT * FROM ".TABLE_PREFIX."rememberus_log l "
					."LEFT JOIN ".TABLE_PREFIX."rememberus r ON r.rid = l.rid "
					."LEFT JOIN ".TABLE_PREFIX."users u ON u.uid = l.uid "
					."WHERE {$filter_sql} 1=1 "
					."ORDER BY dateline DESC LIMIT {$offset}, {$per_page}"
				);
				if($db->num_rows($query) > 0)
				{
					while($logitem = $db->fetch_array($query))
					{
						$logitem['name'] = htmlspecialchars_uni($logitem['name']);
						$logitem['dateline'] = date("jS M Y, G:i", $logitem['dateline']);
						$formatted_username = format_name($logitem['username'], $logitem['usergroup'], $logitem['displaygroup']);
						$logitem['profilelink'] = build_profile_link($formatted_username, $logitem['uid']);
						$trow = alt_trow();
						
						if($logitem['rid'] == 0)
						{
							$logitem['name'] = '<span style="color:red">'.$lang->rememberus_log_unsubscribe.'</span>';
						}
						else
						{
							$logitem['name'] = '<a href="index.php?module=user-rememberus&action=edit&rid='.$logitem['rid'].'">'
									.htmlspecialchars_uni($logitem['name']).'</a>';
						}
						
						$table->construct_cell($logitem['name']);
						$table->construct_cell($logitem['profilelink']);
						$table->construct_cell($logitem['dateline']);
						$table->construct_row();
					}
				}
				else
				{
					$table->construct_cell($lang->rememberus_log_no_results, array("colspan" => "3"));
					$table->construct_row();
				}
				
				$table->output($lang->rememberus_reminders_log);
				
				echo $pagination;
				
				$form = new Form("index.php","GET", "rememberus_log_filter");
				echo $form->generate_hidden_field('module', "user"._MODULE_SEPARATOR."rememberus");
				echo $form->generate_hidden_field('action', "log");
				
				$form_container = new FormContainer($lang->rememberus_filter_reminders);
				
				$reminders_select = array(
					'-1' => "",
					'unsubscribe' => $lang->rememberus_log_unsubscribe,
				);
				$query = $db->simple_select("rememberus");
				while($reminder = $db->fetch_array($query))
				{
					$reminders_select[$reminder['rid']] = $reminder['name'];
				}
				
				$form_container->output_row(
					$lang->rememberus_reminder,
					"",
					$form->generate_select_box('rid', $reminders_select, $rid_selected),
					'rid'
				);
				
				$form_container->output_row(
					$lang->rememberus_username,
					"",
					$form->generate_text_box('username', $username),
					'username'
				);
				
				$form_container->output_row(
					$lang->rememberus_perpage,
					"",
					$form->generate_text_box('perpage', $per_page),
					'perpage'
				);
				
				$form_container->end();
				
				$buttons[] = $form->generate_submit_button($lang->rememberus_filter_reminders);
				$form->output_submit_wrapper($buttons);
				
				$form->end();
				
				$page->output_footer();
				break;
			case 'update':
				if($mybb->request_method == 'post' && is_array($mybb->input['priority']))
				{
					$rids = array_keys($mybb->input['priority']);
					foreach($mybb->input['priority'] as $rid => $priority)
					{
						$db->update_query("rememberus", array('priority'=>intval($priority)), "rid=".intval($rid));
					}
					
					flash_message($lang->rememberus_update_success,'success');
				}
				
				admin_redirect("index.php?module=user"._MODULE_SEPARATOR."rememberus");
				break;
			case 'status':
				$rid = intval($mybb->input['rid']);
				$query = $db->simple_select("rememberus", "active", "rid=$rid");
				$active = $db->fetch_field($query, "active") == 0 ? 1 : 0;
				$db->update_query("rememberus", array('active'=>intval($active)), "rid=".intval($rid));
				
				flash_message($lang->rememberus_edit_success,'success');
				admin_redirect("index.php?module=user"._MODULE_SEPARATOR."rememberus");
				break;
			case 'testmail':
				$rid = intval($mybb->input['rid']);
				
				if($mybb->request_method == 'post')
				{
					$query = $db->simple_select("rememberus", "*", "rid=".$rid);
					$reminder = $db->fetch_array($query);
					
					if($reminder)
					{
						//insert the default disclaimer if the unsubscribe_link is not provided
						if(strpos($reminder['message_html'], "{unsubscribe_link}") === false)
						{
							$reminder['message_html'] .= "\n\n----\n".$lang->rememberus_unsubscribe_disclaimer;
						}
						if(strpos($reminder['message_txt'], "{unsubscribe_link}") === false)
						{
							$reminder['message_txt'] .= "\n\n----\n".$lang->rememberus_unsubscribe_disclaimer;
						}
						
						$message_html = rememberus_replace_placeholders($reminder['message_html'], $mybb->user);
						$message_html = nl2br($message_html);
						$message_txt = rememberus_replace_placeholders($reminder['message_txt'], $mybb->user);
						
						$emails = explode("\n", $mybb->input['emails']);
						foreach($emails as $email)
						{
							$email = trim($email);
							if(validate_email_format($email))
							{
								my_mail(
									$email,
									$reminder['subject'],
									$message_html,
									"",
									"",
									"",
									false,
									"both",
									$message_txt,
									""
								);
							}
						}
						
						flash_message($lang->rememberus_testmail_success, 'success');
					}
					else
					{
						flash_message($lang->rememberus_testmail_error, 'error');
					}
					
					admin_redirect("index.php?module=user"._MODULE_SEPARATOR."rememberus");
				}
				
				$page->add_breadcrumb_item($lang->rememberus_testmail_reminder, "index.php?module=user"._MODULE_SEPARATOR."rememberus&action=testmail&rid=".$rid);
				
				$page->output_header($lang->rememberus_testmail_reminder);
				
				$page->output_nav_tabs($edit_sub_tabs,'testmail');
				
				$form = new Form("index.php?module=user"._MODULE_SEPARATOR."rememberus&amp;action=testmail&rid=".$rid,"POST", "reminder_form");
				
				$form_container = new FormContainer($lang->rememberus_emails);
				
				$form_container->output_row(
					$lang->rememberus_email_description,
					$lang->rememberus_email_perline,
					$form->generate_text_area('emails', "", array('id' => 'emails', 'class' => '', 'style' => 'width: 100%; height: 300px;')),
					'emails'
				);
			
				$form_container->end();
				
				$buttons[] = $form->generate_submit_button($lang->rememberus_send);
				$form->output_submit_wrapper($buttons);
				
				$form->end();
				
				$page->output_footer();
				
				break;
			case 'reminders':
			default:
				$page->output_header($lang->rememberus_reminders);
				
				$page->output_nav_tabs($sub_tabs,'reminders');
				
				$form = new Form("index.php?module=user"._MODULE_SEPARATOR."rememberus&amp;action=update","POST");
				
				$form_container = new FormContainer($lang->rememberus_reminders);
				$form_container->output_row_header($lang->rememberus_name);
				$form_container->output_row_header($lang->rememberus_priority, array("class" => "align_center","style" => "width: 10%"));
				$form_container->output_row_header($lang->controls, array("class" => "align_center","style" => "width: 10%"));
				
				// iterate through the reminders
				$query = $db->query("SELECT * FROM ".TABLE_PREFIX."rememberus ORDER BY priority DESC");
				while($reminder = $db->fetch_array($query))
				{
					if($reminder['active'] == 1)
					{
						$icon = "<img src=\"styles/{$page->style}/images/icons/bullet_on.gif\" alt=\"({$lang->alt_enabled})\" title=\"{$lang->alt_enabled}\"  style=\"vertical-align: middle;\" /> ";
						$status = $lang->rememberus_deactivate_reminder;
					}
					else
					{
						$icon = "<img src=\"styles/{$page->style}/images/icons/bullet_off.gif\" alt=\"({$lang->alt_disabled})\" title=\"{$lang->alt_disabled}\"  style=\"vertical-align: middle;\" /> ";
						$status = $lang->rememberus_activate_reminder;
					}
					$reminder['rid'] = intval($reminder['rid']);
					$popup = new PopupMenu("reminder_group_{$reminder['rid']}", $lang->options);
					$popup->add_item($lang->edit, "index.php?module=user"._MODULE_SEPARATOR."rememberus&amp;action=edit&amp;rid={$reminder['rid']}");
					$popup->add_item($lang->delete, "index.php?module=user"._MODULE_SEPARATOR."rememberus&amp;action=delete&amp;rid={$reminder['rid']}");
					$popup->add_item($status, "index.php?module=user"._MODULE_SEPARATOR."rememberus&amp;action=status&amp;rid={$reminder['rid']}");
					$popup->add_item($lang->rememberus_view_log, "index.php?module=user"._MODULE_SEPARATOR."rememberus&amp;action=log&amp;rid={$reminder['rid']}");
					$popup->add_item($lang->rememberus_testmail_reminder, "index.php?module=user"._MODULE_SEPARATOR."rememberus&amp;action=testmail&amp;rid={$reminder['rid']}");
					
					$form_container->output_cell($icon.'<strong>'.htmlspecialchars_uni($reminder['name']).'</strong>');
					$form_container->output_cell($form->generate_text_box("priority[".$reminder['rid']."]", $reminder['priority'], array('style' =>"width:30px;text-align:center")), array('style' => "text-align:center"));
					$form_container->output_cell($popup->fetch(), array('class'=>"align_center"));
					$form_container->construct_row();
				}
				
				$form_container->end();
				
				$buttons[] = $form->generate_submit_button($lang->rememberus_update);
				$form->output_submit_wrapper($buttons);
				
				$form->end();
				
				$page->output_footer();

				break;
		}
		
		$page->output_header();
		
		die();
	}
}

/**
 * Implementation of the usercp_start hook
 * 
 */
function rememberus_usercp_start()
{
	global $mybb, $db, $lang;
	
	if($mybb->input['action'] == "rememberus_unsubscribe")
	{
		rememberus__lang_load();
		
		$email = urldecode($mybb->input['email']);
		$code = $mybb->input['code'];
		
		if($mybb->user['email'] == $email && $code == rememberus_generate_security_code($mybb->user))
		{
			$db->insert_query("rememberus_log", array(
				'rid' => '-1',
				'uid' => $mybb->user['uid'],
				'dateline' => time()
			));
			
			redirect("usercp.php", $lang->rememberus_unsubscribe_success);
		}
	}
}

/**
 * Implementation of the redirect hook
 * 
 * Force the redirect page to show up
 */
function rememberus_redirect($redirect_args)
{
	global $mybb, $lang;
	
	rememberus__lang_load();

	if($redirect_args['message'] == $lang->rememberus_unsubscribe_success)
	{
		$mybb->settings['redirects'] = 1;
		$mybb->user['showredirect'] = 1;
	}
}

/**
 * Build the conditions array from the POST input
 * to save to the database.
 */
function rememberus_build_condition_array()
{
	global $mybb;
	
	$array = array();
	$field_count = count($mybb->input['field']);
	for($i=0;$i<$field_count;$i++)
	{
		if(!empty($mybb->input['field'][$i]))
		{
			$array[] = array(
				'field' => $mybb->input['field'][$i],
				'test' => $mybb->input['test'][$i],
				'value' => $mybb->input['value'][$i]
			);
		}
	}
	
	return $array;
}

/**
 * The fields array
 */
function rememberus_fields()
{
	global $db, $lang, $plugins;
	
	rememberus__lang_load("",false,true);
	
	$fields = array(
		'uid' => array(
			'title' => $lang->rememberus_field_uid,
			'type' => 'int'
		),
		'username' => array(
			'title' => $lang->rememberus_field_username,
			'type' => 'string'
		),
		'email' => array(
			'title' => $lang->rememberus_field_email,
			'type' => 'string'
		),
		'postnum' => array(
			'title' => $lang->rememberus_field_postnum,
			'type' => 'int'
		),
		'usergroup' => array(
			'title' => $lang->rememberus_field_usergroup,
			'type' => 'int'
		),
		'regdate' => array(
			'title' => $lang->rememberus_field_regdate,
			'type' => 'date'
		),
		'lastactive' => array(
			'title' => $lang->rememberus_field_lastactive,
			'type' => 'date'
		),
		'lastpost' => array(
			'title' => $lang->rememberus_field_lastpost,
			'type' => 'date'
		),
		'away' => array(
			'title' => $lang->rememberus_field_away,
			'type' => 'boolean'
		),
		'returndate' => array(
			'title' => $lang->rememberus_field_returndate,
			'type' => 'date'
		),
		'reputation' => array(
			'title' => $lang->rememberus_field_reputation,
			'type' => 'int'
		),
		'regip' => array(
			'title' => $lang->rememberus_field_regip,
			'type' => 'ip'
		),
		'lastip' => array(
			'title' => $lang->rememberus_field_lastip,
			'type' => 'ip'
		),
		'timeonline' => array(
			'title' => $lang->rememberus_field_timeonline,
			'type' => 'int'
		),
		'language' => array(
			'title' => $lang->rememberus_field_language,
			'type' => 'string'
		),
	);
	
	$query = $db->simple_select("profilefields", "fid, name, type", "", array('order_by' => 'disporder'));
	while($pfield = $db->fetch_array($query))
	{
		$fields['fid'.$pfield['fid']] = array(
			'title' => $lang->rememberus_field_custom . ": " . $pfield['name'],
			'type' => 'string'
		);
	}
	
	$plugins->run_hooks('rememberus_fields', $fields);
	
	return $fields;
}

/**
 * Returns an array of interval to use with Form::generate_select_box()
 */
function rememberus_interval_select()
{
	global $lang;
	
	rememberus__lang_load("",false,true);
	
	return array(
		'once' => $lang->rememberus_interval_once,
		'day' => $lang->rememberus_interval_day,
		'week' => $lang->rememberus_interval_week,
		'2week' => $lang->rememberus_interval_2week,
		'month' => $lang->rememberus_interval_month,
		'3month' => $lang->rememberus_interval_3month,
		'6month' => $lang->rememberus_interval_6month,
		'year' => $lang->rememberus_interval_year,
		'2year' => $lang->rememberus_interval_2year,
	);
}

/**
 * Returns an array of the test parameters to use with Form::generate_select_box()
 */
function rememberus_test_select()
{
	global $lang;
	
	rememberus__lang_load("",false,true);
	
	return array(
		'' => '',
		'eq' => $lang->rememberus_test_eq,
		'neq' => $lang->rememberus_test_neq,
		'null' => $lang->rememberus_test_null,
		'notnull' => $lang->rememberus_test_notnull,
		'empty' => $lang->rememberus_test_empty,
		'notempty' => $lang->rememberus_test_notempty,
		'gt' => $lang->rememberus_test_gt,
		'lt' => $lang->rememberus_test_lt,
		'gte' => $lang->rememberus_test_gte,
		'lte' => $lang->rememberus_test_lte,
		'in' => $lang->rememberus_test_in,
		'nin' => $lang->rememberus_test_nin,
		'like' => $lang->rememberus_test_like,
		'nlike' => $lang->rememberus_test_nlike
	);
}

/**
 * Returns the placeholder links
 */
function rememberus_placeholders($target_id)
{
	global $lang, $plugins;
	
	rememberus__lang_load("",false,true);
	
	$placeholders = array(
		'username' => $lang->rememberus_ph_username,
		'useremail' => $lang->rememberus_ph_useremail,
		'regdate' => $lang->rememberus_ph_regdate,
		'lastactive' => $lang->rememberus_ph_lastactive,
		'returndate' => $lang->rememberus_ph_returndate,
		'bbname' => $lang->rememberus_ph_bbname,
		'bburl' => $lang->rememberus_ph_bburl,
		'bbemail' => $lang->rememberus_ph_bbemail,
		'unsubscribe_link' => $lang->rememberus_ph_unsubscribe_link,
	);
	
	$placeholders_links = array();
	foreach($placeholders as $placeholder => $translation)
	{
		$placeholders_links[] = "[<a href=\"#\" onclick=\"insertText('{".$placeholder."}', $('".$target_id."')); return false;\">".$translation."</a>]";
	}
	
	$plugins->run_hooks('rememberus_placeholders_links', $placeholders_links);
	
	return implode(", ", $placeholders_links);
}

/**
 * Replace the placeholders in a text
 */
function rememberus_replace_placeholders($text, $user)
{
	global $mybb, $plugins;
	
	$user_dateformat = rememberus_get_dateformat($user);
	
	$search = array(
		'{username}',
		'{useremail}',
		'{regdate}',
		'{lastactive}',
		'{returndate}',
		'{bbname}',
		'{bburl}',
		'{bbemail}',
		'{unsubscribe_link}'
	);
	
	$replace = array(
		htmlspecialchars_uni($user['username']),
		$user['email'],
		my_date($user_dateformat, $user['regdate']),
		my_date($user_dateformat, $user['lastactive']),
		my_date($user_dateformat, strtotime($user['returndate'])),
		htmlspecialchars_uni($mybb->settings['bbname']),
		$mybb->settings['bburl'],
		$mybb->settings['adminemail'],
		rememberus_unsubscribe_link($user)
	);
	
	$plugins->run_hooks('rememberus_placeholders_replace', $search, $replace);
	
	return str_replace($search, $replace, $text);
}

/**
 * Get the dateformat for specific user
 */
function rememberus_get_dateformat($user)
{	
	if($user['dateformat'] != 0 && $user['dateformat'] != '')
	{
		// if the user has a preferred dateformat use that
		global $date_formats;
		if($date_formats[$user['dateformat']])
		{
			return $date_formats[$user['dateformat']];
		}
	}
	
	// otherwise use the board's default dateformat and not the preferred
	// dateformat of the currently logged in user which is stored in
	// ($mybb->settings['dateformat'])
	global $settings;
	return $settings['dateformat'];
}

/**
 * Build query from the conditions
 */
function rememberus_build_query($reminder)
{
	global $db, $plugins;
	
	if(is_integer($reminder))
	{
		$query = $db->simple_select("rememberus", "*", "rid=".intval($reminder));
		$reminder = $db->fetch_array($query);
	}
	
	if(!is_array($reminder['conditions']))
	{
		$reminder['conditions'] = unserialize($reminder['conditions']);
	}
	
	$fields = rememberus_fields();
	$sql = array();
	
	foreach($reminder['conditions'] as $condition)
	{
		$sql_tests = array(
			'eq' => " = ",
			'neq' => " <> ",
			'empty' => " = ''",
			'notempty' => " <> ''",
			'null' => " IS NULL",
			'notnull' => " IS NOT NULL",
			'gt' => " > ",
			'lt' => " < ",
			'lte' => " <= ",
			'gte' => " >= ",
			'in' => " IN", // special test
			'nin' => " NOT IN", // special test
			'like' => " LIKE ",
			'nlike' => " NOT LIKE "
		);
		
		// check if the test is 'in' or 'nin' and get the values seperated with a comma
		if($condition['test'] == "in" || $condition['test'] == "nin")
		{
			$values = array();
			// don't include comma's between quotes
			if(preg_match_all("/[^,\"']+|\"([^\"]*)\"|'([^']*)'/si", $condition['value'], $matches))
			{
				$condition['value'] = array();
				foreach($matches[0] as $value)
				{
					$condition['value'][] = str_replace(array("\"","'"),"",$value);
				}
			}
		}
		
		$field = $condition['field'];
		$sql_test = isset($sql_tests[$condition['test']]) ? $sql_tests[$condition['test']] : " = ";
		$field_type = isset($fields[$condition['field']]) ? $fields[$condition['field']]['type'] : "string";
		
		// special fields that need some extra SQL stuff
		switch($field)
		{
			case 'returndate':
				// returndate is not a timestamp so we need to
				// replace the field with a function
				$field = 'UNIX_TIMESTAMP(STR_TO_DATE(returndate,"%d-%m-%Y"))';
				break;
		}
		
		$values = $condition['value'];
		if(!is_array($values))
		{
			$values = array($values);
		}
		
		$clean_values = array();
		switch($field_type)
		{
			case 'date':
				foreach($values as $value)
				{
					if(is_numeric($condition['value']))
					{
						$clean_values[] = intval($condition['value']);
					}
					else
					{
						$clean_values[] = strtotime($condition['value']);
					}
				}
				break;
			case 'int':
				foreach($values as $value)
				{
					$clean_values[] = intval($value);
				}
				break;
			case 'boolean':
				foreach($values as $value)
				{
					$clean_values[] = $value == 1 ? 1 : 0;
				}
				break;
			case 'ip':
				foreach($values as $value)
				{
					if(
						$condition['test'] == 'gt' || 
						$condition['test'] == 'lt' || 
						$condition['test'] == 'gte' || 
						$condition['test'] == 'lte'
					)
					{
						$field = $field."long";
						if(!is_numeric($value))
						{
							$value = ip2long($value);
						}
					}
					
					$clean_values[] = "'".$db->escape_string($value)."'";
				}
				break;
			case 'string':
				foreach($values as $value)
				{
					$clean_values[] = "'".$db->escape_string($value)."'";
				}
				break;
		}
		
		if($condition['test'] == 'in' || $condition['test'] == 'nin')
		{
			// only if the test is in or nin separate the values with a comma
			$clean_values = "(".implode(',',$clean_values).")";
		}
		elseif(
			$condition['test'] == 'empty' ||
			$condition['test'] == 'notempty' ||
			$condition['test'] == 'null' ||
			$condition['test'] == 'notnull'
		)
		{
			$clean_values = "";
		}
		else
		{
			// otherwise just use the first value
			$clean_values = str_replace('*','%',$clean_values[0]);
		}
		
		$sql[] = $field . $sql_test . $clean_values;
	}
	
	$plugins->run_hooks('rememberus_build_query', $reminder);
	
	// defaults conditions
	// 1. only send an e-mail if the user wants to receive messages from the admin
	$sql[] = "allownotices = 1";
	
	// 2. only send when the previous mail was longer ago than the selected interval
	//    and to people that didn't unsubscribe 
	if(isset($reminder['rid']))
	{
		$prev_timestamp = 0;
		switch($reminder['interval'])
		{
			case 'day':
				$prev_timestamp = strtotime("-1 day");
				break;
			case 'week':
				$prev_timestamp = strtotime("-1 week");
				break;
			case '2week':
				$prev_timestamp = strtotime("-2 week");
				break;
			case 'month':
				$prev_timestamp = strtotime("-1 month");
				break;
			case '3month':
				$prev_timestamp = strtotime("-3 month");
				break;
			case '6month':
				$prev_timestamp = strtotime("-6 month");
				break;
			case 'year':
				$prev_timestamp = strtotime("-1 year");
				break;
			case '2year':
				$prev_timestamp = strtotime("-2 year");
				break;
			case 'once':
			default:
				$prev_timestamp = 0;
				break;
		}
		
		if($prev_timestamp > 0)
		{
			// if the previous timestamp is greater than 0, select the users who already received the reminder in the given interval
			$sql[] = "u.uid NOT IN(SELECT uid FROM ".TABLE_PREFIX."rememberus_log WHERE (rid=".intval($reminder['rid'])." AND dateline > {$prev_timestamp}) OR rid=0)";
		}
		else
		{
			// select all users who already got this reminder
			$sql[] = "u.uid NOT IN(SELECT uid FROM ".TABLE_PREFIX."rememberus_log WHERE rid=".intval($reminder['rid'])." OR rid=0)";
		}
	}
	
	$limit = "";
	if(isset($reminder['perpage']))
	{
		if($reminder['perpage'] >= 0)
		{
			// fallback to the default if perpage is 0
			$reminder['perpage'] = 25;
		}
		$limit = "LIMIT ".intval($reminder['perpage']);
	}
	
	// build the query and return it
	$where = implode(" AND ", $sql);
	return "SELECT * FROM ".TABLE_PREFIX."users u LEFT JOIN ".TABLE_PREFIX."userfields f ON u.uid = f.ufid WHERE {$where} {$limit}";
}

/**
 * Return the javascript insertText function
 */
function rememberus_inserttext()
{
	return '
	function insertText(value, textarea)
	{
		// Internet Explorer
		if(document.selection)
		{
			textarea.focus();
			var selection = document.selection.createRange();
			selection.text = value;
		}
		// Firefox
		else if(textarea.selectionStart || textarea.selectionStart == \'0\')
		{
			var start = textarea.selectionStart;
			var end = textarea.selectionEnd;
			textarea.value = textarea.value.substring(0, start)	+ value	+ textarea.value.substring(end, textarea.value.length);
		}
		else
		{
			textarea.value += value;
		}
	}';
}

/**
 * Generate security code
 */
function rememberus_generate_security_code($user)
{
	return md5($user['loginkey'].$user['salt'].$user['regdate']);
}

/**
 * Generate unsubscribe link
 */
function rememberus_unsubscribe_link($user)
{
	global $mybb;
	
	$urlsafe_email = urlencode($user['email']);
	$security_code = rememberus_generate_security_code($user);
	return $mybb->settings['bburl']."/usercp.php?action=rememberus_unsubscribe&email=".$urlsafe_email."&code=".$security_code;
}

/**
 * Helper function to load language files for the plugin
 */
function rememberus__lang_load($file="", $supress_error=false, $force_admin=false)
{
	global $lang;
	
	$plugin_name = str_replace('__lang_load', '', __FUNCTION__);
	$plugin_lang_dir = MYBB_ROOT."inc/plugins/{$plugin_name}/lang/";
	if(empty($file)) $file = $plugin_name;
	
	$langparts = explode("/", $lang->language, 2);
	$language = $langparts[0];
	if(isset($langparts[1]))
	{
		$dir = "/".$langparts[1];
	}
	else
	{
		$dir = "";
	}
	
	if($force_admin)
	{
		$dir = "/admin";
	}
	
	if(file_exists($plugin_lang_dir.$language.$dir."/{$file}.lang.php"))
	{
		require_once $plugin_lang_dir.$language.$dir."/{$file}.lang.php";
	}
	elseif(file_exists($plugin_lang_dir."english".$dir."/{$file}.lang.php"))
	{
		require_once $plugin_lang_dir."english".$dir."/{$file}.lang.php";
	}
	else
	{
		if($supress_error != true)
		{
			die($plugin_lang_dir."english".$dir."/{$file}.lang.php");
		}
	}
	
	if(is_array($l))
	{
		foreach($l as $key => $val)
		{
			if(empty($lang->$key) || $lang->$key != $val)
			{
				$lang->$key = $val;
			}
		}
	}
}