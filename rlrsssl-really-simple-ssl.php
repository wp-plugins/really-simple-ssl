<?php
/**
 * Plugin Name: Really Simple SSL
 * Plugin URI: http://www.rogierlankhorst.com/really-simple-ssl
 * Description: Lightweight plugin without any setup to make your site ssl proof
 * Version: 2.1.18
 * Text Domain: rlrsssl-really-simple-ssl
 * Domain Path: /lang
 * Author: Rogier Lankhorst
 * Author URI: http://www.rogierlankhorst.com
 * License: GPL2
 */

/*  Copyright 2014  Rogier Lankhorst  (email : rogier@rogierlankhorst.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

    rlrsssl: rl really simple ssl
*/

defined('ABSPATH') or die("you do not have acces to this page!");

if ( ! class_exists( 'rlrsssl_really_simple_ssl' ) ) {

  if (is_admin()) {
    require_once( dirname( __FILE__ ) .  '/class-cache.php' );
    require_once( dirname( __FILE__ ) .  '/class-database.php' );
    require_once( dirname( __FILE__ ) .  '/class-files.php' );
    require_once( dirname( __FILE__ ) .  '/class-scan.php' );
    require_once( dirname( __FILE__ ) .  '/class-admin.php' );
  }

  if(is_admin()) {
      class rlrsssl_admin_layer extends rlrsssl_admin {
        //load admin functionality
      }
  } else {
      class rlrsssl_admin_layer {
        //no admin functionality loaded
      }
  }

class rlrsssl_really_simple_ssl extends rlrsssl_admin_layer {
  public
   //front end
   $force_ssl_without_detection     = FALSE,
   $site_has_ssl                    = FALSE,
   $autoreplace_insecure_links      = TRUE,
   $http_urls                       = array();

  public function __construct()
  {
      $this->get_options();
      //only for backend
      if (is_admin()) {
        $this->get_admin_options();
        $this->set_plugin_filename(__FILE__);
        $this->get_plugin_url();
        $this->getABSPATH();
        $this->get_plugin_version();
        $is_settings_page = $this->is_settings_page();

        register_activation_hook(__FILE__, array($this,'activate') );
        register_deactivation_hook(__FILE__, array($this, 'deactivate') );

        //detect configuration on activation, upgrade, or when we are on settings Page
        //but also when setup is not completed: ssl is not detected, or we detected a wpconfig_issue or wpconfig load balancer fix.
    		if (!$this->site_has_ssl || $this->wpconfig_loadbalancer_fix_failed || $this->wpconfig_issue || $is_settings_page || $this->plugin_activated() || $this->plugin_upgraded()) {
          $this->check_for_ssl();
    			add_action('plugins_loaded',array($this,'configure_ssl'),20);
    		}
        add_action('plugins_loaded',array($this,'check_plugin_conflicts'),30);

      	//add the settings page for the plugin
      	add_action('admin_menu',array($this,'setup_admin_page'),30);

    		if ($is_settings_page) {
            add_option('really_simple_ssl_do_scan', 'activate_scan' );
    				add_action('admin_init',array($this,'start_scan'),30);
    		} elseif ((get_option( 'really_simple_ssl_do_scan') == 'activate_scan')) {
            delete_option('really_simple_ssl_do_scan');
            add_action('admin_init',array($this,'start_scan'),30);
        }

    		//if necessary, flush cache
        $cache = new rlrsssl_cache;
        add_action('admin_init',array($cache,'flush'),40);


      	//callbacks for the ajax dismiss buttons
      	add_action('wp_ajax_dismiss_fail_message', array($this,'dismiss_fail_message_callback') );
      	add_action('wp_ajax_dismiss_success_message', array($this,'dismiss_success_message_callback') );
        add_action('wp_ajax_dismiss_woocommerce_forcessl_message', array($this,'dismiss_woocommerce_forcessl_message_callback') );

        //handle notices
        add_action('admin_notices', array($this,'show_notices'));
      }

      //front end actions, javascript redirect, and mixed content replacement
      add_action('wp_print_scripts', array($this,'force_ssl_with_javascript'));
      add_filter('template_include', array($this,'replace_insecure_links'));
  }

  /**
   * Creates an array of insecure links that should be https and an array of secure links to replace with
   *
   * @since  2.0
   *
   * @access public
   *
   */

  public function build_url_list() {
    $home_no_www  = str_replace ( "://www." , "://" , get_option('home'));
    $home_yes_www = str_replace ( "://" , "://www." , $home_no_www);

    $this->http_urls = array(
        str_replace ( "https://" , "http://" , $home_yes_www),
        str_replace ( "https://" , "http://" , $home_no_www),
        "src='http://",
        'src="http://',
        "src=http://",
    );
  }

  /**
   * Get the options for this plugin
   *
   * @since  2.0
   *
   * @access public
   *
   */

  public function get_options(){
    $options = get_option('rlrsssl_options');

    if (isset($options)) {
      $this->force_ssl_without_detection = isset($options['force_ssl_without_detection']) ? $options['force_ssl_without_detection'] : FALSE;
      $this->site_has_ssl = isset($options['site_has_ssl']) ? $options['site_has_ssl'] : FALSE;
      $this->autoreplace_insecure_links = isset($options['autoreplace_insecure_links']) ? $options['autoreplace_insecure_links'] : TRUE;
    }

    if ($this->autoreplace_insecure_links || is_admin()) {
      $this->build_url_list();
    }
  }

  /**
   * Just before the page is sent to the visitor's browser, all homeurl links are replaced with https.
   *
   * @since  1.0
   *
   * @access public
   *
   */

  public function replace_insecure_links($template) {
    if (($this->site_has_ssl || $this->force_ssl_without_detection) && $this->autoreplace_insecure_links) {
      ob_start(array($this, 'end_buffer_capture'));  // Start Page Buffer
    }
    return $template;
  }

  /**
   * Just before the page is sent to the visitor's browser, all homeurl links are replaced with https.
   *
   * filter: rlrsssl_replace_url_args
   * This filter allows for extending the range of urls that are replaced with https.
   *
   * @since  1.0
   *
   * @access public
   *
   */

  public function end_buffer_capture($buffer) {
    $search_array = apply_filters('rlrsssl_replace_url_args', $this->http_urls);
	  $ssl_array = str_replace ( "http://" , "https://", $search_array);
    //now replace these links
    $buffer = str_replace ($search_array , $ssl_array , $buffer);
    return $buffer;
  }

  /**
   * Adds some javascript to redirect to https.
   *
   * @since  1.0
   *
   * @access public
   *
   */

  public function force_ssl_with_javascript() {
    if ($this->site_has_ssl || $this->force_ssl_without_detection) {
        ?>
        <script>
        if (document.location.protocol != "https:") {
            document.location = document.URL.replace(/^http:/i, "https:");
        }
        </script>
        <?php
      }
  }

}}

$rlrsssl = new rlrsssl_really_simple_ssl();
