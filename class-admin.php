<?php
defined('ABSPATH') or die("you do not have acces to this page!");

  class rlrsssl_admin {
  public
     $ssl_redirect_set_in_htaccess      = FALSE, //moved to backend because we don't check it anymore for the javascript redirect.
     $settings_changed                  = FALSE,
     $wpconfig_issue                    = FALSE,
     $wpconfig_loadbalancer_fix_failed  = FALSE,
     $do_wpconfig_loadbalancer_fix      = FALSE,
     $set_rewriterule_per_site          = FALSE,
     $ssl_type                          = "NA",
                                        //"SERVER-HTTPS-ON"
                                        //"SERVER-HTTPS-1"
                                        //"SERVERPORT443"
                                        //"LOADBALANCER"
                                        //"CDN"
     $capability                        = 'manage_options',
     $plugin_url,
     $plugin_version,
     $error_number                      = 0,
     $curl_installed                    = FALSE,
     $ssl_test_page_error,
     $htaccess_test_success             = FALSE,
     $main_plugin_filename,
	   $debug							                = TRUE,
     $ABSpath,

     $ssl_fail_message_shown          = FALSE,
     $ssl_success_message_shown       = FALSE,
     $hsts                            = FALSE,
     $debug_log,
     $plugin_conflict                 = FALSE,
     $plugin_db_version;

  public function __construct()
  {

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

    $options = get_option('rlrsssl_options');
    if (isset($options)) {
      $this->hsts                       = isset($options['hsts']) ? $options['hsts'] : FALSE;
      $this->ssl_fail_message_shown     = isset($options['ssl_fail_message_shown']) ? $options['ssl_fail_message_shown'] : FALSE;
      $this->ssl_success_message_shown  = isset($options['ssl_success_message_shown']) ? $options['ssl_success_message_shown'] : FALSE;
      $this->plugin_db_version          = isset($options['plugin_db_version']) ? $options['plugin_db_version'] : "1.0";
      $this->wpconfig_issue             = isset($options['wpconfig_issue']) ? $options['wpconfig_issue'] : FALSE;
      $this->wpconfig_loadbalancer_fix_failed = isset($options['wpconfig_loadbalancer_fix_failed']) ? $options['wpconfig_loadbalancer_fix_failed'] : FALSE;
      $this->set_rewriterule_per_site   = isset($options['set_rewriterule_per_site']) ? $options['set_rewriterule_per_site'] : FALSE;
      $this->debug                      = isset($options['debug']) ? $options['debug'] : FALSE;
      $this->debug_log                  = isset($options['debug_log']) ? $options['debug_log'] : "";
    }
    if ($this->debug) {
      //earliest moment of logging, so clear log
      $this->debug_log = "";
      $this->trace_log("loading options...");
    }
  }

  /**
   * set the plugin filename
   *
   * @since  2.1
   *
   * @access public
   *
   */

  public function set_plugin_filename($filename) {
    $this->main_plugin_filename = $filename;
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
    //$this->save_options();
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
   * functions: test_htaccess_redirect, check_for_siteurl_in_wpconfig, editHtaccess, set_siteurl_to_ssl
   * params: backend: capability, ssl_type,
   * params, frontend: site_has_ssl, force_ssl_without_detection
   * @since  2.1
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

        //when an known ssl_type was found, test if the redirect works
        if ($this->ssl_type != "NA") {
            $this->test_htaccess_redirect();
        }

        //fix siteurl definitions in wpconfig, if any
        $this->fix_siteurl_defines_in_wpconfig();

        //in a configuration of loadbalancer without a set server variable https = 0, add code to wpconfig
        if ($this->do_wpconfig_loadbalancer_fix) {
          $this->wpconfig_loadbalancer_fix();
        }

        $this->editHtaccess();
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
      $plugin_file = basename( ( $this->main_plugin_filename ) );
      $this->plugin_version = $plugin_folder[$plugin_file]['Version'];
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
      //only if loadbalancer AND NOT SERVER-HTTPS-ON should the following be added.
      if (strpos($wpconfig, "//Begin Really Simple SSL Load balancing fix")===FALSE) {
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
          //$wpconfig = preg_replace("/\n+/","\n", $wpconfig);
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
    if (current_user_can($this->capability)) {
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
      'set_rewriterule_per_site'          => $this->set_rewriterule_per_site,
      'debug'                             => $this->debug,
      'debug_log'                         => $this->debug_log,
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
    $this->removeHtaccessEdit();
    $this->remove_ssl_from_siteurl();
    $this->remove_ssl_from_siteurl_in_wpconfig();
    $this->force_ssl_without_detection  = FALSE;
    $this->site_has_ssl                 = FALSE;
    $this->hsts                         = FALSE;
    $this->ssl_fail_message_shown       = FALSE;
    $this->ssl_success_message_shown    = FALSE;
    $this->autoreplace_insecure_links   = TRUE;
    $this->save_options();

  }

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
        curl_setopt($ch, CURLOPT_FRESH_CONNECT, TRUE);

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

  public function check_for_ssl() {
    if ($this->debug) {$this->trace_log("testing for ssl...");}

    $old_ssl_setting = $this->site_has_ssl;
    $testpage_url = trailingslashit(str_replace ("http://" , "https://" , $this->plugin_url))."ssl-test-page.php";

    $filecontents = $this->get_url_contents($testpage_url);

    /*
          also check if we are currently on ssl protocol,
          so if an error occurs but is_ssl_extended returns true,
          the site_has_ssl will still be set to true.
          This should never happen, but it prevents cases where an
          ssl page returns a "no ssl detected message"
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
      } else {
        //no recognized response, so set to NA
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
    $testpage_url = str_replace ("http://" , "https://" ,$this->plugin_url."testssl/");
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
      //$this->get_curl_error($this->error_number)
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

    $this->plugin_url = trailingslashit(plugins_url()).trailingslashit(dirname(plugin_basename(__FILE__)));
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
        if ($this->debug) {$this->trace_log("removing htaccess rules...");}
        $htaccess = file_get_contents($this->ABSpath.".htaccess");
        $htaccess = preg_replace("/#\s?BEGIN\s?rlrssslReallySimpleSSL.*?#\s?END\s?rlrssslReallySimpleSSL/s", "", $htaccess);
        $htaccess = preg_replace("/\n+/","\n", $htaccess);

        file_put_contents($this->ABSpath.".htaccess", $htaccess);
        $this->ssl_redirect_set_in_htaccess =  FALSE;
        $this->save_options();
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
      if($this->debug) $this->trace_log("checking if .htaccess can or should be edited...");
      if ( !defined( 'RLRSSSL_DO_NOT_EDIT_HTACCESS' ) ) {
          define( 'RLRSSSL_DO_NOT_EDIT_HTACCESS' , FALSE );
      }

      if (!RLRSSSL_DO_NOT_EDIT_HTACCESS && file_exists($this->ABSpath.".htaccess") && is_writable($this->ABSpath.".htaccess")) {
        //exists and is writable
        $htaccess = file_get_contents($this->ABSpath.".htaccess");

        $rules = $this->get_redirect_rules();
        if(!$this->contains_rsssl_rules($htaccess)){
          //really simple ssl rules not in the file, so add.
		      if ($this->debug) {$this->trace_log("no rules there, adding rules...");}
          $htaccess = $htaccess.$rules;
          file_put_contents($this->ABSpath.".htaccess", $htaccess);
        } elseif ($this->contains_previous_version($htaccess) || ($this->hsts!=$this->contains_hsts($htaccess))) {
          //old version, so remove all rules and add new
          //or the hsts option has changed, so we need to edit the htaccess anyway.
          if ($this->debug) {$this->trace_log("old version or hsts option change, updating htaccess...");}
		      $htaccess = preg_replace("/#\s?BEGIN\s?rlrssslReallySimpleSSL.*?#\s?END\s?rlrssslReallySimpleSSL/s", "", $htaccess);
          $htaccess = preg_replace("/\n+/","\n", $htaccess);
          $htaccess = $htaccess.$rules;
          file_put_contents($this->ABSpath.".htaccess", $htaccess);
        } else {
          if ($this->debug) {$this->trace_log("current version in .htaccess, nice!");}
          //current version, so do nothing.
        }
      } else {
        if($this->debug) $this->trace_log("not able to edit .htaccess: does not exist, or is not writable, or is blocked constant");
        $this->ssl_redirect_set_in_htaccess =  FALSE;
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

  public function get_redirect_rules($examplecode=false) {
      if (!current_user_can($this->capability)) return;
      //only add the redirect rules when a known type of ssl was detected. Otherwise, we use https.
      $rule="";
      if (!$examplecode) {
        $rule .= "# BEGIN rlrssslReallySimpleSSL rsssl_version[".$this->plugin_version."]\n";
      }
      //if the htaccess test was successfull, and we know the redirectype, edit
      if ($examplecode || ($this->htaccess_test_success && ($this->ssl_type != "NA"))) {
        //set redirect_set_in_htaccess to true, because we are now making a redirect rule.
        if (!$examplecode) {
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

        //in case of multisite, when plugin is activated per site, the rewrite rules should be added per site.
        /*
        if ($this->set_rewriterule_per_site) {
          $blogids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
          foreach ($blogids as $blog_id) {
            //add domain condition: or based on current site, or based on domain mapping info?
            switch_to_blog($blog_id);
            $domain = home_url();
            $domain  = str_replace ( "." , "\." , $domain);
            $domain_no_www  = str_replace ( "://www\." , "://" , $domain);
            $domain_yes_www = str_replace ( "://" , "://www\." , $home_no_www);
            $rule .= "RewriteCond %{HTTP_HOST} ^".$domain_no_www." [OR]"."\n"; //really-simple-ssl\.com
            $rule .= "RewriteCond %{HTTP_HOST} ^".$domain_yes_www."\n"; //www\.really-simple-ssl\.com
          }
        }
        */

        //generic rule is always valid: in case of multisite, the conditions are added per site.
        $rule .= "RewriteRule ^(.*) https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]"."\n";
        $rule .= "</IfModule>"."\n";
      } else {
        $this->ssl_redirect_set_in_htaccess = FALSE;
      }

      if ($this->hsts) {
        //owasp security best practice https://www.owasp.org/index.php/HTTP_Strict_Transport_Security
        $rule .= "<IfModule mod_headers.c>"."\n";
        $rule .= "Header always set Strict-Transport-Security 'max-age=31536000' env=HTTPS"."\n";
        $rule .= "</IfModule>"."\n";
      }
      if (!$examplecode) {
          $rule .= "# END rlrssslReallySimpleSSL"."\n";
      }

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

      <div id="message" class="error fade notice is-dismissible rlrsssl-fail"><p>
      <?php _e("No SSL was detected. If you are just waiting for your ssl certificate to kick in you can dismiss this warning.","rlrsssl-really-simple-ssl");?>
      </p>
      <p><strong>
      <?php printf('<a href="%1$s">'.__("I'm sure I have an active SSL certificate, force it!","rlrsssl-really-simple-ssl").'</a>', '?'.http_build_query(array_merge($params, array('rlrsssl_force_ssl'=>'1'))));?>
      |
      <a href="options-general.php?page=rlrsssl_really_simple_ssl"><?php echo __("Scan SSL setup again","rlrsssl-really-simple-ssl");?></a>
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

  //some notices for ssl situations
  if ($this->site_has_ssl || $this->force_ssl_without_detection) {

      if ($this->plugin_conflict) {
        ?>
        <div id="message" class="error fade notice"><p>
        <?php _e("Really Simple SSL has a conflict with another plugin.","rlrsssl-really-simple-ssl");?><br>
        <?php _e("The force rewrite titles option in Yoast SEO prevents Really Simple SSL plugin from fixing mixed content.","rlrsssl-really-simple-ssl");?><br>
        <a href="admin.php?page=wpseo_titles"><?php _e("Show me this setting","rlrsssl-really-simple-ssl");?></a>

        </p></div>
        <?php
      }

      if ($this->wpconfig_issue) {
        ?>
        <div id="message" class="error fade notice"><p>
        <?php echo __("We detected a definition of siteurl or homeurl in your wp-config.php, but the file is not writable. Because of this, we cannot set the siteurl to https.","rlrsssl-really-simple-ssl");?>
        </p></div>
        <?php
      }

      if ($this->wpconfig_loadbalancer_fix_failed) {
        ?>
        <div id="message" class="error fade notice"><p>
        <?php echo __("Because your site is behind a loadbalancer and is_ssl() returns false, you should add the following line of code to your wp-config.php. Your wp-config.php could not be written automatically.","rlrsssl-really-simple-ssl");?>

        <br><br><code>
            if (isset($_SERVER["HTTP_X_FORWARDED_PROTO"] ) &amp;&amp; "https" == $_SERVER["HTTP_X_FORWARDED_PROTO"] ) {<br>
            &nbsp;&nbsp;$_SERVER["HTTPS"] = "on";<br>
          }
        </code><br>
        </p></div>
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

    $screen->add_help_tab( array(
        'id'	=> "force_ssl_without_detection",
        'title'	=> __("Force SSL without detection","rlrsssl-really-simple-ssl"),
        'content'	=> '<p>' . __("This plugin tries to open a page within the plugin directory over https. If that fails, it is assumed that ssl is not availble. But as this may not cover all eventualities, it is possible to force the site over ssl anyway.<br><br> If you force your site over ssl without a valid ssl certificate, your site may break. In that case, remove the 'really simple ssl' rules from your .htaccess file (if present), and remove or rename the really simple ssl plugin.","rlrsssl-really-simple-ssl") . '</p>',
    ) );
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
        <table <?php if ($this->site_has_ssl||$this->force_ssl_without_detection) {echo 'id="scan-result"';}?>>
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
              <?php echo $this->ssl_redirect_set_in_htaccess ? $this->img("success") :$this->img("warning");?>
            </td>
            <td>
            <?php
              if($this->ssl_redirect_set_in_htaccess) {
                 _e("https redirect set in .htaccess","rlrsssl-really-simple-ssl");
              } else {
                 _e("Https redirect was set in javascript because the htaccess redirect rule could not be verified. Set manually if you want to redirect in .htaccess.","rlrsssl-really-simple-ssl");

                 $examplecode = true;
                 $rules = $this->get_redirect_rules($examplecode);
                 echo "&nbsp;";
                 if (strlen($rules)>0) {
                    $arr_search = array("<",">","\n");
                    $arr_replace = array("&lt","&gt","<br>");
                    $rules = str_replace($arr_search, $arr_replace, $rules);
                    _e("Try to add these rules at the bottom of your .htaccess","rlrsssl-really-simple-ssl");
                     ?>
                     <br><br>
                     <code>
                         <?php echo $rules; ?>
                       </code>
                     <?php
                   }
              }
            ?>
            </td><td></td>
          </tr>

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
              }
            ?>
            </td><td><a href="?page=rlrsssl_really_simple_ssl&tab=settings"><?php _e("Manage settings","rlrsssl-really-simple-ssl");?></a></td>
          </tr>

          <?php }
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
         ?>
    <div>
      <?php
      if ($this->debug) {
        echo "<h2>Log for debugging purposes.</h2>";
        echo "<p>Send me a copy of these lines if you have any issues. The log will be erased when debug is set to false.</p>";
        echo "<div class='debug-log'>";
        echo $this->debug_log;
        echo "</div>";
        $this->debug_log.="<br><b>-----------------------</b>";
        $this->save_options();
      }
      else {
        echo "To get results here, set the debugging option under configuration to 'on'.";
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

    $plugin = plugin_basename($this->main_plugin_filename);
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
      //only show option to enable or disable autoreplace when ssl is detected
      register_setting( 'rlrsssl_options', 'rlrsssl_options', array($this,'options_validate') );
      add_settings_section('rlrsssl_settings', __("Settings","rlrsssl-really-simple-ssl"), array($this,'section_text'), 'rlrsssl');

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
  $newinput['site_has_ssl']                     = $this->site_has_ssl;
  $newinput['ssl_success_message_shown']        = $this->ssl_success_message_shown;
  $newinput['ssl_fail_message_shown']           = $this->ssl_fail_message_shown;
  $newinput['plugin_db_version']                = $this->plugin_db_version;
  $newinput['wpconfig_issue']                   = $this->wpconfig_issue;
  $newinput['wpconfig_loadbalancer_fix_failed'] = $this->wpconfig_loadbalancer_fix_failed;
  $newinput['set_rewriterule_per_site']         = $this->set_rewriterule_per_site;
  $newinput['debug_log']                        = $this->debug_log;

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
  echo '<input id="rlrsssl_options"  onClick="return confirm(\''.__("Are you sure? Your visitors will keep going to a https site for a year after you turn this off.","rlrsssl-really-simple-ssl").'\');" name="rlrsssl_options[hsts]" size="40" type="checkbox" value="1"' . checked( 1, $this->hsts, false ) ." />";
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
      $this->plugin_conflict = TRUE;
      if ($this->debug) {$this->trace_log("Force rewrite titles set in Yoast plugin, which prevents really simple ssl from replacing mixed content");}
    } else {
      if ($this->debug) {$this->trace_log("No conflict issues with Yoast SEO detected");}
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
