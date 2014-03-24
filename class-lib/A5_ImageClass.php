<?php

/**
 *
 * Class A5 Images
 * * @ A5 Plugin Framework
 * Version: 0.9.8 alpha
 *
 * Gets the alt and title tag for attachments
 *
 * Gets all thumbnail related stuff
 *
 */

class A5_Image {
	
	public static function tags($language_file) {
		
		$id = get_the_ID();
		
		if (has_post_thumbnail()) :
			
			$attachment_id = get_post_thumbnail_id();
			
			$attachment = get_post($attachment_id);
		
		else:
			
			$args = array(
				'post_type' => 'attachment',
				'posts_per_page' => 1,
				'post_status' => null,
				'post_parent' => $id
			);
			
			$attachments = get_posts( $args );
			
			if ( $attachments ) $attachment = $attachments[0];
			
		endif;
		
		if (!isset($attachment)) return false;
		
		$title = get_the_title($id);
		
		$title_tag = __('Permalink to', $language_file).' '.esc_attr($title);
				  
		$image_alt = trim(strip_tags( get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true) ));
		
		$image_title = trim(strip_tags( $attachment->post_title ));
	
		$image_alt = (empty($image_alt)) ? esc_attr($title) : esc_attr($image_alt);
		$image_title = (empty($image_title)) ? esc_attr($title) : esc_attr($image_title);
		
		$tags = array(
			'image_alt' => $image_alt,
			'image_title' => $image_title,
			'title_tag' => $title_tag
		);
		
		return $tags;
	
	} // tags
	
	/**
 	 *
	 * getting the post thumbnail in the size we want as featured image
	 * if there's no thumbnail, an attachment is taken as featured image
	 * if there's no attachment, the first image of the post is taken as featured image
	 * if the variable number is specified all of the above is skipped and we look for that specific image in the post
	 *
	 * @param: $id, $option (for caching, so that we don't touch the file over and over again)
	 *
	 * @optional param: [$image_size (i.e 'medium')], [$width], [$height], [$number]
	 * 
	 */
	 
	public static function thumbnail($args) {
		
		extract($args);
		
		if (!isset($image_size) && !isset($height) && !isset($width)) $image_size = 'thumbnail';
		
		$default_sizes = array('large', 'medium', 'thumbnail');
		
		$defaults = self::get_defaults();
		
		if (in_array($image_size, $default_sizes)) :
		
			$width = $default_sizes[$image_size]['w'];
		
			$height = $default_sizes[$image_size]['h'];
		
		else :
		
			global $_wp_additional_image_sizes;
			
			$width = $_wp_additional_image_sizes[$image_size]['width'];
			
			$height = ($_wp_additional_image_sizes[$image_size]['crop'] === false) ? $_wp_additional_image_sizes[$image_size]['height'] : 9999;
		
		endif;
		
		if ($width <= $defaults['large']['w']) $size = 'large';
		if ($width <= $defaults['medium']['w']) $size = 'medium';
		if ($width <= $defaults['thumbnail']['w']) $size = 'thumbnail';
		
		if (!isset($height)) $height = 9999;
		
		if (!isset($number)) :
		
			$post_meta = get_post_meta($id);
			
			if (array_key_exists('_thumbnail_id', $post_meta)) $attachment_id = $post_meta['_thumbnail_id'][0];
				
			if (!isset($attachment_id)) :
			
				$args = array(
				'post_type' => 'attachment',
				'posts_per_page' => 1,
				'post_status' => null,
				'post_parent' => $id
				);
				
				$attachments = get_posts( $args );
				
				if ( $attachments ) $attachment_id = $attachments[0]->ID;
			
			endif;

		endif;
		
		if (isset($number) || !isset($attachment_id)) : 
	
			$image = preg_match_all('#(?:<a[^>]+?href=["|\'](?P<link_url>[^\s]+?)["|\'][^>]*?>\s*)?(?P<img_tag><img[^>]+?src=["|\'](?P<img_url>[^\s]+?)["|\'].*?>){1}(?:\s*</a>)?#is', do_shortcode(get_the_content()), $matches);
		
			if (0 == $image) return false;
		
			$number = (isset($number)) ? $number : 1;
			
			if ($number == 'last' || $number > count($matches ['img_url'])) $number = count($matches ['img_url']);
			
			if ($number > 0) $number -= 1;
			
			$img_src = $matches ['img_url'] [$number];
			
			$upload_dir = wp_upload_dir();
			
			if (strstr($img_src, $upload_dir['baseurl'])) $attachment_id = self::get_attachment_id_from_src($img_src);
			
		endif;
		
		if (isset($attachment_id)) :
				
			$thumb = wp_get_attachment_image_src($attachment_id, $image_size);
			
			if ($thumb) : 
			
				if ($thumb[3] === false) $smaller_thumb = wp_get_attachment_image_src($attachment_id, $size);
				
				if (isset($smaller_thumb)) $thumb[0] = $smaller_thumb[0];
				
				return $thumb;
			
			endif;
		
		endif;
		
		$options = ($multisite) ? get_site_option($options) : get_option($options);
		
		$cache = $options['cache'];
		
		if (array_key_exists($img_src, $cache)) return array($img_src, $cache[$img_src][1], $cache[$img_src][2]);
		
		$img_tag = $matches['img_tag'][$number];
		
		$size = self::get_size($img_tag, $img_src);
		
		if ($width > $size['width']) $width = $size['width'];
		
		if ($height > $size['height']) $height = $size['height'];
		
		$thumb_width = $size['width'];
		
		$thumb_height = $size['height'];
		
		if (!$thumb_height) return false;
		
		$ratio = $thumb_width/$thumb_height;
		
		$args = array(
			'ratio' => $ratio,
			'thumb_width' => $thumb_width,
			'thumb_height' => $thumb_height,
			'width' => $width,
			'height' => $height
		);
		
		$new_size = self::count_size($args);
		
		$thumb_width = $new_size['width'];
		$thumb_height = $new_size['height'];
		
		$thumb = array ($img_src, $thumb_width, $thumb_height);
		
		$cache[$img_src] = array($thumb_width, $thumb_height);
		
		$options['cache'] = $cache;
		
		if ($multisite) update_site_option($option, $options);
		
		else update_option($option, $options);
		
		return $thumb;
	
	} // thumbnail
	
	// getting the image size
	
	private static function get_size($tag, $img) {
		
		// First, check the image tag
		if ( preg_match( '#width=["|\']?([\d%]+)["|\']?#i', $tag, $width_string ) )
			$width = $width_string[1];

		if ( preg_match( '#height=["|\']?([\d%]+)["|\']?#i', $tag, $height_string ) )
			$height = $height_string[1];
			
		if (strpos($width, '%') || strpos($height, '%')) :
		
			unset($width, $height);
			
		else :
		
			return array('width' => $width, 'height' => $height);
			
		endif;
			
		$image_info = wp_get_image_editor($img);
			
		if ( ! is_wp_error($image_info) ) :
			
			$size = $image_info->get_size();
			
		else :
	
			$uploaddir = wp_upload_dir();
			
			$img = str_replace($uploaddir['baseurl'], $uploaddir['basedir'], $img);
			
			$imgsize = @getimagesize($img);
			
			if (empty($imgsize)) :
			
				if ( ! function_exists( 'download_url' ) ) require_once ABSPATH.'/wp-admin/includes/file.php';
			
				$tmp_image = download_url($img);
				
				if (!is_wp_error($tmp_image)) $imgsize = @getimagesize($img);
				
				@unlink($tmp_image);
				
			endif;
			
			$size = array ( 'width' => $imgsize[0], 'height' => $imgsize[1] );
		
		endif;
		
		return $size;
	
	}
	
	// counting the new size of the image
	
	private static function count_size($args) {
		
		extract($args);
		
		if ($thumb_width && $height != 9999) :
			
			if ($ratio > 1) :
					
				$thumb_height = intval($thumb_height/($thumb_width/$width));
				
				$thumb_width = $width;
					
				else :
				
				$thumb_width = intval($thumb_width/($thumb_height/$height));
				
				$thumb_height = $height;
				
			endif;
			
		else :
		
			$ratio = $thumb_width/$thumb_height;
		
			$thumb_width = $width;
			
			$thumb_height = intval($thumb_width/$ratio);
	
		endif;	
		
		return array('width' => $thumb_width, 'height' => $thumb_height);
	
	}
	
	// getting the default sizes
	
	private static function get_defaults() {
	
		$defaults['large']['w'] = (get_option('large_size_w')) ? $width = get_option('large_size_w') : 1024;
		$defaults['large']['h'] = (get_option('large_size_h')) ? $width = get_option('large_size_h') : 1024;
		
		$defaults['medium']['w'] = (get_option('medium_size_w')) ? $width = get_option('medium_size_w') : 300;
		$defaults['medium']['h'] = (get_option('medium_size_h')) ? $width = get_option('medium_size_h') : 300;
		
		$defaults['thumbnail']['w'] = (get_option('thumbnail_size_w')) ? $width = get_option('thumbnail_size_w') : 150;
		$defaults['thumbnail']['h'] = (get_option('thumbnail_size_h')) ? $width = get_option('thumbnail_size_h') : 150;
		
		return $defaults;
		
	}
	
	// trying to get the attachment id from the image source
	
	private static function get_attachment_id_from_src ($image_src) {

		global $wpdb;
		
		$upload_dir = wp_upload_dir();
		
		$image_src = preg_replace( '/-\d+x\d+(?=\.(jpg|jpeg|png|gif)$)/i', '', $image_src );
		
		$image_src = str_replace( $upload_dir['baseurl'] . '/', '', $image_src );
		
		$id = $wpdb->get_var( $wpdb->prepare( "SELECT wposts.ID FROM $wpdb->posts wposts, $wpdb->postmeta wpostmeta WHERE wposts.ID = wpostmeta.post_id AND wpostmeta.meta_key = '_wp_attached_file' AND wpostmeta.meta_value = '%s' AND wposts.post_type = 'attachment'", $image_src ) );
		
		if (!isset($id)) return false;
		
		return $id;

	}
	
	// Check whether url has status 200 and is image (not in use at the moment)
	 
	public static function check_url($url, $type = false) {
		
		$return = get_headers($url, 1);
		
		switch ($type) :
		
			case 'url' :
			
				if (strstr($return[0], '200')) return true;
			
			break;
			
			case 'image' :
			
				if (strstr($return['Content-Type'], 'image')) return true;
			
			break;
			
			default : 
			
				if (strstr($return[0], '200') && strstr($return['Content-Type'], 'image')) : 
				
					return true;
					
				else : 
				
					return false;
					
				endif;
		
		endswitch;
		
	}
	
}

?>