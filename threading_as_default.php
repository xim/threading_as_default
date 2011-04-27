<?php

/**
 * In here there be hacks. We do three things:
 *  - If someone sets list view, save this in our settings.
 *  - For new folders, select "Threads" as default
 *  - Every time we are rendering the list of mailboxes, get the user
 *    preferences and see if the user has overridden the view mode for any
 *    folder. Use tihs to rebuild the message_threading setting.
 *
 * Notes:
 *  - Does not respect setting "list" view mode from drop-down in list view in
 *    the long run.
 *  - Does not clean the settings when you delete folders
 *  - Uses "." as delimiter for folders.
 *  - Not properly tested. Yet.
 *
 * @version 0.8
 * @author Morten Minde Neergaard
 */

function unrecurse($tree) {
	$paths = array();
	foreach ($tree as $key => $value) {
		$paths[] = $value['id'];
		$paths = array_merge($paths, unrecurse($value['folders']));
	}
	return $paths;
}

class threading_as_default extends rcube_plugin
{
	public $task = 'mail|settings';

	function init()
	{
		$this->add_hook('render_mailboxlist', array($this, 'default_override'));
		$this->add_hook('folder_update', array($this, 'folder_save_override'));
		$this->add_hook('folder_form', array($this, 'folder_form_override'));
	}
	function folder_form_override($args) {
		$rcmail = rcmail::get_instance();
		$prefs = $rcmail->config->get('thread_override', array());
		if (array_key_exists('info', $args['form']['props']['fieldsets'])) {
			$path = $args['form']['props']['fieldsets']['location']['content']['path']['value'];
			$path = preg_replace('/.*value="([^"]*)".*/', "$1", $path);
			if ($path == '') {
				$folder = '';
			} else {
				$folder = $path . '.';
			}
			$path = $args['form']['props']['fieldsets']['location']['content']['name']['value'];
			$path = preg_replace('/.*value="([^"]*)".*/', "$1", $path);
			$folder .= $path;
		} else {
			$folder = '';
		}
		if (! array_key_exists($folder, $prefs)) {
			$args['form']['props']['fieldsets']['settings']['content']['viewmode']['value'] = <<<EOF
<select name="_viewmode" id="_listmode">

<option value="0">List</option>
<option value="1" selected="selected">Threads</option>
</select>
EOF;
		}
		return $args;
	}
	function folder_save_override($args) {
		$rcmail = rcmail::get_instance();
		$prefs = $rcmail->config->get('thread_override', array());
		if (! $args['record']['settings']['view_mode']) {
			$prefs[$args['record']['name']] = 0;
			$rcmail->user->save_prefs(array('thread_override' => $prefs));
		}
		print_r($prefs);
		return $args;
	}
	function default_override($args) {
		$rcmail = rcmail::get_instance();
		$prefs = $rcmail->config->get('thread_override', array());
		$a_thread = $rcmail->config->get('message_threading', array());
		$list = unrecurse($args['list']);
		$changed = false;
		foreach ($list as $folder) {
			if ((! array_key_exists($folder, $prefs)) && (! array_key_exists($folder, $a_thread))) {
				$changed = true;
				$a_thread[$folder] = true;
			}
		}
		if ($changed) {
			$rcmail->user->save_prefs(array('message_threading' => $a_thread));
		}

		return $args;
	}
}
