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

