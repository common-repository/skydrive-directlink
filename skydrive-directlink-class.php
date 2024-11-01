<?php

$wp_sddl_plugin_url = trailingslashit( get_bloginfo('wpurl') ).PLUGINDIR.'/'. dirname( plugin_basename(__FILE__) );
$wp_sddl_plugin_path = ABSPATH . PLUGINDIR . '/'.dirname( plugin_basename(__FILE__) );
require_once( $wp_sddl_plugin_path . '/http_wp.php' );

/* string for match */
define('DOWNLOAD_KEYWORD', '"urls":{"download"');
define('DOWNLOAD_START', ':"');
define('DOWNLOAD_END', '"');
define('FOLDER_START', 'id="dvFolder');
define('FOLDER_END', '</a>');
define('FILE_START', 'id="dvFile');
define('FILE_END', '</a>');
define('NAME_START', '<td valign="middle" class="dvNameColumn"><a href="');
define('NAME_END', '" id="');

define ('FOLDER_TYPE', 'FOLDER');
//define('SDDL_OPTION_NAME', 'skydrive_directlink_options');

class skydrive_directlink_core {
	var $http;
	var $db;
	// The below has been done since 0.5.
	// @todo: add background_update_time for the next version:
	// Add a database table or xml file to store all the skydrive tags appeared in posts and comments.
	// Also actions will be added When edited a post or comment.
	// Auto update all url link in background.
	// When load a post or comment, get direct link from the table and check if the link is correct, if not, update it and the table at once.
	var $default_options = array('default_url'=>'http://cid-955fceff19f67540.skydrive.live.com/', 
	'rough_locate'=>'_download', 'accurate_locate_start'=>"'", 'accurate_locate_end'=>"'", 
	'hook_priority'=>'-1', 'time_limit'=>'20', 
	'test_url'=>"http://cid-955fceff19f67540.skydrive.live.com/self.aspx/.Public/howdy.txt", 
	'cache_mode'=>'2', 'auto_update_round_unit'=>'86400', 'auto_update_round_value'=>'1');

	function skydrive_directlink_core() {
		$this->init_options();
		$this->http = new skydrive_directlink_http();
		$this->db = new skydrive_directlink_db();
	}

	function init_options() {
		$options = get_option('skydrive_directlink_options');
		foreach($this->default_options as $key=>$value) {
			if ( !isset($options[$key]) )
			$options[$key] = $value;
		}
		update_option('skydrive_directlink_options', $options);
	}

	function direct_link_in_content($content) {
		// @todo: 通过修改skydrive标签，来设定skydrive显示的样式（这是加入FileMeta的主要目的）
		if(strpos($content, "[skydrive:")!==false) {
			preg_match_all('/\[skydrive:([)a-zA-Z0-9\/:\.\|\-_\s%#\?\,\+=~@&;]+)\]/', $content, $matches, PREG_SET_ORDER);
			foreach($matches as $match) {
				$filemeta = $this->get_file_meta($match[1]);
				if ($filemeta === false or $filemeta->direct_url == '') {
					$filemeta->direct_url = __('Error: Cannot find any direct link for the skydrive file: ', 'skydrive-directlink') . $filemeta->skydrive_url;
				}
				$content = preg_replace("/\[skydrive:([)a-zA-Z0-9\/:\.\|\-_\s%#\?\,\+=~@&;]+)\]/", $filemeta->direct_url, $content,1);
			}
		}
		return $content;
	}

	function is_absolute_url($source)
	{
		if (strpos($source, "http://") === 0) {
			return true;
		}
		if (strpos($source, "https://") === 0) {
			return true;
		}
		return false;
	}

	function get_links_count() {
		return $this->db->get_links_count();
	}

	function auto_update_now()
	{
		wp_schedule_single_event(time()+60, 'auto_update_sddl_event');
	}

	function auto_update_start()
	{
		$this->auto_update_stop();
		if (!wp_next_scheduled('auto_update_sddl_event')) {
			wp_schedule_event(time(), 'updatesddl', 'auto_update_sddl_event');
		}
	}

	function auto_update_stop()
	{
		wp_clear_scheduled_hook('auto_update_sddl_event');
	}

	function update_all_links()
	{
		$links = $this->db->select_all_items();
		foreach($links as $l) {
			$fm = new skydrive_file_meta($l->skydrive_url);
			// Http Get
			$httpresult = $this->http->search_directlink($fm);
			// update database
			if ($httpresult == false) {
				// TODO: 加入记录位，标志上次更新失败。
				;
			} else {
				$this->db->update_item($httpresult);
			}
		}
	}

	/**
	 * Input a skydrive file url, get the file meta from database or http get.
	 * 
	 * DB records may be updated or inserted.
	 * @since 0.4.0
	 *
	 * Get url by http get.
	 * @since 0.1.0
	 * 
	 * @param string $url 
	 * @param string $method can be "nocache", "cacheonly", "cacheandcheck"
	 * @return false|file meta
	 */
	function get_file_meta($url, $method='') {
		$options = get_option('skydrive_directlink_options');
		if ($method === ''){
			switch (intval($options['cache_mode'])){
				case 3:	$method = "nocache"; break;
				case 1: $method = "cacheandcheck"; break;
				case 2: $method = "cacheonly";break;
			}
		}
		
		if ( $this->is_absolute_url($url) === false ) {
			$url = $options['default_url'] . $url;
		}

		while(strpos($url, '//') !== false) {
			$url = str_replace('//', '/', $url);
		}
		if (substr($url, 0, 6) === 'http:/')
			$url = "http://" . substr($url, 6);
		else if (substr($url, 0, 7) === 'https:/')
			$url = "https://" . substr($url, 7);
			
		// 从2011年6月22日起，微软再次改变链接格式
		// 需要去掉#号
		$wen = strpos($url, "?");
		$jing = strpos($url, "#");
		if ($jing != FALSE && $wen != FALSE) {
			$url = substr_replace($url, "", $wen+1, $jing-$wen);
		}

		$filemeta = new skydrive_file_meta($url, $options);
		// Check db
		$dbresult = $this->db->select_item($filemeta);
		if ($dbresult !== false){
			if ($method === "cacheonly") {
				return $dbresult;
			} elseif ($method === "cacheandcheck") {
				if ( $this->http->ffox_is_http_200($dbresult->direct_url) ) {
					return $dbresult;
				}
			}
			$dbresult = new skydrive_file_meta($url, $options);
			foreach($dbresult as $k=>$v) {
				$dbresult->$k = $filemeta->$k;
			}
		}

		// Http Get
		$httpresult = $this->http->search_directlink($filemeta);
//		echo $filemeta->direct_url;
		
		// update database
		if ($httpresult == false) {
			return false;
		} elseif ($dbresult == false) {
			$this->db->insert_item($httpresult);
		} elseif ($dbresult->direct_url !== $httpresult->direct_url) {
			$this->db->update_item($httpresult);
		}

		return $httpresult;
	}
	
	function get_url_load_info($url) {
		$result = array();
		$starttime = gettimeofday();
		$filemeta = $this->get_file_meta($url, "nocache");
		$endtime = gettimeofday();
		$period = ($endtime['sec'] - $starttime['sec']) * 1000000 + $endtime['usec'] - $starttime['usec'];
		$result[__('Load Time(no cache)','skydrive-directlink')] = sprintf('%.3f',$period / 1000000) . 
			__(' seconds','skydrive-directlink');
		$starttime = gettimeofday();
		$filemeta1 = $this->get_file_meta($url, "cacheandcheck");
		$endtime = gettimeofday();
		$period = ($endtime['sec'] - $starttime['sec']) * 1000000 + $endtime['usec'] - $starttime['usec'];
		$result[__('Load Time(cache and validate)','skydrive-directlink')] = sprintf('%.3f',$period / 1000000) .
			 __(' seconds','skydrive-directlink');
		$starttime = gettimeofday();
		$filemeta2 = $this->get_file_meta($url,"cacheonly");
		$endtime = gettimeofday();
		$period = ($endtime['sec'] - $starttime['sec']) * 1000000 + $endtime['usec'] - $starttime['usec'];
		$result[__('Load Time(cache only)','skydrive-directlink')] = sprintf('%.3f',$period / 1000000) .
			 __(' seconds','skydrive-directlink');
		
		$result[__('Direct Link', 'skydrive-directlink')] = 
			($filemeta->direct_url=='') ? __('Cannot find.','skydrive-directlink') : 
			'<a href="' . $filemeta->direct_url . '">' . $filemeta->name . '</a>';
		$result[__('File Size', 'skydrive-directlink')] = $filemeta->size/1000 . ' KB';
		$result[__('File Type', 'skydrive-directlink')] = $filemeta->type;
		return $result;
	}
}

class skydrive_file_meta {
	var $ispath;
	var $name = '';
	var $skydrive_url;
	var $direct_url = '';
	var $size = 0;
	var $dlcount;
	var $type = '';
	var $lmt;	// last modified time

	function skydrive_file_meta($purl) {
		$this->skydrive_url = $purl;
	}

}

class skydrive_directlink_db {
	var $db_table_name = '';

	function skydrive_directlink_db() {
		global $wpdb;
		if (empty($wpdb)) {
			return false;
		}

		$this->db_table_name = $wpdb->prefix . "skydrive_directlink";
		// Check whether db table exists
		if (!$this->is_db_installed()) {
			$this->install();
		}
		// Check whether updatetime column exists
		// since 0.5
		if (!$this->is_updatetime_exists()) {
			$this->add_column_updatetime();
		}
	}

	function is_updatetime_exists() {
		global $wpdb;
		$query = $wpdb->prepare("describe `$this->db_table_name` updatetime");
		$result = $wpdb->get_results($query);
		if ($result[0] == NULL)
			return false;
		else
			return true;
	}

	function add_column_updatetime() {
		global $wpdb;
		$query = $wpdb->prepare("alter table `$this->db_table_name` ADD updatetime TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, "
		 . "CHANGE file_type file_type varchar(100) character set utf8 not null");
		$result = $wpdb->query($query);
	}
	
	function update_item($filemeta) {
		global $wpdb;
		
		$query = $wpdb->prepare("update `$this->db_table_name` set `direct_url`=%s, `file_name`=%s, `file_type`=%s, `file_size`=%d, `updatetime`=%s where `skydrive_url`=%s",  
			$filemeta->direct_url, $filemeta->name, $filemeta->type, $filemeta->size, current_time('mysql'), $filemeta->skydrive_url);
		
		$sql = $wpdb->query($query);
		if ($sql !== 1)
			return false;
		else
			return true;
	}

	function insert_item($filemeta) {
		global $wpdb;
		
		// @todo add other file metas
		$query = $wpdb->prepare("insert into `$this->db_table_name`
			(`disk_type`,`skydrive_url`, `direct_url`, `file_name`, `file_type`, `file_size`) 
			values(%s, %s, %s, %s, %s, %d)", 
			'SKYDRIVE', $filemeta->skydrive_url, $filemeta->direct_url, 
			$filemeta->name, $filemeta->type, $filemeta->size);
		$sql = $wpdb->query($query);
		if ($sql !== 1)
			return false;
		else
			return true;
	}

	function select_item($filemeta) {
		global $wpdb;
		$query = $wpdb->prepare("select * from `$this->db_table_name` where `skydrive_url`=%s",
					$filemeta->skydrive_url);
		$sql = $wpdb->get_results($query);
		if( sizeof($sql) === 0 ) {
			return false;
		}
		else {
			$filemeta->direct_url = $sql[0]->direct_url;
			$filemeta->name = $sql[0]->file_name;
			$filemeta->size = intval($sql[0]->file_size);
			$filemeta->type = $sql[0]->file_type;
			return $filemeta;
		}
	}

	function select_all_items() {
		global $wpdb;
		$query = $wpdb->prepare("select * from `$this->db_table_name`");
		$result = $wpdb->get_results($query);
		return $result;
	}

	function get_links_count() {
		global $wpdb;
		$query = $wpdb->prepare("select count(*) as linkcount from `$this->db_table_name`");
		$result = $wpdb->get_results($query);
		return $result[0]->linkcount;
	}

	function is_db_installed() {
		global $wpdb;
		$query = $wpdb->prepare("select * from `". $this->db_table_name. "` limit 1");
		@mysql_query($query, $wpdb->dbh);
		if ( @mysql_errno() == 1146 ) {
			return false;
		} else if (@mysql_errno() != 0) {
			echo @mysql_error();
			return false;
		}
		return true;
	}

	function install() {
		global $wpdb;
		$query = $wpdb->prepare("CREATE TABLE IF NOT EXISTS `".$this->db_table_name."` (
						`id` bigint(20) NOT NULL AUTO_INCREMENT,
						`disk_type` varchar(20) character set utf8 NOT NULL,
						`skydrive_url` varchar(2048) character set utf8 NOT NULL,
						`direct_url` varchar(2048) character set utf8 NOT NULL,
						`file_name` varchar(1024) character set utf8 NOT NULL,
						`file_type` varchar(100) character set utf8 NOT NULL,
						`file_size` bigint(20) NOT NULL,
						`updatetime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
						PRIMARY KEY (`id`),
						KEY `skydrive_url` (`skydrive_url`(255)), 
						KEY `file_type` (`file_type`),
						KEY `disk_type` (`disk_type`)
						) AUTO_INCREMENT=1 ;");

		$result = !($wpdb->query($query));
		return $result;
	}

	/**
	* 从0.4.50开始，卸载时不删除数据库表
	*/
	function uninstall() {
/*		global $wpdb;
		$query = $wpdb->prepare("DROP TABLE IF EXISTS `".$this->db_table_name . "`");
		$result = !($wpdb->query($query));
		return $result;
*/
	}
}

class skydrive_directlink_http {

	function search_directlink($filemeta)
	{
		$options = get_option('skydrive_directlink_options');
		$timelimit = $options['time_limit'];
		@set_time_limit( $timelimit );

		$options = get_option('skydrive_directlink_options');

		$url = $filemeta->skydrive_url;

		// 从0.4.20开始，定位字符串直接写入程序
		$keyword = DOWNLOAD_KEYWORD;

//		$body = $this->ffox_http_part_request($url, $keyword);
		$http = new SDDL_WP_Http();
		$body = $http->request($url);
	/*	foreach($body as $k=>$v) {
			foreach($v as $kk=>$vv)
				foreach($vv as $kkk=>$vvv)
			echo $kkk . " " . $vvv . "<br/>";
		}*/
		$body = $body['body'];
//		$file=fopen('c:/111.txt', 'a');
//		fwrite($file, $body);
//		fclose($file);

		$pos = strpos($body, $keyword);
		$body = substr($body, $pos);

		$startstr = DOWNLOAD_START;
		$endstr = DOWNLOAD_END;
		$start = strpos($body, $startstr);
		if ($start == false)
			return false;
		$start += strlen($startstr);
		$end = strpos($body, $endstr, $start);
		if ($end == false or $end<=$start)
			return false;

		$directlink = substr($body, $start, $end-$start);
//		preg_match_all('/&#(\d+);/i', $directlink, $matches, PREG_SET_ORDER);
//		foreach($matches as $match) {
//			$ch = chr($match[1]);
//			$directlink = preg_replace("/&#(\d+);/i", $ch, $directlink, 1);
//		}
		$directlink = str_replace('\/', '/', $directlink);

		// 去掉文件名后的?
		$delstr = '?';
		$pos = stripos($directlink, $delstr);

		if ($pos >=0 )
			$directlink = substr($directlink, 0, $pos);

		if ($directlink != '') {
			$filemeta->direct_url = $directlink;
			$filemeta->name = urldecode(substr($filemeta->direct_url, strrpos($filemeta->direct_url, '/') + 1));
			$filemeta = $this->ffox_get_http_filemeta($filemeta);
			return $filemeta;
		}
		else
			return false;
	}

	function ffox_is_http_200($url)
	{
		$http = new SDDL_WP_Http();
		$par['maxresplength'] = 1024;
		$resp = $http->request($url, $par);
/*		foreach($resp as $k=>$v) {
			echo $k . ":" . "<br/>";
			foreach($v as $kk=>$vv)
				echo "  " . $kk . " : " . $vv. "<br/>";
		}
*/
		if (!is_array($resp))
			return false;
		if (empty($resp['response']['code']))
			 return false;
		if ($resp['response']['code'] != 200 && $resp['response']['code'] != 206)
			 return false;
		return true;
}

/**
* This function is replaced with WP_Http::request since V0.6
*/
/*	function ffox_http_part_request($url, $keyword)
	{
		$options = get_option('skydrive_directlink_options');
		$timelimit = $options['time_limit'];
		@set_time_limit( $timelimit );

		$args = array();
		$defaults = array(
			'method' => 'GET', 'timeout' => 5,
			'redirection' => 5, 'httpversion' => '1.0',
			'blocking' => true,
			'headers' => array(), 'body' => null, 'cookies' => array()
		);

		$r = wp_parse_args( $args, $defaults );

		if ( isset($r['headers']['User-Agent']) ) {
			$r['user-agent'] = $r['headers']['User-Agent'];
			unset($r['headers']['User-Agent']);
		} else if( isset($r['headers']['user-agent']) ) {
			$r['user-agent'] = $r['headers']['user-agent'];
			unset($r['headers']['user-agent']);
		}

		// Construct Cookie: header if any cookies are set
		SDDL_WP_Http::buildCookieHeader( $r );

		$iError = null; // Store error number
		$strError = null; // Store error string

		$arrURL = parse_url($url);

		$fsockopen_host = $arrURL['host'];

		$secure_transport = false;

		if ( ! isset( $arrURL['port'] ) ) {
			if ( ( $arrURL['scheme'] == 'ssl' || $arrURL['scheme'] == 'https' ) && extension_loaded('openssl') ) {
				$fsockopen_host = "ssl://$fsockopen_host";
				$arrURL['port'] = 443;
				$secure_transport = true;
			} else {
				$arrURL['port'] = 80;
			}
		}

		// There are issues with the HTTPS and SSL protocols that cause errors that can be safely
		// ignored and should be ignored.
		if ( true === $secure_transport )
		$error_reporting = error_reporting(0);

		$startDelay = time();

		$proxy = new SDDL_WP_Http_Proxy();

		if ( !defined('WP_DEBUG') || ( defined('WP_DEBUG') && false === WP_DEBUG ) ) {
			if ( $proxy->is_enabled() && $proxy->send_through_proxy( $url ) )
			$handle = @fsockopen( $proxy->host(), $proxy->port(), $iError, $strError, $r['timeout'] );
			else
			$handle = @fsockopen( $fsockopen_host, $arrURL['port'], $iError, $strError, $r['timeout'] );
		} else {
			if ( $proxy->is_enabled() && $proxy->send_through_proxy( $url ) )
			$handle = fsockopen( $proxy->host(), $proxy->port(), $iError, $strError, $r['timeout'] );
			else
			$handle = fsockopen( $fsockopen_host, $arrURL['port'], $iError, $strError, $r['timeout'] );
		}

		$endDelay = time();

		// If the delay is greater than the timeout then fsockopen should't be used, because it will
		// cause a long delay.
		$elapseDelay = ($endDelay-$startDelay) > $r['timeout'];
		if ( true === $elapseDelay )
		add_option( 'disable_fsockopen', $endDelay, null, true );

		if ( false === $handle )
		return false;

		stream_set_timeout($handle, $r['timeout'] );

		if ( $proxy->is_enabled() && $proxy->send_through_proxy( $url ) ) //Some proxies require full URL in this field.
		$requestPath = $url;
		else
		$requestPath = $arrURL['path'] . ( isset($arrURL['query']) ? '?' . $arrURL['query'] : '' );

		if ( empty($requestPath) )
		$requestPath .= '/';

		$strHeaders = strtoupper($r['method']) . ' ' . $requestPath . ' HTTP/' . $r['httpversion'] . "\r\n";

		if ( $proxy->is_enabled() && $proxy->send_through_proxy( $url ) )
		$strHeaders .= 'Host: ' . $arrURL['host'] . ':' . $arrURL['port'] . "\r\n";
		else
		$strHeaders .= 'Host: ' . $arrURL['host'] . "\r\n";

		if ( isset($r['user-agent']) )
		$strHeaders .= 'User-agent: ' . $r['user-agent'] . "\r\n";

		if ( is_array($r['headers']) ) {
			foreach ( (array) $r['headers'] as $header => $headerValue )
			$strHeaders .= $header . ': ' . $headerValue . "\r\n";
		} else {
			$strHeaders .= $r['headers'];
		}

		if ( $proxy->use_authentication() )
		$strHeaders .= $proxy->authentication_header() . "\r\n";

		$strHeaders .= "\r\n";

		if ( ! is_null($r['body']) )
		$strHeaders .= $r['body'];

		fwrite($handle, $strHeaders);

		if ( ! $r['blocking'] ) {
			fclose($handle);
			return array( 'headers' => array(), 'body' => '', 'response' => array('code' => false, 'message' => false), 'cookies' => array() );
		}

		$strResponse = '';
		$bFounded = false;
		while ( ! feof($handle) ) {
			$strResponse .= fread($handle, 4096);
			if ($bFounded == true)
				break;
			if (strrpos($strResponse, $keyword) != false)
				$bFounded = true;
		}
		fclose($handle);

		if ($bFounded == true) {
			return $strResponse;
		} else {
			return false;
		}
	}
*/

	function ffox_get_http_filemeta($filemeta)
	{
		$url = $filemeta->direct_url;
		$options = get_option('skydrive_directlink_options');
		$timelimit = $options['time_limit'];
		@set_time_limit( $timelimit );

		$http = new SDDL_WP_Http();
		$par['maxresplength'] = 1024;
		$arrHeader = $http->request($url, $par);
		if ( (int)$arrHeader['response']['code'] != 200 && (int)$arrHeader['response']['code'] != 206)
			return $filemeta;
		
		$filemeta->size = (int)$arrHeader['headers']['content-length'];
		$filemeta->type = $arrHeader['headers']['content-type'];
		$filemeta->lmt = $arrHeader['headers']['last-modified'];

		return $filemeta;
	}

/*
	function ffox_get_http_filemeta($filemeta)
	{
		$url = $filemeta->direct_url;
		$options = get_option('skydrive_directlink_options');
		$timelimit = $options['time_limit'];
		@set_time_limit( $timelimit );

		$args = array();
		$defaults = array(
			'method' => 'GET', 'timeout' => 5,
			'redirection' => 5, 'httpversion' => '1.0',
			'blocking' => true,
			'headers' => array(), 'body' => null, 'cookies' => array()
		);

		$r = wp_parse_args( $args, $defaults );

		if ( isset($r['headers']['User-Agent']) ) {
			$r['user-agent'] = $r['headers']['User-Agent'];
			unset($r['headers']['User-Agent']);
		} else if( isset($r['headers']['user-agent']) ) {
			$r['user-agent'] = $r['headers']['user-agent'];
			unset($r['headers']['user-agent']);
		}

		// Construct Cookie: header if any cookies are set
		SDDL_WP_Http::buildCookieHeader( $r );

		$iError = null; // Store error number
		$strError = null; // Store error string

		$arrURL = parse_url($url);

		$fsockopen_host = $arrURL['host'];

		$secure_transport = false;

		if ( ! isset( $arrURL['port'] ) ) {
			if ( ( $arrURL['scheme'] == 'ssl' || $arrURL['scheme'] == 'https' ) && extension_loaded('openssl') ) {
				$fsockopen_host = "ssl://$fsockopen_host";
				$arrURL['port'] = 443;
				$secure_transport = true;
			} else {
				$arrURL['port'] = 80;
			}
		}

		// There are issues with the HTTPS and SSL protocols that cause errors that can be safely
		// ignored and should be ignored.
		if ( true === $secure_transport )
		$error_reporting = error_reporting(0);

		$startDelay = time();

		$proxy = new SDDL_WP_Http_Proxy();

		if ( !defined('WP_DEBUG') || ( defined('WP_DEBUG') && false === WP_DEBUG ) ) {
			if ( $proxy->is_enabled() && $proxy->send_through_proxy( $url ) )
				$handle = @fsockopen( $proxy->host(), $proxy->port(), $iError, $strError, $r['timeout'] );
			else
				$handle = @fsockopen( $fsockopen_host, $arrURL['port'], $iError, $strError, $r['timeout'] );
		} else {
			if ( $proxy->is_enabled() && $proxy->send_through_proxy( $url ) )
				$handle = fsockopen( $proxy->host(), $proxy->port(), $iError, $strError, $r['timeout'] );
			else
				$handle = fsockopen( $fsockopen_host, $arrURL['port'], $iError, $strError, $r['timeout'] );
		}

		$endDelay = time();

		// If the delay is greater than the timeout then fsockopen should't be used, because it will
		// cause a long delay.
		$elapseDelay = ($endDelay-$startDelay) > $r['timeout'];
		if ( true === $elapseDelay )
			add_option( 'disable_fsockopen', $endDelay, null, true );

		if ( false === $handle )
			return $filemeta;

		stream_set_timeout($handle, $r['timeout'] );

		if ( $proxy->is_enabled() && $proxy->send_through_proxy( $url ) ) //Some proxies require full URL in this field.
			$requestPath = $url;
		else
			$requestPath = $arrURL['path'] . ( isset($arrURL['query']) ? '?' . $arrURL['query'] : '' );

		if ( empty($requestPath) )
			$requestPath .= '/';

		$strHeaders = strtoupper($r['method']) . ' ' . $requestPath . ' HTTP/' . $r['httpversion'] . "\r\n";

		if ( $proxy->is_enabled() && $proxy->send_through_proxy( $url ) )
			$strHeaders .= 'Host: ' . $arrURL['host'] . ':' . $arrURL['port'] . "\r\n";
		else
			$strHeaders .= 'Host: ' . $arrURL['host'] . "\r\n";

		if ( isset($r['user-agent']) )
			$strHeaders .= 'User-agent: ' . $r['user-agent'] . "\r\n";

		if ( is_array($r['headers']) ) {
			foreach ( (array) $r['headers'] as $header => $headerValue )
				$strHeaders .= $header . ': ' . $headerValue . "\r\n";
		} else {
			$strHeaders .= $r['headers'];
		}

		if ( $proxy->use_authentication() )
			$strHeaders .= $proxy->authentication_header() . "\r\n";

		$strHeaders .= "\r\n";

		if ( ! is_null($r['body']) )
			$strHeaders .= $r['body'];

		fwrite($handle, $strHeaders);

		if ( ! $r['blocking'] ) {
			fclose($handle);
			return $filemeta;
		}

		$strResponse = '';
		$meta_count = 0;
//		while ( ! feof($handle) ) {
			$strResponse .= fread($handle, 1024);
			$process = SDDL_WP_Http::processResponse($strResponse);
			$arrHeader = SDDL_WP_Http::processHeaders($process['headers']);
//		}
		fclose($handle);

//foreach($arrHeader['headers'] as $k=>$v)
//echo $k . ":" . $v . "<br/>";
		
		if ( (int)$arrHeader['response']['code'] != 200 )
			return $filemeta;
		
		$filemeta->size = (int)$arrHeader['headers']['content-length'];
		$filemeta->type = $arrHeader['headers']['content-type'];
		$filemeta->lmt = $arrHeader['headers']['last-modified'];

		return $filemeta;
	}
*/
	function echo_response($response) {
		foreach($response as $k=>$v) {
			if ($k == 'headers') {
				echo 'headers:<br/>';
				foreach($v as $kk=>$vv) {
					echo $kk . ' : ' . $vv . '<br/>';
				}
			} else if ($k == 'body') {
				echo 'body:<br/>';
				foreach($v as $kk=>$vv) {
					echo $kk . ' : ' . $vv . '<br/>';
				}
			} else if ($k == 'response') {
				echo 'response:<br/>';
				foreach($v as $kk=>$vv) {
					echo $kk . ' : ' . $vv . '<br/>';
				}
			}
			if ($k != 'body')
			echo $k . ' : ' . $v . '<br/>';
		}
	}
}

/*
filemeta:
	var $ispath;
	var $name = '';
	var $skydrive_url;
	var $direct_url = '';
	var $size = 0;
	var $dlcount;
	var $type = '';
	var $lmt;	// last modified time
将
*/

class skydrive_files_list {
	var $url;

	function list_one_folder($folder_url) {
		$result = array();
		$http = new SDDL_WP_Http();
		$resp = $http->request($folder_url);
		// 先找出所有文件夹
		$body = $resp['body'];
//echo $body;
		$start = FOLDER_START;
		$pos = strpos($body, $start);
		while($pos > 0) {
			$body = substr ($body, $pos + strlen($start));
			$namestart = NAME_START;
			$nameend = NAME_END;
			$p1 = strpos($body, $namestart);
			if ($p1 != false) {
				$p1 += strlen($namestart);
				$p2 = strpos($body, $nameend, $p1);
				if ($p2 != false && $p2 > $p1) {
					$link = substr($body ,$p1, $p2-$p1);
					preg_match_all('/&#(\d+);/i', $link, $matches, PREG_SET_ORDER);
					foreach($matches as $match) {
						$ch = chr($match[1]);
						$link = preg_replace("/&#(\d+);/i", $ch, $link, 1);
					}
					// 为$link生成filemeta并加入数组
					$fm = new skydrive_file_meta($link);
					$fm->ispath = true;
					$fm->type = FOLDER_TYPE;
					$fm->name = urldecode(substr($fm->skydrive_url, strrpos($fm->skydrive_url, '/') + 1));
					$result[$link] = $fm;
				}
			}
			// find next folder
			$pos = strpos($body, $start);
		}

		// 再找出所有文件
		$body = $resp['body'];
		$start = FILE_START;
		$pos = strpos($body, $start);
		while($pos > 0) {
			$body = substr ($body, $pos + strlen($start));
			$namestart = NAME_START;
			$nameend = NAME_END;
			$p1 = strpos($body, $namestart);
			if ($p1 != false) {
				$p1 += strlen($namestart);
				$p2 = strpos($body, $nameend, $p1);
				if ($p2 != false && $p2 > $p1) {
					$link = substr($body ,$p1, $p2-$p1);
					preg_match_all('/&#(\d+);/i', $link, $matches, PREG_SET_ORDER);
					foreach($matches as $match) {
						$ch = chr($match[1]);
						$link = preg_replace("/&#(\d+);/i", $ch, $link, 1);
					}
					// 获取$link直链，生成filemeta并加入数组
					$fm = new skydrive_file_meta($link);
					$fm->ispath = false;
					$http = new skydrive_directlink_http();
					$fm = $http->search_directlink($fm);
					
					// update database
					if ($fm != false) {
						$result[$link] = $fm;
					}
				}
			}
			// find next folder
			$pos = strpos($body, $start);
		}
		// 返回
		return $result;
	}

	/*
	* 递归列出某个链接下的所有子目录
	*/
	function list_all_sub($parent_url) {
		// 
	}
}
?>
