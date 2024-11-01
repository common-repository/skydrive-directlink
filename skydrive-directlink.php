<?php
/*
 Plugin Name: Skydrive Directlink
 Plugin URI: http://wordpress.org/extend/plugins/skydrive-directlink/
 Version: 0.7.0
 Author: Flarefox
 Description: Auto update direct link of a skydrive file.
 Author URI: http://yunjie.org/
 */
?>
<?php
/*
 Copyright 2011  FlareFox  (email : flarefox@163.com)

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */
?>
<?php
$wp_sddl_plugin_url = trailingslashit( get_bloginfo('wpurl') ).PLUGINDIR.'/'. dirname( plugin_basename(__FILE__) );
$wp_sddl_plugin_path = ABSPATH . PLUGINDIR . '/'.dirname( plugin_basename(__FILE__) );
require_once( $wp_sddl_plugin_path . '/skydrive-directlink-class.php' );
$sddl = new skydrive_directlink_core();

// Add Options Page
add_action('admin_menu', 'sddl_add_pages');
function sddl_add_pages()
{
	add_options_page(__('Skydrive Directlink','skydrive-directlink'), __('Skydrive directlink','skydrive-directlink'), 'manage_options', 'skydrive-directlink/skydrive-directlink-options.php');
}

// textdomain
add_action('init', 'ffox_skydrive_directlink_textdomain');
function ffox_skydrive_directlink_textdomain(){
	load_plugin_textdomain('skydrive-directlink', false, 'skydrive-directlink');
}

function skydrive_directlink($content) {
	global $sddl;
	return $sddl->direct_link_in_content($content);
}

$sddl_options = get_option('skydrive_directlink_options');
$priority = empty($sddl_options['hook_priority']) ? -1 : $sddl_options['hook_priority'];
add_filter('the_content', 'skydrive_directlink', $priority);

// cron job
// since 0.5
function sddl_add_cron($schedules)
{
	global $sddl_options;
	$interval = $sddl_options['auto_update_round_value'] * $sddl_options['auto_update_round_unit'];
	if($interval <= 0)
		return $schedules;
	$schedules['updatesddl'] = array(
		'interval'	=>	$interval,
		'display'	=>	__('Customed cycle for automatically updating skydrive links', 'skydrive-directlink')
	);
	return $schedules;
}
add_filter('cron_schedules', 'sddl_add_cron');

$interval = $sddl_options['auto_update_round_value'] * $sddl_options['auto_update_round_unit'];
if($interval > 0) {
	if (!wp_next_scheduled('auto_update_sddl_event')) {
		wp_schedule_event(time(), 'updatesddl', 'auto_update_sddl_event');
	}
}

function auto_update_sddl_func()
{
	global $sddl;
	$sddl->update_all_links();
}
add_action('auto_update_sddl_event', 'auto_update_sddl_func');

function stop_auto_update()
{
	global $sddl;
	$sddl->auto_update_stop();
}
register_deactivation_hook(basename(__FILE__), 'stop_auto_update');

// only for debug
//$result = skydrive_files_list::list_one_folder('http://cid-ce319fecb363fa30.office.live.com/browse.aspx/.Public');
//foreach($result as $k=>$fm) {
	// 入库
//	$db = new skydrive_directlink_db();
//	$db->insert_item($fm);
//}
?>