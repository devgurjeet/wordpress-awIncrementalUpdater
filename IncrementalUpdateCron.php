<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require '/var/www/html/wp-load.php';
require 'AwbDbInterfaceCron.php';

class IncrementalUpdateCron{
	public static $lastTimeUpdated;
	public static $sourceBlogs;
	public static $feedBlogs;
	public static $feedBlogsFiltered;

	function __construct(){
		self::getLastTimeUpdated();
		self::logTime();
	}

	public static function getLastTimeUpdated(){
		// self::$lastTimeUpdated = 1479907291169;
		self::$lastTimeUpdated = file_get_contents(dirname(__FILE__)."/time/lastupdate.log");
	}
	public static function updateLastTimeUpdated( $lastupdated = null ){
		if( $lastupdated == null ){
			$lastupdated = time();
		}

		file_put_contents(dirname(__FILE__)."/time/lastupdate.log", print_r($lastupdated, true));
	}

	//** Executionn Log Time**//
	public static function logTime(){

		// $time 			=  date('Y-m-d H:i:s');
		// $logTime 		= "Updated at: ". $time. "\n";
		// $file_time 		=  date('d-m-Y');
		// $log_filname 	= "logs/autoScriptLog-".$file_time .".log";
		// //** Hold list of all Blogs **//
		// $blogUpdate 	= array();
		//** Executionn Log Time code ends **//
		set_time_limit(0);

		global $wpdb;
		//** All RSS FEEDS WILL BE STORED IN THIS ARRAY **//
		$rssfeedarray 	= array();
		$rssFileList 	= $wpdb->get_results("SELECT * FROM wp_rssfilelist WHERE status='Active'");
		// $feedBlogs 		= self::getRssFeedLinks($rssFileList);

		self::$feedBlogs 	= self::getRssFeedLinks($rssFileList);
		self::$sourceBlogs 	= AwbDbInterfaceCron::getBloglist();

		self::processBlogs();
		self::processFilteredBlogs();
	}



	public static function processBlogs(){

		$allBlogs = array_map(function ($ar) {return $ar[2];}, self::$feedBlogs);

		$allBlogs  	= array_unique($allBlogs);
		$allBlogs1  = array();

		foreach ($allBlogs as $blog) {
			$tempBlogData = array();
			foreach (self::$feedBlogs as $item ) {
				if( $blog === $item[2] ){
					$tempBlogData[] = $item;
				}
			}
			$allBlogs1[$blog] = $tempBlogData;
		}
		self::$feedBlogsFiltered = $allBlogs1;
	}

	public static function processFilteredBlogs(){
		$feedBlogsFiltered 	= self::$feedBlogsFiltered;
		$sourceBlogs	 	= self::$sourceBlogs;


		foreach ($sourceBlogs as $blog) {
			$blogName  = $blog['site_slug'];

			// if(  $blogName == 'friidrettsnytt' OR $blogName == 'fotball-pl'){
			if(   $blogName == 'fotball-pl' OR $blogName == 'fotboll-pl'){
				// continue;
			}else {
				continue;
			}

			if (!array_key_exists( $blogName, $feedBlogsFiltered)) {
			    echo "No Updates for: ".$blogName;
			    continue;
			}
			echo "<br>Updates for: ".$blogName."<br>";
			/*connect to destination DB */
			AwbDbInterfaceCron::setDestinationDb( $blogName  );

			$blogFeeds = $feedBlogsFiltered[$blogName];
			if( !empty($blogFeeds) ){
				AwbDbInterfaceCron::getDestionationLinks( $blogName );

				foreach ($blogFeeds as $item) {
					self::processItem($blogName,  $item );
				}
			}
		}
	}

	public static function processItem( $blogName,  $item){
		$options 		= $item[1];
		$blogSlug 		= $item[2];
		$feed 			= $item[3];

		$newFeed	=	str_replace('_incr', '', $feed);
		if( !in_array($newFeed, AwbDbInterfaceCron::$destionationLinks)){
			return;
		}

		$optionItems 	= explode(":", $options);
		$count 			= count($optionItems);
		$posts 			= self::getPosts( $feed, $count );
		$postsCount 	= count($posts);
		$postsToProcess = min($count, $postsCount);

		for ($i = 0; $i < $postsToProcess; $i++) {
			$option 	= $optionItems[$i];
			$post 		= $posts[$i];
			$post_id 	= AwbDbInterfaceCron::checkIfpostExists( $post['smguid'] );

			if( $option == '*' ){
				if( !$post_id ){
					AwbDbInterfaceCron::addPostsCron( $post );
				}else{
					AwbDbInterfaceCron::updatePostsCron( $post_id, $post );
				}
			}else if( $option == 'B' ){
				if( !$post_id ){
					AwbDbInterfaceCron::addPostsCron( $post );
				}else{
					AwbDbInterfaceCron::updatePostsCron( $post_id, $post );
				}
			}
		}
	}

	public static function getPosts( $rss, $count ){
		$rssDocument = new DOMDocument();

		$rssDocument->load($rss);

		$rssPosts 		= array();
		$items = 0;
		foreach ($rssDocument->getElementsByTagName('item') as $node ){

			$description 	=	preg_replace('~>\s+<~m', '><', $node->getElementsByTagName('description')->item(0)->nodeValue);
			$description 	= 	trim($description);

			$enclosurelink 	= $node->getElementsByTagName('enclosure');

			if( $enclosurelink->item(0)!=""){
				$URL 		= $enclosurelink->item(0)->getAttribute('url');
				$imagetype 	= $enclosurelink->item(0)->getAttribute('type');
			}else{
				$URL 		= "";
				$imagetype 	= "";
			}

			$title 	= preg_replace('/\s+/',' ',trim($node->getElementsByTagName('title')->item(0)->nodeValue));

			$postItem['post_title'] 		=	$title;
			$postItem['post_name'] 			= 	sanitize_title($title);
			$postItem['post_date'] 			= 	date("Y-m-d H:i:s",strtotime($node->getElementsByTagName('pubDate')->item(0)->nodeValue));
			$postItem['description'] 		= 	$description;
			$postItem['excerpt'] 			= 	$description;

			$postItem['post_author'] 		= 	1;
			$postItem['category'] 			= 	preg_replace('~>\s+<~m', '><', $node->getElementsByTagName('category')->item(0)->nodeValue);

			$postItem['smblock'] 			= 	$node->getElementsByTagName('block')->item(0)->nodeValue;
			$postItem['smmetatitle']		= 	$node->getElementsByTagName('meta-title')->item(0)->nodeValue;
			$postItem['smmetadesc']			=	$node->getElementsByTagName('meta-description')->item(0)->nodeValue;
			$postItem['smmetaimage']		= 	$node->getElementsByTagName('meta-image')->item(0)->nodeValue;
			$postItem['enclosure'] 			= 	$URL;
			$postItem['post_mimie_type'] 	= 	$imagetype;
			$postItem['sourcelink'] 		= 	$node->getElementsByTagName('link')->item(0)->nodeValue;
			$postItem['smguid'] 			= 	$node->getElementsByTagName('guid')->item(0)->nodeValue;


			$rssPosts[] = $postItem;
			$items++;

		}
		return $rssPosts;
	}

	public static function getRssFeedLinks($rssFileList){

		$rssBlogUpdates  	= array();

		$fileurlarray 	= array();
		for( $x = 0; $x < count($rssFileList); $x++ ){
			$fileurlarray[] = $rssFileList[$x]->fileurl;
		}

		return $rssBlogUpdates  = self::getIncrementalData( $fileurlarray );
	}

	public static function getIncrementalData( $fileurlarray ){
		$rssBlogUpdates = array();
		//** Check if there are files to exceute. **//
		if( !empty( $fileurlarray ) ){
			//** check each file in the list. **//
			foreach( $fileurlarray as $file ){
				$contents 	= file( $file, true);
				$cnt 		= 0;
				$i 			= 0;

				foreach( $contents as $feedItem ){
					//** get each line form the cron jon list. **//
					// $contentssub 	= explode("\n",	$value );
					if( $feedItem == "" ){
						continue;
					}
					$itemValues = preg_split("/[\s,]+/", $feedItem );

					if ( $itemValues[0] <= self::$lastTimeUpdated) {
						break;
					}

					$rssBlogUpdates[] = $itemValues;
					continue;
				}
				unset( $contents );
			}

			/*update last updated. */
			self::updateLastTimeUpdated($rssBlogUpdates[0][0]);

			usort($rssBlogUpdates, 'self::sortByTime');
			return $rssBlogUpdates;

		}else{
			return false;
		}
	}

	/*function to sort array by time. */
	public static function sortByTime ($a, $b){
		return $a[0] - $b[0];
	}
}

$IncrementalUpdateCron = new IncrementalUpdateCron();
?>