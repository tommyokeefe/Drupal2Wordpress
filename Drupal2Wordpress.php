<?php

	require_once("php-mysql.php");

	//Database Host Name
	$DB_HOSTNAME	= 'localhost';

	//Wordpress Database Name, Username and Password
	$DB_WP_USERNAME	= 'root';
	$DB_WP_PASSWORD	= 'root';
	$DB_WORDPRESS	= 'wfpdev';

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

	message('Database Connection successful');

	//Empty the current worpdress Tables
	$wc->query("TRUNCATE TABLE ".$DB_WORDPRESS_PREFIX."comments");
	$wc->query("TRUNCATE TABLE ".$DB_WORDPRESS_PREFIX."links");
	$wc->query("TRUNCATE TABLE ".$DB_WORDPRESS_PREFIX."postmeta");
	$wc->query("TRUNCATE TABLE ".$DB_WORDPRESS_PREFIX."posts");
	$wc->query("TRUNCATE TABLE ".$DB_WORDPRESS_PREFIX."term_relationships");
	$wc->query("TRUNCATE TABLE ".$DB_WORDPRESS_PREFIX."term_taxonomy");
	$wc->query("TRUNCATE TABLE ".$DB_WORDPRESS_PREFIX."terms");
	message('Wordpress Table Truncated');

	//Get all drupal Tags and add it into worpdress terms table
	$drupal_tags = $dc->results("SELECT DISTINCT d.tid, d.name, REPLACE(LOWER(d.name), ' ', '_') AS slug FROM ".$DB_DRUPAL_PREFIX."taxonomy_term_data d INNER JOIN ".$DB_DRUPAL_PREFIX."taxonomy_term_hierarchy h ON (d.tid = h.tid) ORDER BY d.tid ASC");
	foreach($drupal_tags as $dt)
	{
    //add space for existing terms
    $dt['tid'] = intval($dt['tid'] + 266);

		$wc->query("REPLACE INTO ".$DB_WORDPRESS_PREFIX."terms (term_id, name, slug) VALUES ('%s','%s','%s')", $dt['tid'], $dt['name'], $dt['slug']);
	}

	//Update worpdress term_taxonomy table
	$drupal_taxonomy = $dc->results("SELECT DISTINCT d.tid AS term_id, 'post_tag' AS post_tag, d.description AS description, h.parent AS parent FROM ".$DB_DRUPAL_PREFIX."taxonomy_term_data d INNER JOIN ".$DB_DRUPAL_PREFIX."taxonomy_term_hierarchy h ON (d.tid = h.tid) ORDER BY 'term_id' ASC");
	foreach($drupal_taxonomy as $dt)
	{
    //add space for existing terms
    $dt['term_id'] = intval($dt['term_id'] + 266);
    $dt['parent'] = intval($dt['parent'] + 266);

		$wc->query("INSERT INTO ".$DB_WORDPRESS_PREFIX."term_taxonomy (term_taxonomy_id, term_id, taxonomy, description, parent) VALUES ('%s','%s','%s','%s','%s')", $dt['term_id'], $dt['term_id'], $dt['post_tag'], $dt['description'], $dt['parent']);
	}

	message('Tags Updated');

	// Update worpdress category for a new Blog entry (as catgegory) which
	// is a must for a post to be displayed well
	// Insert a fake new category named Blog
	$wc->query("INSERT INTO ".$DB_WORDPRESS_PREFIX."terms (name, slug) VALUES ('%s','%s')", 'Blog', 'blog');

	// Then query to get this entry so we can attach it to content we create
	$blog_term_id = 0;
	$row = $wc->row("SELECT term_id FROM ".$DB_WORDPRESS_PREFIX."terms WHERE name = '%s' AND slug = '%s'", 'Blog', 'blog');
	if (!empty($row['term_id'])) {
		$blog_term_id = $row['term_id'];

		$wc->query("INSERT INTO ".$DB_WORDPRESS_PREFIX."term_taxonomy (term_id, taxonomy) VALUES ('%d','%s')", $blog_term_id, 'category');

	}

	message('Category Updated');


	//Get all post from Drupal and add it into wordpress posts table
	$drupal_posts = $dc->results("SELECT DISTINCT n.nid AS id, n.uid AS post_author, FROM_UNIXTIME(n.created) AS post_date, r.body_value AS post_content, n.title AS post_title, r.body_summary AS post_excerpt, n.type AS post_type,  IF(n.status = 1, 'publish', 'draft') AS post_status FROM ".$DB_DRUPAL_PREFIX."node n, ".$DB_DRUPAL_PREFIX."field_data_body r WHERE (n.nid = r.entity_id) AND FROM_UNIXTIME(n.created) > '2016-05-26 12:11:00'");
	$post_type = 'page';
	foreach($drupal_posts as $dp)
	{

		// Wordpress basicially has 2 core post_type options, similar to Drupal -
		// either a blog-style content, where in Drupal is referred to as 'article'
		// and in Wordpress this is 'post', and the page-style content which is
		// referred to as 'page' content type in both platforms.

		// For the sake of supporting out of the box seamless migration we will
		// assume that any Drupal 'article' content type should be a Wordpress
		// 'post' type and anything else will be set to 'page'

    $dp['id'] = intval($dp['id'] + 28151);

		if ($dp['post_type'] === 'blog_post') {
      $post_type = 'articles';
    } elseif ($dp['post_type'] === 'press_release') {
      $post_type = 'press_release';
    } elseif ($dp['post_type'] === 'staff_profile') {
      $post_type = 'people';
    } else {
			continue;
    }


		$wc->query("INSERT INTO ".$DB_WORDPRESS_PREFIX."posts (id, post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_type, post_status) VALUES ('%s','%s','%s','%s','%s','%s','%s','%s','%s')", $dp['id'], $dp['post_author'], $dp['post_date'], $dp['post_date'], $dp['post_content'], $dp['post_title'], $dp['post_excerpt'], $post_type, $dp['post_status']);

		// Attach all posts to the Blog category we created earlier
		if ($blog_term_id !== 0) {
			// Attach all posts the terms/tags
			$wc->query("INSERT INTO ".$DB_WORDPRESS_PREFIX."term_relationships (object_id, term_taxonomy_id) VALUES ('%s','%s')", $dp['id'], $blog_term_id);
		}
	}
	message('Posts Updated');

	//Add relationship for post and tags
	$drupal_post_tags = $dc->results("SELECT DISTINCT node.nid, taxonomy_term_data.tid FROM (".$DB_DRUPAL_PREFIX."taxonomy_index taxonomy_index INNER JOIN ".$DB_DRUPAL_PREFIX."taxonomy_term_data taxonomy_term_data ON (taxonomy_index.tid = taxonomy_term_data.tid)) INNER JOIN ".$DB_DRUPAL_PREFIX."node node ON (node.nid = taxonomy_index.nid)");
	foreach($drupal_post_tags as $dpt)
	{
    //match the modified ids
    $dpt['tid'] = intval($dpt['tid'] + 266);
    $dpt['nid'] = intval($dpt['nid'] + 28151);

		$wordpress_term_tax = $wc->row("SELECT DISTINCT term_taxonomy.term_taxonomy_id FROM ".$DB_WORDPRESS_PREFIX."term_taxonomy term_taxonomy  WHERE (term_taxonomy.term_id = ".$dpt['tid'].")");

		// Attach all posts the terms/tags
		$wc->query("INSERT INTO ".$DB_WORDPRESS_PREFIX."term_relationships (object_id, term_taxonomy_id) VALUES ('%s','%s')", $dpt['nid'], $wordpress_term_tax['term_taxonomy_id']);
	}

	message('Tags & Posts Relationships Updated');

	//Update the post type for worpdress
	$wc->query("UPDATE ".$DB_WORDPRESS_PREFIX."posts SET post_type = 'post' WHERE post_type IN ('blog')");
	message('Posted Type Updated');

	//Count the total tags
	$wc->query("UPDATE ".$DB_WORDPRESS_PREFIX."term_taxonomy tt SET count = ( SELECT COUNT(tr.object_id) FROM ".$DB_WORDPRESS_PREFIX."term_relationships tr WHERE tr.term_taxonomy_id = tt.term_taxonomy_id )");
	message('Tags Count Updated');

	//Get the url alias from drupal and use it for the Post Slug
	$drupal_url = $dc->results("SELECT url_alias.source, url_alias.alias FROM ".$DB_DRUPAL_PREFIX."url_alias url_alias WHERE (url_alias.source LIKE 'node%')");
	foreach($drupal_url as $du)
	{
    //match transformed ids
    $source_id = str_replace('node/','',$du['source']);
    $du['source'] = intval($source_id + 28151);

		$update = $wc->query("UPDATE ".$DB_WORDPRESS_PREFIX."posts SET post_name = '%s' WHERE ID = '%s'",
				// Make sure we import without Drupal's leading 'content/' in the URL
				str_replace('content/', '', $du['alias']),
				$du['source']
		);
	}
	message('URL Alias to Slug Updated');

	message('Cheers !!');



	//Preformat the Object for Debuggin Purpose
	function po($obj){
		echo "<pre>";
		print_r($obj);
		echo "</pre>";
	}

	function message($msg){
		echo "<hr>$msg</hr>";
		func_flush();
	}

	function func_flush($s = NULL)
	{
		if (!is_null($s))
			echo $s;

		if (preg_match("/Apache(.*)Win/S", getenv('SERVER_SOFTWARE')))
			echo str_repeat(" ", 2500);
		elseif (preg_match("/(.*)MSIE(.*)\)$/S", getenv('HTTP_USER_AGENT')))
			echo str_repeat(" ", 256);

		if (function_exists('ob_flush'))
		{
			// for PHP >= 4.2.0
			@ob_flush();
		}
		else
		{
			// for PHP < 4.2.0
			if (ob_get_length() !== FALSE)
				ob_end_flush();
		}
		flush();
	}
