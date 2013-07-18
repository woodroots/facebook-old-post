<?php
	if (!defined('ABSPATH') && !defined('WP_UNINSTALL_PLUGIN')) {exit();}
	delete_option('fop_appid');
	delete_option('fop_appsec');
	delete_option('fop_post');
	delete_option('fop_interval');
	wp_clear_scheduled_hook('facebook_old_post_cron');

?>