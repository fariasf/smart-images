<?php
/*
Plugin Name: Smart Images
Description: Replace your content images with low-quality blurred ones. Then smart-load the originals.
Version:     1.0.0
Author:      Facundo Farias
Author URI:  https://facundofarias.com.ar/
License:     GPL2
*/

add_action( 'admin_init', 'smart_images_settings_config' );
function smart_images_settings_config() {
	register_setting( 'media', 'smart_images_thumbnail_type' );
	register_setting( 'media', 'smart_images_inline' );
	register_setting( 'media', 'smart_images_disable' );

	add_settings_section(
		'smart_images_settings',
		'Smart Images',
		'smart_images_settings_description',
		'media'
	);
	add_settings_field(
		'smart_images_thumbnail_type',
		'Thumbnail Type',
		'smart_images_thumbnail_type_markup',
		'media',
		'smart_images_settings'
	);
	add_settings_field(
		'smart_images_inline',
		'Inline Images',
		'smart_images_inline_markup',
		'media',
		'smart_images_settings'
	);
	add_settings_field(
		'smart_images_disable',
		'Disable',
		'smart_images_disable_markup',
		'media',
		'smart_images_settings'
	);
}

function smart_images_settings_description() {
	echo '<p>Configure Smart Images. These settings may affect Page Load Time and User Experience. You may want to experiment with the possible combinations and pick the one that works best for you.</p>';
	echo '<p>For A/B testing purposes, these settings can be changed via URL params.</p>';
}

function smart_images_thumbnail_type_markup() {
	$current_type = get_option( 'smart_images_thumbnail_type', 0 );

	echo '<input type="radio" name="smart_images_thumbnail_type" id="smart_images_thumbnail_type_blurred" value="0" ' . checked( $current_type, 0, false ) . '/>';
	echo '<label for="smart_images_thumbnail_type_blurred">' . __('Blurred') . '</label>';
	echo '<div>';
	echo '<i>' . __('A low quality, blurred thumbnail. Like Medium.com.') . '</i>' . ' <kbd>?smart_images_thumbnail_type=0</kbd>';
	echo '</div>';

	echo '<br />';

	/*
	echo '<input type="radio" disabled="disabled" name="smart_images_thumbnail_type" id="smart_images_thumbnail_type_solid" value="1" ' . checked( $current_type, 1, false ) . '/>';
	echo '<label for="smart_images_thumbnail_type_solid">' . __('Solid Color') . '</label>';
	echo '<div>';
	echo '<i>' . __('A solid color. Like Google Images Search. [NOT IMPLEMENTED YET]') . '</i>';
	echo '</div>';

	echo '<br />';
	*/

	echo '<input type="radio" name="smart_images_thumbnail_type" id="smart_images_thumbnail_type_transparent_png" value="2" ' . checked( $current_type, 2, false ) . '/>';
	echo '<label for="smart_images_thumbnail_type_transparent_png">' . __('Transparent PNG') . '</label>';
	echo '<div>';
	echo '<i>' . __('A transparent 1x1 image, in PNG format.') . '</i>' . ' <kbd>?smart_images_thumbnail_type=2</kbd>';
	echo '</div>';

	echo '<br />';

	echo '<input type="radio" name="smart_images_thumbnail_type" id="smart_images_thumbnail_type_transparent_gif" value="3" ' . checked( $current_type, 3, false ) . '/>';
	echo '<label for="smart_images_thumbnail_type_transparent_gif">' . __('Transparent GIF') . '</label>';
	echo '<div>';
	echo '<i>' . __('A transparent 1x1 image, in GIF format.') . '</i>' . ' <kbd>?smart_images_thumbnail_type=3</kbd>';
	echo '</div>';
}

function smart_images_inline_markup() {
	echo '<input name="smart_images_inline" id="smart_images_inline" type="checkbox" value="1" class="code" ' . checked( 1, get_option( 'smart_images_inline', false ), false ) . ' /> ';
	echo '<label for="smart_images_inline">Using inline images can avoid a few roundtrips to the server on initial page load, but will make the HTML bigger.</label>'  . ' <kbd>?smart_images_inline=0</kbd> / <kbd>smart_images_inline=1</kbd>';
}

function smart_images_disable_markup() {
	echo '<input name="smart_images_disable" id="smart_images_disable" type="checkbox" value="1" class="code" ' . checked( 1, get_option( 'smart_images_disable', false ), false ) . ' /> ';
	echo '<label for="smart_images_disable">Disable the functionality while keeping the plugin on. Useful for A/B testing.</label>'  . ' <kbd>smart_images_disable=1</kbd>';
}

// Add a micro image size
add_action( 'init', 'smart_images_add_size' );
function smart_images_add_size() {
	add_image_size( 'smart_images_low_quality', 20, 10 );
}

// Add the required scripts
add_action( 'wp_enqueue_scripts', 'smart_images_add_scripts');
function smart_images_add_scripts() {
	if ( ! smart_images_is_disabled() ) {
		wp_enqueue_script( 'vanilla-lazyload', 'https://cdnjs.cloudflare.com/ajax/libs/vanilla-lazyload/8.0.1/lazyload.min.js', array(), '8.0.1', true );
		wp_enqueue_script( 'smart-images', plugins_url( 'smart-images.js', __FILE__ ), array( 'vanilla-lazyload' ), '1.0.0', true );
	}
}

// Replace the featured image
add_filter( 'post_thumbnail_html', 'smart_images_post_thumbnail_html', 10, 5 );
function smart_images_post_thumbnail_html( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
	if ( is_feed() || smart_images_is_disabled() ) {
		return $html;
	}

	$new_img = $html;
	preg_match( '/src="([^"]+)"/i', $html, $src_matches );
	if ( ! empty( $src_matches[1] ) && is_string( $src_matches[1] ) ) {
		$src = $src_matches[0];
		$original_url = $src_matches[1];
		$low_quality_src = smart_image_get_src( $post_thumbnail_id );
		$new_src = 'src="' . $low_quality_src . '" data-original-src="' . $original_url . '"';
		$new_img = str_replace( $src, $new_src, $new_img );
		$new_img = str_replace( 'srcset=', 'data-original-srcset=', $new_img );
	}

	return $new_img;
}

// Replace the content with our micro version
add_filter( 'the_content', 'smart_images_filter_content' );
function smart_images_filter_content( $content ) {
	if ( is_feed() || smart_images_is_disabled() ) {
		return $content;
	}

	// Find all the local images
	preg_match_all( '/<img[^>]+\bclass="([^"]*\bwp-image-(\d+)\b[^"]*)"[^>]+>/isx', $content, $matches );

	foreach ( $matches[0] as $index => $image ) {
		$new_img = $matches[0][$index];
		$class = $matches[1][$index];
		$id = $matches[2][$index];

		preg_match( '/src="([^"]+)"/i', $image, $src_matches );
		if ( ! empty( $src_matches[1] ) && is_string( $src_matches[1] ) ) {
			$src = $src_matches[0];
			$original_url = $src_matches[1];
			// Get the low quality version
			$low_quality_src = smart_image_get_src( $id );

			// Replace the source and backup the original
			$new_src = 'src="' . $low_quality_src . '" data-original-src="' . $original_url . '"';
			$new_img = str_replace( $src, $new_src, $new_img );

			// Temporarily disable srcsets
			$new_img = str_replace( 'srcset=', 'data-original-srcset=', $new_img );

			$content = str_replace( $matches[0][$index], $new_img, $content );
		}
	}

	return $content;
}

/**
 * Generate an <img> tag.
 *
 * @param  int     $image_id The image ID.
 * @param  int     $width    The image Width.
 * @param  int     $height   The image Height.
 * @param  string  $src      The original SRC.
 * @param  string  $class    Additional classes for the tag.
 * @return string            An <img> tag with the low quality src.
 */
function smart_image_markup( $image_id, $width, $height, $src, $class ) {
	$low_quality_src = smart_image_get_src( $image_id );

	if ( ! ( $width && $height && $src ) ) {
		$img_data = wp_get_attachment_image_src( $image_id );
		list( $src, $width, $height ) = $img_data;
	}

	$class_markup = ( ! empty( $class) ? 'class="' . $class . '"' : '' );

	return '<img ' . $class_markup . ' src="' . $low_quality_src . '" data-original-src="' . $src . '" width="' . $width . '" height="' . $height . '">';
}

/**
 * Get the low quality SRC for an image.
 *
 * Depending on the settings, this will be a really small thumbnail,
 * a 1x1 transparent PNG or a 1x1 solid color based on the image.
 *
 * Also depending on the settings, it may be full URL or a data-uri.
 *
 * @param  int    $image_id The ID of the image.
 * @return string           The URL.
 */
function smart_image_get_src( $image_id ) {
	$low_quality_src = wp_get_attachment_image_src( $image_id, 'smart_images_low_quality' );
	$url = $low_quality_src[0];

	$current_type = get_option( 'smart_images_thumbnail_type', 0 );

	if ( ! empty( $_GET['smart_images_thumbnail_type'] ) ) {
		if ( in_array( $_GET['smart_images_thumbnail_type'], array( 0, 2, 3 ) ) ) {
			$current_type = $_GET['smart_images_thumbnail_type'];
		}
	}

	if ( $current_type == 1 ) {
		// @todo: get 1x1 pixel using the image dominant color.
	} elseif ( $current_type == 2 ) {
		$url = plugin_dir_url( __FILE__ ) . 'placeholder.png';
		$base64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAAA1BMVEVMaXFNx9g6AAAAAXRSTlMAQObYZgAAAApJREFUeNpjYAAAAAIAAeUn3vwAAAAASUVORK5CYII=';
	} elseif ( $current_type == 3 ) {
		$url = plugin_dir_url( __FILE__ ) . 'placeholder.gif';
		$base64 = 'data:image/gif;base64,R0lGODlhAQABAIAAAP///////yH5BAEAAAEALAAAAAABAAEAAAICTAEAOw==';
	}

	$response = $url;

	// @todo: Check if the thumbnail really exists. We don't want to inline the full size image.
	$inline = get_option( 'smart_images_inline', false );
	if ( ! empty( $_GET['smart_images_inline'] ) ) {
		if ( in_array( $_GET['smart_images_inline'], array( 0, 1 ) ) ) {
			$inline = $_GET['smart_images_inline'];
		}
	}

	if ( $inline && ! $base64 ) {
		$uploads = wp_upload_dir();
		$path = str_replace( $uploads['baseurl'], $uploads['basedir'], $url );
		$type = pathinfo( $path, PATHINFO_EXTENSION );
		$data = file_get_contents( $path );
		$base64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
	}

	if ( $inline && $base64 ) {
		$response = $base64;
	}

	return $response;
}

function smart_images_is_disabled() {
	$disabled = get_option( 'smart_images_disable', false );
	if ( ! empty( $_GET['smart_images_disable'] ) ) {
		if ( in_array( $_GET['smart_images_disable'], array( 1 ) ) ) {
			$disabled = $_GET['smart_images_disable'];
		}
	}

	if ( ! $disabled && function_exists( 'is_amp_endpoint' ) ) {
		$disabled = is_amp_endpoint();
	}

	return (bool) $disabled;
}
