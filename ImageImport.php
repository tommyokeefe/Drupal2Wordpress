<?php

	require_once("php-mysql.php");

	//Database Host Name
	$DB_HOSTNAME	= 'localhost';

	//Wordpress Database Name, Username and Password
	$DB_WP_USERNAME	= 'root';
	$DB_WP_PASSWORD	= 'root';
	$DB_WORDPRESS	= 'wfpusa';

	//Drupal Database Name, Username and Password
	$DB_DP_USERNAME	= 'root';
	$DB_DP_PASSWORD	= 'root';
	$DB_DRUPAL		= 'wfpdrupal';

	//Table Prefix
	$DB_WORDPRESS_PREFIX = 'wp_';
	$DB_DRUPAL_PREFIX	 = '';

	//Create Connection Array for Drupal and Wordpress
	$drupal_connection		= array("host" => "localhost","username" => $DB_DP_USERNAME,"password" => $DB_DP_PASSWORD,"database" => $DB_DRUPAL);
	$wordpress_connection	= array("host" => "localhost","username" => $DB_WP_USERNAME,"password" => $DB_WP_PASSWORD,"database" => $DB_WORDPRESS);

	//Create Connection for Drupal and Wordpress
	$dc = new DB($drupal_connection);
	$wc = new DB($wordpress_connection);

	//Check if database connection is fine
	$dcheck = $dc->check();
	if (!$dcheck){
		echo "This $DB_DRUPAL service is UNAVAILABLE"; die();
	}

	$wcheck = $wc->check();
	if (!$wcheck){
		echo "This $DB_WORDPRESS service is UNAVAILABLE"; die();
	}

  $url_base = 'http://local-world-food-program.com/wp-content/uploads/2016/06/';
  $postmeta_base = '/2016/06/';

  //Get all images and titles from drupal blog posts
  $drupal_images = $dc->results("select node.title as title, file_managed.filename as image from node inner join field_data_field_image on node.nid = field_data_field_image.entity_id inner join file_managed on field_data_field_image.field_image_fid = file_managed.fid where node.type = 'blog_post'");

  foreach($drupal_images as $image) {

    $post_id = $wc->results('select id from wp_posts where post_title = "'.addslashes($image['title']).'" and post_status = "publish"');

    foreach($post_id as $post) {
      $does_image_exist = $wc->results('select meta_value from wp_postmeta where post_id = "'.$post['id'].'" and meta_key = "article_featured_image"');
      if (! sizeof($does_image_exist)) {
        $image_name_array = explode('.', $image['image']);
        $image_name = $image_name_array[0];
        $insert_results = $wc->query('insert into wp_posts (post_type, guid, post_status, post_mime_type, post_parent, post_name, post_title, post_date, post_date_gmt, post_modified, post_modified_gmt) values ("attachment", "'.$url_base.$image['image'].'", "inherit", "image/jpeg", "'.$post['id'].'", "'.$image_name.'", "'.$image_name.'", "2016-09-20 12:00:00", "2016-09-20 12:00:00", "2016-09-20 12:00:00", "2016-09-20 12:00:00")');

        $image_id = $wc->results('select id from wp_posts where post_name = "'.$image_name.'"');
        $current_image_id = $image_id[0]['id'];

        $postmeta_results = $wc->query('insert into wp_postmeta (meta_value, meta_key, post_id) values ("'.$current_image_id.'", "article_featured_image", "'.$post['id'].'")');

        $postmeta_results = $wc->query('insert into wp_postmeta (meta_value, meta_key, post_id) values ("'.$postmeta_base.$image['image'].'", "_wp_attached_file", "'.$current_image_id.'")');

        $postmeta_results = $wc->query('insert into wp_postmeta (meta_value, meta_key, post_id) values ("field_575a5235c6d7e", "_article_featured_image", "'.$post['id'].'")');

        $postmeta_results = $wc->query('insert into wp_postmeta (meta_value, meta_key, post_id) values ("standard", "article_image_display", "'.$post['id'].'")');

        $postmeta_results = $wc->query('insert into wp_postmeta (meta_value, meta_key, post_id) values ("field_575a524cc6d7f", "_article_image_display", "'.$post['id'].'")');
      }
    }
  }
