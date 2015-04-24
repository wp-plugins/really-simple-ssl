<?php
/**
 * Plugin Name: Really Simple SSL
 * Plugin URI: http://www.rogierlankhorst.com/really-simple-ssl
 * Description: Lightweight plugin to make your site ssl proof
 * Version: 1.0.3
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

class rlrsssl_really_simple_ssl {
    public function __construct()
    {
        // if site is set to run on SSL, then force-enable SSL detection!
        if (stripos(get_option('siteurl'), 'https://') === 0) {
            //$_SERVER['HTTPS'] = 'on';
            // add JavaScript detection of page protocol
            add_action('wp_print_scripts', array($this,'force_ssl_url_scheme_script'),1);
            //now replace remaining http urls, but only to those within this site!
            add_filter('template_include', array($this,'replace_http_with_https'),1);
        }
    }

    public function replace_http_with_https($template) {
      ob_start(array($this, 'end_buffer_capture'));  // Start Page Buffer
      return $template;
    }

    public function end_buffer_capture($buffer) {
        //get url from this domain. This domain is set to https, or we wouldnt be here
        //we replace also major domains that work on https as well.
        $ssl_array = array(
            get_option('siteurl'),
            "https://www.youtube"
            );
        //now enter the domains without https
        $standard_array = array(
            str_replace ( "https://" , "http://" , get_option('siteurl')),
            "http://www.youtube"
            );
        //now replace all these links
        $buffer = str_replace ( $standard_array , $ssl_array , $buffer);
        return $buffer;
    }

    public function force_ssl_url_scheme_script() {
        ?>
        <script>
        if (document.location.protocol != "https:") {
            document.location = document.URL.replace(/^http:/i, "https:");
        }
        </script>
    <?php
    }
}

$rlrsssl_really_simple_ssl = new rlrsssl_really_simple_ssl();
