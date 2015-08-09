<?php
defined('ABSPATH') or die("you do not have acces to this page!");

class rlrsssl_cache {
  private
       $capability  = 'install_plugins';

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

  public function flush() {
    if (current_user_can($this->capability)) {
      add_action( 'init', array($this,'flush_w3tc_cache'));
      add_action( 'init', array($this,'flush_fastest_cache'));
      add_action( 'init', array($this,'flush_zen_cache'));

      //reset settings changed
      $this->settings_changed = FALSE;
    }

  }

  private function flush_w3tc_cache() {
    if( class_exists('W3_Plugin_TotalCacheAdmin') )
    {
      if (function_exists('w3tc_flush_all')) {
        w3tc_flush_all();
      }
    }
  }

  private function flush_fastest_cache() {
    if(class_exists('WpFastestCache') )
    {
      $GLOBALS["wp_fastest_cache"]->deleteCache(TRUE);
    }
  }

  private function flush_zen_cache() {
    if (class_exists('\\zencache\\plugin') )
    {
      $GLOBALS['zencache']->clear_cache();
    }
  }

}
