<?php
/**
 * Plugin Name: Really Simple SSL
 * Plugin URI: http://www.rogierlankhorst.com/really-simple-ssl
 * Description: Lightweight plugin without any setup to make your site ssl proof
 * Version: 2.0.3
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
      multisite compatibility
*/

defined('ABSPATH') or die("you do not have acces to this page!");

if ( ! class_exists( 'rlrsssl_really_simple_ssl' ) ) {

class rlrsssl_really_simple_ssl {
    public
     $force_ssl_without_detection   = FALSE,
     $hide_nag_notice               = FALSE,
     $ssl_redirect_set_in_htaccess  = FALSE,
     $site_has_ssl                  = FALSE,
     $ssl_success_message_shown     = FALSE,
     $settings_changed              = FALSE,
     $capability                    = 'install_plugins',
     $ssl_warning,
     $plugin_url,
     $error_number = 0,
     $ABSpath;

    public function __construct()
    {
        $this->get_options();

        //only for backend
        if (is_admin()) {
          $this->get_plugin_url();
          $this->getABSPATH();
          register_deactivation_hook(__FILE__, array($this, 'deactivate') );
          add_action('plugins_loaded',array($this,'check_for_ssl'));

          //if ssl, edit htaccess to redirect to https if possible, and change the siteurl
          if ($this->site_has_ssl || $this->force_ssl_without_detection) {
            add_action('plugins_loaded',array($this,'editHtaccess'));
            add_action('plugins_loaded',array($this,'set_siteurl_to_ssl'));
          }
          //check if the htaccess was edited, if so, remove edit and set db value for htaccess edit to false
          elseif ($this->ssl_redirect_set_in_htaccess) {
            add_action('plugins_loaded',array($this,'removeHtaccessEdit'));
            add_action('plugins_loaded',array($this,'remove_ssl_from_siteurl'));
          }

          //build the options page and options functionality
          add_action('plugins_loaded',array($this,'admin_init'));
          //check if cache should be flushed
          if ($this->settings_changed) {
            add_action('plugins_loaded',array($this,'flush_cache'));
          }
        }

        //front end actions
        if ($this->site_has_ssl || $this->force_ssl_without_detection) {
            //if redirect is not set in .htaccess, add javascript redirect
            if (!$this->ssl_redirect_set_in_htaccess) {
              add_action('wp_print_scripts', array($this,'force_ssl_url_scheme_script'));
            }

            //now replace all http urls, but only to those within current site
            add_filter('template_include', array($this,'replace_http_with_https'));
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
        $newsiteurl = str_replace ( "http://" , "https://" , get_option('siteurl'));
        $newhomeurl = str_replace ( "http://" , "https://" , get_option('home'));
        update_option('siteurl',$newsiteurl);
        update_option('home',$newhomeurl);
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
        $newsiteurl = str_replace ( "https://" , "http://" , get_option('siteurl'));
        $newhomeurl = str_replace ( "https://" , "http://" , get_option('home'));

        update_option('siteurl',$newsiteurl);
        update_option('home',$newhomeurl);
      }
    }

    public function get_options(){
      $options = get_option('rlrsssl_options');

      if (isset($options)) {
        $this->ssl_redirect_set_in_htaccess = isset($options['ssl_redirect_set_in_htaccess']) ? $options['ssl_redirect_set_in_htaccess'] : FALSE;
        $this->site_has_ssl = isset($options['site_has_ssl']) ? $options['site_has_ssl'] : FALSE;
        $this->force_ssl_without_detection = isset($options['force_ssl_without_detection']) ? $options['force_ssl_without_detection'] : FALSE;
        $this->hide_nag_notice = isset($options['hide_nag_notice']) ? $options['hide_nag_notice'] : FALSE;
        $this->ssl_success_message_shown = isset($options['ssl_success_message_shown']) ? $options['ssl_success_message_shown'] : FALSE;
        $this->settings_changed = isset($options['settings_changed']) ? $options['settings_changed'] : FALSE;
      }
    }

    public function save_options() {
      $options = array(
        'ssl_redirect_set_in_htaccess'  => $this->ssl_redirect_set_in_htaccess,
        'site_has_ssl'                  => $this->site_has_ssl,
        'force_ssl_without_detection'   => $this->force_ssl_without_detection,
        'hide_nag_notice'               => $this->hide_nag_notice,
        'ssl_success_message_shown'     => $this->ssl_success_message_shown,
        'settings_changed'              => $this->settings_changed
      );
      update_option('rlrsssl_options',$options);

    }

    public function load_translation()
    {
        load_plugin_textdomain('rlrsssl-really-simple-ssl', FALSE, dirname(plugin_basename(__FILE__)).'/lang/');
        $this->ssl_warning = __("No SSL was detected. If you are just waiting for your ssl certificate to kick in, click 'Do nothing' to dismiss the warning.<bR><br>If you are sure you have SSL, you can force SSL anyway, and your site should run over ssl without any problems.<br><b>Warning!</b> If you force ssl without having a valid SSL certificate, you may break your site. In that case, follow <a target='_blank' href='https://www.rogierlankhorst.com/instructions-to-manually-remove-the-ssl-plugin-after-forcing-ssl'>these instructions</a>, then wait with reactivating until you have acquired an ssl certificate.","rlrsssl-really-simple-ssl");
    }

    public function deactivate() {
      $this->removeHtaccessEdit();
      add_action('plugins_loaded',array($this,'remove_ssl_from_siteurl'));
      $this->ssl_redirect_set_in_htaccess = FALSE;
      $this->site_has_ssl                 = FALSE;
      $this->force_ssl_without_detection  = FALSE;
      $this->hide_nag_notice              = FALSE;
      $this->ssl_success_message_shown    = FALSE;
      $this->settings_changed             = FALSE;
      $this->save_options();
      //@TODO add cache flushing here
    }

    public function ssl_behind_load_balancer_or_cdn() {
      if(!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
        return TRUE;
      }
      else {
        return FALSE;
      }
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
          $homepage = file_get_contents($testpage_url);
          //errors back to normal
          restore_error_handler();
          if ($this->error_number==0) {
            $this->site_has_ssl = TRUE;
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
     * Flushes the cache for popular caching plugins to prevent mixed content errors
     * When .htaccess is changed, all traffic should flow over https, so clear cache when possible.
     * supported: W3TC, WP fastest Cache, Zen Cache
     *
     * @since  2.0
     *
     * @access public
     *
     */

    public function flush_cache() {
      if (current_user_can($this->capability)) {

        add_action( 'init', array($this,'flush_w3tc_cache'));
        add_action( 'init', array($this,'flush_fastest_cache'));
        add_action( 'init', array($this,'flush_zen_cache'));

        //reset settings changed
        $this->settings_changed = FALSE;
      }

    }

    public function flush_w3tc_cache() {
      if( class_exists('W3_Plugin_TotalCacheAdmin') )
      {
        if (function_exists('w3tc_flush_all')) {
          w3tc_flush_all();
        }
      }
    }

    public function flush_fastest_cache() {
      if(class_exists('WpFastestCache') )
      {
        $GLOBALS["wp_fastest_cache"]->deleteCache(TRUE);
      }
    }

    public function flush_zen_cache() {
      if (class_exists('\\zencache\\plugin') )
      {
        $GLOBALS['zencache']->clear_cache();
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

    public function replace_http_with_https($template) {
      ob_start(array($this, 'end_buffer_capture'));  // Start Page Buffer
      return $template;
    }

    /**
     * Just before the page is sent to the visitor's browser, alle homeurl links are replaced with https.
     *
     * filters: rlrsssl_replacewith_url_args, rlrsssl_replace_url_args
     * These filters allow for extending the range of urls that are replaced with https.
     *
     * @since  1.0
     *
     * @access public
     *
     */

    public function end_buffer_capture($buffer) {
        //to be sure we get both www as non www domains, we create both.
        $home_no_www  = str_replace ( "://www." , "://" , get_option('home'));
        $home_yes_www = str_replace ( "://" , "://www." , $home_no_www);

        $ssl_array = apply_filters( 'rlrsssl_replacewith_url_args', array(
            str_replace ( "http://" , "https://" , $home_yes_www),
            str_replace ( "http://" , "https://" , $home_no_www),
            "https://www.youtube", //Embed Video Fix
            "https://ajax.googleapis.com/ajax"
            ) );

        //https is inserted in homeurl, so replace back to http
        $standard_array = apply_filters( 'rlrsssl_replace_url_args', array(
            str_replace ( "https://" , "http://" , $home_yes_www),
            str_replace ( "https://" , "http://" , $home_no_www),
            "http://www.youtube", //Embed Video Fix
            "http://ajax.googleapis.com/ajax"
            ) );

        //now replace all these links
        $buffer = str_replace ($standard_array , $ssl_array , $buffer);
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
        }

        $this->ssl_redirect_set_in_htaccess =  FALSE;
        $this->save_options();
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

        if (!file_exists($this->ABSpath.".htaccess") || !is_writable($this->ABSpath.".htaccess")) {
          $this->ssl_redirect_set_in_htaccess =  FALSE;
        } else {
          //exists and is writable
          $htaccess = file_get_contents($this->ABSpath.".htaccess");
          $htaccess = $this->insertRedirectRule($htaccess);
          $htaccess = preg_replace("/\n+/","\n", $htaccess);

          file_put_contents($this->ABSpath.".htaccess", $htaccess);
          $this->ssl_redirect_set_in_htaccess =  TRUE;
        }
        $this->save_options();
      }
    }

    /**
     * Actual insertion of redirect rule.
     *
     * @since  2.0
     *
     * @access public
     *
     */

    public function insertRedirectRule($htaccess) {
        $rule = "\n\n".
                "# BEGIN rlrssslReallySimpleSSL"."\n".
                "RewriteEngine on"."\n".
                "RewriteCond %{HTTPS} !=on"."\n".
                "RewriteRule (.*) https://%{HTTP_HOST}%{REQUEST_URI} [L]"."\n".
					      "# END rlrssslReallySimpleSSL"."\n";

				preg_match("/BEGIN rlrssslReallySimpleSSL/", $htaccess, $check);
				if(count($check) === 0){
					return $htaccess.$rule;
				}else{
					return $htaccess;
				}
			}

    /**
     * Adds some javascript to redirect to https, when .htaccess is not writable.
     *
     * @since  1.0
     *
     * @access public
     *
     */

    public function force_ssl_url_scheme_script() {
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
    if (!$this->site_has_ssl && !$this->hide_nag_notice && !$this->force_ssl_without_detection) {
      parse_str($_SERVER['QUERY_STRING'], $params);
        ?>

        <div id="message" class="updated fade"><p>
        <?php echo $this->ssl_warning;?>
        </p>
        <p><strong>
        <?php printf('<a href="%1$s">'.__("Do nothing yet, I'm waiting for my certificate","rlrsssl-really-simple-ssl").'</a>', '?'.http_build_query(array_merge($params, array('rlrsssl_nag_ignore'=>'1'))));?>
        |
        <?php printf('<a href="%1$s">'.__("I'm sure I have an active SSL certificate, force it!","rlrsssl-really-simple-ssl").'</a>', '?'.http_build_query(array_merge($params, array('rlrsssl_force_ssl'=>'1'))));?>
        |
        <a href="options-general.php?page=rlrsssl_really_simple_ssl"><?php echo __("View your detected setup","rlrsssl-really-simple-ssl");?></a>
        </strong></p></div>
        <?php
    }

    if ($this->site_has_ssl && !$this->ssl_success_message_shown) {

          add_action('admin_print_footer_scripts', array($this, 'insert_dismiss_processing'));
          ?>
          <div id="message" class="updated fade notice is-dismissible rlrsssl-success"><p>
          <?php echo __("SSl was detected and successfully activated!","rlrsssl-really-simple-ssl");?>
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

  public function insert_dismiss_processing() {
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
                //alert(response);
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
     * Process the dismissal of the ssl failure message.
     *
     * @since  2.0
     *
     * @access public
     *
     */

  public function process_errormsg_reaction() {
      if ( isset($_GET['rlrsssl_nag_ignore']) && '1' == $_GET['rlrsssl_nag_ignore'] ) {
        $this->hide_nag_notice = TRUE;
    	}
      if ( isset($_GET['rlrsssl_force_ssl']) && '1' == $_GET['rlrsssl_force_ssl'] ) {
        $this->force_ssl_without_detection = TRUE;
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
          'id'	=> "force_ssl_without_detection",
          'title'	=> __("Force SSL without detection","rlrsssl-really-simple-ssl"),
          'content'	=> '<p>' . __("This plugin tries to open a page within the plugin directory over https. If that fails, it is assumed that ssl is not availble. But as this may not cover all eventualities, it is possible to force the site over ssl anyway.<br><br> If you force your site over ssl without a valid ssl certificate, your site may break. In that case, remove the 'really simple ssl' rules from your .htaccess file (if present), and remove or rename the really simple ssl plugin.","rlrsssl-really-simple-ssl") . '</p>',
      ) );

      $screen->add_help_tab( array(
          'id'	=> "hide_nag_notice",
          'title'	=> __("Ignore ssl error","rlrsssl-really-simple-ssl"),
          'content'	=> '<p>' . __("When you don't want the 'no ssl detected message' to keep nagging you, you can disable it. This does not disable functionality: if an ssl certificate is installed and detected your site will start running on ssl. You have to log in to your wordpress dashboard for this to happen though.") . '</p>',
      ) );

      $screen->add_help_tab( array(
          'id'	=> "ssl_certificate",
          'title'	=> __("How to get an SSL certificate","rlrsssl-really-simple-ssl"),
          'content'	=> '<p>' . __("To secure your site with ssl, you need an SSL certificate. How you can get a certificate depends on your hosting provider, but can often be requested on the control panel of your website. If you are not sure what to do, you can contact your hosting provider.") . '</p>',
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

    ?>
      <div>
          <h2><?php echo __("SSL settings","rlrsssl-really-simple-ssl");?></h2>
          <?php if ($this->site_has_ssl) { ?>
              <p>
                <?php echo __("We detected SSL on your website and did some setup. If all went according to plan, your site should be running over https by now.","rlrsssl-really-simple-ssl");?></p>
          <?php } else { ?>
              <p><?php echo $this->ssl_warning;?></p>

          <?php }

          ?>

          <h2><?php echo __("Detected setup","rlrsssl-really-simple-ssl");?></h2>
          <table>
            <tr>
              <td><?php echo $this->site_has_ssl ? $this->img_true() : $this->img_false();?></td>
              <td><?php
                      if (!$this->site_has_ssl) {
                        //no ssl detected, show why
                        echo __("No SSL detected.","rlrsssl-really-simple-ssl")."&nbsp;";

                        if (!is_ssl()) {
                          echo __("The check for ssl with wp is_ssl() failed.","rlrsssl-really-simple-ssl")."&nbsp;";
                        }
                        if (!$this->ssl_behind_load_balancer_or_cdn()) {
                          echo __("The check for ssl behind load balancer failed.","rlrsssl-really-simple-ssl");
                        }
                      }
                      else {
                        //ssl detected, no problems!
                        echo __("An SSL certificate appears to be active on your site. ","rlrsssl-really-simple-ssl");
                      }
                  ?>
            </tr>
            <?php if($this->site_has_ssl || $this->force_ssl_without_detection) { ?>
            <tr>
              <td><?php echo $this->ssl_redirect_set_in_htaccess ? $this->img_true() :$this->img_false();?></td>
              <td><?php echo $this->ssl_redirect_set_in_htaccess ? __("https redirect set in .htaccess","rlrsssl-really-simple-ssl") :__("Https redirect was set in javascript, because .htaccess was not available or writable.","rlrsssl-really-simple-ssl");?>
            </tr>
            <?php } ?>

          </table>
      <?php if (!$this->site_has_ssl) { ?>
          <form action="options.php" method="post">
          <?php settings_fields('rlrsssl_options'); ?>
          <?php do_settings_sections('rlrsssl'); ?>

          <input class="button button-primary" name="Submit" type="submit" value="<?php echo __("Save","rlrsssl-really-simple-ssl"); ?>" />
          </form>
      <?php
      }
      ?>
      </div>
  <?php
  }

    /**
     * Returns a check/true image for the settings page
     *
     * @since  2.0
     *
     * @access public
     *
     */

  public function img_true() {
    return "<img class='icons' src='".$this->plugin_url."img/check-icon.png' alt='true'>";
  }

    /**
     * Returns a false image for the settings page
     *
     * @since  2.0
     *
     * @access public
     *
     */

  public function img_false() {
    return "<img class='icons' src='".$this->plugin_url."img/cross-icon.png' alt='false'>";
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

      add_action('admin_menu', array($this, 'add_settings_page'));
      add_action('admin_init', array($this, 'create_form'));
      $plugin = plugin_basename(__FILE__);
      add_filter("plugin_action_links_$plugin", array($this,'plugin_settings_link'));

      add_action('admin_init', array($this, 'process_errormsg_reaction'));
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
        register_setting( 'rlrsssl_options', 'rlrsssl_options', array($this,'options_validate') );
        add_settings_section('rlrsssl_settings', __("Settings","rlrsssl-really-simple-ssl"), array($this,'section_text'), 'rlrsssl');

        if(!$this->site_has_ssl) {
          //no sense in showing force or ignore warning options when ssl is detected: everything should work fine
          add_settings_field('id_force_ssl_without_detection', __("Force SSL without detection","rlrsssl-really-simple-ssl"), array($this,'get_option_force_ssl_withouth_detection'), 'rlrsssl', 'rlrsssl_settings');

          //hide nag not relevant when force ssl is active
          if (!$this->force_ssl_without_detection) {
            add_settings_field('id_hide_nag_notice', __("Ignore SSL detection error","rlrsssl-really-simple-ssl"), array($this,'get_option_hide_nag_notice'), 'rlrsssl', 'rlrsssl_settings');
          }
        }
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
    <?php echo __("To force SSL on your site, without detection, just check the 'force ssl anyway' checkbox.","rlrsssl-really-simple-ssl");?>
    <br>
    <?php echo __("To stop the errormessage nagging you about your ssl setup, just check the option 'ignore ssl detection error'.","rlrsssl-really-simple-ssl");?>

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

    if (!empty($input['force_ssl_without_detection']) && $input['force_ssl_without_detection']=='1') {
      $newinput['force_ssl_without_detection'] = TRUE;
    } else {
      $newinput['force_ssl_without_detection'] = FALSE;
    }

    if (!empty($input['hide_nag_notice']) && $input['hide_nag_notice']=='1') {
      $newinput['hide_nag_notice'] = TRUE;
    } else {
      $newinput['hide_nag_notice'] = FALSE;
    }

    return $newinput;
  }

    /**
     * Insert option into settings form
     *
     * @since  2.0
     *
     * @access public
     *
     */

  public function get_option_force_ssl_withouth_detection() {
    $options = get_option('rlrsssl_options');
    echo "<input id='rlrsssl_options' name='rlrsssl_options[force_ssl_without_detection]' size='40' type='checkbox' value='1'" . checked( 1, $this->force_ssl_without_detection, false ) ." />";
  }

    /**
     * Insert option into settings form
     *
     * @since  2.0
     *
     * @access public
     *
     */

  public function get_option_hide_nag_notice() {
    $options = get_option('rlrsssl_options');
    echo "<input id='rlrsssl_options' name='rlrsssl_options[hide_nag_notice]' size='40' type='checkbox' value='1'" . checked( 1, $this->hide_nag_notice, false ) ." />";
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

$rlrsssl_really_simple_ssl = new rlrsssl_really_simple_ssl();
