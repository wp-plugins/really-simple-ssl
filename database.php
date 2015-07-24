<?php
defined('ABSPATH') or die("you do not have acces to this page!");

class rlrsssl_database {
  public
    $postsWithHTTP                 = Array(),
    $optionsWithHTTP               = Array(),
    $search_array                  = Array();

  public function __construct()
  {

  }

  public function build_query( $where = '' ){
      global $wpdb;
      $where.= ' AND (';

      $arr = $this->search_array;
      $i = 0;
      foreach ($arr as $needle) {
        $i++;
        $where.= sprintf(' %1$s.post_content ',$wpdb->posts);
        $where.= " LIKE '%{$needle}%'";
        if ($i<count($arr)) {
          $where.= " OR ";
        }
      }
      $where.=") ";

      //extend to all post types
      $where = str_replace("AND wp_posts.post_type = 'post' AND", "AND", $where);
      return $where;
  }

    public function fix_insecure_post_links() {
        //error_log("fixing insecure post links");
        global $wpdb;
        /*
        $wpdb->query(
          $wpdb->prepare(
            "
            UPDATE $wpdb->posts
            SET Value = REPLACE(Value, '%1$s', '%2$s')
            WHERE ID <=4
            ",
                  $string1, $string2
                )
        );*/
      }

      public function scan($search_array) {
        $this->search_array = $search_array;

        //scan posts
        add_filter('posts_where', array($this,'build_query'));
        $args = array('suppress_filters' => false );
        $the_query = new WP_Query($args);
        //limit to 25
        $count = 0;
        if ($the_query->have_posts() ) {
          while ( $the_query->have_posts() && $count<25) {
            $count++;
            $the_query->the_post();
            $this->postsWithHTTP[get_the_title()] = get_the_ID();
          }
        }
        remove_filter( 'posts_where', array($this,'build_query'));
        wp_reset_postdata();

        //scan options
        global $wpdb;
        $where = sprintf('Select * FROM %1$s WHERE (',$wpdb->options);
        $arr = $this->search_array;
        $i = 0;
        foreach ($arr as $needle) {
          $i++;
          $where.= sprintf(' %1$s.option_value ',$wpdb->options);
          $where.= " LIKE '%{$needle}%'";
          if ($i<count($arr)) {
            $where.= " OR ";
          }
        }
        //ignore siteurl and home, because we take care of these
        $where.= sprintf(') AND NOT (%1$s.option_name = "siteurl" OR %1$s.option_name = "home")',$wpdb->options);
        $results = $wpdb->get_results($where);
        //limit to 25
        $count=0;
        foreach ($results as $result) {
          if ($count<25) {
            array_push($this->optionsWithHTTP,$result->option_name);
            $count++;
          }
        }

      }
}
 ?>
