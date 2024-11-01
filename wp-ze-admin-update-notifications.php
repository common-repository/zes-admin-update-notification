<?php
/*
Plugin Name: Ze's admin update notifications
Plugin URI: https://nbox.org/ze/devs/wordpress-plugin-ze-admin-update-notification
Description: Sending notification to administrators when plugin or WordPress updates are available
Author: Yann 'Ze' Richard
Version: 0.6
Author URI: https://nbox.org/ze/
*/
/*  Copyright 2008-2009  Yann 'Ze' Richard <ze(arobase)nbox.org>

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as
    published by the Free Software Foundation, either version 3 of the
    License, or (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

    http://www.gnu.org/licenses/agpl-3.0.txt
 */
//require_once( dirname( dirname( dirname( dirname(__FILE__) ) ) ) . '/wp-load.php');
define(WP_ZAUN_PATH, dirname(__FILE__) . '/../../..');
define(WP_ZAUN_VERSION, '0.5');
define(WP_ZAUN_TEST, FALSE);

require_once(WP_ZAUN_PATH.'/wp-admin/includes/plugin.php');
require_once(WP_ZAUN_PATH.'/wp-admin/includes/user.php');
require_once(WP_ZAUN_PATH.'/wp-includes/functions.php');
require_once(WP_ZAUN_PATH.'/wp-includes/plugin.php');
require_once(WP_ZAUN_PATH.'/wp-includes/cron.php');
require_once(WP_ZAUN_PATH.'/wp-includes/capabilities.php');
require_once(WP_ZAUN_PATH.'/wp-includes/pluggable.php');
require_once(WP_ZAUN_PATH.'/wp-includes/l10n.php');

/* Registering */
register_activation_hook(__FILE__, 'zaun_activation');
register_deactivation_hook(__FILE__, 'zaun_deactivation');
add_action('zaun_cron_event', 'zaun_hourly');
add_action('zaun_test','zaun_hourly');

function zaun_activation() {
    wp_schedule_event(time(), 'hourly', 'zaun_cron_event');
    // Schedule test in 10s if test env is ON
    if ( WP_ZAUN_TEST ) {
        wp_schedule_single_event(time()+10, 'zaun_test');
    }
}
function zaun_deactivation() {
    wp_clear_scheduled_hook('zaun_cron_event');
    delete_option('zaun_notified');
}

function zaun_hourly() {
    zaun_VerifyAndNotify();
}
/* End Registering */

function zaun_update_msg($file) {
  $current = get_option( 'update_plugins' );
  if ( !isset( $current->response[ $file ] ) ) {
    return false;
  }
  $r = $current->response[ $file ];
  $plugin_data = zaun_getPluginData( $file );

  $ret = sprintf( zaun__('There is a new version of %1$s available. Your version: %2$s | New version: %3$s'), $plugin_data['Name'], $current['checked'][$file] , $r->new_version );
  return $ret;
}


function zaun_getPluginData($plugin_file) {
  $plugins_allowedtags = array('a' => array('href' => array(),'title' => array()),'abbr' => array('title' => array()),'acronym' => array('title' => array()),'code' => array(),'em' => array(),'strong' => array());
  $all_plugins = get_plugins();
  $plugin_data = $all_plugins[$plugin_file];

  // Sanitize all displayed data
  $plugin_data['Title']       = wp_kses($plugin_data['Title'], $plugins_allowedtags);
  $plugin_data['Version']     = wp_kses($plugin_data['Version'], $plugins_allowedtags); 
  $plugin_data['Description'] = wp_kses($plugin_data['Description'], $plugins_allowedtags);
  $plugin_data['Author']      = wp_kses($plugin_data['Author'], $plugins_allowedtags);
  if( ! empty($plugin_data['Author']) )
    $plugin_data['Description'] .= ' <cite>' . sprintf( __('By %s'), $plugin_data['Author'] ) . '.</cite>';

  //Filter into individual sections 
  if ( is_plugin_active($plugin_file) ) {
    $active_plugins[ $plugin_file ] = $plugin_data;
  } else {
    if ( isset( $recently_activated[ $plugin_file ] ) ) //Was the plugin recently activated?
      $recent_plugins[ $plugin_file ] = $plugin_data;
    else
      $inactive_plugins[ $plugin_file ] = $plugin_data;
  }
  return $plugin_data;
}

function zaun_VerifyAndNotify() {
  $t = get_option('zaun_notified');
  if ( $t === FALSE ) {
      $t = array();
      update_option('zaun_notified', $t);
  }

  $update_plugins = (array) get_option('update_plugins');
  $all_plugins = get_plugins();
  $mesg = array();

  $cur = get_option('update_core');
  if ( isset($cur->response) && $cur->response == 'upgrade' ) {
        // WordPress have new version !
        if ( isset($t['wordpress']) && $t['wordpress'] ==  $cur->current ) {
            // We are already notified about this ..
        } else {
            $mesg[] = sprintf( zaun__('There is a new version of WordPress available ! Your version: %1$s | New version: %2$s'), $GLOBALS['wp_version'], $cur->current);
            $t['wordpress'] = $cur->current;
        }
  } else {
      if ( isset($t['wordpress']) ) {
          unset($t['wordpress']);
      }
  }

  if ( isset($update_plugins['response']) ) {
    // new version detected
    foreach ( $update_plugins['response'] as $file => $plugClass ) {
      // is admin already notified about this ?
      if ( zaun_admin_need_notified($file, $plugClass->new_version) ) {
        $r = $update_plugins['response'][$file];
        $plugin_data = zaun_getPluginData( $file );
        $mesg[] = sprintf( zaun__('There is a new version of %1$s available. Your version: %2$s | New version: %3$s'), $plugin_data['Name'], $update_plugins['checked'][$file] , $r->new_version );
        $t[$file] = $r->new_version;
      } else {
          if ( !isset($update_plugins['checked'][$file]) && isset($t[$file]) ) {
            // plugins does not exists any more.. cleaning
            unset($t[$file]);
          }
      }
    }
  }
  // cleaning
  foreach ( $t as $file => $version ) {
    if ( !isset($update_plugins['checked'][$file]) && $file != 'wordpress' ) {
      unset($t[$file]);
    }
  }
  update_option('zaun_notified', $t);
  if ( ! empty($mesg) ) {
    zaun_send_notification( $mesg );
  }
}
//zaun_VerifyAndNotify();

function zaun_send_notification( $mesg ) {
    $subject  = '['.stripslashes_deep(get_settings('aiosp_home_title')).'] '. zaun__('Update for your blog need your actions !');
    $message  = zaun__('There is one or more update who need your action :') . "\n\n";
    foreach ( $mesg as $file => $m ) {
        $message .= '    - '.$m . "\n";
    }
    $url = get_option('siteurl') . '/wp-admin/plugins.php';
    $message .= "\n\n". sprintf( zaun__('To update, go to your admin panel: %1$s'),$url);
    $message .= "\n\n   " . zaun__("Notified by Ze's Admin Update Notification Plugin") . "\n\n";
    $t = new WP_User_Search('', '', 'administrator');
    $res = $t->get_results();
    foreach ( $res as $userid ) {
        $user_object = new WP_User($userid);
        wp_mail($user_object->user_email, $subject, $message);
    }
}

// Load translation file if any
function zaun_load_text_domain() {
        $locale = get_locale();
        $mofile = WP_PLUGIN_DIR.'/'.plugin_basename(dirname(__FILE__)).'/translations/zaun' . '-' . $locale . '.mo';
        load_textdomain('zaun', $mofile);
}

// Translation wrapper
function zaun__($string) {
        zaun_load_text_domain();
        return __($string, 'zaun');
}

function zaun_admin_need_notified($file,$version) {
    $t = get_option('zaun_notified');
    if ( isset( $t[$file] ) ) {
        if ( $s1 != $s2 ) {
            return TRUE;
        }
    } else {
        return TRUE;
    }
    return FALSE;
}
?>
