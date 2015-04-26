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
function task_rememberus($task)
{
	global $db, $mybb, $lang;
	
	rememberus__lang_load("",false,true);
	
	$reminder_query = $db->query("SELECT * FROM ".TABLE_PREFIX."rememberus WHERE active = 1 ORDER BY priority DESC");
	while($reminder = $db->fetch_array($reminder_query))
	{
		$name = $reminder['name'];
		$subject = $reminder['subject'];
		$interval = $reminder['interval'];
		$conditions = unserialize($reminder['conditions']);
		
		$sql = rememberus_build_query($reminder);
		$user_query = $db->query($sql);
		if(($num = $db->num_rows($user_query)) > 0)
		{
			while($user = $db->fetch_array($user_query))
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
				
				$message_html = rememberus_replace_placeholders($reminder['message_html'], $user);
				$message_html = nl2br($message_html);
				$message_txt = rememberus_replace_placeholders($reminder['message_txt'], $user);
				
				my_mail(
					$user['email'],
					$subject,
					$message_html,
					"",
					"",
					"",
					false,
					"both",
					$message_txt,
					""
				);
				
				$db->insert_query("rememberus_log", array(
					'rid' => $reminder['rid'],
					'uid' => $user['uid'],
					'dateline' => TIME_NOW
				));
			}
			
			add_task_log($task, sprintf($lang->rememberus_task_log, $num, $name));
			
			// although we load every reminder every time this task runs we only want one to run
			// so only continue when no e-mails were sent out for the current reminder.
			break;
		}
	}
}