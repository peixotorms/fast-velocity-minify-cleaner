<?php
/*
Plugin Name: Fast Velocity Minify Cleaner
Plugin URI: http://fastvelocity.com
Description: This will completely delete the Fast Velocity Minify plugin, cronjobs, plugin settings, and leftover cache directories. Please call it from https://yoursite.com/?fvm_cleaner=1 
Author: Raul Peixoto
Author URI: http://fastvelocity.com
Version: 1.0.0
License: GPL2

------------------------------------------------------------------------
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/


# don't run on wp-admin
if(!is_admin()) {
	
	# only run on specific cleanup url
	# http://yoursite.com/?fvm_cleaner=1 
	if(isset($_GET['fvm_cleaner']) && is_numeric($_GET['fvm_cleaner'])) {
		add_action( 'template_redirect', 'fvm_cleaner');
	}
	
}


function fvm_cleaner(){
    if(isset($_GET['fvm_cleaner']) && is_numeric($_GET['fvm_cleaner'])) {
	    	
	    	# purge all caches
	    	fvm_cleaner_purge_caches();
	    
		# what to do
		$level = filter_var($_GET['fvm_cleaner'], FILTER_SANITIZE_NUMBER_INT);
		
		# https://yoursite.com/?fvm_cleaner=1
		# https://yoursite.com/?fvm_cleaner=3
		if($level == 1 || $level == 3) {
			
			echo "Attempting to delete leftover directories...<br />";
		
			# get plugin and cache directories
			$delete_files = array(
					WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'cache'. DIRECTORY_SEPARATOR .'fvm', 
					WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'uploads'. DIRECTORY_SEPARATOR .'fvm', 
					WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'plugins'. DIRECTORY_SEPARATOR .'fast-velocity-minify'
				);
			
			# delete directories
			$done = 0;
			foreach ($delete_files as $delete) {
				if(is_dir($delete)) {
					fvm_cleaner_rrmdir($delete, true);
					$done++;
					echo "We have deleted the '$delete' directory.<br />";
				}
			}
			
			if($done == 0) {
				echo "There was nothing to delete.<br />";
			}
		
		}
		
		# https://yoursite.com/?fvm_cleaner=1
		if($level == 1) {
			
			echo "Attempting to delete leftover settings...<br />";
		
			# delete all fastvelocity settings from the database
			global $wpdb;
			$done = 0;
			$plugopt = $wpdb->get_results("SELECT option_name FROM $wpdb->options WHERE option_name LIKE 'fastvelocity\_%'");
			if(is_array($plugopt) && count($plugopt) > 0) {
				foreach( $plugopt as $option ) { delete_option( $option->option_name ); }
				$done++;
				echo "We have deleted ". count($plugopt) ." fastvelocity settings from the options table.<br />";
			}
			
			if($done == 0) {
				echo "There was nothing to delete.<br />";
			}
		
		}
		
		
		echo "---<br />";
		
		# https://yoursite.com/?fvm_cleaner=1
		# https://yoursite.com/?fvm_cleaner=2
		if($level == 1 || $level == 2) {
			
			# get all cronjobs into an array
			echo "Attempting to fetch all cron jobs from the options table...<br />";
			$cron = _get_cron_array();
			
			# fail if not an array
			if (!is_array($cron)) {
				echo "Error: The cron option on the options table is either empty, doesn't exist or too large to be fetched!";
				exit();
			}
			
			# Checker
			$start = count($cron);
					
			# keep only the ones that don't match the cron name
			$updated = array_filter($cron, function($v){return !array_key_exists("fastvelocity_purge_old_cron_event",$v);});
			
			# Checker
			$end = count($updated);
			
			# if same, then don't update
			if ($start == $end) {
				
				echo "We found $end cronjobs on your database. <br />";
				echo "There are no FVM related cron jobs to delete! <br />";
				echo "We left those other $end cronjobs alone. <br />";
				echo "Feel free to run this again or delete the plugin via wp-admin.<br />";
				echo "---<br />";
				
			} else {
			
				# resave new cron option
				_set_cron_array($updated);
				echo "You had $start cronjobs on your database in total.<br />";
				echo "We deleted ". ($start - $end)." leftover cron jobs from FVM.<br />";
				echo "---<br />";
				echo "You now have $end cronjobs on your database.<br />";
				echo "Feel free to run this again or delete the plugin via wp-admin.<br />";
				echo "---<br />";
				
			}
		}
	
		# purge all caches
	    	fvm_cleaner_purge_caches();
		
		# stop here
		echo "All done!<br />";
		echo "---<br />";
		exit();

	}
}



# remove all cache files
function fvm_cleaner_rrmdir($path) {
	clearstatcache();
	if(is_dir($path)) {
		$i = new DirectoryIterator($path);
		foreach($i as $f){
			if($f->isFile()){ unlink($f->getRealPath());
			} else if(!$f->isDot() && $f->isDir()){
				fvm_cleaner_rrmdir($f->getRealPath());
				if(is_dir($f->getRealPath())) { rmdir($f->getRealPath()); }
			}
		}
		
		# self
		if(is_dir($path)) { rmdir($path); }
	}
}


# purge supported hosting and plugins
function fvm_cleaner_purge_caches(){
	
# third party plugins
	
# Purge all W3 Total Cache
if (function_exists('w3tc_pgcache_flush')) {
	w3tc_pgcache_flush();
	return __('<div class="notice notice-info is-dismissible"><p>All caches on <strong>W3 Total Cache</strong> have been purged.</p></div>');
}

# Purge WP Super Cache
if (function_exists('wp_cache_clear_cache')) {
	wp_cache_clear_cache();
	return __('<div class="notice notice-info is-dismissible"><p>All caches on <strong>WP Super Cache</strong> have been purged.</p></div>');
}

# Purge WP Rocket
if (function_exists('rocket_clean_domain')) {
	rocket_clean_domain();
	return __('<div class="notice notice-info is-dismissible"><p>All caches on <strong>WP Rocket</strong> have been purged.</p></div>');
}

# Purge Cachify
if (function_exists('cachify_flush_cache')) {
	cachify_flush_cache();
	return __('<div class="notice notice-info is-dismissible"><p>All caches on <strong>Cachify</strong> have been purged.</p></div>');
}

# Purge Comet Cache
if ( class_exists("comet_cache") ) {
	comet_cache::clear();
	return __('<div class="notice notice-info is-dismissible"><p>All caches on <strong>Comet Cache</strong> have been purged.</p></div>');
}

# Purge Zen Cache
if ( class_exists("zencache") ) {
	zencache::clear();
	return __('<div class="notice notice-info is-dismissible"><p>All caches on <strong>Comet Cache</strong> have been purged.</p></div>');
}

# Purge LiteSpeed Cache 
if (class_exists('LiteSpeed_Cache_Tags')) {
	LiteSpeed_Cache_Tags::add_purge_tag('*');
	return __('<div class="notice notice-info is-dismissible"><p>All caches on <strong>LiteSpeed Cache</strong> have been purged.</p></div>');
}

# Purge Hyper Cache
if (class_exists( 'HyperCache' )) {
    do_action( 'autoptimize_action_cachepurged' );
    return __( '<div class="notice notice-info is-dismissible"><p>All caches on <strong>HyperCache</strong> have been purged.</p></div>');
}

# purge cache enabler
if ( has_action('ce_clear_cache') ) {
    do_action('ce_clear_cache');
	return __( '<div class="notice notice-info is-dismissible"><p>All caches on <strong>Cache Enabler</strong> have been purged.</p></div>');
}

# purge wpfc
if (function_exists('wpfc_clear_all_cache')) {
	wpfc_clear_all_cache(true);
}

# add breeze cache purge support
if (class_exists("Breeze_PurgeCache")) {
	Breeze_PurgeCache::breeze_cache_flush();
	return __( '<div class="notice notice-info is-dismissible"><p>All caches on <strong>Breeze</strong> have been purged.</p></div>');
}


# swift
if (class_exists("Swift_Performance_Cache")) {
	Swift_Performance_Cache::clear_all_cache();
	return __( '<div class="notice notice-info is-dismissible"><p>All caches on <strong>Swift Performance</strong> have been purged.</p></div>');
}


# hosting companies

# Purge SG Optimizer (Siteground)
if (function_exists('sg_cachepress_purge_cache')) {
	sg_cachepress_purge_cache();
	return __('<div class="notice notice-info is-dismissible"><p>All caches on <strong>SG Optimizer</strong> have been purged.</p></div>');
}

# Purge Godaddy Managed WordPress Hosting (Varnish + APC)
if (class_exists('WPaaS\Plugin') && method_exists( 'WPass\Plugin', 'vip' )) {
	fastvelocity_godaddy_request('BAN');
	return __('<div class="notice notice-info is-dismissible"><p>A cache purge request has been sent to <strong>Go Daddy Varnish</strong></p></div>');
}


# Purge WP Engine
if (class_exists("WpeCommon")) {
	if (method_exists('WpeCommon', 'purge_memcached')) { WpeCommon::purge_memcached(); }
	if (method_exists('WpeCommon', 'purge_varnish_cache')) { WpeCommon::purge_varnish_cache(); }

	if (method_exists('WpeCommon', 'purge_memcached') || method_exists('WpeCommon', 'purge_varnish_cache')) {
		return __('<div class="notice notice-info is-dismissible"><p>A cache purge request has been sent to <strong>WP Engine</strong></p></div>');
	}
}


# Purge Kinsta
global $kinsta_cache;
if ( isset($kinsta_cache) && class_exists('\\Kinsta\\CDN_Enabler')) {
	if (!empty( $kinsta_cache->kinsta_cache_purge)){
		$kinsta_cache->kinsta_cache_purge->purge_complete_caches();
		return __('<div class="notice notice-info is-dismissible"><p>A cache purge request has been sent to <strong>Kinsta</strong></p></div>');
	}
}

# Purge Pagely
if ( class_exists( 'PagelyCachePurge' ) ) {
	$purge_pagely = new PagelyCachePurge();
	$purge_pagely->purgeAll();
	return __('<div class="notice notice-info is-dismissible"><p>A cache purge request has been sent to <strong>Pagely</strong></p></div>');
}

# Purge Pressidum
if (defined('WP_NINUKIS_WP_NAME') && class_exists('Ninukis_Plugin')){
	$purge_pressidum = Ninukis_Plugin::get_instance();
	$purge_pressidum->purgeAllCaches();
	return __('<div class="notice notice-info is-dismissible"><p>A cache purge request has been sent to <strong>Pressidium</strong></p></div>');
}

# Purge Savvii
if (defined( '\Savvii\CacheFlusherPlugin::NAME_DOMAINFLUSH_NOW')) {
	$purge_savvii = new \Savvii\CacheFlusherPlugin();
	if ( method_exists( $plugin, 'domainflush' ) ) {
		$purge_savvii->domainflush();
		return __('<div class="notice notice-info is-dismissible"><p>A cache purge request has been sent to <strong>Savvii</strong></p></div>');
	}
}

# Purge Pantheon Advanced Page Cache plugin
if(function_exists('pantheon_wp_clear_edge_all')) {
	pantheon_wp_clear_edge_all();
}

# last actions, php and wordpress in this order

# flush opcache if available
if(function_exists('opcache_reset')) {
	opcache_reset();
	return __( '<div class="notice notice-info is-dismissible"><p>All caches on <strong>OPCache</strong> have been purged.</p></div>'
}

# wordpress default cache
if (function_exists('wp_cache_flush')) {
	wp_cache_flush();
}

}

