<?php
/**
 * MyBB 1.6
 * Copyright � 2009 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
  * License: http://www.mybb.com/about/license
 *
 * $Id: forumperms.php 4394 2010-12-14 14:38:21Z ralgith $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class IPB3_Converter_Module_Forumperms extends Converter_Module_Forumperms {

	var $settings = array(
		'friendly_name' => 'forum permissions',
		'progress_column' => 'id',
		'default_per_screen' => 1000,
	);
	
	function pre_setup()
	{
		global $import_session;
		
		if(empty($import_session['forumperms_groups']))
		{
			$query = $this->old_db->query("
				SELECT p.perm_id, g.g_perm_id, g.g_id 
				FROM ".OLD_TABLE_PREFIX."forum_perms p
				LEFT JOIN ".OLD_TABLE_PREFIX."groups g ON (p.perm_id=g.g_perm_id)
			");			
			while($permgroup = $this->old_db->fetch_array($query))
			{
				$import_session['forumperms_groups'][$permgroup['g_perm_id']] = $permgroup;
			}
			$this->old_db->free_result($query);
			$import_session['forumperms_groups_count'] = count($import_session['forumperms_groups']);
		}
	}
	
	function import()
	{
		global $import_session;

		$query = $this->old_db->simple_select("groups", "	
g_id,
g_view_board,
g_mem_info,
g_use_search,
g_edit_profile,
g_post_new_topics,
g_reply_own_topics,
g_reply_other_topics,
g_edit_posts,
g_delete_own_posts,
g_delete_own_topics,
g_post_polls,
g_vote_polls,
g_use_pm,
g_is_supmod,
g_access_cp,
g_max_messages,
g_max_mass_pm,
g_can_msg_attach

", "", array('limit_start' => $this->trackers['start_forumperms'], 'limit' => $import_session['forumperms_per_screen']));
		while($perm = $this->old_db->fetch_array($query))
		{
			$this->process_permission($perm);
		}
	}
	
	function process_permission($data)
	{
		$permission_array = $data;
		if(!is_array($permission_array))
		{
			$permission_array = array (
				'start_perms'		=> "*",
				'reply_perms'		=> "*",
				'read_perms'		=> "*",
				'upload_perms'		=> "*",
				'download_perms'	=> "*",
				'show_perms'		=> "*"
			);
		}
		$this->debug->log->datatrace('$permission_array', $permission_array);
		
		foreach($permission_array as $key => $permission)
		{
			$this->debug->log->trace3("\$key: {$key} \$permission: {$permission}");
			// All permissions are on (global)
			if($permission == '1')
			{
				$query = $this->old_db->simple_select("groups", "g_id");
				while($group = $this->old_db->fetch_array($query))
				{
					$new_perms[$this->board->get_group_id($group['g_id'], array("not_multiple" => true))][$key] = 1;
				}
			}
			else
			{						
				$perm_split = explode(',', $permission);						
				foreach($perm_split as $key2 => $gid)
				{
					$new_perms[$this->board->get_group_id($gid, array("not_multiple" => true))][$key] = 1;
				}
			}
		}
		
		$this->debug->log->datatrace('$new_perms', $new_perms);
		
		if(!empty($new_perms))
		{
			foreach($new_perms as $gid => $perm2)
			{
				foreach($permission_array as $key => $value)
				{
					if(!array_key_exists($key, $perm2))
					{
						$perm2[$key] = 0;
					}
				}
				$perm_array = $perm2;
				$perm_array['gid'] = $gid;
				
				$this->debug->log->datatrace('$perm_array', $perm_array);

				$this->insert($perm_array);
			}
		}
	}
	
	function convert_data($data)
	{
		$insert_data = array();
				
		// Invision Power Board 3 values
		$insert_data['fid'] = $this->get_import->fid($data[0]);
		$insert_data['gid'] = $data[0];
		$insert_data['canpostthreads'] = $data[4];
		$insert_data['canview'] = $data[1];
		$insert_data['cansearch'] = $data[3];
		$insert_data['canpostreplys'] = $data[5];
		$insert_data['caneditposts'] = $data[7];
		$insert_data['candeleteposts'] = $data[8];
		$insert_data['candeletethreads'] = $data[9];
		$insert_data['canpostpolls'] = $data[10];
		$insert_data['canvotepolls'] = $data[11];
		$insert_data['canpostattachments'] = $data[17];

	
		return $insert_data;
	}
	
	function fetch_total()
	{
		global $import_session;
		
		// Get number of forum permissions
		if(!isset($import_session['total_forumperms']))
		{
			$query = $this->old_db->simple_select("forums", "COUNT(*) as count");
			$import_session['total_forumperms'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}
		
		return $import_session['total_forumperms'];
	}
}
/*
	var $settings = array(
		'friendly_name' => 'forum permissions',
		'progress_column' => 'fid',
		'default_per_screen' => 1000,
	);

	var $convert_val = array(
			'caneditposts' => 'f_edit',
			'candeleteposts' => 'f_delete',
			'caneditattachments' => 'f_attach',
			'canpostpolls' => 'f_poll',
			'canvotepolls' => 'f_vote',
			'canpostthreads' => 'f_post',
			'canpostreplys' => 'f_post',
			'candlattachments' => 'f_download',
			'canpostattachments' => 'f_attach',
			'canviewthreads' => 'f_read',
			'canview' => 'f_read',
			'cansearch' => 'f_search',
		);

	function import()
	{
		global $import_session;

		$query = $this->old_db->query("
			SELECT g.group_id, g.forum_id, g.auth_option_id, g.auth_setting, o.auth_option
			FROM ".OLD_TABLE_PREFIX."acl_groups g
			LEFT JOIN ".OLD_TABLE_PREFIX."acl_options o ON (g.auth_option_id=o.auth_option_id)
			WHERE g.auth_option_id > 0 AND o.auth_option IN ('".implode("','", $this->convert_val)."')
			LIMIT {$this->trackers['start_forumperms']}, {$import_session['forumperms_per_screen']}
		");
		while($perm = $this->old_db->fetch_array($query))
		{
			$this->debug->log->datatrace('$perm', $perm);

			$this->permissions[$perm['forum_id']][$perm['group_id']][$perm['auth_option']] = $perm['auth_setting'];
		}

		$this->process_permissions();
	}

	function process_permissions()
	{
		$this->debug->log->datatrace('$this->permissions', $this->permissions);

		if(is_array($this->permissions))
		{
			foreach($this->permissions as $fid => $groups)
			{
				foreach($groups as $gid => $columns)
				{
					$perm = array(
						'fid' => $fid,
						'gid' => $gid,
						'columns' => $columns,
					);

					$this->debug->log->datatrace('$perm', $perm);

					$this->insert($perm);
				}
			}
		}
	}

	function convert_data($data)
	{
		$insert_data = array();

		// phpBB 3 values
		$insert_data['fid'] = $this->get_import->fid($data['fid']);
		$insert_data['gid'] = $this->get_import->gid($data['gid']);

		foreach($this->convert_val as $mybb_column => $phpbb_column)
		{
			if(!$data['columns'][$phpbb_column])
			{
				$data['columns'][$phpbb_column] = 0;
			}
			else
			{
				$data['columns'][$phpbb_column] = 1;
			}

			$insert_data[$mybb_column] = $data['columns'][$phpbb_column];
		}

		return $insert_data;
	}

	function test()
	{
		$this->get_import->cache_fids = array(
			2 => 10,
		);

		$this->get_import->cache_gids = array(
			3 => 11,
		);

		$data = array(
			'fid' => 2,
			'gid' => 3,
			'columns' => array(
				'f_edit' => 1,
				'f_delete' => 1,
				'f_attach' => 1,
				'f_poll' => 1,
				'f_vote' => 1,
				'f_post' => 1,
				'f_download' => 1,
				'f_attach' => 1,
				'f_read' => 1,
				'f_search' => 1,
			),
		);

		$match_data = array(
			'fid' => 10,
			'gid' => 11,
			'caneditposts' => 1,
			'candeleteposts' => 1,
			'caneditattachments' => 1,
			'canpostpolls' => 1,
			'canvotepolls' => 1,
			'canpostthreads' => 1,
			'canpostreplys' => 1,
			'candlattachments' => 1,
			'canpostattachments' => 1,
			'canviewthreads' => 1,
			'canview' => 1,
			'cansearch' => 1,
		);

		$this->assert($data, $match_data);
	}

	function fetch_total()
	{
		global $import_session;

		// Get number of forum permissions
		if(!isset($import_session['total_forumperms']))
		{
			$query = $this->old_db->query("
				SELECT COUNT(*) as count
				FROM ".OLD_TABLE_PREFIX."acl_groups g
				LEFT JOIN ".OLD_TABLE_PREFIX."acl_options o ON (g.auth_option_id=o.auth_option_id)
				WHERE o.is_local=1 AND o.auth_option IN ('".implode("','", $this->convert_val)."')
			");
			$import_session['total_forumperms'] = $this->old_db->fetch_field($query, 'count');
			$this->old_db->free_result($query);
		}

		return $import_session['total_forumperms'];
	}
}
*/
?>