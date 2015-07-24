<?php
defined('ABSPATH') or die("you do not have acces to this page!");

class rlrsssl_scan {
  private $search_array;
  private $img_warning;
  private $img_success;
  private $img_error;
  private $mixed_content_detected;
  private $autoreplace_insecure_links;

public function set_images($success,$error,$warning) {
  $this->img_warning = $warning;
  $this->img_success = $success;
  $this->img_error   = $error;
}

public function init($search_array, $autoreplace){
  $this->search_array               = $search_array;
  $this->autoreplace_insecure_links = $autoreplace;
}
public function insert_scan() {
  $ajax_nonce = wp_create_nonce( "rlrsssl-really-simple-ssl" );
  ?>
  <script type='text/javascript'>
    jQuery(document).ready(function($) {
        $('#scan-results tr:last').after('<tr id="loader"><td></td><td><div class="loader"><?php _e("Scanning...", "rlrsssl-really-simple-ssl");?></div></td></tr>');
        var data = {
          'action': 'scan',
          'security': '<?php echo $ajax_nonce; ?>',
        };
        $.post(ajaxurl, data, function(response) {
            $('#loader').replaceWith(response);
        });
    });
  </script>
  <?php
}

public function scan_callback() {
  global $wpdb;
  check_ajax_referer( 'rlrsssl-really-simple-ssl', 'security' );

  $files = new rlrsssl_files;
  $files->scan($this->search_array);
  $database = new rlrsssl_database;
  $database->scan($this->search_array);

  if (count($database->postsWithHTTP)>0 || count($files->filesWithHTTP)>0 || count($database->optionsWithHTTP)>0) {
    $this->mixed_content_detected = TRUE;
  }

  if ($this->mixed_content_detected) {
    ?>
    <tr>
      <td><?php echo $this->mixed_content_detected ? $this->img_warning :$this->img_success;?></td>
      <td>
        <?php
          if ($this->mixed_content_detected) {
              if ($this->autoreplace_insecure_links) {
                  $autoreplace = __("currently ACTIVE","rlrsssl-really-simple-ssl");
              } elseif(!$this->autoreplace_insecure_links) {
                  $autoreplace = __("currently NOT active","rlrsssl-really-simple-ssl");
              }
              echo sprintf(__('Auto replace script might be necessary for your website (%s), because mixed content was detected in the following posts, files and options.','rlrsssl-really-simple-ssl'),$autoreplace);

          } else {
            _e("No mixed content was detected. You could try to run your site without using the auto replace of insecure links. ","rlrsssl-really-simple-ssl");
          }
          ?>
      </td>
    </tr>
    <tr><td></td><td id="scan-results">
      <table class="wp-list-table widefat fixed striped pages">
    <?php
  }
  foreach ($database->postsWithHTTP as $name => $id) {
    ?>
    <tr><td><?php echo $name;?>&nbsp;|&nbsp;<a href="post.php?post=<?php echo $id;?>&action=edit"><?php _e('edit','rlrsssl-really-simple-ssl');?></a></td></tr>
    <?php
  }
  foreach ($files->filesWithHTTP as $fName => $file) {
    ?>
    <tr><td>Theme file: <?php echo $files->get_path_to_themes($file);?></td></tr>
    <?php
  }
  foreach ($database->optionsWithHTTP as $option) {
    ?>
    <tr><td>Option: <?php echo $option?></td></tr>
    <?php
  }?>
  </table><!--end list of insecure posts and files-->
  <?php
  if ($this->mixed_content_detected) {
    parse_str($_SERVER['QUERY_STRING'], $params);
    ?>
        <br>
        <button id="rlrsssl_scan" class="button button-primary" onclick="document.location.reload();"><?php _e("Scan again","rlrsssl-really-simple-ssl");?></button>
        <?php
        /*
        <button class="button button-primary" onclick="document.location.href='<?php printf('%1$s', '?'.http_build_query(array_merge($params, array('rlrsssl_fixposts'=>'1'))));?>'">
          <?php _e("Fix posts","rlrsssl-really-simple-ssl"); ?>
        </button>
        */
        ?>
    <?php
  }

  ?>
  </td>
  </tr>

<?php
  wp_die(); // this is required to terminate immediately and return a proper response
}

}
?>
