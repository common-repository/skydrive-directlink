<?php
global $sddl;

if (!empty($_POST['submitted']))
{
	$sddl_options = array();
	$sddl_options['default_url'] = stripslashes($_POST['default_url']);
	$sddl_options['rough_locate'] = stripslashes($_POST['rough_locate']);
	$sddl_options['accurate_locate_start'] = stripslashes($_POST['accurate_locate_start']);
	$sddl_options['accurate_locate_end'] = stripslashes($_POST['accurate_locate_end']);
	$sddl_options['hook_priority'] = intval($_POST['hook_priority']);
	$sddl_options['time_limit'] = intval($_POST['time_limit']);
	$sddl_options['cache_mode'] = intval($_POST['cache_mode']);
	$sddl_options['auto_update_round_value'] = intval($_POST['auto_update_round_value']);
	$sddl_options['auto_update_round_unit'] = intval($_POST['auto_update_round_unit']);
	// 若修改了周期设定，则重新生成事件
	$old_options = get_option('skydrive_directlink_options');
	$old_round = $old_options['auto_update_round_value'] * $old_options['auto_update_round_unit'];
	$new_round = $sddl_options['auto_update_round_value'] * $sddl_options['auto_update_round_unit'];
	if ($old_round != $new_round) {
		// 重新生成事件
		$sddl->auto_update_start($new_round);
	}

	$update_sddl_queries = array();
	$update_sddl_text = array();
	$update_sddl_queries[] = update_option('skydrive_directlink_options', $sddl_options);
	$update_sddl_text[] = __('Skydrive Directlink Options', 'skydrive-directlink');
	$i=0;
	$text = '';
	foreach($update_sddl_queries as $update_sddl_query) {
		if($update_sddl_query) {
			$text .= '<font color="green">'.$update_sddl_text[$i].' '.__('Updated', 'skydrive-directlink').'</font><br />';
		}
		$i++;
	}
	if(empty($text)) {
		$text = '<font color="red">'.__('No Skydrive Directlink Option Updated', 'skydrive-directlink').'</font>';
	}
}

$sddl_options = get_option('skydrive_directlink_options');
### --Init Options Start
$default_options = $sddl->default_options;
foreach($default_options as $key=>$value) {
	if ( !isset($sddl_options[$key]) )
	$sddl_options[$key] = $value;
}

### --Init Options End
if (!empty($_POST['testurlsubmitted'])) {
	$sddl_options['test_url'] = $_POST['test_url'];
	if (empty($sddl_options['test_url'])) {
		$info = '<font color="#ff0000">' . __('Please input a correct skydrive url.', 'skydrive-directlink') . '</font>';
	} else {
		$info = '';
//		$sddl->http->ffox_is_http_200('http://pucg3g.blu.livefilestore.com/y1pZIXwiYyRcddEEBAlwFMUo5-N5uFxCybYKJFC3mTqA1758chXP_CFntJYtVDAS7GFUhf1P7soDro1uIu9c4ktdQ/howdy.txt');
		foreach($sddl->get_url_load_info($sddl_options['test_url']) as $k=>$v) {
			$info .= '<strong>' . $k . '</strong> : ' . $v . '<br/>';
		}
	}
}

if (!empty($_POST['infosubmitted'])) {
//	$sddl->update_all_links();
	$sddl->auto_update_now();
}

update_option('skydrive_directlink_options', $sddl_options);

$string_next_update_time = __("Disabled", 'skydrive-directlink');
$next_update_time = wp_next_scheduled('auto_update_sddl_event');
if ($next_update_time) {
	if (!$gmt)
		$next_update_time += get_option('gmt_offset') * 3600;
	// Y年m月j日 H点i分
	$string_next_update_time = date(__('Y-m-j H:i', 'skydrive-directlink'), $next_update_time);
}
$links_count = $sddl->get_links_count();

?>
<!-- Configuration Page -->
<?php if(!empty($text)) { echo '<!-- Last Action --><div id="message" class="updated fade"><p>'.$text.'</p></div>'; } ?>
<div class="wrap" style="max-width: 950px !important;">
<form name="sddlform" method="post"
	action="<?php echo $_SERVER['PHP_SELF']; ?>?page=<?php echo plugin_basename(__FILE__); ?>">
<?php screen_icon(); ?>
<h2><?php echo __('Skydrive Directlink Option Page','skydrive-directlink'); ?></h2>
<input type="hidden" name="submitted" value="1" />
<h3><?php _e('Information','skydrive-directlink') ?></h3>
<p><?php _e('Skydrive directlink plugin can auto update the outer link of a skydrive file.','skydrive-directlink') ?></p>
<p><?php _e('Usage','skydrive-directlink')?> : <strong><font
	color="#FF0000">[skydrive:</font></strong><?php _e('url to skydrive file download page','skydrive-directlink') ?><strong><font
	color="#FF0000">]</font></strong></p>
<p><?php _e('Examples','skydrive-directlink') ?>: <br />
[skydrive:http://***.skydrive.live.com/.../mysong.mp3]<br />
[audio:[skydrive:/music/song1.mp3]]&nbsp;&nbsp;<i><?php _e('(Default url will be auto added)','skydrive-directlink'); ?></i></p>
<h3><?php _e('Options','skydrive-directlink')?></h3>

<table class="form-table">
	<tr>
		<td valign="top" width="20%">
		<div><label for="default_url"><?php _e('Default Url','skydrive-directlink')?>:
		</label></div>
		</td>
		<td>
		<div><input for="default_url" type="text" id="default_url"
			name="default_url"
			value="<?php echo htmlspecialchars($sddl_options['default_url']); ?>"
			size="30" />
		</div>
		</td>
	</tr>
	<tr>
		<td valign="top" width="20%"><?php _e('Cache mode', 'skydrive-directlink'); ?>:</td>
		<td>
			<select name="cache_mode" size="1">
				<option value="1"<?php selected('1', $sddl_options['cache_mode']); ?>><?php _e('Cache and validate', 'skydrive-directlink'); ?></option>
				<option value="2"<?php selected('2', $sddl_options['cache_mode']); ?>><?php _e('Cache only', 'skydrive-directlink'); ?></option>
				<option value="3"<?php selected('3', $sddl_options['cache_mode']); ?>><?php _e('No Cache', 'skydrive-directlink'); ?></option>
			</select>    
		</td>
	</tr>

	<tr>
		<td valign="top" width="20%">
		<label for="time_limit"><?php _e('Page Load Time Limit(Seconds)','skydrive-directlink') ?>:
		</label>
		
		</td>
		<td><input for="time_limit" type="text" id="time_limit"
			name="time_limit"
			value="<?php echo $sddl_options['time_limit']; ?>" size="30" />
		</td>
	<tr>
	<tr>
		<td valign="top" width="20%">
		<label for="auto_update_round_value"><?php _e('Auto Update Cycle','skydrive-directlink') ?>:
		</label>
		
		</td>
		<td><input for="auto_update_round_value" type="text" id="auto_update_round_value"
			name="auto_update_round_value"
			value="<?php echo $sddl_options['auto_update_round_value']; ?>" size="15" />
			<select name="auto_update_round_unit" size="1">
				<option value="0"<?php selected('0', $sddl_options['auto_update_round_unit']); ?>><?php _e('Disabled', 'skydrive-directlink'); ?></option>
				<option value="60"<?php selected('60', $sddl_options['auto_update_round_unit']); ?>><?php _e('Minutes', 'skydrive-directlink'); ?></option>
				<option value="3600"<?php selected('3600', $sddl_options['auto_update_round_unit']); ?>><?php _e('Hours', 'skydrive-directlink'); ?></option>
				<option value="86400"<?php selected('86400', $sddl_options['auto_update_round_unit']); ?>><?php _e('Days', 'skydrive-directlink'); ?></option>
				<option value="604800"<?php selected('604800', $sddl_options['auto_update_round_unit']); ?>><?php _e('Weeks', 'skydrive-directlink'); ?></option>
			</select>
		</td>
	</tr>
</table>
<div class="submit"><input type="submit" name="Submit"
	value="<?php _e('Update options','skydrive-directlink')?>" /></div>
</form>

<form name="sddlform" method="post"
	action="<?php echo $_SERVER['PHP_SELF']; ?>?page=<?php echo plugin_basename(__FILE__); ?>">
<input type="hidden" name="infosubmitted" value="1" />
<h3><?php _e('Skydrive Direct Links Info', 'skydrive-directlink') ?></h3>
<table class="form-table">
	<tr>
		<td valign="top" width="20%">
		<label><?php _e('Links Count:', 'skydrive-directlink'); ?></label>
		</td>
		<td>
		<?php echo $links_count; ?>
		</td>
	<tr>
		<td valign="top" width="20%">
		<label><?php _e('Next Refresh Time: ', 'skydrive-directlink'); ?></label>
		</td>
		<td>
		<div style="float:left"><label><?php echo $string_next_update_time; ?>&nbsp;&nbsp;</label></div>
		<div><input type="submit" value="<?php _e('Update Now', 'skydrive-directlink') ?>" id="btnUpdateNow" name="btnUpdateNow" /></div>
		</td>
	</tr>
	<tr><td>
	</td></tr>
</table>
</form>

<form name="sddlform" method="post"
	action="<?php echo $_SERVER['PHP_SELF']; ?>?page=<?php echo plugin_basename(__FILE__); ?>">
<input type="hidden" name="testurlsubmitted" value="1" />
<h3><?php _e('Skydrive Connection Test', 'skydrive-directlink') ?></h3>
<table class="form-table">
	<tr>
		<td valign="top" width="20%"><label for="test_url"><?php _e('Test URL Address:', 'skydrive-directlink') ?></label></td>
		<td>
		<div><input for="test_url" type="text" id="test_url"
			name="test_url"
			value="<?php echo htmlspecialchars($sddl_options['test_url']); ?>"
			size="50" />
		</td>
	</tr>
</table>
<div style="margin-left: 2em;"><?php echo $info; ?></div>
<div class="submit"><input type="submit" name="Submit"
	value="<?php _e('Start Test', 'skydrive-directlink') ?>" /></div>
</form>
<h5><?php printf(__('Skydrive directlink plugin by %s','skydrive-directlink'), '<a href="http://www.yunjie.org/">Flarefox</a>') ?></h5>
</div>

