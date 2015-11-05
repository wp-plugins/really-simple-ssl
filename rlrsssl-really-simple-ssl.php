<?php
/**
 * Plugin Name: Really Simple SSL
 * Plugin URI: http://www.rogierlankhorst.com/really-simple-ssl
 * Description: Lightweight plugin without any setup to make your site ssl proof
 * Version: 2.2.0
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
require_once( dirname( __FILE__ ) .  '/class-front-end.php' );

if (is_admin()) {
  require_once( dirname( __FILE__ ) .  '/class-admin.php' );
  $rl_rsssl = new rl_rsssl_admin();
  $rl_rsssl->init();
  $rl_rsssl->force_ssl();

} else {

  $rl_rsssl = new rl_rsssl_front_end();
  $rl_rsssl->force_ssl();

}
