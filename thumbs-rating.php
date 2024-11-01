<?php
/*
Plugin Name: Thumbs Rating
Plugin URI: http://wordpress.org/plugins/thumbs-rating/
Description: Add thumbs up/down rating to your content.
Author: Ricard Torres
Version: 5.0.0
Author URI: https://ricard.dev/
Text Domain: thumbs-rating
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/*-----------------------------------------------------------------------------------*/
/* Define the URL and DIR path */
/*-----------------------------------------------------------------------------------*/

define('thumbs_rating_url', plugins_url() ."/".dirname( plugin_basename( __FILE__ ) ) );
define('thumbs_rating_path', WP_PLUGIN_DIR."/".dirname( plugin_basename( __FILE__ ) ) );


/*-----------------------------------------------------------------------------------*/
/* Init */
/* Localization */
/*-----------------------------------------------------------------------------------*/


if  ( ! function_exists( 'thumbs_rating_init' ) ):

	function thumbs_rating_init() {

		load_plugin_textdomain( 'thumbs-rating', false, basename( dirname( __FILE__ ) ) . '/languages' );
	}
	add_action('plugins_loaded', 'thumbs_rating_init');

endif;



/*-----------------------------------------------------------------------------------*/
/* Encue the Scripts for the Ajax call */
/*-----------------------------------------------------------------------------------*/

if  ( ! function_exists( 'thumbs_rating_scripts' ) ):

	function thumbs_rating_scripts()
	{
		wp_enqueue_script('thumbs_rating_scripts', thumbs_rating_url . '/js/general.js', array(), '4.0.3');
		wp_localize_script( 'thumbs_rating_scripts', 'thumbs_rating_ajax', array( 'ajax_url' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'thumbs-rating-nonce' ) ) );
	}
	add_action('wp_enqueue_scripts', 'thumbs_rating_scripts');

endif;


/*-----------------------------------------------------------------------------------*/
/* Encue the Styles for the Thumbs up/down */
/*-----------------------------------------------------------------------------------*/

if  ( ! function_exists( 'thumbs_rating_styles' ) ):

	function thumbs_rating_styles()
	{
		wp_register_style( "thumbs_rating_styles",  thumbs_rating_url . '/css/style.css' , "", "4.0.3");
		wp_enqueue_style( 'thumbs_rating_styles' );
	}
	add_action('wp_enqueue_scripts', 'thumbs_rating_styles');

endif;

/*-----------------------------------------------------------------------------------*/
/* Add the thumbs up/down links to the content */
/*-----------------------------------------------------------------------------------*/

if  ( ! function_exists( 'thumbs_rating_getlink' ) ):

	function thumbs_rating_getlink($post_ID = '', $type_of_vote = '', $wrapper = true)
	{

		// Sanatize params

		$post_ID = intval( sanitize_text_field( $post_ID ) );
		$type_of_vote = intval ( sanitize_text_field( $type_of_vote ) );

		$thumbs_rating_link = "";

		if( !$post_ID ) $post_ID = get_the_ID();

		$thumbs_rating_up_count = get_post_meta($post_ID, '_thumbs_rating_up', true) != '' ? get_post_meta($post_ID, '_thumbs_rating_up', true) : '0';
		$thumbs_rating_down_count = get_post_meta($post_ID, '_thumbs_rating_down', true) != '' ? get_post_meta($post_ID, '_thumbs_rating_down', true) : '0';

		$link_up = '<button class="thumbs-rating-up'. ( (isset($thumbs_rating_up_count) && intval($thumbs_rating_up_count) > 0 ) ? ' thumbs-rating-voted' : '' ) .'" onclick="thumbs_rating_vote(' . $post_ID . ', 1);">' . __('Vote Up','thumbs-rating') . ' +' . $thumbs_rating_up_count . '</button>';
		$link_down = '<button class="thumbs-rating-down'. ( (isset($thumbs_rating_down_count) && intval($thumbs_rating_down_count) > 0 ) ? ' thumbs-rating-voted' : '' ) .'" onclick="thumbs_rating_vote(' . $post_ID . ', 2);">' . __('Vote Down','thumbs-rating') . ' -' . $thumbs_rating_down_count . '</button>';

		$thumbs_rating_link = '';

		if ($wrapper == true) {
			$thumbs_rating_link .= '<div  class="thumbs-rating-container" id="thumbs-rating-'.$post_ID.'" data-content-id="'.$post_ID.'">';
		}

		$thumbs_rating_link .= $link_up;
		$thumbs_rating_link .= ' ';
		$thumbs_rating_link .= $link_down;
		$thumbs_rating_link .= '<span class="thumbs-rating-already-voted">' . __('You already voted!', 'thumbs-rating') . '</span>';

		if ($wrapper == true) {
			$thumbs_rating_link .= '</div>';
		}

		return $thumbs_rating_link;
	}

endif;


/*-----------------------------------------------------------------------------------*/
/* Handle the Ajax request to vote up or down */
/*-----------------------------------------------------------------------------------*/

if  ( ! function_exists( 'thumbs_rating_add_vote_callback' ) ):

	function thumbs_rating_add_vote_callback()
	{

		// Check the nonce - security
		check_ajax_referer( 'thumbs-rating-nonce', 'nonce' );

		global $wpdb;

		// Get the POST values

		$post_ID = intval( $_POST['postid'] );
		$type_of_vote = intval( $_POST['type'] );

		// If the post is private, check if the user is logged in

		if ( get_post_status ( $post_ID ) == 'private' && !is_user_logged_in()) {
			die( 'This post is private' );
		}

		// Check the type and retrieve the meta values

		if ( $type_of_vote == 1 ){

			$meta_name = "_thumbs_rating_up";

		} elseif( $type_of_vote == 2){

			$meta_name = "_thumbs_rating_down";

		}

		// Retrieve the meta value from the DB

		$thumbs_rating_count = get_post_meta($post_ID, $meta_name, true) != '' ? get_post_meta($post_ID, $meta_name, true) : '0';
		$thumbs_rating_count = $thumbs_rating_count + 1;

		// Update the meta value

		update_post_meta($post_ID, $meta_name, $thumbs_rating_count);

		$results = thumbs_rating_getlink($post_ID, $type_of_vote, false);

		do_action( 'thumbs_rating_vote_action', $post_ID, $type_of_vote, $thumbs_rating_count);

		die($results);
	}

	add_action( 'wp_ajax_thumbs_rating_add_vote', 'thumbs_rating_add_vote_callback' );
	add_action('wp_ajax_nopriv_thumbs_rating_add_vote', 'thumbs_rating_add_vote_callback');

endif;


/*-----------------------------------------------------------------------------------*/
/* Add Votes +/- columns to the Admin */
/*-----------------------------------------------------------------------------------*/

if  ( ! function_exists( 'thumbs_rating_columns' ) ):

	function thumbs_rating_columns($columns)
	{
	    return array_merge($columns,
	              array('thumbs_rating_up_count' =>  __( 'Up Votes', 'thumbs-rating' ),
	                    'thumbs_rating_down_count' => __( 'Down Votes', 'thumbs-rating' )));
	}
	add_filter('manage_posts_columns' , 'thumbs_rating_columns');
	add_filter('manage_pages_columns' , 'thumbs_rating_columns');

endif;


/*-----------------------------------------------------------------------------------*/
/* Add Values to the new Admin columns */
/*-----------------------------------------------------------------------------------*/

if  ( ! function_exists( 'thumbs_rating_column_values' ) ):

	function thumbs_rating_column_values( $column, $post_id ) {
	    switch ( $column ) {
		case 'thumbs_rating_up_count' :
		   	echo get_post_meta($post_id, '_thumbs_rating_up', true) != '' ? "+" . get_post_meta($post_id, '_thumbs_rating_up', true) : '0';
		   break;

		case 'thumbs_rating_down_count' :
		      echo get_post_meta($post_id, '_thumbs_rating_down', true) != '' ? "-" . get_post_meta($post_id, '_thumbs_rating_down', true) : '0';
		    break;
	    }
	}

	add_action( 'manage_posts_custom_column' , 'thumbs_rating_column_values', 10, 2 );
	add_action( 'manage_pages_custom_column' , 'thumbs_rating_column_values', 10, 2 );

endif;


/*-----------------------------------------------------------------------------------*/
/* Make our columns are sortable */
/*-----------------------------------------------------------------------------------*/

if  ( ! function_exists( 'thumbs_rating_sortable_columns' ) ):

	function thumbs_rating_sortable_columns( $columns )
	{
		$columns[ 'thumbs_rating_up_count' ] = 'thumbs_rating_up_count';
		$columns[ 'thumbs_rating_down_count' ] = 'thumbs_rating_down_count';
		return $columns;
	}


	// Apply this to all public post types

	add_action( 'admin_init', 'thumbs_rating_sort_all_public_post_types' );

	function thumbs_rating_sort_all_public_post_types() {

		foreach ( get_post_types( array( 'public' => true ), 'names' ) as $post_type_name ) {

			add_action( 'manage_edit-' . $post_type_name . '_sortable_columns', 'thumbs_rating_sortable_columns' );
		}

		add_filter( 'request', 'thumbs_rating_column_sort_orderby' );
	}

	// Tell WordPress our fields are numeric

	function thumbs_rating_column_sort_orderby( $vars ) {

		if ( isset( $vars['orderby'] ) && 'thumbs_rating_up_count' == $vars['orderby'] ) {
			$vars = array_merge( $vars, array(
				'meta_key' => '_thumbs_rating_up',
				'orderby'  => 'meta_value_num'
			) );
		}
		if ( isset( $vars['orderby'] ) && 'thumbs_rating_down_count' == $vars['orderby'] ) {
			$vars = array_merge( $vars, array(
				'meta_key' => '_thumbs_rating_down',
				'orderby'  => 'meta_value_num'
			) );
		}
		return $vars;
	}

endif;

/*-----------------------------------------------------------------------------------*/
/* Two functions to show the ratings values in your theme */
/*-----------------------------------------------------------------------------------*/

if  ( ! function_exists( 'thumbs_rating_show_up_votes' ) ):
	function thumbs_rating_show_up_votes ( $post_id = "") {

		   if( $post_id == "" ){

		   	$post_id = get_the_ID();
		   }else{

		   	$post_id = intval( sanitize_text_field( $post_id ) );
		   }

		    return get_post_meta($post_id, '_thumbs_rating_up', true) != '' ? get_post_meta($post_id, '_thumbs_rating_up', true) : '0';
	}
endif;

if  ( ! function_exists( 'thumbs_rating_show_down_votes' ) ):
	function thumbs_rating_show_down_votes ( $post_id = "") {

		   if( $post_id == "" ){

		   	$post_id = get_the_ID();
		   }else{

		   	$post_id = intval( sanitize_text_field( $post_id ) );
		   }

		    return get_post_meta($post_id, '_thumbs_rating_down', true) != '' ? get_post_meta($post_id, '_thumbs_rating_down', true) : '0';
	}
endif;


/*-----------------------------------------------------------------------------------*/
/* Top Votes Shortcode [thumbs_rating_top] */
/*-----------------------------------------------------------------------------------*/

if  ( ! function_exists( 'thumbs_rating_top_func' ) ):
	function thumbs_rating_top_func( $atts ) {

		$return = '';

		// Parameters accepted

		extract( shortcode_atts( array(
			'include_posts' => '',
			'exclude_posts' => '',
			'type' => 'positive',
			'posts_per_page' => 5,
			'category' => '',
			'show_votes' => 'yes',
			'post_type' => 'any',
			'show_both' => 'no',
			'order' => 'DESC',
			'orderby' => 'meta_value_num'
		), $atts ) );

		// Check wich meta_key the user wants

		if( $type == 'positive' ){

				$meta_key = '_thumbs_rating_up';
				$other_meta_key = '_thumbs_rating_down';
				$sign = "+";
				$other_sign = "-";
		}
		else{
				$meta_key = '_thumbs_rating_down';
				$other_meta_key = '_thumbs_rating_up';
				$sign = "-";
				$other_sign = "+";
		}

		// Build up the args array

	  $args = array (
	    'post_type'				=> $post_type,
			'post_status'			=> 'publish',
			'cat'					=> $category,
			'pagination'			=> false,
			'posts_per_page'		=> $posts_per_page,
			'cache_results'			=> true,
			'meta_key'				=> $meta_key,
			'order'					=> $order,
			'orderby'				=> $orderby,
			'ignore_sticky_posts'	=> true
		);

		// Has to be done this way, or the query will return 0 results

		if ($exclude_posts) {
			$args['post__not_in'] = explode(",", $exclude_posts);
		}

		if ($include_posts) {
			$args['post__in'] = explode(",", $include_posts);
		}

		// Get the posts

		$thumbs_ratings_top_query = new WP_Query($args);

		// Build the post list

		if($thumbs_ratings_top_query->have_posts()) :

			$return .= '<ol class="thumbs-rating-top-list">';

			while($thumbs_ratings_top_query->have_posts()){

				$thumbs_ratings_top_query->the_post();

				$return .= '<li>';

				$return .= '<a href="' . get_permalink() . '">' . get_the_title() . '</a>';

				if( $show_votes == "yes" ){

					// Get the votes

					$meta_values = get_post_meta(get_the_ID(), $meta_key);

					// Add the votes to the HTML

						$return .= ' (' . $sign;

						if( sizeof($meta_values) > 0){

							$return .= $meta_values[0];

						}else{

							$return .= "0";
						}

						// Show the other votes if needed

						if( $show_both == 'yes' ){

							$other_meta_values = get_post_meta(get_the_ID(), $other_meta_key);

							$return .= " " . $other_sign;

							if( sizeof($other_meta_values) > 0){

								$return .= $other_meta_values[0];

							}else{

								$return .= "0";
							}
						}

						$return .= ')';

					}
				}

				$return .= '</li>';


			$return .= '</ol>';

			// Reset the post data or the sky will fall

			wp_reset_postdata();

		endif;

		return $return;
	}

	add_shortcode( 'thumbs_rating_top', 'thumbs_rating_top_func' );
endif;


/*-----------------------------------------------------------------------------------*/
/* Create Shortcode for the buttons */
/*-----------------------------------------------------------------------------------*/

function thumbs_rating_shortcode_func( $atts ){

	$return = thumbs_rating_getlink();

	return $return;
}
add_shortcode( 'thumbs-rating-buttons', 'thumbs_rating_shortcode_func' );
