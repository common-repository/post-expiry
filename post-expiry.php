<?php
/*
	Plugin Name: Post Expiry Plugin
	Plugin URI: http://www.tonibarrett.com/post-expiry
	Description: Simple plugin to move your blog posts into another category at a specified time. This is useful for if you have a post that you want moved out of a featured category or one you want to archive without having to delete.
	Author: Toni Barrett
	Version: 1.1
	Author URI: http://www.tonibarrett.com
*/

/*	
	Copyright 2011  Toni Barrett  (email : wordpress@tonibarrett.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as 
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

*/
	$id_post = get_the_ID();
	add_action('add_meta_boxes', 'postexpiry');

	/* Do something with the data entered */
	add_action('save_post', 'postexpiry_save_postdata');

	/* Add a box to the sidebar on the Post add/edit screens */
	function postexpiry() {
		add_meta_box( 'postexpiry_sectionid', __( 'Post Expiry Date', 'postexpiry_textdomain' ), 
					'postexpiry_inner_custom_box', 'post', 'side' );
	}

	/* Display the box content */
	function postexpiry_inner_custom_box() {

		// Use nonce for verification
		wp_nonce_field( plugin_basename(__FILE__), 'postexpiry_noncename' );
			
		// The actual fields for data entry
		echo '<p>Expires: (24 hour time)</p>';
		
		global $post_ID;
		$expiry_date = get_post_meta($post_ID, 'post-expiry', true);
			if ($expiry_date){
					$current_month = substr(trim($expiry_date), 5, 2);
					$current_year = substr(trim($expiry_date), 0, 4);
					$current_day = substr(trim($expiry_date), -8, 2);
					$current_hour = substr(trim($expiry_date), -5, 2);
					$current_min = substr(trim($expiry_date), -2);																
					
				}
		$months = array('','Jan','Feb','Mar','Apr','May','June','July','Aug','Sep','Oct','Nov','Dec');

		echo('<select id="expiry-month" name="expiry-month">');

		for ($i = 01; $i <= 12; $i++) {
			echo '<option value="'.$i.'"';
			if ($i == $current_month){
				echo 'selected = "selected" ';
			}
			echo '>'.$months[$i].'</option>';
		}
		echo('</select>
			<input type="text" id="expiry-day" name="expiry-day" value="'.$current_day.'" size="2" maxlength="2" style="width:2em;" />,
			<input type="text" id="expiry-year" name="expiry-year" value="'.$current_year.'" size="4" maxlength="4" style="width:4em;" /> @ 
			<input type="text" id="expiry-hour" name="expiry-hour" value="'.$current_hour.'" size="2" maxlength="2" style="width:2em;" /> : 
			<input type="text" id="expiry-min" name="expiry-min" value="'.$current_min.'" size="2" maxlength="2" style="width:2em;" /> 
			<br />
			Move to: 
				<select id="expiry-category" name="expiry-category">');
				$categories = get_all_category_ids();
				foreach($categories as $i => $value) {
				  $catname = get_catname($categories[$i]);
				  echo '<option value="'.$categories[$i].'">'.$catname.'</option>';
				}
				echo '</select>';
		}

	/* When the post is saved, saves our custom data */
	function postexpiry_save_postdata( $post_id ) {
		global $flag;
		if ($flag == 1) {

			// Verify this came from the our screen and with proper authorization
			if ( !wp_verify_nonce( $_POST['postexpiry_noncename'], plugin_basename(__FILE__) )) {
				return $post_id;
			}

			// Verify if this is an auto save routine. If it is our form has not been submitted, so we dont want to do anything
			if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) 
				return $post_id;
			  
			// Check permissions
			if ( 'page' == $_POST['post_type'] ) {
				if ( !current_user_can( 'edit_page', $post_id ) )
				  return $post_id;
			} else {
				if ( !current_user_can( 'edit_post', $post_id ) )
				  return $post_id;
			}

			// OK, we're authenticated: we need to find & clean the data
			$posted_day = $_POST['expiry-day'];
			if (strlen($posted_day) ==1){ $posted_day = '0'.$posted_day;}
			
			$posted_month = $_POST['expiry-month'];
			if (strlen($posted_month) ==1){ $posted_month = '0'.$posted_month;}
			
			$posted_hour = $_POST['expiry-hour'];
			if (strlen($posted_hour) ==1){ $posted_hour = '0'.$posted_hour;}
			
			$posted_min = $_POST['expiry-min'];
			if (strlen($posted_min) ==1){ $posted_min = '0'.$posted_min;}
			
			$expiry_date = $_POST['expiry-year'] . '/' .$posted_month. '/' .$posted_day. ' ' .$posted_hour. ':' .$posted_min; 	  

			//Insert into database
			if (strlen($expiry_date) > 15){
				add_post_meta($post_id, 'post-expiry', $expiry_date , true) or update_post_meta($post_id, 'post-expiry', $expiry_date );
			
				$expiry_category = $_POST['expiry-category'];
				add_post_meta($post_id, 'post-expiry-category', $expiry_category , true) or update_post_meta($post_id, 'post-expiry-category', $expiry_category );
			}
			else{ return;}
			postexpiry_schedule_event($expiry_date, $post_id);
			return;
		}
		$flag = 1;
	}
	
		//Schedule a job so that the post will be moved when the expiry date is reached
		function postexpiry_schedule_event($expiry_date, $post_id){
			$expiry_time = strtotime($expiry_date);
			wp_schedule_single_event($expiry_time, 'postexpiry_move_post_event', array($post_id));								
		}
		
		//Move the post to the category specified
		function postexpiry_move_post($post_id) {
			$postid = $post_id;
			$post_meta = str_split(get_post_meta($postid, 'post-expiry-category', true));
			wp_set_post_categories($postid, $post_meta);
		}

		add_action('postexpiry_move_post_event','postexpiry_move_post');			

?>
