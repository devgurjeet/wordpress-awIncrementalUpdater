<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class AwbDbInterfaceCron {

	public static $sourceWpdb;
	public static $destinationWpdb;

	public static $destinationsiteUrl;
	public static $categories;

	public static $destionationLinks;


	public static $destination;

	/**
	 * [getBloglist: create and return list of all blogs]
	 * @return String: list of all blogs in HTML Format.
	 */
	public static function getBloglist(){
		global $wpdb;

		$sql 	  = "SELECT * FROM wp_aw_blog_sites";
		$results  = $wpdb->get_results( $sql, ARRAY_A );
		return $results;
	}

	/**
	 * [getDatabaseName: return the database name from Blogname]
	 * @param  String $blogAddress
	 * @return String
	 */
	public static function getDatabaseName( $blogAddress ) {
		return 	str_replace('-', '_', $blogAddress);
	}

	/**
	 * [setSourceDb Update the sourceDb class variable]
	 */
	public static function setSourceDb(){

		$host 		   = 'localhost';
		$username 	   = 'iris';
		$password 	   = 'For$Db!php5';
		$databaseName  = 'scanmine';

		self::$sourceWpdb = new wpdb( $username, $password, $databaseName, $host );
	}

	/**
	 * [setDestinationDb: Update the destinationDb class variable. ]
	 * @param String $destination
	 */
	public static function setDestinationDb( $destination  ){
		$host 		   = 'localhost';
		$username 	   = 'iris';
		$password 	   = 'For$Db!php5';
		$databaseName  = self::getDatabaseName( $destination );
		self::$destinationWpdb  = new wpdb( $username, $password, $databaseName, $host );

		/*setup site url */
		self::getSiteUrl();

		/*get Categories */
		self::getCategories();

		self::$destination = '/var/www/html/'.$destination;

		return true;
	}

	public static function hasCategoryIndex(){
		$wpdb = self::$destinationWpdb;
		$table_name = 'category_post_index';

		if($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
			return false;
		}else{
			return true;
		}
	}

	public static function getSiteUrl( ){
		$wpdb = self::$destinationWpdb;

		$table_name = "wp_options";
		$sql = "SELECT option_value FROM ".$table_name." WHERE option_name = 'siteurl';";
		$root_url = $wpdb->get_var($sql);

		$lastChar = substr($root_url,-1);
		if ( $lastChar !== "/"){
			$root_url = $root_url."/";
		}
		self::$destinationsiteUrl =	 $root_url;
	}

	public static function getCategories() {
		$wpdb = self::$destinationWpdb;
		$sql = "SELECT *
					FROM wp_term_relationships
					LEFT JOIN wp_term_taxonomy
					   ON (wp_term_relationships.term_taxonomy_id = wp_term_taxonomy.term_taxonomy_id)
					LEFT JOIN wp_terms on wp_term_taxonomy.term_taxonomy_id = wp_terms.term_id
					WHERE wp_term_taxonomy.taxonomy = 'category'
					GROUP BY wp_term_taxonomy.term_id";

		$results  = $wpdb->get_results( $sql, ARRAY_A );

		$category = array();
		foreach ($results as $cat) {
			$category[$cat['name']] = $cat['term_id'];
		}

		self::$categories =	 $category;
	}

	public static function checkIfpostExists( $smguid ){
		$wpdb = self::$destinationWpdb;

		$table_name = "wp_postmeta";
		$sql 		= "SELECT post_id FROM ".$table_name." WHERE meta_key = 'sm:guid' AND meta_value = '".$smguid."';";
		$post_id 	= $wpdb->get_var($sql);

		if( $post_id ){
			return $post_id;
		}else{
			return false;
		}
	}

	public static function createDestinationDatabse( $destination ){}
	public static function copyDatabase( $sourceDB, $destinationDB ){}
	public static function updateWpOptions(){}
	public static function updateAwBloggerList( ){}
	public static function insertCategories( ){}
	public static function insertFeeds( ){}
	public static function createFooterMenu(){}
	public static function createTopMenu(){}
	public static function updateTermRelation( $object_id, $term_taxonomy_id ){}
	public static function createMenuPosts( $menuOrder ){}
	public static function addMenuPostMeta( $postID, $menuItemType = '', $itemType = '', $menuID, $ItemID  ){}
	public static function getMenuID( $location ) {}

	/*udpated for cron */

	public static function getDestionationLinks(){
		$wpdb  	  =  self::$destinationWpdb;

		$sql 	  = "SELECT link_url FROM wp_links";
		$results  = $wpdb->get_results( $sql, ARRAY_A );
		$items 	  = array();
		foreach ($results as $feed) {
			$items[] = $feed['link_url'];
		}
		self::$destionationLinks = $items;

	}

	/* ============================ Section for update posts =========================================*/
	public static function updatePostsCron( $post_id, $post ){
		$postID = $post_id;

		self::updatePost( $post_id, $post );

		$postmeta = array();
		$postmeta["sm:block"] 	=  	$post['smblock'];

		// if( !empty($post['enclosure'])){
		// 	$thumbnailid = self::insertAttachment($postID, $post );
		// 	if( $thumbnailid ){
		// 		$postmeta['_thumbnail_id'] 	= $thumbnailid;
		// 		$postmeta['enclosure'] 		= $post['enclosure'];
		// 	}
		// }

		// // add post Meta.
		// $postmeta["sm:guid"] 				=  	$post['smguid'];
		/*$postmeta["sm:meta-title"] 			=  	$post['smmetatitle'];
		$postmeta["sm:meta-description"] 	= 	$post['smmetadesc'];
		$postmeta["sm:meta-image"] 			=	$post['smmetaimage'];

		if(!empty($post['sourcelink'])){
			$postmeta["syndication_permalink"] = 	$post['sourcelink'];
		}
		if(!empty($post['sourcelink'])){
			$postmeta["syndication_permalink"] = 	$post['sourcelink'];
		}*/

		self::updatePostMeta( $postID, $postmeta);

		// self::updateCategoryIndexCronUpdate( $postID, $post['category']);

		echo "<br>Post Updated: ".$post['post_title']."<br>";

		file_put_contents(dirname(__FILE__)."/updatePost.log", print_r(self::$destinationsiteUrl, true),FILE_APPEND );
		file_put_contents(dirname(__FILE__)."/updatePost.log", print_r("\n", true),FILE_APPEND );
		file_put_contents(dirname(__FILE__)."/updatePost.log", print_r("Post Updated: ID: ".$postID." Post Title: ".$post['post_title'], true),FILE_APPEND );
		file_put_contents(dirname(__FILE__)."/updatePost.log", print_r("\n", true),FILE_APPEND );

	}
	/* ============================ Section for update posts =========================================*/

	/*========================= Section to Insert Posts using Cron ====================================== */
	public static function addPostsCron( $post ){

		$postID = self::insertPost( $post );
		if($postID ){
			//update category.
			$term_id = self::$categories[$post['category']];
			self::insertCategory( $postID, $term_id );

			// add post Attachment.
			$postmeta = array();

			if( !empty($post['enclosure'])){
				$thumbnailid = self::insertAttachment($postID, $post );
				if( $thumbnailid ){
					$postmeta['_thumbnail_id'] 	= $thumbnailid;
					$postmeta['enclosure'] 		= $post['enclosure'];
				}
			}

			// add post Meta.
			$postmeta["sm:guid"] 				=  	$post['smguid'];
			$postmeta["sm:block"] 				=  	$post['smblock'];
			$postmeta["sm:meta-title"] 			=  	$post['smmetatitle'];
			$postmeta["sm:meta-description"] 	= 	$post['smmetadesc'];
			$postmeta["sm:meta-image"] 			=	$post['smmetaimage'];

			if(!empty($post['sourcelink'])){
				$postmeta["syndication_permalink"] = 	$post['sourcelink'];
			}
			if(!empty($post['sourcelink'])){
				$postmeta["syndication_permalink"] = 	$post['sourcelink'];
			}

			self::insertPostMeta( $postID, $postmeta);

			self::updateCategoryIndexCronCreate( $postID, $post['category']);

			echo "<br>Post Added: ".$post['post_title']."<br>";
			file_put_contents(dirname(__FILE__)."/createPost.log", print_r(self::$destinationsiteUrl, true),FILE_APPEND );
			file_put_contents(dirname(__FILE__)."/createPost.log", print_r("\n", true),FILE_APPEND );
			file_put_contents(dirname(__FILE__)."/createPost.log", print_r("Post Created: ID: ".$postID." Post Title: ".$post['post_title'], true),FILE_APPEND );
			file_put_contents(dirname(__FILE__)."/createPost.log", print_r("\n", true),FILE_APPEND );
		}
	}
	/*===================================================================================================== */



	/*====================== section to insert posts ======================*/
	public static function setupPosts( ){
		$feeds = AwbXmlInterface::getFeeds();

		foreach ($feeds as $feed) {
			self::addPosts( $feed );
		}
		self:: updateCategoryIndex();
	}

	public static function addPosts( $feed ){

		$rssPosts = AwbRssInterface::getPosts($feed);
		foreach ($rssPosts as $post ) {

			$postID = self::insertPost( $post );

			if($postID ){
				//update category.
				$term_id = self::$categories[$post['category']];
				self::insertCategory( $postID, $term_id );

				// add post Attachment.
				$postmeta = array();

				if( !empty($post['enclosure'])){
					$thumbnailid = self::insertAttachment($postID, $post );
					if( $thumbnailid ){
						$postmeta['_thumbnail_id'] 	= $thumbnailid;
						$postmeta['enclosure'] 		= $post['enclosure'];
					}
				}

				// add post Meta.
				$postmeta["sm:block"] 				=  	$post['smblock'];
				$postmeta["sm:meta-title"] 			=  	$post['smmetatitle'];
				$postmeta["sm:meta-description"] 	= 	$post['smmetadesc'];
				$postmeta["sm:meta-image"] 			=	$post['smmetaimage'];

				if(!empty($post['sourcelink'])){
					$postmeta["syndication_permalink"] = 	$post['sourcelink'];
				}
				if(!empty($post['sourcelink'])){
					$postmeta["syndication_permalink"] = 	$post['sourcelink'];
				}

				self::insertPostMeta( $postID, $postmeta);
			}




		}
	}

	public static function insertPost( $post ){

		$wpdb 	= self::$destinationWpdb;

		$wpdb->insert(
			'wp_posts',
			array(
				'post_author' 			=> $post['post_author'],
				'post_date'				=> $post['post_date'],
				'post_date_gmt'			=> $post['post_date'],
				'post_content'			=> $post['description'],
				'post_title'			=> $post['post_title'],
				'post_name'				=> $post['post_name'],
				'post_excerpt'			=> $post['excerpt'],
				'post_status'			=> 'publish',
				'comment_status'		=> 'closed',
				'ping_status'			=> 'closed',
				'post_password'			=> '',
				'to_ping'				=> '',
				'pinged'				=> '',
				'post_modified'			=> '',
				'post_modified_gmt'		=> '',
				'post_content_filtered'	=> '',
				'post_parent'			=> 0,
				'post_type'				=> 'post',
				'post_mime_type'		=> '',
				'comment_count' 		=> 0,
				'menu_order'			=> '',
				'sm_block'				=> $post['smblock'],
			),
			array(
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
				'%d',
				'%d',
				'%s',
			)
		);

		$lastID = $wpdb->insert_id;


		$guid = self::$destinationsiteUrl."?".$lastID;

		$wpdb->update(
			'wp_posts',
			array(
				'guid'		=> $guid,
				'sm_block'	=> $post['smblock'],
			),
			array( 'ID' => $lastID ),
			array(
				'%s',
				'%s'
			),
			array( '%d' )
		);
		return  $lastID;

	}

	public static function insertPostMeta( $postID, $postmeta) {

		$wpdb 	= self::$destinationWpdb;

		foreach ( $postmeta as $meta_key => $meta_value) {
			$wpdb->insert(
				'wp_postmeta',
				array(
					'post_id' 		=> $postID,
					'meta_key' 		=> $meta_key,
					'meta_value' 	=> $meta_value,
				),
				array(
					'%d',
					'%s',
					'%s',
				)
			);
		}
	}

	public static function insertAttachment( $postID, $post ) {

		$wpdb 	= self::$destinationWpdb;
		if(!empty($post['enclosure'])){
			$enclosure   = $post['enclosure'];
			$source 	 = $enclosure;
			$destination = self::$destination."/wp-content/uploads/".basename($enclosure)."";			// if(!is_dir($destination)){
			// 	mkdir(dirname($destination), 0775, true);
			// }
			@copy($source,$destination);
			$postmetaarray["enclosure"] = $enclosure;

			$imageurlguid 	= self::$destination."/wp-content/uploads/".basename($enclosure)."";
			$posttitle 		= preg_replace('/\.[^.]+$/', '', basename($enclosure));
			$postname 		= sanitize_title($posttitle);

			$wpdb->insert(
			'wp_posts',
				array(
					'post_author'  		=> $post_author,
					'post_date'  		=> $post['post_date'],
					'post_date_gmt'   	=> $post['post_date'],
					'post_title'  		=> $posttitle,
					'post_status'  		=> "inherit",
					'comment_status'  	=> "open",
					'ping_status'  		=> "open",
					'post_name'  		=> $postname,
					'post_modified'  	=> $post['post_date'],
					'post_modified_gmt' => $post['post_date'],
					'post_parent'  		=> $postID,
					'guid'  			=> $imageurlguid,
					'post_type'  		=> "attachment",
					'post_mime_type'  	=> $post['post_mimie_type']

				),
				array(
					'%d',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%s',
					'%d',
					'%s',
					'%s',
					'%s',
					'%s',
				)
			);

			$thumbnailid = $wpdb->insert_id;

			if(!empty($enclosure) && $thumbnailid != 0 ){
				$wpdb->insert(
					'wp_postmeta',
					array(
						'post_id'		=>	$thumbnailid,
						'meta_key'		=> '_wp_attached_file',
						'meta_value' 	=> basename($enclosure),
					),
					array(
						'%d',
						'%s',
						'%s',
					)
				);

			}
			return $thumbnailid;
		}else{
			return false;
		}
	}

	public static function insertCategory( $object_id, $term_id ){

		$wpdb 	= self::$destinationWpdb;
		$sql 	= "SELECT *
					FROM `wp_term_taxonomy`
					WHERE 	`term_id` = $term_id LIMIT 0,1";

		$result = $wpdb->get_row( $sql );
		$count  =  $result->count;

		$count  =  $count + 1;

		$wpdb->update(
			'wp_term_taxonomy',
			array(
				'count' => $count,
			),
			array( 'term_id' => $term_id ),
			array(
				'%d',
			),
			array( '%d' )
		);

		$wpdb->insert(
			'wp_term_relationships',
			array(
				'object_id' 		=> $object_id,
				'term_taxonomy_id' 	=> $term_id,
				'term_order' 		=> 0,
			),
			array(
				'%d',
				'%d',
				'%d',
			)
		);
		return true;
	}


	public static function updateCategoryIndex(){
		$wpdb 	= self::$destinationWpdb;

		$categories = self::$categories;
		foreach ($categories as $key => $value) {
			$sql = "INSERT INTO `category_post_index`(`post_ID`, `post_category`)
				SELECT ID, '$key'
					FROM wp_posts WHERE sm_block LIKE '%$key%'
					GROUP BY post_title
					ORDER BY ID DESC LIMIT 0,100;";

			$wpdb->query($sql);
		}


		return true;
	}

	public static function updateCategoryIndexCronCreate($postID, $categoryName ) {
        $wpdb 	= self::$destinationWpdb;

        $firstPost = $wpdb->get_row( "SELECT * FROM `category_post_index` WHERE post_category = '$categoryName' ORDER BY ID ASC LIMIT 0,1" );
        $IndexID = $firstPost->ID;
        if( $IndexID ){
            $wpdb->delete( 'category_post_index', array( 'ID' => $IndexID ), array( '%d' ) );
        }

        $wpdb->insert(
            'category_post_index',
            array(
                'post_ID' => $postID,
                'post_category' => $categoryName
            ),
            array(
                '%d',
                '%s'
            )
        );
	}

	public static function updateCategoryIndexCronUpdate($postID, $categoryName ) {
        $wpdb 	= self::$destinationWpdb;

       	$wpdb->delete( 'category_post_index', array( 'post_ID' => $postID ), array( '%d' ) );

       	$wpdb->insert(
            'category_post_index',
            array(
                'post_ID' => $postID,
                'post_category' => $categoryName
            ),
            array(
                '%d',
                '%s'
            )
        );
	}

	/*=====================================================================*/



	/*=================================Update post section ====================================*/
	public static function updatePost($post_id, $post ){
		$wpdb 	= self::$destinationWpdb;

		$wpdb->update(
			'wp_posts',
			array(
				'sm_block'	=> $post['smblock'],
			),
			array( 'ID' => $post_id ),
			array(
				'%s',
			),
			array( '%d' )
		);
		return true;
	}

	public static function updatePost1( $post_id, $post ){

		$wpdb 	= self::$destinationWpdb;

		$wpdb->update(
			'wp_posts',
			array(
				'post_author' 			=> $post['post_author'],
				'post_date'				=> $post['post_date'],
				'post_date_gmt'			=> $post['post_date'],
				'post_content'			=> $post['description'],
				'post_title'			=> $post['post_title'],
				'post_name'				=> $post['post_name'],
				'post_excerpt'			=> $post['excerpt'],
				'post_status'			=> 'publish',
				'comment_status'		=> 'closed',
				'ping_status'			=> 'closed',
				'post_password'			=> '',
				'to_ping'				=> '',
				'pinged'				=> '',
				'post_modified'			=> '',
				'post_modified_gmt'		=> '',
				'post_content_filtered'	=> '',
				'post_parent'			=> 0,
				'post_type'				=> 'post',
				'post_mime_type'		=> '',
				'comment_count' 		=> 0,
				'menu_order'			=> '',
				'sm_block'				=> $post['smblock'],
			),
			array( 'ID' => $post_id ),
			array(
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%d',
				'%s',
				'%s',
				'%d',
				'%d',
				'%s',
			),
			array( '%d' )
		);

		return true;
	}


	public static function updatePostMeta( $postID, $postmeta) {
		$wpdb 	= self::$destinationWpdb;
		foreach ( $postmeta as $meta_key => $meta_value) {
			$sql = "UPDATE wp_postmeta
						SET `meta_value` = '$meta_value',
    					WHERE `post_id` = '$postID' AND `meta_key` = '$meta_key'";
			$wpdb->query( $sql );
		}
		return true;
	}



	/*=========================================================================================*/
}/* class ends here */

?>