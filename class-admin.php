<?php
defined('ABSPATH') or die("you do not have acces to this page!");

  class rl_rsssl_admin extends rl_rsssl_front_end {
  //wpconfig fixing variables @TODO: convert to error array
  //true when siteurl and homeurl are defined in wp-config and can't be changed
  public $wpconfig_issue                      = FALSE;
  public $wpconfig_loadbalancer_fix_failed    = FALSE;
  public $wpconfig_server_variable_fix_failed = FALSE;
  public $no_server_variable                  = FALSE;
  public $errors                              = Array();

  public $do_wpconfig_loadbalancer_fix        = FALSE;

  //multisite variables
  public $set_rewriterule_per_site          = FALSE;
  public $sites                             = Array(); //for multisite, list of all activated sites.

  //general settings
  public $capability                        = 'manage_options';

  public $error_number                      = 0;
  public $curl_installed                    = FALSE;
  public $ssl_test_page_error;
  public $htaccess_test_success             = FALSE;

  public $plugin_dir                        = "really-simple-ssl";
  public $plugin_filename                   = "rlrsssl-really-simple-ssl.php";
  public $ABSpath;

  public $do_not_edit_htaccess              = FALSE;
  public $ssl_fail_message_shown            = FALSE;
  public $ssl_success_message_shown         = FALSE;
  public $hsts                              = FALSE;
  public $debug							                = TRUE;
  public $debug_log;

  public $plugin_conflict                   = ARRAY();
  public $plugin_url;
  public $plugin_version;
  public $plugin_db_version;

  public $ssl_redirect_set_in_htaccess      = FALSE;
  public $settings_changed                  = FALSE;
  public $ssl_type                          = "NA";
                                            //possible values:
                                            //"NA":     test page did not return valid response
                                            //"SERVER-HTTPS-ON"
                                            //"SERVER-HTTPS-1"
                                            //"SERVERPORT443"
                                            //"LOADBALANCER"
                                            //"CDN"

  public function __construct()
  {
    require_once( dirname( __FILE__ ) .  '/class-cache.php' );
    require_once( dirname( __FILE__ ) .  '/class-database.php' );
    require_once( dirname( __FILE__ ) .  '/class-files.php' );
    require_once( dirname( __FILE__ ) .  '/class-scan.php' );

    $this->get_options();
    $this->get_admin_options();
    $this->get_plugin_url();
    $this->getABSPATH();
    $this->get_plugin_version();
  }

  /**
   * Initializes the admin class
   *
   * @since  2.2
   *
   * @access public
   *
   */

  public function init() {
    $is_settings_page = $this->is_settings_page();
    if ($this->set_rewriterule_per_site) $this->build_domain_list();

    register_activation_hook(  dirname( __FILE__ )."/".$this->plugin_filename, array($this,'activate') );
    register_deactivation_hook(dirname( __FILE__ )."/".$this->plugin_filename, array($this, 'deactivate') );

    //detect configuration on activation, upgrade, or when we are on settings Page
    //but also when setup is not completed: ssl is not detected, or we detected a wpconfig_issue or wpconfig load balancer fix.
    if (!$this->site_has_ssl || $this->wpconfig_loadbalancer_fix_failed || $this->wpconfig_issue || $is_settings_page || $this->plugin_activated() || $this->plugin_upgraded()) {
      $this->detect_configuration();
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

    //check if the uninstallfile is safely renamed to php.
    $this->check_for_uninstall_file();

    //callbacks for the ajax dismiss buttons
    add_action('wp_ajax_dismiss_fail_message', array($this,'dismiss_fail_message_callback') );
    add_action('wp_ajax_dismiss_success_message', array($this,'dismiss_success_message_callback') );
    add_action('wp_ajax_dismiss_woocommerce_forcessl_message', array($this,'dismiss_woocommerce_forcessl_message_callback') );

    //handle notices
    add_action('admin_notices', array($this,'show_notices'));
  }

  /*
  *     Check if the uninstall file is renamed to .php
  */

  protected function check_for_uninstall_file() {
    if (file_exists(dirname( __FILE__ ) .  '/force-deactivate.php')) {
      $this->errors["DEACTIVATE_FILE_NOT_RENAMED"] = true;
    }
  }

  /**
   * Get the options for this plugin
   *
   * @since  2.0
   *
   * @access public
   *
   */

  public function get_admin_options(){
    /*
      if the define is true, it overrides the db setting.
    */
    $this->do_not_edit_htaccess = (defined( 'RLRSSSL_DO_NOT_EDIT_HTACCESS' ) &&  RLRSSSL_DO_NOT_EDIT_HTACCESS) ? TRUE : FALSE;

    $options = get_option('rlrsssl_options');
    if (isset($options)) {
      $this->hsts                               = isset($options['hsts']) ? $options['hsts'] : FALSE;
      $this->ssl_fail_message_shown             = isset($options['ssl_fail_message_shown']) ? $options['ssl_fail_message_shown'] : FALSE;
      $this->ssl_success_message_shown          = isset($options['ssl_success_message_shown']) ? $options['ssl_success_message_shown'] : FALSE;
      $this->plugin_db_version                  = isset($options['plugin_db_version']) ? $options['plugin_db_version'] : "1.0";
      $this->wpconfig_issue                     = isset($options['wpconfig_issue']) ? $options['wpconfig_issue'] : FALSE;
      $this->wpconfig_loadbalancer_fix_failed   = isset($options['wpconfig_loadbalancer_fix_failed']) ? $options['wpconfig_loadbalancer_fix_failed'] : FALSE;
      $this->wpconfig_server_variable_fix_failed= isset($options['wpconfig_server_variable_fix_failed']) ? $options['wpconfig_server_variable_fix_failed'] : FALSE;
      $this->set_rewriterule_per_site           = isset($options['set_rewriterule_per_site']) ? $options['set_rewriterule_per_site'] : FALSE;
      $this->debug                              = isset($options['debug']) ? $options['debug'] : FALSE;
      $this->do_not_edit_htaccess               = isset($options['do_not_edit_htaccess']) ? $options['do_not_edit_htaccess'] : $this->do_not_edit_htaccess;
    }
    if ($this->debug) {
      $this->trace_log("loading options...");
    }
  }

  /**
   * Checks if the site is multisite, and if the plugin was installed networkwide
   * If not networkwide and multisite, the htaccess rewrite should be on a per site basis.
   *
   * @since  2.1
   *
   * @access public
   *
   */

  public function detect_if_rewrite_per_site($networkwide) {
    if (is_multisite() && !$networkwide) {
        $this->set_rewriterule_per_site = TRUE;
    } else {
        $this->set_rewriterule_per_site = FALSE;
    }
    $this->save_options();
  }

  /**
   * Creates an array of all domains where the plugin is active AND ssl is active, only used for multisite.
   *
   * @since  2.1
   *
   * @access public
   *
   */

  public function build_domain_list() {
    //create list of all activated  sites with ssl
    $this->sites = array();
    $sites = wp_get_sites();
    if ($this->debug) $this->trace_log("building domain list for multiste...");
    foreach ( $sites as $site ) {
        switch_to_blog( $site[ 'blog_id' ] );
        $plugin = $this->plugin_dir."/".$this->plugin_filename;
        $options = get_option('rlrsssl_options');

        $blog_has_ssl = FALSE;
        if (isset($options)) {
          $blog_has_ssl = isset($options['site_has_ssl']) ? $options['site_has_ssl'] : FALSE;
        }

        if (is_plugin_active($plugin) && $blog_has_ssl) {
          if ($this->debug) $this->trace_log("adding: ".home_url());
          $this->sites[] = home_url();
        }
        restore_current_blog(); //switches back to previous blog, not current, so we have to do it each loop
      }

      $this->save_options();
  }

  /**
   * Workaround to use add_action after plugin activation
   *
   * @since  2.1
   *
   * @access public
   *
   */

  public function activate($networkwide) {
    $this->detect_if_rewrite_per_site($networkwide);
    add_option('really_simple_ssl_activated', 'activated' );
  }

  /**
   * check if the plugin was upgraded to a new version
   *
   * @since  2.1
   *
   * @access public
   *
   */

  public function plugin_upgraded() {
  	if ($this->plugin_db_version!=$this->plugin_version) {
  		$this->plugin_db_version = $this->plugin_version;
  		return true;
  	}
    return false;
  }

  /**
   * check if the plugin was just activated
   *
   * @since  2.1
   *
   * @access public
   *
   */

  public function plugin_activated() {
	if (get_option('really_simple_ssl_activated') == 'activated') {
		delete_option( 'really_simple_ssl_activated' );
		return true;
	}
	return false;
  }

  /**
   * Log events during plugin execution
   *
   * @since  2.1
   *
   * @access public
   *
   */

  public function trace_log($msg) {
    $this->debug_log = substr($this->debug_log."<br>".$msg,-1500);
    $this->debug_log = strstr($this->debug_log,'<br>');
    error_log($msg);
  }

  /**
   * Initialize scanning of the website for insecure links.
   *
   * @since  2.1
   *
   * @access public
   *
   */

  public function start_scan(){
    //set up scanning
    //No need for scanning if no ssl was detected

    if ($this->site_has_ssl || $this->force_ssl_without_detection) {
      $this->scan = new rlrsssl_scan;
      $this->scan->init($this->http_urls, $this->autoreplace_insecure_links);

      $this->scan->set_images(
              $this->img("success"),
              $this->img("error"),
              $this->img("warning")
      );

     add_action( 'wp_ajax_scan', array($this->scan,'scan_callback'));
    }
  }

  /**
   * Configures the site for ssl
   *
   * @since  2.2
   *
   * @access public
   *
   */

  public function configure_ssl() {
      if (!current_user_can($this->capability)) return;

    	if ($this->debug) {
          $ssl = $this->site_has_ssl ? "TRUE" : "FALSE";
          $this->trace_log("--- ssl detected: ".$ssl);
          $force_ssl_without_detection = $this->force_ssl_without_detection ? "TRUE" : "FALSE";
          $this->trace_log("--- force ssl: ".$force_ssl_without_detection);

          if ($this->site_has_ssl || $this->force_ssl_without_detection) {
            $this->trace_log("Starting ssl configuration");
          } else {
            $this->trace_log("No ssl detected and not forced, stopping configuration");
          }


          if ($this->site_has_ssl || $this->force_ssl_without_detection) {
            if ($this->do_wpconfig_loadbalancer_fix) {
              $this->trace_log("Loadbalancer, but is_ssl() returns false, so doing wp-config fix...");
            } elseif ($this->ssl_type=="LOADBALANCER"){
              $this->trace_log("Loadbalancer detected, but is_ssl returns true, so no wp-config fix needed.");
            } else {
              $this->trace_log("No loadbalancer detected, so no wp-config fix needed.");
            }
          }
      }

      //if ssl, edit htaccess to redirect to https if possible, and change the siteurl
      if ($this->site_has_ssl || $this->force_ssl_without_detection) {

        //when a known ssl_type was found, test if the redirect works
        if ($this->ssl_type != "NA")
            $this->test_htaccess_redirect();

        //in a configuration of loadbalancer without a set server variable https = 0, add code to wpconfig
        if ($this->do_wpconfig_loadbalancer_fix)
          $this->wpconfig_loadbalancer_fix();


        if ($this->no_server_variable)
            $this->wpconfig_server_variable_fix();

        $this->editHtaccess();

        //fix siteurl definitions in wpconfig, if any
        $this->fix_siteurl_defines_in_wpconfig();
        $this->set_siteurl_to_ssl();
      }
  }

  /**
   * Check to see if we are on the settings page, action hook independent
   *
   * @since  2.1
   *
   * @access public
   *
   */

  public function is_settings_page() {
    parse_str($_SERVER['QUERY_STRING'], $params);
    if (array_key_exists("page", $params) && ($params["page"]=="rlrsssl_really_simple_ssl")) {
        return true;
    }
    return false;
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
      $this->plugin_version = $plugin_folder[$this->plugin_filename]['Version'];
      if ($this->debug) {$this->trace_log("plugin version: ".$this->plugin_version);}
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
          } else {
            $this->errors['wpconfig not writable'] = TRUE;
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

  public function fix_siteurl_defines_in_wpconfig() {
      $wpconfig_path = $this->find_wp_config_path();

      if (empty($wpconfig_path)) return;

      $wpconfig = file_get_contents($wpconfig_path);

      $homeurl_pos = strpos($wpconfig, "define('WP_HOME','http://");
      $siteurl_pos = strpos($wpconfig, "define('WP_SITEURL','http://");
      $this->wpconfig_issue = FALSE;
      if (($homeurl_pos !== false) || ($siteurl_pos !== false)) {
        if (is_writable($wpconfig_path)) {
          if ($this->debug) {$this->trace_log("wp config siteurl/homeurl edited.");}
          $search_array = array("define('WP_HOME','http://","define('WP_SITEURL','http://");
          $ssl_array = array("define('WP_HOME','https://","define('WP_SITEURL','https://");
          //now replace these urls
          $wpconfig = str_replace ($search_array , $ssl_array , $wpconfig);
          file_put_contents($wpconfig_path, $wpconfig);
        }
        else {
          if ($this->debug) {$this->trace_log("not able to fix wpconfig siteurl/homeurl.");}
          //only when siteurl or homeurl is defined in wpconfig, and wpconfig is not writable is there a possible issue because we cannot edit the defined urls.
          $this->wpconfig_issue = TRUE;
        }
      } else {
        if ($this->debug) {$this->trace_log("no siteurl/homeurl defines in wpconfig");}
      }
      $this->save_options();
  }

  /**
   * In case of load balancer without server https on, add fix in wp-config
   *
   * @since  2.1
   *
   * @access public
   *
   */

  public function wpconfig_loadbalancer_fix() {
      $wpconfig_path = $this->find_wp_config_path();
      if (empty($wpconfig_path)) return;
      $wpconfig = file_get_contents($wpconfig_path);
      $this->wpconfig_loadbalancer_fix_failed = FALSE;
      //only if loadbalancer AND NOT SERVER-HTTPS-ON should the following be added. (is_ssl = false)
      if (strpos($wpconfig, "//Begin Really Simple SSL Load balancing fix")===FALSE ) {
        if (is_writable($wpconfig_path)) {
          $rule  = "\n"."//Begin Really Simple SSL Load balancing fix"."\n";
          $rule .= 'if (isset($_SERVER["HTTP_X_FORWARDED_PROTO"] ) && "https" == $_SERVER["HTTP_X_FORWARDED_PROTO"] ) {'."\n";
          $rule .= '$_SERVER["HTTPS"] = "on";'."\n";
          $rule .= "}"."\n";
          $rule .= "//END Really Simple SSL"."\n";

          $insert_after = "<?php";
          $pos = strpos($wpconfig, $insert_after);
          if ($pos !== false) {
              $wpconfig = substr_replace($wpconfig,$rule,$pos+1+strlen($insert_after),0);
          }

          file_put_contents($wpconfig_path, $wpconfig);
          if ($this->debug) {$this->trace_log("wp config loadbalancer fix inserted");}
        } else {
          if ($this->debug) {$this->trace_log("wp config loadbalancer fix FAILED");}
          $this->wpconfig_loadbalancer_fix_failed = TRUE;
        }
      } else {
        if ($this->debug) {$this->trace_log("wp config loadbalancer fix already in place, great!");}
      }
      $this->save_options();

  }

  /**
   * Checks if we are on a subfolder install. (domain.com/site1 )
   *
   * @since  2.2
   *
   * @access protected
   *
   */

  protected function is_multisite_subfolder_install() {
    if (!is_multisite()) return FALSE;
    $subfolder_install = FALSE;
    //we check this manually, as the SUBDOMAIN_INSTALL constant of wordpress might return false for domain mapping configs
    foreach ($this->sites as $site) {
      if ($this->is_subfolder($site)) return TRUE;
    }

    return FALSE;
  }

    /**
     * Getting Wordpress to recognize setup as being ssl when no https server variable is available
     *
     * @since  2.1
     *
     * @access public
     *
     */

    public function wpconfig_server_variable_fix() {

      $wpconfig_path = $this->find_wp_config_path();
      if (empty($wpconfig_path)) return;
      $wpconfig = file_get_contents($wpconfig_path);
      $this->wpconfig_server_variable_fix_failed = FALSE;

      //check permissions
      if (!is_writable($wpconfig_path)) {
        if ($this->debug) $this->trace_log("wp-config.php not writable");
        $this->wpconfig_server_variable_fix_failed = TRUE;
        return;
      }

      //when more than one blog, first remove what we have
      if (is_multisite() && !$this->is_multisite_subfolder_install() && $this->set_rewriterule_per_site && count($this->sites)>1) {
        $wpconfig = preg_replace("/\/\/Begin\s?Really\s?Simple\s?SSL.*?\/\/END\s?Really\s?Simple\s?SSL/s", "", $wpconfig);
        $wpconfig = preg_replace("/\n+/","\n", $wpconfig);
        file_put_contents($wpconfig_path, $wpconfig);
      }

      //now create new

      //check if the fix is already there
      if (strpos($wpconfig, "//Begin Really Simple SSL Server variable fix")!==FALSE ) {
          if ($this->debug) {$this->trace_log("wp config server variable fix already in place, great!");}
          return;
      }

      if ($this->debug) {$this->trace_log("Adding server variable to wpconfig");}
      $rule = $this->get_server_variable_fix_code();

      $insert_after = "<?php";
      $pos = strpos($wpconfig, $insert_after);
      if ($pos !== false) {
          $wpconfig = substr_replace($wpconfig,$rule,$pos+1+strlen($insert_after),0);
      }
      file_put_contents($wpconfig_path, $wpconfig);
      if ($this->debug) $this->trace_log("wp config server variable fix inserted");

      $this->save_options();
  }


protected function get_server_variable_fix_code(){
  if ($this->set_rewriterule_per_site && $this->is_multisite_subfolder_install()) {
      if ($this->debug) $this->trace_log("per site activation on subfolder install, wp config server variable fix skipped");
      return "";
  }

  if (is_multisite() && $this->set_rewriterule_per_site && count($this->sites)==0) {
    if ($this->debug) $this->trace_log("no sites left with ssl, wp config server variable fix skipped");
    return "";
  }

  if (is_multisite() && $this->set_rewriterule_per_site) {
    $rule  = "\n"."//Begin Really Simple SSL Server variable fix"."\n";
    foreach ($this->sites as $domain ) {
        //remove http or https.
        if ($this->debug) {$this->trace_log("getting server variable rule for:".$domain);}
        $domain = preg_replace("/(http:\/\/|https:\/\/)/","",$domain);

        //we excluded subfolders, so treat as domain
        //check only for domain without www, as the www variant is found as well with the no www search.
        $domain_no_www  = str_replace ( "www." , "" , $domain);

        $rule .= 'if ( strpos($_SERVER["HTTP_HOST"], "'.$domain_no_www.'")!==FALSE ) {'."\n";
        $rule .= '   $_SERVER["HTTPS"] = "on";'."\n";
        $rule .= '}'."\n";
    }
    $rule .= "//END Really Simple SSL"."\n";
  } else {
    $rule  = "\n"."//Begin Really Simple SSL Server variable fix"."\n";
    $rule .= '$_SERVER["HTTPS"] = "on";'."\n";
    $rule .= "//END Really Simple SSL"."\n";
  }

  return $rule;
}

  /**
   * Removing changes made to the wpconfig
   *
   * @since  2.1
   *
   * @access public
   *
   */

  public function remove_wpconfig_edit() {

    $wpconfig_path = $this->find_wp_config_path();
    if (empty($wpconfig_path)) return;
    $wpconfig = file_get_contents($wpconfig_path);

    //check for permissions
    if (!is_writable($wpconfig_path)) {
      if ($this->debug) $this->trace_log("could not remove wpconfig edits, wp-config.php not writable");
      $this->errors['wpconfig not writable'] = TRUE;
      return;
    }

    //remove edits
    $wpconfig = preg_replace("/\/\/Begin\s?Really\s?Simple\s?SSL.*?\/\/END\s?Really\s?Simple\s?SSL/s", "", $wpconfig);
    $wpconfig = preg_replace("/\n+/","\n", $wpconfig);
    file_put_contents($wpconfig_path, $wpconfig);

    //in multisite environment, with per site activation, re-add
    if (is_multisite() && $this->set_rewriterule_per_site) {

      if ($this->do_wpconfig_loadbalancer_fix)
        $this->wpconfig_loadbalancer_fix();

      if ($this->no_server_variable)
        $this->wpconfig_server_variable_fix();

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
      if ($this->debug) {$this->trace_log("converting siteurl and homeurl to https");}
      $siteurl_ssl = str_replace ( "http://" , "https://" , get_option('siteurl'));
      $homeurl_ssl = str_replace ( "http://" , "https://" , get_option('home'));
      update_option('siteurl',$siteurl_ssl);
      update_option('home',$homeurl_ssl);
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
    if (is_multisite()) {
      $sites = wp_get_sites();
      foreach ( $sites as $site ) {
          switch_to_blog( $site[ 'blog_id' ] );

          $siteurl_no_ssl = str_replace ( "https://" , "http://" , get_option('siteurl'));
          $homeurl_no_ssl = str_replace ( "https://" , "http://" , get_option('home'));
          update_option('siteurl',$siteurl_no_ssl);
          update_option('home',$homeurl_no_ssl);

          restore_current_blog(); //switches back to previous blog, not current, so we have to do it each loop
        }
    } else {
      $siteurl_no_ssl = str_replace ( "https://" , "http://" , get_option('siteurl'));
      $homeurl_no_ssl = str_replace ( "https://" , "http://" , get_option('home'));
      update_option('siteurl',$siteurl_no_ssl);
      update_option('home',$homeurl_no_ssl);
    }
  }

  /**
   * Save the plugin options
   *
   * @since  2.0
   *
   * @access public
   *
   */

  public function save_options() {

    //any options added here should also be added to function options_validate()
    $options = array(
      'force_ssl_without_detection'       => $this->force_ssl_without_detection,
      'site_has_ssl'                      => $this->site_has_ssl,
      'hsts'                              => $this->hsts,
      'ssl_fail_message_shown'            => $this->ssl_fail_message_shown,
      'ssl_success_message_shown'         => $this->ssl_success_message_shown,
      'autoreplace_insecure_links'        => $this->autoreplace_insecure_links,
      'plugin_db_version'                 => $this->plugin_db_version,
      'wpconfig_issue'                    => $this->wpconfig_issue,
      'wpconfig_loadbalancer_fix_failed'  => $this->wpconfig_loadbalancer_fix_failed,
      'wpconfig_server_variable_fix_failed'  => $this->wpconfig_server_variable_fix_failed,
      'set_rewriterule_per_site'          => $this->set_rewriterule_per_site,
      'debug'                             => $this->debug,
      'do_not_edit_htaccess'              => $this->do_not_edit_htaccess,
    );
    update_option('rlrsssl_options',$options);
  }

  /**
   * Load the translation files
   *
   * @since  1.0
   *
   * @access public
   *
   */

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
    $this->remove_ssl_from_siteurl();
    $this->remove_ssl_from_siteurl_in_wpconfig();
    $this->force_ssl_without_detection          = FALSE;
    $this->site_has_ssl                         = FALSE;
    $this->hsts                                 = FALSE;
    $this->ssl_fail_message_shown               = FALSE;
    $this->ssl_success_message_shown            = FALSE;
    $this->autoreplace_insecure_links           = TRUE;
    $this->do_not_edit_htaccess                 = FALSE;
    $this->wpconfig_server_variable_fix_failed  = FALSE;
    $this->save_options();

    //when on multisite, per site activation, recreate domain list for htaccess and wpconfig rewrite actions
    if ($this->set_rewriterule_per_site) $this->build_domain_list();

    $this->remove_wpconfig_edit();
    $this->removeHtaccessEdit();
  }

  /**
   * Checks if the curl function is available
   *
   * @since  2.1
   *
   * @access public
   *
   */

  public function is_curl_installed() {
  	if  (in_array  ('curl', get_loaded_extensions())) {
  		return true;
  	}
  	else {
      if ($this->debug) {$this->trace_log("curl not installed on this server...");}
  		return false;
  	}
  }


  /**
   * Checks if we are currently on ssl protocol, but extends standard wp with loadbalancer check.
   *
   * @since  2.0
   *
   * @access public
   *
   */

  public function is_ssl_extended(){
    if(!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' || !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on') {
      $loadbalancer = TRUE;
    }
    else {
      $loadbalancer = FALSE;
    }

    if (is_ssl() || $loadbalancer){
      return true;
    } else {
      return false;
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

  public function get_url_contents($url) {
    $this->curl_installed = $this->is_curl_installed();

    //preferrably with curl, but else with file get contents
    if ($this->curl_installed) {

        $ch = curl_init();
        $timeout = 5;
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
        curl_setopt($ch,CURLOPT_CONNECTTIMEOUT,$timeout);
        curl_setopt($ch,CURLOPT_FRESH_CONNECT, TRUE);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST, false);

        $filecontents = curl_exec($ch);
        if(curl_errno($ch)) {
          $this->error_number = curl_errno($ch);
        } else {
          $this->error_number = 0;
        }

        curl_close($ch);
      } else {
        set_error_handler(array($this,'custom_error_handling'));
        $filecontents = file_get_contents($url);
        //errors back to normal
        restore_error_handler();
      }
      return $filecontents;
  }


  /**
   * Checks for SSL by opening a test page in the plugin directory
   *
   * @since  2.0
   *
   * @access public
   *
   */

  public function detect_configuration() {
    if ($this->debug) {$this->trace_log("testing for ssl...");}
    $old_ssl_setting = $this->site_has_ssl;
    $plugin_url = str_replace ( "http://" , "https://" , $this->plugin_url);
    $testpage_url = trailingslashit($plugin_url)."ssl-test-page.php";

    $filecontents = $this->get_url_contents($testpage_url);

    /*
          also check if we are currently on ssl protocol,
          so if an error occurs but is_ssl_extended returns true,
          the site_has_ssl will still be set to true.
          Cases were reported where a wp admin page on https returned a "no ssl detected message"
          This cannot happen with this extra check
    */

    if(($this->error_number!=0) && !$this->is_ssl_extended()){
      $this->site_has_ssl = FALSE;
    } else {
      $this->site_has_ssl = TRUE;
    }

    if($this->error_number!=0) {
      $errormsg = $this->get_curl_error($this->error_number);
    }

    if ($this->debug) {
      if(!$this->site_has_ssl && ($this->error_number!=0)) {
        //no page loaded, so show error
        $this->trace_log("The ssl page returned an error: ".$errormsg);
      }elseif($this->site_has_ssl && ($this->error_number!=0)){
        //we do have ssl, but the page did not load
        $this->trace_log("We do have ssl, but the test page loaded with an error: ".$errormsg);
      } else{
        $this->trace_log("SSL test page loaded successfully");
      }
    }

    if ($this->site_has_ssl) {
      //check the type of ssl
      if (strpos($filecontents, "#LOADBALANCER#") !== false) {
        $this->ssl_type = "LOADBALANCER";
        //check for is_ssl()
        if ((strpos($filecontents, "#SERVER-HTTPS-ON#") === false) &&
            (strpos($filecontents, "#SERVER-HTTPS-1#") === false) &&
            (strpos($filecontents, "#SERVERPORT443#") === false)) {
          //when Loadbalancer is detected, but is_ssl would return false, we should add some code to wp-config.php
          $this->do_wpconfig_loadbalancer_fix = TRUE;
        }
      } elseif (strpos($filecontents, "#CDN#") !== false) {
        $this->ssl_type = "CDN";
      } elseif (strpos($filecontents, "#SERVER-HTTPS-ON#") !== false) {
        $this->ssl_type = "SERVER-HTTPS-ON";
      } elseif (strpos($filecontents, "#SERVER-HTTPS-1#") !== false) {
        $this->ssl_type = "SERVER-HTTPS-1";
      } elseif (strpos($filecontents, "#SERVERPORT443#") !== false) {
        $this->ssl_type = "SERVERPORT443";
      } elseif (strpos($filecontents, "#NO KNOWN SSL CONFIGURATION DETECTED#") !== false) {
        if ($this->debug) {$this->trace_log("No server variables for ssl are set, so we have to force in wpconfig");}
        //if we are here, SSL was detected, but without any known server variables set.
        //So we can use this info to set a server variable ourselfes.
        $this->no_server_variable = TRUE;
        $this->ssl_type = "NA";
      }else {
        //no valid response, so set to NA
        $this->ssl_type = "NA";
      }
	    if ($this->debug) {$this->trace_log("ssl type: ".$this->ssl_type);}
    }
    if ($old_ssl_setting != $this->site_has_ssl) {
      	//value has changed, note this so we can flush the cache later.
		    if ($this->debug) {$this->trace_log("ssl setting changed...");}
      	add_option('really_simple_ssl_settings_changed', 'settings_changed' );
    }

    $this->save_options();
  }

  /**
   * Test if the htaccess redirect will work
   * This way, no redirect loops occur.
   *
   * @since  2.1
   *
   * @access public
   *
   */

  public function test_htaccess_redirect() {
    if (!current_user_can($this->capability)) return;
	  if ($this->debug) {$this->trace_log("testing htaccess rules...");}
    $filecontents = "";
    $plugin_url = str_replace ( "http://" , "https://" , $this->plugin_url);
    $testpage_url = $plugin_url."testssl/";
    switch ($this->ssl_type) {
    case "LOADBALANCER":
        $testpage_url .= "loadbalancer";
        break;
    case "CDN":
        $testpage_url .= "cdn";
        break;
    case "SERVER-HTTPS-ON":
        $testpage_url .= "serverhttpson";
        break;
    case "SERVER-HTTPS-1":
        $testpage_url .= "serverhttps1";
        break;
    case "SERVERPORT443":
        $testpage_url .= "serverport443";
        break;
    }

    $testpage_url .= ("/ssl-test-page.html");

    $filecontents = $this->get_url_contents($testpage_url);
    if (($this->error_number==0) && (strpos($filecontents, "#SSL TEST PAGE#") !== false)) {
      $this->htaccess_test_success = TRUE;
		  if ($this->debug) {$this->trace_log("htaccess rules test success.");}
    }else{
      //.htaccess rewrite rule seems to be giving problems.
      if ($this->ssl_type)
      $this->htaccess_test_success = FALSE;
      if ($this->debug) {
        if ($this->error_number!=0) {
            $this->trace_log("htaccess rules test failed with error: ".$this->get_curl_error($this->error_number));
        } else {
          $this->trace_log("htaccess test rules failed.");
        }
      }
    }

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
     $this->plugin_url = trailingslashit(plugin_dir_url( __FILE__ ));
     //do not force to ssl yet, we need it also in non ssl situations.
     $home = home_url();

     //however strange, in some case we get a relative url here, so we check that.
     //we compare to urls replaced to https, in case one of them is still on http.
 	   if (strpos(str_replace("http://","https://",$this->plugin_url),str_replace("http://","https://",$home))===FALSE) {
       //make sure we do not have a slash at the start
       $this->plugin_url = ltrim($this->plugin_url,"/");
       $this->plugin_url = trailingslashit($home).$this->plugin_url;
     }

 	   if ($this->debug) {$this->trace_log("pluginurl: ".$this->plugin_url);}
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
      if(file_exists($this->ABSpath.".htaccess") && is_writable($this->ABSpath.".htaccess")){
        $htaccess = file_get_contents($this->ABSpath.".htaccess");


        //if multisite, per site activation and more than one blog remaining on ssl, remove condition for this site only 
        //the domain list has been rebuilt already, so current site is already removed.
        if (is_multisite() && $this->set_rewriterule_per_site && count($this->sites)>0) {
          //remove http or https.
          $domain = preg_replace("/(http:\/\/|https:\/\/)/","",home_url());
          $pattern = "/#wpmu\srewritecond\s?".preg_quote($domain, "/")."\n.*?#end\swpmu\srewritecond\s?".preg_quote($domain, "/")."\n/s";

          //only remove if the pattern is there at all
          if (preg_match($pattern, $htaccess)) $htaccess = preg_replace($pattern, "", $htaccess);
          //now replace any remaining "or" on the last condition.
          $pattern = "/(\[OR\])(?!.*(\[OR\]|#start).*?RewriteRule)/s";
          $htaccess = preg_replace($pattern, "", $htaccess,1);

        } else {
          // remove everything
          $pattern = "/#\s?BEGIN\s?rlrssslReallySimpleSSL.*?#\s?END\s?rlrssslReallySimpleSSL/s";
          //only remove if the pattern is there at all
          if (preg_match($pattern, $htaccess)) $htaccess = preg_replace($pattern, "", $htaccess);

        }

        $htaccess = preg_replace("/\n+/","\n", $htaccess);
        file_put_contents($this->ABSpath.".htaccess", $htaccess);
        //THIS site is not redirected in htaccess anymore.
        $this->ssl_redirect_set_in_htaccess =  FALSE;
        $this->save_options();
      } else {
        $this->errors['htaccess not writable'] = TRUE;
        if ($this->debug) $this->trace_log("could not remove rules from htaccess, file not writable");
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
   * Checks if the hsts rule is already in the htaccess file
   * Set the hsts variable in the db accordingly
   *
   * @since  2.1
   *
   * @access public
   *
   */

  public function contains_hsts($htaccess) {
    preg_match("/Header always set Strict-Transport-Security/", $htaccess, $check);
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
      if (!current_user_can($this->capability)) return;
      //check if htacces exists and  if htaccess is writable
      //update htaccess to redirect to ssl and set redirect_set_in_htaccess
      $this->ssl_redirect_set_in_htaccess =  FALSE;

      if($this->debug) $this->trace_log("checking if .htaccess can or should be edited...");

      //does it exist?
      if (!file_exists($this->ABSpath.".htaccess")) {
        if($this->debug) $this->trace_log(".htaccess not found.");
        return;
      }

      //do we want to edit?
      if ($this->do_not_edit_htaccess) {
        if($this->debug) $this->trace_log("Edit of .htaccess blocked by setting or define 'do not edit htaccess' in really simple ssl.");
        return;
      }

      $htaccess = file_get_contents($this->ABSpath.".htaccess");

      if(!$this->contains_rsssl_rules($htaccess)){
        //really simple ssl rules not in the file, so add if writable.
        if ($this->debug) {$this->trace_log("no rules there, adding rules...");}

        if (!is_writable($this->ABSpath.".htaccess")) {
          if($this->debug) $this->trace_log(".htaccess not writable.");
          return;
        }

        $rules = $this->get_redirect_rules();
        $htaccess = $htaccess.$rules;
        file_put_contents($this->ABSpath.".htaccess", $htaccess);
      //for the time being we do not update the .htaccess on version basis

      // elseif ($this->set_rewriterule_per_site || $this->contains_previous_version($htaccess) || ($this->hsts!=$this->contains_hsts($htaccess)))
      } elseif ($this->set_rewriterule_per_site || ($this->hsts!=$this->contains_hsts($htaccess))) {
        /*
            Remove all rules and add new IF
            -> (temporarily removed) old version,
            or the hsts option has changed, so we need to edit the htaccess anyway.
            or rewrite per site (if a site is added or removed on per site activated
            mulsite we need to rewrite even if the rules are already there.)
        */

        if ($this->debug) {$this->trace_log("per site activation or hsts option change, updating htaccess...");}

        if (!is_writable($this->ABSpath.".htaccess")) {
          if($this->debug) $this->trace_log(".htaccess not writable.");
          return;
        }

	      $htaccess = preg_replace("/#\s?BEGIN\s?rlrssslReallySimpleSSL.*?#\s?END\s?rlrssslReallySimpleSSL/s", "", $htaccess);
        $htaccess = preg_replace("/\n+/","\n", $htaccess);

        $rules = $this->get_redirect_rules();
        $htaccess = $htaccess.$rules;
        file_put_contents($this->ABSpath.".htaccess", $htaccess);
      } else {
        if ($this->debug) {$this->trace_log("rules already added in .htaccess.");}
        $this->ssl_redirect_set_in_htaccess =  TRUE;
        //all is well.
      }

  }


  /**
   * Test if a domain has a subfolder structure
   *
   * @since  2.2
   *
   * @param string $domain
   *
   * @access private
   *
   */

  private function is_subfolder($domain) {
      //remove slashes of the http(s)
      $domain = preg_replace("/(http:\/\/|https:\/\/)/","",$domain);
      if (strpos($domain,"/")!==FALSE) {
        return true;
      }

      return false;
  }

  /**
   * Create redirect rules for the .htaccess.
   *
   * @since  2.1
   *
   * @access public
   *
   */

  public function get_redirect_rules($manual=false) {
      if (!current_user_can($this->capability)) return;

      if ($this->set_rewriterule_per_site && $this->is_multisite_subfolder_install()) {
          if ($this->debug) $this->trace_log("per site activation on subfolder install, adding of htaccess adding skipped");
          $this->ssl_redirect_set_in_htaccess = false;
          return "";
      }

      //only add the redirect rules when a known type of ssl was detected. Otherwise, we use https.
      $rule="\n";

      $rule .= "# BEGIN rlrssslReallySimpleSSL rsssl_version[".$this->plugin_version."]\n";

      //if the htaccess test was successfull, and we know the redirectype, edit
      if ($manual || ($this->htaccess_test_success && ($this->ssl_type != "NA"))) {
        //set redirect_set_in_htaccess to true, because we are now making a redirect rule.
        if (!$manual) {
          $this->ssl_redirect_set_in_htaccess = TRUE;
        }

        $rule .= "<IfModule mod_rewrite.c>"."\n";
        $rule .= "RewriteEngine on"."\n";

        //select rewrite condition based on detected type of ssl
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

        //if multisite, and NOT subfolder install (checked for in the detec_config function)
        //, add a condition so it only applies to sites where plugin is activated
        if (is_multisite() && $this->set_rewriterule_per_site) {
          //disable hsts, because other sites on the network would be forced on ssl as well
          $this->hsts = FALSE;
          if ($this->debug) {$this->trace_log("multisite, per site activation");}

          foreach ($this->sites as $domain ) {
              if ($this->debug) {$this->trace_log("adding condition for:".$domain);}

              //remove http or https.
              $domain = preg_replace("/(http:\/\/|https:\/\/)/","",$domain);
              //We excluded subfolders, so treat as domain

              $domain_no_www  = str_replace ( "www." , "" , $domain);
              $domain_yes_www = "www.".$domain_no_www;

              $rule .= "#wpmu rewritecond ".$domain."\n";
              $rule .= "RewriteCond %{HTTP_HOST} ^".preg_quote($domain_no_www, "/")." [OR]"."\n";
              $rule .= "RewriteCond %{HTTP_HOST} ^".preg_quote($domain_yes_www, "/")." [OR]"."\n";
              $rule .= "#end wpmu rewritecond ".$domain."\n";

          }

          //now remove last [OR] if at least on one site the plugin was activated, so we have at lease one condition
          if (count($this->sites)>0) {
            $rule = strrev(implode("", explode(strrev("[OR]"), strrev($rule), 2)));
          }
        } else {
          if ($this->debug) {$this->trace_log("single site or networkwide activation");}
        }
        $rule .= "RewriteRule ^(.*)$ https://%{HTTP_HOST}$1 [R=301,L]"."\n";
        $rule .= "</IfModule>"."\n";
      } else {
        $this->ssl_redirect_set_in_htaccess = FALSE;
      }

      if ($this->hsts && !is_multisite()) {
        //owasp security best practice https://www.owasp.org/index.php/HTTP_Strict_Transport_Security
        $rule .= "<IfModule mod_headers.c>"."\n";
        $rule .= "Header always set Strict-Transport-Security 'max-age=31536000' env=HTTPS"."\n";
        $rule .= "</IfModule>"."\n";
      }

      $rule .= "# END rlrssslReallySimpleSSL"."\n";

      $rule = preg_replace("/\n+/","\n", $rule);
      return $rule;
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
  /*
      This message is shown when no ssl is detected.
  */

  if (!($this->site_has_ssl || $this->force_ssl_without_detection)  && !$this->ssl_fail_message_shown) {

    parse_str($_SERVER['QUERY_STRING'], $params);
    add_action('admin_print_footer_scripts', array($this, 'insert_dismiss_fail'));
      ?>
      <div id="message" class="error fade notice is-dismissible rlrsssl-fail">
      <p>
        <?php _e("No SSL was detected. If you are just waiting for your ssl certificate to kick in you can dismiss this warning.","rlrsssl-really-simple-ssl");?>
      </p>
      <a href="options-general.php?page=rlrsssl_really_simple_ssl"><?php echo __("Scan SSL setup again","rlrsssl-really-simple-ssl");?></a>
      </div>
      <?php
  }

  if ($this->site_has_ssl && isset($this->errors["DEACTIVATE_FILE_NOT_RENAMED"]) && $this->errors["DEACTIVATE_FILE_NOT_RENAMED"]) {
    ?>
    <div id="message" class="error fade notice is-dismissible rlrsssl-fail">
      <h1>
        <?php _e("Major security issue!","rlrsssl-really-simple-ssl");?>
      </h1>
      <p>
    <?php _e("The 'force-deactivate.php' file has to be renamed to .txt. Otherwise your ssl can be deactived by anyone on the internet.","rlrsssl-really-simple-ssl");?>
    </p>
    <a href="options-general.php?page=rlrsssl_really_simple_ssl"><?php echo __("Check again","rlrsssl-really-simple-ssl");?></a>
    </div>
    <?php
  }
  /*
    encourage network wide for subfolder install.
  */

  if ($this->set_rewriterule_per_site && $this->is_multisite_subfolder_install()) {
    ?>
    <div id="message" class="error fade notice">
      <p>
        <?php _e('You run a Multisite installation with subfolders, which prevents this plugin from handling the .htaccess.','rlrsssl-really-simple-ssl');?>
        <?php _e('Because the domain is the same on all sites. You can just as easily activate ssl on all your sites.','rlrsssl-really-simple-ssl');?>
        <?php _e('So to get rid of this annoying message, just activate networkwide.','rlrsssl-really-simple-ssl');?>
      </p>
    </div>
    <?php
  }

  /*
      SSL success message
  */

  if ($this->site_has_ssl && !$this->ssl_success_message_shown) {
        add_action('admin_print_footer_scripts', array($this, 'insert_dismiss_success'));
        ?>
        <div id="message" class="updated fade notice is-dismissible rlrsssl-success">
          <p>
            <?php echo __("SSl was detected and successfully activated!","rlrsssl-really-simple-ssl");?>
          </p>
        </div>
        <?php
  }

  //some notices for ssl situations
  if ($this->site_has_ssl || $this->force_ssl_without_detection) {

      if (sizeof($this->plugin_conflict)>0) {
        if (isset($this->plugin_conflict["YOAST_FORCE_REWRITE_TITLE"]) && $this->plugin_conflict["YOAST_FORCE_REWRITE_TITLE"]) {
            ?>
            <div id="message" class="error fade notice"><p>
            <?php _e("Really Simple SSL has a conflict with another plugin.","rlrsssl-really-simple-ssl");?><br>
            <?php _e("The force rewrite titles option in Yoast SEO prevents Really Simple SSL plugin from fixing mixed content.","rlrsssl-really-simple-ssl");?><br>
            <a href="admin.php?page=wpseo_titles"><?php _e("Show me this setting","rlrsssl-really-simple-ssl");?></a>

            </p></div>
            <?php
          }
        if (isset($this->plugin_conflict["WOOCOMMERCE_FORCEHTTP"]) && $this->plugin_conflict["WOOCOMMERCE_FORCEHTTP"] && isset($this->plugin_conflict["WOOCOMMERCE_FORCESSL"]) && $this->plugin_conflict["WOOCOMMERCE_FORCESSL"]) {
          ?>
          <div id="message" class="error fade notice"><p>
          <?php _e("Really Simple SSL has a conflict with another plugin.","rlrsssl-really-simple-ssl");?><br>
          <?php _e("The force http after leaving checkout in Woocommerce will create a redirect loop.","rlrsssl-really-simple-ssl");?><br>
          <a href="admin.php?page=wc-settings&tab=checkout"><?php _e("Show me this setting","rlrsssl-really-simple-ssl");?></a>

          </p></div>
          <?php
        }
      }

      if ($this->wpconfig_issue) {
        ?>
        <div id="message" class="error fade notice"><p>
        <?php echo __("We detected a definition of siteurl or homeurl in your wp-config.php, but the file is not writable. Because of this, we cannot set the siteurl to https.","rlrsssl-really-simple-ssl");?>
        </p>
        <a href="options-general.php?page=rlrsssl_really_simple_ssl"><?php echo __("Check again","rlrsssl-really-simple-ssl");?></a>
        </div>
        <?php
      }

      if ($this->wpconfig_loadbalancer_fix_failed) {
        ?>
        <div id="message" class="error fade notice"><p>
        <?php echo __("Because your site is behind a loadbalancer and is_ssl() returns false, you should add the following line of code to your wp-config.php. Your wp-config.php could not be written automatically.","rlrsssl-really-simple-ssl");?>

        <br><br><code>
            //Begin Really Simple SSL Load balancing fix <br>
            if (isset($_SERVER["HTTP_X_FORWARDED_PROTO"] ) &amp;&amp; "https" == $_SERVER["HTTP_X_FORWARDED_PROTO"] ) {<br>
            &nbsp;&nbsp;$_SERVER["HTTPS"] = "on";<br>
          }<br>
            //END Really Simple SSL
        </code><br>
        </p>
        <a href="options-general.php?page=rlrsssl_really_simple_ssl"><?php echo __("Check again","rlrsssl-really-simple-ssl");?></a>
        </div>
        <?php
      }

      if ($this->wpconfig_server_variable_fix_failed) {
        ?>
        <div id="message" class="error fade notice"><p>
        <?php echo __('Because your server does not pass the $_SERVER["HTTPS"] variable, Wordpress cannot function on SSL. You should add the following line of code to your wp-config.php. Your wp-config.php could not be written automatically.','rlrsssl-really-simple-ssl');?>
        <br><br><code>
            <?php
            $rule = $this->get_server_variable_fix_code();
            $arr_search = array("<",">","\n");
            $arr_replace = array("&lt","&gt","<br>");
            echo str_replace($arr_search, $arr_replace, $rule);
            ?>
        </code><br>
        </p>
        <a href="options-general.php?page=rlrsssl_really_simple_ssl"><?php echo __("Check again","rlrsssl-really-simple-ssl");?></a>
        </div>
        <?php
      }
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

/**
 * Insert some ajax script to dismis the ssl fail message, and stop nagging about it
 *
 * @since  2.0
 *
 * @access public
 *
 */

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

/**
 * Process the submits from the warning popups, these are outside any form so need to be processed differently
 *
 * @since  2.1
 *
 * @access public
 *
 */

public function process_submit_without_form() {
    if ( isset($_GET['rlrsssl_fixposts']) && '1' == $_GET['rlrsssl_fixposts'] ) {
      //$database->fix_insecure_post_links();
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
  if (!current_user_can($this->capability)) return;
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
        'id'	=> "Mixed content fix",
        'title'	=> __("Mixed content fixer","rlrsssl-really-simple-ssl"),
        'content'	=> '<p>' . __("In most sites, a lot of links are saved into the content, pluginoptions or even worse, in the theme. When you switch to ssl , these are still http, instead of https. To ensure a smooth transition, this plugin auto replaces all these links. If you see in the scan results that you have fixed most of these links, you can try to run your site without this replace script, which will give you a small performance advantage. If you do not have a lot of reported insecure links, you can try this. If you encounter mixed content warnings, just switch it back on. <br><br><b>How to check for mixed content?</b><br>Go to the the front end of your website, and click on the lock in your browser's address bar. When you have mixed content, this lock is not closed, or has a red cross over it.","rlrsssl-really-simple-ssl") . '</p>',
    ) );

    $screen->add_help_tab( array(
        'id'	=> "HSTS",
        'title'	=> __("HTTP Strict Transport Security (HSTS)","rlrsssl-really-simple-ssl"),
        'content'	=> '<p>' . __("Using this option will prevent users from visiting your website over http for one year, so use this option with caution! HTTP Strict Transport Security (HSTS) is an opt-in security enhancement that is specified by a web application through the use of a special response header. Once a supported browser receives this header that browser will prevent any communications from being sent over HTTP to the specified domain and will instead send all communications over HTTPS. It also prevents HTTPS click through prompts on browsers. ","rlrsssl-really-simple-ssl") . '</p>',
    ) );

    $screen->add_help_tab( array(
        'id'	=> "ssl_certificate",
        'title'	=> __("How to get an SSL certificate","rlrsssl-really-simple-ssl"),
        'content'	=> '<p>' . __("To secure your site with ssl, you need an SSL certificate. How you can get a certificate depends on your hosting provider, but can often be requested on the control panel of your website. If you are not sure what to do, you can contact your hosting provider.","rlrsssl-really-simple-ssl") . '</p>',
    ) );
/*
    $screen->add_help_tab( array(
        'id'	=> "force_ssl_without_detection",
        'title'	=> __("Force SSL without detection","rlrsssl-really-simple-ssl"),
        'content'	=> '<p>' . __("This plugin tries to open a page within the plugin directory over https. If that fails, it is assumed that ssl is not availble. But as this may not cover all eventualities, it is possible to force the site over ssl anyway.<br><br> If you force your site over ssl without a valid ssl certificate, your site may break. In that case, remove the 'really simple ssl' rules from your .htaccess file (if present), and remove or rename the really simple ssl plugin.","rlrsssl-really-simple-ssl") . '</p>',
    ) );
*/
}

  /**
   * Create tabs on the settings page
   *
   * @since  2.1
   *
   * @access public
   *
   */

  public function admin_tabs( $current = 'homepage' ) {
      $tabs = array( 'configuration' => __("Configuration","rlrsssl-really-simple-ssl"),'settings'=>__("Settings","rlrsssl-really-simple-ssl"), 'mixedcontent' => __("Detected mixed content","rlrsssl-really-simple-ssl"), 'debug' => __("Debug","rlrsssl-really-simple-ssl") );
      echo '<h2 class="nav-tab-wrapper">';

      foreach( $tabs as $tab => $name ){
          $class = ( $tab == $current ) ? ' nav-tab-active' : '';
          echo "<a class='nav-tab$class' href='?page=rlrsssl_really_simple_ssl&tab=$tab'>$name</a>";

      }
      echo '</h2>';
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
  if (!current_user_can($this->capability)) return;
  if ( isset ( $_GET['tab'] ) ) $this->admin_tabs($_GET['tab']); else $this->admin_tabs('configuration');
  if ( isset ( $_GET['tab'] ) ) $tab = $_GET['tab']; else $tab = 'configuration';

  //only add scan ajax script if scan was activated
  if (isset($this->scan)) {
    add_action('admin_print_footer_scripts', array($this->scan, 'insert_scan'));
  }

  switch ( $tab ){
      case 'configuration' :

      /*
              First tab, configuration
      */

      ?>
        <h2><?php echo __("Detected setup","rlrsssl-really-simple-ssl");?></h2>
        <table class="really-simple-ssl-table" <?php if ($this->site_has_ssl||$this->force_ssl_without_detection) {echo 'id="scan-result"';}?>>
          <tr>
            <td><?php echo $this->site_has_ssl ? $this->img("success") : $this->img("error");?></td>
            <td><?php
                    if (!$this->site_has_ssl) {
                      if (!$this->force_ssl_without_detection)
                        echo __("No SSL detected.","rlrsssl-really-simple-ssl")."&nbsp;";
                      else
                        echo __("No SSL detected, but SSL is forced.","rlrsssl-really-simple-ssl")."&nbsp;";
                    }
                    else {
                      //ssl detected, no problems!
                      _e("An SSL certificate was detected on your site. ","rlrsssl-really-simple-ssl");
                    }
                ?>
              </td><td></td>
          </tr>
          <?php if($this->site_has_ssl || $this->force_ssl_without_detection) { ?>
          <tr>
            <td>
              <?php echo ($this->ssl_redirect_set_in_htaccess || $this->do_not_edit_htaccess) ? $this->img("success") :$this->img("warning");?>
            </td>
            <td>
            <?php
                if($this->ssl_redirect_set_in_htaccess) {
                 _e("https redirect set in .htaccess","rlrsssl-really-simple-ssl");
              } elseif ($this->do_not_edit_htaccess) {
                 _e("Editing of .htaccess is blocked in Really Simple ssl settings, so you're in control of the .htaccess file.","rlrsssl-really-simple-ssl");
              } else {
                 if (!is_writable($this->ABSpath.".htaccess")) {
                   _e("Https redirect was set in javascript because the .htaccess was not writable. Set manually if you want to redirect in .htaccess.","rlrsssl-really-simple-ssl");
                 } elseif($this->set_rewriterule_per_site && $this->is_multisite_subfolder_install()) {
                   _e("Https redirect was set in javascript because you have activated per site on a multiste subfolder install. Install networkwide to set the .htaccess redirect.","rlrsssl-really-simple-ssl");
                 } else {
                   _e("Https redirect was set in javascript because the htaccess redirect rule could not be verified. Set manually if you want to redirect in .htaccess.","rlrsssl-really-simple-ssl");
                }
                 if ($this->ssl_type!="NA") {
                    $manual = true;
                    $rules = $this->get_redirect_rules($manual);
                    echo "&nbsp;";
                    $arr_search = array("<",">","\n");
                    $arr_replace = array("&lt","&gt","<br>");
                    $rules = str_replace($arr_search, $arr_replace, $rules);
                    _e("Try to add these rules at the bottom of your .htaccess. If it doesn't work, just remove them again.","rlrsssl-really-simple-ssl");
                     ?>
                     <br><br><code>
                         <?php echo $rules; ?>
                       </code>
                     <?php
                  }
              }
            ?>
            </td><td></td>
          </tr>

          <?php
          //HSTS on per site activated multisite is not possible.
          if (!(is_multisite() && $this->set_rewriterule_per_site)) {
          ?>
          <tr>
            <td>
              <?php echo $this->hsts ? $this->img("success") :$this->img("warning");?>
            </td>
            <td>
            <?php
              if($this->hsts) {
                 _e("HTTP Strict Transport Security was set in the .htaccess","rlrsssl-really-simple-ssl");
              } else {
                 _e("HTTP Strict Transport Security was not set in your .htaccess. Do this only if your setup is fully working, and only when you do not plan to revert to http.","rlrsssl-really-simple-ssl");
                 ?>
                <br>
                <a href="https://en.wikipedia.org/wiki/HTTP_Strict_Transport_Security" target="_blank"><?php _e("More info about HSTS","rlrsssl-really-simple-ssl");?></a>
                 <?php
              }
            ?>
            </td><td><a href="?page=rlrsssl_really_simple_ssl&tab=settings"><?php _e("Manage settings","rlrsssl-really-simple-ssl");?></a></td>
          </tr>

          <?php
              }
          }
          ?>

        </table>
    <?php
      break;
      case 'settings' :
      /*
              Second tab, Settings
      */
    ?>
        <form action="options.php" method="post">
        <?php
            settings_fields('rlrsssl_options');
            do_settings_sections('rlrsssl');
        ?>

        <input class="button button-primary" name="Submit" type="submit" value="<?php echo __("Save","rlrsssl-really-simple-ssl"); ?>" />
        </form>
      <?php
        break;
      case 'mixedcontent' :
      /*
        third tab: scan of mixed content
      */

        if ($this->site_has_ssl || $this->force_ssl_without_detection) {
          ?>
          <table id="scan-list"><tr><td colspan="3"></td></tr></table>
          <?php
        } else {
          echo "<p>".__("The mixed content scan is available when SSL is detected or forced.","rlrsssl-really-simple-ssl")."</p>";
        }

        break;
      case 'debug' :
      /*
        fourth tab: debug
      */
         ?>
    <div>
      <?php
      if ($this->debug) {
        echo "<h2>".__("Log for debugging purposes","rlrsssl-really-simple-ssl")."</h2>";
        echo "<p>".__("Send me a copy of these lines if you have any issues. The log will be erased when debug is set to false","rlrsssl-really-simple-ssl")."</p>";
        echo "<div class='debug-log'>";
        echo $this->debug_log;
        echo "</div>";
        $this->debug_log.="<br><b>-----------------------</b>";
        $this->save_options();
      }
      else {
        _e("To view results here, enable the debug option in the settings tab.","rlrsssl-really-simple-ssl");
      }

       ?>
    </div>
    <?php
    break;
  }
     ?>
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
  wp_enqueue_style( 'rlrsssl-css');
}

  /**
   * Initialize admin errormessage, settings page
   *
   * @since  2.0
   *
   * @access public
   *
   */

public function setup_admin_page(){
  if (current_user_can($this->capability)) {
    add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
    add_action('admin_init', array($this, 'load_translation'),20);

    //settings page, from creation and settings link in the plugins page
    add_action('admin_menu', array($this, 'add_settings_page'),40);
    add_action('admin_init', array($this, 'create_form'),40);

    $plugin = $this->plugin_dir."/".$this->plugin_filename;
    add_filter("plugin_action_links_$plugin", array($this,'plugin_settings_link'));

    //actions submitted outside the form
    add_action('admin_init', array($this, 'process_submit_without_form'),50);
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
      add_settings_field('id_do_not_edit_htaccess', __("Stop editing the .htaccess file","rlrsssl-really-simple-ssl"), array($this,'get_option_do_not_edit_htaccess'), 'rlrsssl', 'rlrsssl_settings');

      //only show option to enable or disable autoreplace when ssl is detected
      if($this->site_has_ssl || $this->force_ssl_without_detection) {
        add_settings_field('id_autoreplace_insecure_links', __("Auto replace mixed content","rlrsssl-really-simple-ssl"), array($this,'get_option_autoreplace_insecure_links'), 'rlrsssl', 'rlrsssl_settings');
      }

      if($this->site_has_ssl && file_exists($this->ABSpath.".htaccess") && is_writable($this->ABSpath.".htaccess")) {
        add_settings_field('id_hsts', __("Turn HTTP Strict Transport Security on","rlrsssl-really-simple-ssl"), array($this,'get_option_hsts'), 'rlrsssl', 'rlrsssl_settings');
      }

      if(!$this->site_has_ssl) {
        //no sense in showing force or ignore warning options when ssl is detected: everything should work fine
        add_settings_field('id_force_ssl_without_detection', __("Force SSL without detection","rlrsssl-really-simple-ssl"), array($this,'get_option_force_ssl_withouth_detection'), 'rlrsssl', 'rlrsssl_settings');
      }

      add_settings_field('id_debug', __("Debug","rlrsssl-really-simple-ssl"), array($this,'get_option_debug'), 'rlrsssl', 'rlrsssl_settings');
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
    if ($this->site_has_ssl || $this->force_ssl_without_detection)
      _e('By unchecking the \'auto replace mixed content\' checkbox you can test if your site can run without this extra functionality. Uncheck, empty your cache when you use one, and go to the front end of your site. You should then check if you have mixed content errors, by clicking on the lock icon in the addres bar.','rlrsssl-really-simple-ssl');
    else {
      _e('The force ssl without detection option can be used when the ssl was not detected, but you are sure you have ssl.','rlrsssl-really-simple-ssl');
    }

    if ($this->site_has_ssl && is_multisite() && $this->set_rewriterule_per_site) {
      _e('The HSTS option is not available for per site activated ssl, as it would force other sites over ssl as well.','rlrsssl-really-simple-ssl');
    }
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
  $newinput['site_has_ssl']                       = $this->site_has_ssl;
  $newinput['ssl_success_message_shown']          = $this->ssl_success_message_shown;
  $newinput['ssl_fail_message_shown']             = $this->ssl_fail_message_shown;
  $newinput['plugin_db_version']                  = $this->plugin_db_version;
  $newinput['wpconfig_issue']                     = $this->wpconfig_issue;
  $newinput['wpconfig_loadbalancer_fix_failed']   = $this->wpconfig_loadbalancer_fix_failed;
  $newinput['wpconfig_server_variable_fix_failed']= $this->wpconfig_server_variable_fix_failed;
  $newinput['set_rewriterule_per_site']           = $this->set_rewriterule_per_site;

  //$newinput['debug_log']                        = $this->debug_log;


  if (!empty($input['hsts']) && $input['hsts']=='1') {
    $newinput['hsts'] = TRUE;
  } else {
    $newinput['hsts'] = FALSE;
  }

  if (!empty($input['force_ssl_without_detection']) && $input['force_ssl_without_detection']=='1') {
    $newinput['force_ssl_without_detection'] = TRUE;
  } else {
    $newinput['force_ssl_without_detection'] = FALSE;
  }

  if (!empty($input['autoreplace_insecure_links']) && $input['autoreplace_insecure_links']=='1') {
    $newinput['autoreplace_insecure_links'] = TRUE;
  } else {
    $newinput['autoreplace_insecure_links'] = FALSE;
  }

  if (!empty($input['debug']) && $input['debug']=='1') {
    $newinput['debug'] = TRUE;
  } else {
    $newinput['debug'] = FALSE;
    $this->debug_log = "";
  }

  if (!empty($input['do_not_edit_htaccess']) && $input['do_not_edit_htaccess']=='1') {
    $newinput['do_not_edit_htaccess'] = TRUE;
  } else {
    $newinput['do_not_edit_htaccess'] = FALSE;
  }

  //if autoreplace value is changed, flush the cache
  if (($newinput['autoreplace_insecure_links']!= $this->autoreplace_insecure_links)) {
 	 add_option('really_simple_ssl_settings_changed', 'settings_changed' );
	}

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

public function get_option_debug() {
$options = get_option('rlrsssl_options');
echo '<input id="rlrsssl_options" name="rlrsssl_options[debug]" size="40" type="checkbox" value="1"' . checked( 1, $this->debug, false ) ." />";
}

  /**
   * Insert option into settings form
   * deprecated
   * @since  2.0
   *
   * @access public
   *
   */

public function get_option_hsts() {
  $options = get_option('rlrsssl_options');
  $disabled = ((is_multisite() && $this->set_rewriterule_per_site) || $this->do_not_edit_htaccess) ? "disabled" : "";
  echo '<input id="rlrsssl_options" name="rlrsssl_options[hsts]" onClick="return confirm(\''.__("Are you sure? Your visitors will keep going to a https site for a year after you turn this off.","rlrsssl-really-simple-ssl").'\');" size="40" type="checkbox" '.$disabled.' value="1"' . checked( 1, $this->hsts, false ) ." />";
  if (is_multisite() && $this->set_rewriterule_per_site) _e("On multisite with per site activation, activating HSTS is not possible","rlrsssl-really-simple-ssl");
  if ($this->do_not_edit_htaccess) _e("You have to enable htaccess editing to use this option.","rlrsssl-really-simple-ssl");
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
echo '<input id="rlrsssl_options" onClick="return confirm(\''.__("Are you sure you have an SSL certifcate? Forcing ssl on a non-ssl site can break your site.","rlrsssl-really-simple-ssl").'\');" name="rlrsssl_options[force_ssl_without_detection]" size="40" type="checkbox" value="1"' . checked( 1, $this->force_ssl_without_detection, false ) ." />";
}

/**
 * Insert option into settings form
 *
 * @since  2.0
 *
 * @access public
 *
 */

public function get_option_do_not_edit_htaccess() {
$options = get_option('rlrsssl_options');
echo '<input id="rlrsssl_options" name="rlrsssl_options[do_not_edit_htaccess]" size="40" type="checkbox" value="1"' . checked( 1, $this->do_not_edit_htaccess, false ) ." />";
}

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
  $settings_link = '<a href="options-general.php?page=rlrsssl_really_simple_ssl">'.__("Settings","rlrsssl-really-simple-ssl").'</a>';
  array_unshift($links, $settings_link);
  return $links;
}

public function check_plugin_conflicts() {
  //Yoast conflict only occurs when mixed content fixer is active
  if ($this->autoreplace_insecure_links && defined('WPSEO_VERSION') ) {
    if ($this->debug) {$this->trace_log("Detected Yoast seo plugin");}
    $wpseo_options  = get_option("wpseo_titles");
    $forcerewritetitle = isset($wpseo_options['forcerewritetitle']) ? $wpseo_options['forcerewritetitle'] : FALSE;
    if ($forcerewritetitle) {
      $this->plugin_conflict["YOAST_FORCE_REWRITE_TITLE"] = TRUE;
      if ($this->debug) {$this->trace_log("Force rewrite titles set in Yoast plugin, which prevents really simple ssl from replacing mixed content");}
    } else {
      if ($this->debug) {$this->trace_log("No conflict issues with Yoast SEO detected");}
    }
  }

  if (class_exists('WooCommerce')) {
    $woocommerce_force_ssl_checkout = get_option("woocommerce_force_ssl_checkout");
    $woocommerce_unforce_ssl_checkout = get_option("woocommerce_unforce_ssl_checkout");
    if (isset($woocommerce_force_ssl_checkout) && $woocommerce_force_ssl_checkout!="no") {
      $this->plugin_conflict["WOOCOMMERCE_FORCESSL"] = TRUE;
    }

    //setting force ssl in certain pages with woocommerce will result in redirect errors.
    if (isset($woocommerce_unforce_ssl_checkout) && $woocommerce_unforce_ssl_checkout!="no") {
      $this->plugin_conflict["WOOCOMMERCE_FORCEHTTP"] = TRUE;
      if ($this->debug) {$this->trace_log("Force HTTP when leaving the checkout set in woocommerce, disable this setting to prevent redirect loops.");}
    }
  }

}

public function get_curl_error($error_no) {
  if ($error_no<0 || $error_no>88) {return "unknown error";}
  $error_codes=array(
    0 => 'CURLE_SUCCESS',
    1 => 'CURLE_UNSUPPORTED_PROTOCOL',
    2 => 'CURLE_FAILED_INIT',
    3 => 'CURLE_URL_MALFORMAT',
    4 => 'CURLE_URL_MALFORMAT_USER',
    5 => 'CURLE_COULDNT_RESOLVE_PROXY',
    6 => 'CURLE_COULDNT_RESOLVE_HOST',
    7 => 'CURLE_COULDNT_CONNECT',
    8 => 'CURLE_FTP_WEIRD_SERVER_REPLY',
    9 => 'CURLE_REMOTE_ACCESS_DENIED',
    11 => 'CURLE_FTP_WEIRD_PASS_REPLY',
    13 => 'CURLE_FTP_WEIRD_PASV_REPLY',
    14 =>'CURLE_FTP_WEIRD_227_FORMAT',
    15 => 'CURLE_FTP_CANT_GET_HOST',
    17 => 'CURLE_FTP_COULDNT_SET_TYPE',
    18 => 'CURLE_PARTIAL_FILE',
    19 => 'CURLE_FTP_COULDNT_RETR_FILE',
    21 => 'CURLE_QUOTE_ERROR',
    22 => 'CURLE_HTTP_RETURNED_ERROR',
    23 => 'CURLE_WRITE_ERROR',
    25 => 'CURLE_UPLOAD_FAILED',
    26 => 'CURLE_READ_ERROR',
    27 => 'CURLE_OUT_OF_MEMORY',
    28 => 'CURLE_OPERATION_TIMEDOUT',
    30 => 'CURLE_FTP_PORT_FAILED',
    31 => 'CURLE_FTP_COULDNT_USE_REST',
    33 => 'CURLE_RANGE_ERROR',
    34 => 'CURLE_HTTP_POST_ERROR',
    35 => 'CURLE_SSL_CONNECT_ERROR',
    36 => 'CURLE_BAD_DOWNLOAD_RESUME',
    37 => 'CURLE_FILE_COULDNT_READ_FILE',
    38 => 'CURLE_LDAP_CANNOT_BIND',
    39 => 'CURLE_LDAP_SEARCH_FAILED',
    41 => 'CURLE_FUNCTION_NOT_FOUND',
    42 => 'CURLE_ABORTED_BY_CALLBACK',
    43 => 'CURLE_BAD_FUNCTION_ARGUMENT',
    45 => 'CURLE_INTERFACE_FAILED',
    47 => 'CURLE_TOO_MANY_REDIRECTS',
    48 => 'CURLE_UNKNOWN_TELNET_OPTION',
    49 => 'CURLE_TELNET_OPTION_SYNTAX',
    51 => 'CURLE_PEER_FAILED_VERIFICATION',
    52 => 'CURLE_GOT_NOTHING',
    53 => 'CURLE_SSL_ENGINE_NOTFOUND',
    54 => 'CURLE_SSL_ENGINE_SETFAILED',
    55 => 'CURLE_SEND_ERROR',
    56 => 'CURLE_RECV_ERROR',
    58 => 'CURLE_SSL_CERTPROBLEM',
    59 => 'CURLE_SSL_CIPHER',
    60 => 'CURLE_SSL_CACERT',
    61 => 'CURLE_BAD_CONTENT_ENCODING',
    62 => 'CURLE_LDAP_INVALID_URL',
    63 => 'CURLE_FILESIZE_EXCEEDED',
    64 => 'CURLE_USE_SSL_FAILED',
    65 => 'CURLE_SEND_FAIL_REWIND',
    66 => 'CURLE_SSL_ENGINE_INITFAILED',
    67 => 'CURLE_LOGIN_DENIED',
    68 => 'CURLE_TFTP_NOTFOUND',
    69 => 'CURLE_TFTP_PERM',
    70 => 'CURLE_REMOTE_DISK_FULL',
    71 => 'CURLE_TFTP_ILLEGAL',
    72 => 'CURLE_TFTP_UNKNOWNID',
    73 => 'CURLE_REMOTE_FILE_EXISTS',
    74 => 'CURLE_TFTP_NOSUCHUSER',
    75 => 'CURLE_CONV_FAILED',
    76 => 'CURLE_CONV_REQD',
    77 => 'CURLE_SSL_CACERT_BADFILE',
    78 => 'CURLE_REMOTE_FILE_NOT_FOUND',
    79 => 'CURLE_SSH',
    80 => 'CURLE_SSL_SHUTDOWN_FAILED',
    81 => 'CURLE_AGAIN',
    82 => 'CURLE_SSL_CRL_BADFILE',
    83 => 'CURLE_SSL_ISSUER_ERROR',
    84 => 'CURLE_FTP_PRET_FAILED',
    84 => 'CURLE_FTP_PRET_FAILED',
    85 => 'CURLE_RTSP_CSEQ_ERROR',
    86 => 'CURLE_RTSP_SESSION_ERROR',
    87 => 'CURLE_FTP_BAD_FILE_LIST',
    88 => 'CURLE_CHUNK_FAILED');
    return $error_codes[$error_no];
  }
}
