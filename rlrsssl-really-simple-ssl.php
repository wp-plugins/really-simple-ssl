<?php
/**
 * Plugin Name: Really Simple SSL
 * Plugin URI: http://www.rogierlankhorst.com/really-simple-ssl
 * Description: Lightweight plugin without any setup to make your site ssl proof
 * Version: 2.1.10
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

    todo:
      multisite compatibility check
*/

defined('ABSPATH') or die("you do not have acces to this page!");

if ( ! class_exists( 'rlrsssl_really_simple_ssl' ) ) {

  if (is_admin()) {
    require_once( dirname( __FILE__ ) .  '/cache.php' );
    require_once( dirname( __FILE__ ) .  '/database.php' );
    require_once( dirname( __FILE__ ) .  '/files.php' );
    require_once( dirname( __FILE__ ) .  '/scan.php' );
  }

class rlrsssl_really_simple_ssl {
    public
     //front end
     //$force_ssl_without_detection   = FALSE,
     $ssl_redirect_set_in_htaccess  = FALSE,
     $site_has_ssl                  = FALSE,
     $autoreplace_insecure_links    = TRUE,
     $http_urls                     = array(),

     //admin
     $ssl_fail_message_shown        = FALSE,
     $ssl_success_message_shown     = FALSE,
     $settings_changed              = FALSE,
     $wpconfig_issue                = FALSE,
     $ssl_type                      = "NA",
                                    //"SERVER-HTTPS-ON"
                                    //"SERVER-HTTPS-1"
                                    //"SERVERPORT443"
                                    //"LOADBALANCER"
                                    //"CDN"
     $capability                    = 'install_plugins',
     $plugin_url,
     $plugin_version,
     $error_number = 0,
     $ABSpath;

    public function __construct()
    {
        $this->get_options();

        if ($this->autoreplace_insecure_links || is_admin()) {
          $this->build_url_list();
        }

        //only for backend
        if (is_admin()) {
          $this->get_plugin_url();
          $this->getABSPATH();
          $this->get_plugin_version();

          register_deactivation_hook(__FILE__, array($this, 'deactivate') );
          add_action('plugins_loaded',array($this,'check_for_ssl'));

          //if ssl, edit htaccess to redirect to https if possible, and change the siteurl
          if ($this->site_has_ssl) {
            //check for siteurl definitions in wpconfig
            add_action('plugins_loaded',array($this,'check_for_siteurl_in_wpconfig'));
            // wpconfig issue does not pose serious problems on the front end, so we continue, but notify of this issue when it arises

            add_action('plugins_loaded',array($this,'editHtaccess'));
            add_action('plugins_loaded',array($this,'set_siteurl_to_ssl'));
          }
          else {
            add_action('plugins_loaded',array($this,'removeHtaccessEdit'));
            add_action('plugins_loaded',array($this,'remove_ssl_from_siteurl'));
          }

          //build the options page and options functionality
          add_action('plugins_loaded',array($this,'admin_init'));

          //check if cache should be flushed
          if ($this->settings_changed) {
            $cache = new rlrsssl_cache;
            add_action('wp_loaded',array($cache,'flush'));
          }
        }

        //front end actions, only do something when .htaccess is inaccessible, or mixed content has been found
        if ($this->site_has_ssl) {
            //Always add javascript redirect, in case the htaccess redirect fails or could not be written.
            //if (!$this->ssl_redirect_set_in_htaccess) {
            add_action('wp_print_scripts', array($this,'force_ssl_with_javascript'));
            //}

            if ($this->autoreplace_insecure_links) {
              add_filter('template_include', array($this,'replace_insecure_links'));
            }
        }
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
          "http://www.youtube", //Embed Video Fix
          "http://ajax.googleapis.com/ajax",
      );
    }

    /**
     * Retrieves the current version of this plugin
     *
     * @since  2.1
     *
     * @access public
     *
     */

    public function get_plugin_version() {
  	    if ( ! function_exists( 'get_plugins' ) )
  	        require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
  	    $plugin_folder = get_plugins( '/' . plugin_basename( dirname( __FILE__ ) ) );
  	    $plugin_file = basename( ( __FILE__ ) );
  	    $this->plugin_version = $plugin_folder[$plugin_file]['Version'];
    }

    /**
     * Find the path to wp-config
     *
     * @since  2.1
     *
     * @access public
     *
     */

    public function find_wp_config_path() {
      //limit nr of iterations to 20
      $i=0;
      $maxiterations = 20;
      $dir = dirname(__FILE__);
      do {
          $i++;
          if( file_exists($dir."/wp-config.php") ) {
              return $dir."/wp-config.php";
          }
      } while( ($dir = realpath("$dir/..")) && ($i<$maxiterations) );
      return null;
    }

    /**
     * remove https from defined siteurl and homeurl in the wpconfig, if present
     *
     * @since  2.1
     *
     * @access public
     *
     */

    public function remove_ssl_from_siteurl_in_wpconfig() {
      if (current_user_can($this->capability)) {
        $wpconfig_path = $this->find_wp_config_path();
        if (!empty($wpconfig_path)) {
          $wpconfig = file_get_contents($wpconfig_path);

          $homeurl_pos = strpos($wpconfig, "define('WP_HOME','https://");
          $siteurl_pos = strpos($wpconfig, "define('WP_SITEURL','https://");

          if (($homeurl_pos !== false) || ($siteurl_pos !== false)) {
            if (is_writable($wpconfig_path)) {
              $search_array = array("define('WP_HOME','https://","define('WP_SITEURL','https://");
              $ssl_array = array("define('WP_HOME','http://","define('WP_SITEURL','http://");
              //now replace these urls
              $wpconfig = str_replace ($search_array , $ssl_array , $wpconfig);
              file_put_contents($wpconfig_path, $wpconfig);
            }
          }

        }
      }
    }

    /**
     * Check if the siteurl or homeurl are defined in the wp-config. If so, try to fix, (replace with https)
     * If wp config.php is not writable, make a note of this by setting wpconfig_issue to TRUE, so we can notify
     *
     * @since  2.1
     *
     * @access public
     *
     */

    public function check_for_siteurl_in_wpconfig() {
      if (current_user_can($this->capability)) {
        $wpconfig_path = $this->find_wp_config_path();
        if (!empty($wpconfig_path)) {
          $wpconfig = file_get_contents($wpconfig_path);

          $homeurl_pos = strpos($wpconfig, "define('WP_HOME','http://");
          $siteurl_pos = strpos($wpconfig, "define('WP_SITEURL','http://");

          if (($homeurl_pos !== false) || ($siteurl_pos !== false)) {
            if (is_writable($wpconfig_path)) {
              $search_array = array("define('WP_HOME','http://","define('WP_SITEURL','http://");
              $ssl_array = array("define('WP_HOME','https://","define('WP_SITEURL','https://");
              //now replace these urls
              $wpconfig = str_replace ($search_array , $ssl_array , $wpconfig);
              file_put_contents($wpconfig_path, $wpconfig);
            }
            else {
              //only when siteurl or homeurl is defined in wpconfig, and wpconfig is not writable is there a possible issue because we cannot edit the defined urls.
              $this->wpconfig_issue = TRUE;
            }
          }

        }
      }
    }

    /**
     * Changes the siteurl and homeurl to https
     *
     * @since  2.0
     *
     * @access public
     *
     */

    public function set_siteurl_to_ssl() {
      if (current_user_can($this->capability)) {
        $siteurl_ssl = str_replace ( "http://" , "https://" , get_option('siteurl'));
        $homeurl_ssl = str_replace ( "http://" , "https://" , get_option('home'));
        update_option('siteurl',$siteurl_ssl);
        update_option('home',$homeurl_ssl);
      }
    }

    /**
     * On de-activation, siteurl and homeurl are reset to http
     *
     * @since  2.0
     *
     * @access public
     *
     */

    public function remove_ssl_from_siteurl() {
      if (current_user_can($this->capability)) {
        $siteurl_no_ssl = str_replace ( "https://" , "http://" , get_option('siteurl'));
        $homeurl_no_ssl = str_replace ( "https://" , "http://" , get_option('home'));
        update_option('siteurl',$siteurl_no_ssl);
        update_option('home',$homeurl_no_ssl);
      }
    }

    public function get_options(){
      $options = get_option('rlrsssl_options');

      if (isset($options)) {
        $this->ssl_redirect_set_in_htaccess = isset($options['ssl_redirect_set_in_htaccess']) ? $options['ssl_redirect_set_in_htaccess'] : FALSE;
        $this->site_has_ssl = isset($options['site_has_ssl']) ? $options['site_has_ssl'] : FALSE;
        //$this->force_ssl_without_detection = isset($options['force_ssl_without_detection']) ? $options['force_ssl_without_detection'] : FALSE;
        $this->ssl_fail_message_shown = isset($options['ssl_fail_message_shown']) ? $options['ssl_fail_message_shown'] : FALSE;
        $this->ssl_success_message_shown = isset($options['ssl_success_message_shown']) ? $options['ssl_success_message_shown'] : FALSE;
        $this->settings_changed = isset($options['settings_changed']) ? $options['settings_changed'] : FALSE;
        $this->autoreplace_insecure_links = isset($options['autoreplace_insecure_links']) ? $options['autoreplace_insecure_links'] : TRUE;
      }
    }

    public function save_options() {
      $options = array(
        'ssl_redirect_set_in_htaccess'  => $this->ssl_redirect_set_in_htaccess,
        'site_has_ssl'                  => $this->site_has_ssl,
        //'force_ssl_without_detection'   => $this->force_ssl_without_detection,
        'ssl_fail_message_shown'        => $this->ssl_fail_message_shown,
        'ssl_success_message_shown'     => $this->ssl_success_message_shown,
        'settings_changed'              => $this->settings_changed,
        'autoreplace_insecure_links'    => $this->autoreplace_insecure_links,
      );

      update_option('rlrsssl_options',$options);

    }

    public function load_translation()
    {
        load_plugin_textdomain('rlrsssl-really-simple-ssl', FALSE, dirname(plugin_basename(__FILE__)).'/lang/');
    }

    /**
     * Handles deactivation of this plugin
     *
     * @since  2.0
     *
     * @access public
     *
     */

    public function deactivate() {
      $this->removeHtaccessEdit();
      $this->remove_ssl_from_siteurl();
      $this->remove_ssl_from_siteurl_in_wpconfig();
      $this->ssl_redirect_set_in_htaccess = FALSE;
      $this->site_has_ssl                 = FALSE;
      //$this->force_ssl_without_detection  = FALSE;
      $this->ssl_fail_message_shown       = FALSE;
      $this->ssl_success_message_shown    = FALSE;
      $this->settings_changed             = FALSE;
      $this->autoreplace_insecure_links   = TRUE;
      $this->save_options();
      //@TODO add cache flushing here
    }

    /**
     * Handles any errors as the result of trying to open a https page when there may be no ssl.
     *
     * @since  2.0
     *
     * @access public
     *
     */

    public function custom_error_handling($errno, $errstr, $errfile, $errline, array $errcontext) {
          $this->error_number = $errno;
    }

    /**
     * Checks for SSL by opening a test page in the plugin directory
     *
     * @since  2.0
     *
     * @access public
     *
     */

    public function check_for_ssl() {
      if (current_user_can($this->capability)) {
          $site_has_ssl = $this->site_has_ssl;
          $testpage_url = trailingslashit(str_replace ("http://" , "https://" , $this->plugin_url))."ssl-test-page.php";
          //do the error handling myself, because non functioning ssl will result in a warning
          set_error_handler(array($this,'custom_error_handling'));
          $filecontents = file_get_contents($testpage_url);
          //errors back to normal
          restore_error_handler();
          if ($this->error_number==0) {
            $this->site_has_ssl = TRUE;
            //check the type of ssl
            if (strpos($filecontents, "#SERVER-HTTPS-ON#") !== false) {
              $this->ssl_type = "SERVER-HTTPS-ON";
            } elseif (strpos($filecontents, "#SERVER-HTTPS-1#") !== false) {
              $this->ssl_type = "SERVER-HTTPS-1";
            } elseif (strpos($filecontents, "#SERVERPORT443#") !== false) {
              $this->ssl_type = "SERVERPORT443";
            } elseif (strpos($filecontents, "#LOADBALANCER#") !== false) {
              $this->ssl_type = "LOADBALANCER";
            } elseif (strpos($filecontents, "#CDN#") !== false) {
              $this->ssl_type = "CDN";
            } else {
              //no recognized response, so set to NA
              $this->ssl_type = "NA";
            }
          }
          else {
            $this->site_has_ssl = FALSE;
            //reset error
            $this->error_number = 0;
          }

          if ($site_has_ssl != $this->site_has_ssl) {
            //value has changed, note this so we can flush the cache later.
            $this->settings_changed = TRUE;
          }
          $this->save_options();
      }
    }

    /**
     * Just before the page is sent to the visitor's browser, alle homeurl links are replaced with https.
     *
     * @since  1.0
     *
     * @access public
     *
     */

    public function replace_insecure_links($template) {
      ob_start(array($this, 'end_buffer_capture'));  // Start Page Buffer
      return $template;
    }

    /**
     * Just before the page is sent to the visitor's browser, all homeurl links are replaced with https.
     *
     * filters: rlrsssl_replace_url_args (rlrsssl_replacewith_url_args,  is deprecated)
     * These filter allows for extending the range of urls that are replaced with https.
     *
     * @since  1.0
     *
     * @access public
     *
     */

    public function end_buffer_capture($buffer) {
      $search_array = apply_filters('rlrsssl_replace_url_args', $this->http_urls);
      $ssl_array = str_replace ( "http://" , "https://" , $this->http_urls);

      //now replace these links
      $buffer = str_replace ($search_array , $ssl_array , $buffer);
      return $buffer;
    }

    /**
     * removes the added redirect to https rules to the .htaccess file.
     *
     * @since  2.0
     *
     * @access public
     *
     */

    public function removeHtaccessEdit() {
      //when admin is logged in, update .htaccess if possible and necessary
      if (current_user_can($this->capability)) {

        if(file_exists($this->ABSpath.".htaccess") && is_writable($this->ABSpath.".htaccess")){

          $htaccess = file_get_contents($this->ABSpath.".htaccess");
          $htaccess = preg_replace("/#\s?BEGIN\s?rlrssslReallySimpleSSL.*?#\s?END\s?rlrssslReallySimpleSSL/s", "", $htaccess);
          $htaccess = preg_replace("/\n+/","\n", $htaccess);

          file_put_contents($this->ABSpath.".htaccess", $htaccess);
          $this->ssl_redirect_set_in_htaccess =  FALSE;
          $this->save_options();
        }
      }
    }

    public function contains_previous_version($htaccess) {
      $versionpos = strpos($htaccess, "rsssl_version");
      if ($versionpos===false) {
        //no version found, so old version
        return true;
      } else {
        //find closing marker of version
        $close = strpos($htaccess, "]", $versionpos);
        $version = substr($htaccess, $versionpos+14, $close-($versionpos+14));
        if ($version != $this->plugin_version) {
          return true;
        }
        else {
          return false;
        }
      }
    }

    public function contains_rsssl_rules($htaccess) {
      preg_match("/BEGIN rlrssslReallySimpleSSL/", $htaccess, $check);
      if(count($check) === 0){
        return false;
      } else {
        return true;
      }
    }

    /**
     * Adds redirect to https rules to the .htaccess file.
     *
     * @since  2.0
     *
     * @access public
     *
     */

    public function editHtaccess(){
      //when admin is logged in, update .htaccess if possible and necessary
      if (current_user_can($this->capability)) {
        //check if htacces exists and  if htaccess is writable
        //update htaccess to redirect to ssl and set redirect_set_in_htaccess

        if ( !defined( 'RLRSSSL_DO_NOT_EDIT_HTACCESS' ) ) {
            define( 'RLRSSSL_DO_NOT_EDIT_HTACCESS' , FALSE );
        }

        if (!RLRSSSL_DO_NOT_EDIT_HTACCESS && file_exists($this->ABSpath.".htaccess") && is_writable($this->ABSpath.".htaccess")) {
          //exists and is writable
          $htaccess = file_get_contents($this->ABSpath.".htaccess");
          $rules = $this->get_redirect_rules();
          $this->ssl_redirect_set_in_htaccess = !($this->ssl_type=="NA");

          if(!$this->contains_rsssl_rules($htaccess)){
            //really simple ssl rules not in the file, so add.
            $htaccess = $htaccess.$rules;
            file_put_contents($this->ABSpath.".htaccess", $htaccess);
          } elseif ($this->contains_previous_version($htaccess)) {
            //old version, so remove all rules and add new.
            $htaccess = preg_replace("/#\s?BEGIN\s?rlrssslReallySimpleSSL.*?#\s?END\s?rlrssslReallySimpleSSL/s", "", $htaccess);
            $htaccess = preg_replace("/\n+/","\n", $htaccess);
            $htaccess = $htaccess.$rules;
            file_put_contents($this->ABSpath.".htaccess", $htaccess);
          } else {
            //current version, so do nothing.
          }
        } else {
          $this->ssl_redirect_set_in_htaccess =  FALSE;
        }
        //if the htaccess setting was changed, we save it here.
        $this->save_options();
      }
    }

    /**
     * Create redirect rules for the .htaccess.
     *
     * @since  2.1
     *
     * @access public
     *
     */

    public function get_redirect_rules() {
        //only add the redirect rules when a known type of ssl was detected. Otherwise, we use https.
        $rule  = "# BEGIN rlrssslReallySimpleSSL rsssl_version[".$this->plugin_version."]\n";
        if ($this->ssl_type != "NA") {
          //set redirect_set_in_htaccess to true, because we are now making a redirect rule.
          $this->ssl_redirect_set_in_htaccess = TRUE;
          $rule .= "<IfModule mod_rewrite.c>"."\n";
          $rule .= "RewriteEngine on"."\n";

          //select rewrite conditino based on detected type of ssl
          if ($this->ssl_type == "SERVER-HTTPS-ON") {
              $rule .= "RewriteCond %{HTTPS} !=on [NC]"."\n";
          } elseif ($this->ssl_type == "SERVER-HTTPS-1") {
              $rule .= "RewriteCond %{HTTPS} !=1"."\n";
          } elseif ($this->ssl_type == "SERVERPORT443") {
             $rule .= "RewriteCond %{SERVER_PORT} !443"."\n";
          } elseif ($this->ssl_type == "LOADBALANCER") {
              $rule .="RewriteCond %{HTTP:X-Forwarded-Proto} !https"."\n";
          } elseif ($this->ssl_type == "CDN") {
              $rule .= "RewriteCond %{HTTP:X-Forwarded-SSL} !on"."\n";
          }
          //$rule .= "RewriteRule ^(.*)$ https\:\/\/%{HTTP_HOST}\/$1 [R=301,L]"."\n";
          $rule .= "RewriteRule ^(.*) https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]"."\n";
          $rule .= "</IfModule>"."\n";
        }

        //owasp security best practice https://www.owasp.org/index.php/HTTP_Strict_Transport_Security
        #$rule .= "<IfModule mod_headers.c>"."\n";
        #$rule .= "Header always set Strict-Transport-Security 'max-age=31536000' env=HTTPS"."\n";
        #$rule .= "</IfModule>"."\n";
	      $rule .= "# END rlrssslReallySimpleSSL"."\n";

        $rule = preg_replace("/\n+/","\n", $rule);
        return $rule;
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
        ?>
        <script>
        if (document.location.protocol != "https:") {
            document.location = document.URL.replace(/^http:/i, "https:");
        }
        </script>
    <?php
    }

    /**
     * Find if this wordpress installation is installed in a subdirectory
     *
     * @since  2.0
     *
     * @access protected
     *
     */

  protected function is_subdirectory_install(){
      if(strlen(site_url()) > strlen(home_url())){
        return true;
      }
      return false;
   }

   /**
    * Get the absolute path the the www directory of this site, where .htaccess lives.
    *
    * @since  2.0
    *
    * @access public
    *
    */

  public function getABSPATH(){
    $path = ABSPATH;
    if($this->is_subdirectory_install()){
      $siteUrl = site_url();
      $homeUrl = home_url();
      $diff = str_replace($homeUrl, "", $siteUrl);
      $diff = trim($diff,"/");
        $pos = strrpos($path, $diff);
        if($pos !== false){
          $path = substr_replace($path, "", $pos, strlen($diff));
          $path = trim($path,"/");
          $path = "/".$path."/";
        }
      }
      $this->ABSpath = $path;
    }

    /**
     * Get the url of this plugin
     *
     * @since  2.0
     *
     * @access public
     *
     */

    public function get_plugin_url(){
      $this->plugin_url = trailingslashit(WP_PLUGIN_URL).trailingslashit(dirname(plugin_basename(__FILE__)));
    }

    /**
     * Show error message when ssl check fails.
     *
     * @since  2.0
     *
     * @access public
     *
     */

  public function show_notices()
  {
    if (!$this->site_has_ssl && !$this->ssl_fail_message_shown) {
      add_action('admin_print_footer_scripts', array($this, 'insert_dismiss_fail'));
        ?>

        <div id="message" class="error fade notice is-dismissible rlrsssl-fail"><p>
        <?php _e("No SSL was detected. If you are just waiting for your ssl certificate to kick in you can dismiss this warning.","rlrsssl-really-simple-ssl");?>
        </p>
        <p><strong>
        <a href="options-general.php?page=rlrsssl_really_simple_ssl"><?php echo __("View your detected setup","rlrsssl-really-simple-ssl");?></a>
        </strong></p></div>
        <?php
    }

    if ($this->site_has_ssl && !$this->ssl_success_message_shown) {

          add_action('admin_print_footer_scripts', array($this, 'insert_dismiss_success'));
          ?>
          <div id="message" class="updated fade notice is-dismissible rlrsssl-success"><p>
          <?php echo __("SSl was detected and successfully activated!","rlrsssl-really-simple-ssl");?>
          </p></div>
          <?php

    }

    if ($this->site_has_ssl && $this->wpconfig_issue) {
      ?>
      <div id="message" class="error fade notice"><p>
      <?php echo __("We detected a definition of siteurl or homeurl in your wp-config.php, but the file is not writable. Because of this, we cannot set the siteurl to https.","rlrsssl-really-simple-ssl");?>
      </p></div>
      <?php
    }

}

    /**
     * Insert some ajax script to dismis the ssl success message, and stop nagging about it
     *
     * @since  2.0
     *
     * @access public
     *
     */

  public function insert_dismiss_success() {
    $ajax_nonce = wp_create_nonce( "rlrsssl-really-simple-ssl" );
    ?>
    <script type='text/javascript'>
      jQuery(document).ready(function($) {
        $(".rlrsssl-success.notice.is-dismissible").on("click", ".notice-dismiss", function(event){
              var data = {
                'action': 'dismiss_success_message',
                'security': '<?php echo $ajax_nonce; ?>'
              };

              $.post(ajaxurl, data, function(response) {

              });
          });
      });
    </script>
    <?php
  }

  public function insert_dismiss_fail() {
    $ajax_nonce = wp_create_nonce( "rlrsssl-really-simple-ssl" );
    ?>
    <script type='text/javascript'>
      jQuery(document).ready(function($) {
          $(".rlrsssl-fail.notice.is-dismissible").on("click", ".notice-dismiss", function(event){
                var data = {
                  'action': 'dismiss_fail_message',
                  'security': '<?php echo $ajax_nonce; ?>'
                };
                $.post(ajaxurl, data, function(response) {

                });
            });
      });
    </script>
    <?php
  }

    /**
     * Process the ajax dismissal of the success message.
     *
     * @since  2.0
     *
     * @access public
     *
     */

  public function dismiss_success_message_callback() {
  	global $wpdb; // this is how you get access to the database
    check_ajax_referer( 'rlrsssl-really-simple-ssl', 'security' );
    $this->ssl_success_message_shown = TRUE;
    $this->save_options();
  	wp_die(); // this is required to terminate immediately and return a proper response
  }

  /**
   * Process the ajax dismissal of the fail message.
   *
   * @since  2.1
   *
   * @access public
   *
   */

  public function dismiss_fail_message_callback() {
    global $wpdb;
    check_ajax_referer( 'rlrsssl-really-simple-ssl', 'security' );

    $this->ssl_fail_message_shown = TRUE;
    $this->save_options();
    wp_die(); // this is required to terminate immediately and return a proper response
  }

  public function process_submit_without_form() {
      if ( isset($_GET['rlrsssl_fixposts']) && '1' == $_GET['rlrsssl_fixposts'] ) {

        //$database->fix_insecure_post_links();
      }
      $this->save_options();
  	}


    /**
     * Adds the admin options page
     *
     * @since  2.0
     *
     * @access public
     *
     */

  public function add_settings_page() {
    $admin_page = add_options_page(
      __("SSL settings","rlrsssl-really-simple-ssl"), //link title
      __("SSL","rlrsssl-really-simple-ssl"), //page title
      $this->capability, //capability
      'rlrsssl_really_simple_ssl', //url
      array($this,'settings_page')); //function

      // Adds my_help_tab when my_admin_page loads
      add_action('load-'.$admin_page, array($this,'admin_add_help_tab'));
  }

    /**
     * Admin help tab
     *
     * @since  2.0
     *
     * @access public
     *
     */

  public function admin_add_help_tab() {
      $screen = get_current_screen();
      // Add my_help_tab if current screen is My Admin Page
      $screen->add_help_tab( array(
          'id'	=> "detected_setup",
          'title'	=> __("Detected setup","rlrsssl-really-simple-ssl"),
          'content'	=> '<p>' . __("In the detected setup section you can see what we detected for your site.<br><br><b>SSL detection:</b> if it is possible to open a page on your site with https, it is assumed you have a valid ssl certificate. No guarantees can be given.<br><br><B>SSL redirect in .htaccess:</b> (Only show when ssl is detected) If possible, the redirect will take place in the .htaccess file. If this file is not available or not writable, javascript is used to enforce ssl.","rlrsssl-really-simple-ssl") . '</p>',
      ) );

      $screen->add_help_tab( array(
          'id'	=> "Autoreplace",
          'title'	=> __("Auto replace insecure links","rlrsssl-really-simple-ssl"),
          'content'	=> '<p>' . __("In most sites, a lot of links are saved into the content, pluginoptions or even worse, in the theme. When you switch to ssl , these are still http, instead of https. To ensure a smooth transition, this plugin auto replaces all these links. If you see in the scan results that you have fixed most of these links, you can try to run your site without this replace script, which will give you a small performance advantage. If you do not have a lot of reported insecure links, you can try this. If you encounter mixed content warnings, just switch it back on. <br><br><b>How to check for mixed content?</b><br>Go to the the front end of your website, and click on the lock in your browser's address bar. When you have mixed content, this lock is not closed, or has a red cross over it.","rlrsssl-really-simple-ssl") . '</p>',
      ) );

      $screen->add_help_tab( array(
          'id'	=> "ssl_certificate",
          'title'	=> __("How to get an SSL certificate","rlrsssl-really-simple-ssl"),
          'content'	=> '<p>' . __("To secure your site with ssl, you need an SSL certificate. How you can get a certificate depends on your hosting provider, but can often be requested on the control panel of your website. If you are not sure what to do, you can contact your hosting provider.","rlrsssl-really-simple-ssl") . '</p>',
      ) );
  }


    /**
     * Build the settings page
     *
     * @since  2.0
     *
     * @access public
     *
     */

  public function settings_page() {
    //only add scan ajax script if scan was activated
    if (isset($this->scan)) {
      add_action('admin_print_footer_scripts', array($this->scan, 'insert_scan'));
    }
    ?>
      <div>
          <h2><?php echo __("SSL settings","rlrsssl-really-simple-ssl");?></h2>
              <p>
                <?php echo __("On your SSL settings page you can view the detected setup of your system, and optimize accordingly. If no mixed content was found, you could try disabling the 'auto replace insecure links' option, and test if the front end runs without the mixed content error message (click on the lock in your browser address bar). This way you can prevent unnecessary replacement actions.","rlrsssl-really-simple-ssl");?>
              </p>


          <h3><?php echo __("Detected setup","rlrsssl-really-simple-ssl");?></h3>
          <table id="scan-results">
            <tr>
              <td><?php echo $this->site_has_ssl ? $this->img("success") : $this->img("error");?></td>
              <td><?php
                      if (!$this->site_has_ssl) {
                        echo __("No SSL detected.","rlrsssl-really-simple-ssl")."&nbsp;";
                      }
                      else {
                        //ssl detected, no problems!
                        _e("An SSL certificate was detected on your site. ","rlrsssl-really-simple-ssl");
                      }
                  ?>
                </td>
            </tr>
            <?php if($this->site_has_ssl) { ?>
            <tr>
              <td>
                <?php echo $this->ssl_redirect_set_in_htaccess ? $this->img("success") :$this->img("warning");?>
              </td>
              <td>
                <?php echo $this->ssl_redirect_set_in_htaccess ? __("https redirect set in .htaccess","rlrsssl-really-simple-ssl") :__("Https redirect was set in javascript, because .htaccess was not available or writable, or the ssl configuration was not recognized.","rlrsssl-really-simple-ssl");?>
              </td>
            </tr>
            <?php }
            ?>

          </table>
        <?php
        if ($this->site_has_ssl) {
        ?>
          <form action="options.php" method="post">
          <?php
              settings_fields('rlrsssl_options');
              do_settings_sections('rlrsssl');
          ?>

          <input class="button button-primary" name="Submit" type="submit" value="<?php echo __("Save","rlrsssl-really-simple-ssl"); ?>" />
          </form>
          <?php
        }
          ?>
      </div>
  <?php
  }

    /**
     * Returns a succes, error or warning image for the settings page
     *
     * @since  2.0
     *
     * @access public
     *
     * @param string $type the type of image
     *
     * @return html string
     */

  public function img($type) {
    if ($type=='success') {
      return "<img class='icons' src='".$this->plugin_url."img/check-icon.png' alt='success'>";
    } elseif ($type=="error") {
      return "<img class='icons' src='".$this->plugin_url."img/cross-icon.png' alt='error'>";
    } else {
      return "<img class='icons' src='".$this->plugin_url."img/warning-icon.png' alt='warning'>";
    }
  }

    /**
     * Add some css for the settings page
     *
     * @since  2.0
     *
     * @access public
     *
     */

  public function enqueue_assets(){
    wp_register_style( 'rlrsssl-css', $this->plugin_url . 'css/main.css');
    wp_enqueue_style( 'rlrsssl-css' );
  }

    /**
     * Initialize admin errormessage, settings page
     *
     * @since  2.0
     *
     * @access public
     *
     */

  public function admin_init(){
    if (current_user_can($this->capability)) {
      add_action( 'admin_enqueue_scripts', array($this, 'enqueue_assets'));
      add_action('init', array($this, 'load_translation'));

      //set up scanning
      //No need for scanning if no ssl certificate was detected
      if ($this->site_has_ssl) {
        $this->scan = new rlrsssl_scan;
        $this->scan->init($this->http_urls, $this->autoreplace_insecure_links);
        $this->scan->set_images($this->img("success"),$this->img("error"),$this->img("warning"));
        add_action( 'wp_ajax_scan', array($this->scan,'scan_callback'));
      }

      //settings page, from creation and settings link in the plugins page
      add_action('admin_menu', array($this, 'add_settings_page'));
      add_action('admin_init', array($this, 'create_form'));
      $plugin = plugin_basename(__FILE__);
      add_filter("plugin_action_links_$plugin", array($this,'plugin_settings_link'));

      //actions submitted outside the form
      add_action('admin_init', array($this, 'process_submit_without_form'));

      //handle notices
      add_action( 'wp_ajax_dismiss_fail_message', array($this,'dismiss_fail_message_callback') );
      add_action( 'wp_ajax_dismiss_success_message', array($this,'dismiss_success_message_callback') );
      add_action('admin_notices', array($this,'show_notices'));
    }
  }

    /**
     * Create the settings page form
     *
     * @since  2.0
     *
     * @access public
     *
     */

  public function create_form(){
        //only show option to enable or disable autoreplace when ssl is detected
        register_setting( 'rlrsssl_options', 'rlrsssl_options', array($this,'options_validate') );
        add_settings_section('rlrsssl_settings', __("Settings","rlrsssl-really-simple-ssl"), array($this,'section_text'), 'rlrsssl');
        add_settings_field('id_autoreplace_insecure_links', __("Auto replace insecure links","rlrsssl-really-simple-ssl"), array($this,'get_option_autoreplace_insecure_links'), 'rlrsssl', 'rlrsssl_settings');

        //force is deprecated
        //if(!$this->site_has_ssl) {
        //  add_settings_field('id_force_ssl_without_detection', __("Force SSL without detection","rlrsssl-really-simple-ssl"), array($this,'get_option_force_ssl_withouth_detection'), 'rlrsssl', 'rlrsssl_settings');
        //}
      }

    /**
     * Insert some explanation above the form
     *
     * @since  2.0
     *
     * @access public
     *
     */

  public function section_text() {
    ?>
    <p>
    <?php
        _e('By unchecking the \'auto replace insecure links\' checkbox you can test if your site can run without this extra functionality. Uncheck, empty your cache when you use one, and go to the front end of your site. You should then check if you have mixed content errors, by clicking on the lock icon in the addres bar.','rlrsssl-really-simple-ssl');
    ?>
    </p>
    <?php
    }

    /**
     * Check the posted values in the settings page for validity
     *
     * @since  2.0
     *
     * @access public
     *
     */

  public function options_validate($input) {
    //fill array with current values, so we don't lose any
    $newinput = array();
    $newinput['ssl_redirect_set_in_htaccess'] = $this->ssl_redirect_set_in_htaccess;
    $newinput['site_has_ssl']                 = $this->site_has_ssl;
    $newinput['ssl_success_message_shown']    = $this->ssl_success_message_shown;
    $newinput['ssl_fail_message_shown']       = $this->ssl_fail_message_shown;
    //$newinput['force_ssl_without_detection']  = $this->force_ssl_without_detection;
    $newinput['autoreplace_insecure_links']   = $this->autoreplace_insecure_links;


/*
    //force option deprecated

    if (!empty($input['force_ssl_without_detection']) && $input['force_ssl_without_detection']=='1') {
      $newinput['force_ssl_without_detection'] = TRUE;
    } else {
      $newinput['force_ssl_without_detection'] = FALSE;
    }
*/

    if (!empty($input['autoreplace_insecure_links']) && $input['autoreplace_insecure_links']=='1') {
      $newinput['autoreplace_insecure_links'] = TRUE;
    } else {
      $newinput['autoreplace_insecure_links'] = FALSE;
    }

    //try to flush cache, because a change to autoreplace or force_ssl should be made visible as soon as possible
    $cache = new rlrsssl_cache;
    $cache->flush();
    return $newinput;
  }

    /**
     * Insert option into settings form
     * deprecated
     * @since  2.0
     *
     * @access public
     *
     */

/*
 //deprecated
  public function get_option_force_ssl_withouth_detection() {
    $options = get_option('rlrsssl_options');
    echo '<input id="rlrsssl_options"  onClick="return confirm(\''.__("Are you sure? This could seriously break up your site! Backup before you go!","rlrsssl-really-simple-ssl").'\');" name="rlrsssl_options[force_ssl_without_detection]" size="40" type="checkbox" value="1"' . checked( 1, $this->force_ssl_without_detection, false ) ." />";
  }
*/

  /**
   * Insert option into settings form
   *
   * @since  2.1
   *
   * @access public
   *
   */

  public function get_option_autoreplace_insecure_links() {
    $options = get_option('rlrsssl_options');
    echo "<input id='rlrsssl_options' name='rlrsssl_options[autoreplace_insecure_links]' size='40' type='checkbox' value='1'" . checked( 1, $this->autoreplace_insecure_links, false ) ." />";
  }
      /**
       * Add settings link on plugins overview page
       *
       * @since  2.0
       *
       * @access public
       *
       */

  public function plugin_settings_link($links) {
    $settings_link = '<a href="options-general.php?page=rlrsssl_really_simple_ssl">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
  }
}}

$rlrsssl = new rlrsssl_really_simple_ssl();
