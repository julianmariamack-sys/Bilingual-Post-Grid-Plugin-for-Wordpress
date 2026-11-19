<?php
/**
 * Plugin Name: Bilingual Post Grid for TranslatePress
 * Description: Responsive, paginated WordPress post grid with TranslatePress-aware titles and excerpts. Includes a Gutenberg block and shortcode.
 * Version: 1.9.8
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: OpenAI
 * License: GPL-2.0-or-later
 * Text Domain: bilingual-post-grid
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BPG_VERSION', '1.9.8' );
define( 'BPG_URL', plugin_dir_url( __FILE__ ) );

/**
 * Translate a string using TranslatePress when it is active.
 *
 * TranslatePress documents trp_translate() as the supported function for
 * translating custom/plugin-generated text in the currently active language.
 */
function bpg_translate_text( $text ) {
	$text = (string) $text;

	if ( function_exists( 'trp_translate' ) ) {
		return trp_translate( $text, null, true );
	}

	return esc_html( $text );
}


/**
 * Return the Read More label in the active TranslatePress language.
 *
 * French is explicitly labelled "En Savoir Plus". Other languages continue
 * to use TranslatePress' string translation lookup for "Read More".
 */
function bpg_get_read_more_label() {
	$current_language = '';

	if ( function_exists( 'trp_get_current_language' ) ) {
		$current_language = (string) trp_get_current_language();
	}

	// TranslatePress may expose the active language through its global object.
	if ( '' === $current_language && isset( $GLOBALS['TRP_LANGUAGE'] ) ) {
		$current_language = (string) $GLOBALS['TRP_LANGUAGE'];
	}

	// Locale fallback for installations where the language API is unavailable.
	if ( '' === $current_language ) {
		$current_language = (string) determine_locale();
	}

	if ( 0 === stripos( $current_language, 'fr' ) ) {
		return 'En Savoir Plus';
	}

	return bpg_translate_text( 'Read More' );
}


/**
 * Build a TranslatePress-compatible excerpt.
 *
 * TranslatePress matches the source string (including its formatted HTML).
 * Trimming generated excerpts before calling trp_translate() changes that
 * source string and can prevent an existing translation from being found.
 * Translate the full formatted excerpt/content first, then strip markup and
 * trim the translated result to the requested word count.
 */
function bpg_get_translated_excerpt( $post_id, $word_count = 20 ) {
	$post = get_post( $post_id );

	if ( ! $post instanceof WP_Post ) {
		return '';
	}

	$manual_excerpt = trim( (string) $post->post_excerpt );

	if ( '' !== $manual_excerpt ) {
		$source_html = apply_filters( 'the_content', $manual_excerpt );
	} else {
		$source_html = apply_filters( 'the_content', (string) $post->post_content );
	}

	/*
	 * TranslatePress recommends passing the formatted the_content output
	 * to trp_translate() so WordPress formatting (wpautop/wptexturize etc.)
	 * matches the string TranslatePress sees on the front end.
	 */
	if ( function_exists( 'trp_translate' ) ) {
		$translated_html = trp_translate( $source_html, null, true );
	} else {
		$translated_html = $source_html;
	}

	$plain_text = strip_shortcodes( (string) $translated_html );
	$plain_text = wp_strip_all_tags( $plain_text, true );
	$plain_text = html_entity_decode( $plain_text, ENT_QUOTES, get_bloginfo( 'charset' ) );
	$plain_text = trim( preg_replace( '/\s+/u', ' ', $plain_text ) );

	return wp_trim_words( $plain_text, max( 1, absint( $word_count ) ), '…' );
}

/**
 * Safely output text returned by trp_translate().
 * TranslatePress may wrap the result in a data-no-translation span.
 */
function bpg_translation_markup( $text ) {
	return wp_kses(
		(string) $text,
		array(
			'span' => array(
				'data-no-translation' => true,
			),
		)
	);
}


/**
 * Default global presentation settings.
 */
function bpg_default_settings() {
	return array(
		'row_gap'        => 30,
		'sort_by'        => 'modified',
		'column_gap'     => 12,
		'card_height'    => 360,
		'title_font'     => 12,
		'title_space_top'    => 0,
		'title_space_bottom' => 2,
		'excerpt_font'   => 11,
		'category_font'  => 9,
		'date_font'      => 9,
		'text_padding_top'    => 9,
		'text_padding_right'  => 0,
		'text_padding_bottom' => 0,
		'text_padding_left'   => 0,
		'button_width'   => 0,
		'button_height'  => 0,
		'button_space_above' => 10,
		'included_categories' => array(),
		'button_font_size'   => 10,
		'button_font_weight' => 400,
		'border_width'   => 0,
		'border_color'   => '#dddddd',
		'border_radius'  => 0,
		'shadow_enabled' => 0,
		'shadow_x'       => 0,
		'shadow_y'       => 2,
		'shadow_blur'    => 8,
		'shadow_spread'  => 0,
		'shadow_color'   => 'rgba(0,0,0,0.12)',
	);
}

/**
 * Get normalized settings.
 */
function bpg_get_settings() {
	$saved = get_option( 'bpg_settings', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return wp_parse_args( $saved, bpg_default_settings() );
}


/**
 * One-time migration: change the previous default row gap (12px) to 30px.
 * Other administrator-selected values are left unchanged.
 */
function bpg_migrate_row_gap_191() {
	if ( get_option( 'bpg_row_gap_191_migrated' ) ) {
		return;
	}

	$saved = get_option( 'bpg_settings', array() );
	if ( is_array( $saved ) && isset( $saved['row_gap'] ) && 12 === (int) $saved['row_gap'] ) {
		$saved['row_gap'] = 30;
		update_option( 'bpg_settings', $saved );
	}

	update_option( 'bpg_row_gap_191_migrated', 1 );
}
add_action( 'init', 'bpg_migrate_row_gap_191' );


/**
 * Register settings and add a top-level dashboard menu.
 */
function bpg_register_admin_settings() {
	register_setting(
		'bpg_settings_group',
		'bpg_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'bpg_sanitize_settings',
			'default'           => bpg_default_settings(),
		)
	);
}
add_action( 'admin_init', 'bpg_register_admin_settings' );

function bpg_sanitize_settings( $input ) {
	$defaults = bpg_default_settings();
	$input    = is_array( $input ) ? $input : array();
	$output   = array();

	$numeric_fields = array(
		'row_gap'       => array( 0, 200 ),
		'column_gap'    => array( 0, 200 ),
		'card_height'   => array( 150, 1200 ),
		'title_font'    => array( 6, 72 ),
		'title_space_top'    => array( 0, 100 ),
		'title_space_bottom' => array( 0, 100 ),
		'excerpt_font'  => array( 6, 48 ),
		'category_font' => array( 6, 48 ),
		'date_font'     => array( 6, 48 ),
		'text_padding_top'    => array( 0, 200 ),
		'text_padding_right'  => array( 0, 200 ),
		'text_padding_bottom' => array( 0, 200 ),
		'text_padding_left'   => array( 0, 200 ),
		'button_width'  => array( 0, 1000 ),
		'button_height' => array( 0, 300 ),
		'button_space_above' => array( 0, 200 ),
		'button_font_size' => array( 6, 48 ),
		'button_font_weight' => array( 100, 900 ),
		'border_width'  => array( 0, 20 ),
		'border_radius' => array( 0, 100 ),
		'shadow_x'      => array( -50, 50 ),
		'shadow_y'      => array( -50, 50 ),
		'shadow_blur'   => array( 0, 100 ),
		'shadow_spread' => array( -50, 50 ),
	);

	foreach ( $numeric_fields as $key => $bounds ) {
		$value = isset( $input[ $key ] ) ? intval( $input[ $key ] ) : $defaults[ $key ];
		$output[ $key ] = max( $bounds[0], min( $bounds[1], $value ) );
	}

	$sort_by = isset( $input['sort_by'] ) ? sanitize_key( $input['sort_by'] ) : $defaults['sort_by'];
	$output['sort_by'] = in_array( $sort_by, array( 'modified', 'date' ), true ) ? $sort_by : 'modified';

	$output['border_color'] = isset( $input['border_color'] ) ? sanitize_hex_color( $input['border_color'] ) : $defaults['border_color'];
	if ( ! $output['border_color'] ) {
		$output['border_color'] = $defaults['border_color'];
	}

	$output['shadow_color'] = isset( $input['shadow_color'] ) ? sanitize_text_field( $input['shadow_color'] ) : $defaults['shadow_color'];
	if ( ! preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\([0-9.,\s]+\))$/', $output['shadow_color'] ) ) {
		$output['shadow_color'] = $defaults['shadow_color'];
	}

	$output['shadow_enabled'] = ! empty( $input['shadow_enabled'] ) ? 1 : 0;

	$category_ids = isset( $input['included_categories'] ) && is_array( $input['included_categories'] )
		? array_map( 'absint', $input['included_categories'] )
		: array();
	$category_ids = array_values( array_unique( array_filter( $category_ids ) ) );
	$valid_category_ids = array();
	foreach ( $category_ids as $category_id ) {
		if ( term_exists( $category_id, 'category' ) ) {
			$valid_category_ids[] = $category_id;
		}
	}
	$output['included_categories'] = $valid_category_ids;

	return $output;
}

function bpg_add_admin_menu() {
	add_menu_page(
		__( 'Bilingual Post Grid Settings', 'bilingual-post-grid' ),
		__( 'Bilingual Post Grid', 'bilingual-post-grid' ),
		'manage_options',
		'bilingual-post-grid',
		'bpg_render_settings_page',
		'dashicons-grid-view',
		58
	);
}
add_action( 'admin_menu', 'bpg_add_admin_menu' );

function bpg_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = bpg_get_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Bilingual Post Grid', 'bilingual-post-grid' ); ?></h1>
		<p><?php esc_html_e( 'Adjust the appearance of the Bilingual Post Grid block and shortcode. Changes apply globally to all grids.', 'bilingual-post-grid' ); ?></p>

		<?php
		$readme_path = plugin_dir_path( __FILE__ ) . 'readme.md';
		$readme_content = is_readable( $readme_path ) ? file_get_contents( $readme_path ) : '';
		?>
		<div class="bpg-documentation" style="margin:16px 0 22px;">
			<button type="button" class="button button-secondary" id="bpg-toggle-readme" aria-expanded="false" aria-controls="bpg-readme-panel">
				<?php esc_html_e( 'View Plugin Readme / User Guide', 'bilingual-post-grid' ); ?>
			</button>
			<div id="bpg-readme-panel" hidden style="margin-top:12px;padding:16px 20px;background:#fff;border:1px solid #c3c4c7;max-width:1000px;max-height:650px;overflow:auto;">
				<?php if ( '' !== $readme_content ) : ?>
					<pre style="white-space:pre-wrap;overflow-wrap:anywhere;margin:0;font:13px/1.6 -apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;"><?php echo esc_html( $readme_content ); ?></pre>
				<?php else : ?>
					<p><?php esc_html_e( 'The readme.md file could not be loaded. Please check that it is present in the plugin folder.', 'bilingual-post-grid' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<script>
		(function() {
			var button = document.getElementById('bpg-toggle-readme');
			var panel = document.getElementById('bpg-readme-panel');
			if (!button || !panel) return;
			button.addEventListener('click', function() {
				var isExpanded = button.getAttribute('aria-expanded') === 'true';
				button.setAttribute('aria-expanded', isExpanded ? 'false' : 'true');
				panel.hidden = isExpanded;
				button.textContent = isExpanded ? 'View Plugin Readme / User Guide' : 'Hide Plugin Readme / User Guide';
			});
		})();
		</script>

		<form method="post" action="options.php">
			<?php settings_fields( 'bpg_settings_group' ); ?>

			<h2><?php esc_html_e( 'Post categories to include', 'bilingual-post-grid' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Choose one or more categories. Leave all dropdowns blank to include posts from every category.', 'bilingual-post-grid' ); ?></p>
			<?php
			$all_categories = get_categories(
				array(
					'taxonomy'   => 'category',
					'hide_empty' => false,
					'orderby'    => 'name',
					'order'      => 'ASC',
				)
			);
			$selected_categories = isset( $settings['included_categories'] ) && is_array( $settings['included_categories'] )
				? array_values( array_filter( array_map( 'absint', $settings['included_categories'] ) ) )
				: array();
			if ( empty( $selected_categories ) ) {
				$selected_categories = array( 0 );
			}
			?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Included categories', 'bilingual-post-grid' ); ?></th>
					<td>
						<div id="bpg-category-selectors">
							<?php foreach ( $selected_categories as $selected_category ) : ?>
								<div class="bpg-category-row" style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
									<select name="bpg_settings[included_categories][]" class="regular-text">
										<option value="0"><?php esc_html_e( '— Select a category —', 'bilingual-post-grid' ); ?></option>
										<?php foreach ( $all_categories as $category_option ) : ?>
											<option value="<?php echo esc_attr( $category_option->term_id ); ?>" <?php selected( $selected_category, $category_option->term_id ); ?>><?php echo esc_html( $category_option->name ); ?></option>
										<?php endforeach; ?>
									</select>
									<button type="button" class="button bpg-add-category" aria-label="<?php esc_attr_e( 'Add category', 'bilingual-post-grid' ); ?>">+</button>
									<button type="button" class="button bpg-remove-category" aria-label="<?php esc_attr_e( 'Remove category', 'bilingual-post-grid' ); ?>">−</button>
								</div>
							<?php endforeach; ?>
						</div>
						<template id="bpg-category-row-template">
							<div class="bpg-category-row" style="display:flex;align-items:center;gap:8px;margin-bottom:8px;">
								<select name="bpg_settings[included_categories][]" class="regular-text">
									<option value="0"><?php esc_html_e( '— Select a category —', 'bilingual-post-grid' ); ?></option>
									<?php foreach ( $all_categories as $category_option ) : ?>
										<option value="<?php echo esc_attr( $category_option->term_id ); ?>"><?php echo esc_html( $category_option->name ); ?></option>
									<?php endforeach; ?>
								</select>
								<button type="button" class="button bpg-add-category" aria-label="<?php esc_attr_e( 'Add category', 'bilingual-post-grid' ); ?>">+</button>
								<button type="button" class="button bpg-remove-category" aria-label="<?php esc_attr_e( 'Remove category', 'bilingual-post-grid' ); ?>">−</button>
							</div>
						</template>
						<script>
						(function() {
							var wrap = document.getElementById('bpg-category-selectors');
							var template = document.getElementById('bpg-category-row-template');
							if (!wrap || !template) return;
							wrap.addEventListener('click', function(event) {
								var addButton = event.target.closest('.bpg-add-category');
								var removeButton = event.target.closest('.bpg-remove-category');
								if (addButton) {
									event.preventDefault();
									wrap.appendChild(template.content.cloneNode(true));
								}
								if (removeButton) {
									event.preventDefault();
									var rows = wrap.querySelectorAll('.bpg-category-row');
									if (rows.length > 1) {
										removeButton.closest('.bpg-category-row').remove();
									} else {
										var select = rows[0].querySelector('select');
										if (select) select.value = '0';
									}
								}
							});
						})();
						</script>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Post order', 'bilingual-post-grid' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bpg-sort-by"><?php esc_html_e( 'Organize posts by', 'bilingual-post-grid' ); ?></label></th>
					<td>
						<select id="bpg-sort-by" name="bpg_settings[sort_by]">
							<option value="modified" <?php selected( $settings['sort_by'], 'modified' ); ?>><?php esc_html_e( 'Modified date (newest first)', 'bilingual-post-grid' ); ?></option>
							<option value="date" <?php selected( $settings['sort_by'], 'date' ); ?>><?php esc_html_e( 'Creation date (newest first)', 'bilingual-post-grid' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'This setting applies to both the shortcode and Gutenberg block.', 'bilingual-post-grid' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Grid spacing', 'bilingual-post-grid' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bpg-row-gap"><?php esc_html_e( 'Vertical space between rows of cards (px)', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-row-gap" type="number" min="0" max="200" name="bpg_settings[row_gap]" value="<?php echo esc_attr( $settings['row_gap'] ); ?>" class="small-text"> px</td>
				</tr>
				<tr>
					<th scope="row"><label for="bpg-column-gap"><?php esc_html_e( 'Space between columns (px)', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-column-gap" type="number" min="0" max="200" name="bpg_settings[column_gap]" value="<?php echo esc_attr( $settings['column_gap'] ); ?>" class="small-text"> px</td>
				</tr>
				<tr>
					<th scope="row"><label for="bpg-card-height"><?php esc_html_e( 'Card height (px)', 'bilingual-post-grid' ); ?></label></th>
					<td>
						<input id="bpg-card-height" type="number" min="150" max="1200" name="bpg_settings[card_height]" value="<?php echo esc_attr( $settings['card_height'] ); ?>" class="small-text"> px
						<p class="description"><?php esc_html_e( 'Sets the same fixed overall height for every card. The Read More button is pushed toward the bottom, leaving flexible space after the excerpt.', 'bilingual-post-grid' ); ?></p>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Typography', 'bilingual-post-grid' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bpg-title-font"><?php esc_html_e( 'Post title font size (px)', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-title-font" type="number" min="6" max="72" name="bpg_settings[title_font]" value="<?php echo esc_attr( $settings['title_font'] ); ?>" class="small-text"> px</td>
				</tr>
				<tr>
					<th scope="row"><label for="bpg-title-space-top"><?php esc_html_e( 'Space above title (px)', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-title-space-top" type="number" min="0" max="100" name="bpg_settings[title_space_top]" value="<?php echo esc_attr( $settings['title_space_top'] ); ?>" class="small-text"> px</td>
				</tr>
				<tr>
					<th scope="row"><label for="bpg-title-space-bottom"><?php esc_html_e( 'Space below title (px)', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-title-space-bottom" type="number" min="0" max="100" name="bpg_settings[title_space_bottom]" value="<?php echo esc_attr( $settings['title_space_bottom'] ); ?>" class="small-text"> px</td>
				</tr>
				<tr>
					<th scope="row"><label for="bpg-excerpt-font"><?php esc_html_e( 'Excerpt font size (px)', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-excerpt-font" type="number" min="6" max="48" name="bpg_settings[excerpt_font]" value="<?php echo esc_attr( $settings['excerpt_font'] ); ?>" class="small-text"> px</td>
				</tr>
				<tr>
					<th scope="row"><label for="bpg-category-font"><?php esc_html_e( 'Category font size (px)', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-category-font" type="number" min="6" max="48" name="bpg_settings[category_font]" value="<?php echo esc_attr( $settings['category_font'] ); ?>" class="small-text"> px</td>
				</tr>
				<tr>
					<th scope="row"><label for="bpg-date-font"><?php esc_html_e( 'Date font size (px)', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-date-font" type="number" min="6" max="48" name="bpg_settings[date_font]" value="<?php echo esc_attr( $settings['date_font'] ); ?>" class="small-text"> px</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Text area padding', 'bilingual-post-grid' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Adjust the space inside the text portion of each card. All values are in pixels.', 'bilingual-post-grid' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bpg-padding-top"><?php esc_html_e( 'Top padding (px)', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-padding-top" type="number" min="0" max="200" name="bpg_settings[text_padding_top]" value="<?php echo esc_attr( $settings['text_padding_top'] ); ?>" class="small-text"> px</td>
				</tr>
				<tr>
					<th scope="row"><label for="bpg-padding-right"><?php esc_html_e( 'Right padding (px)', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-padding-right" type="number" min="0" max="200" name="bpg_settings[text_padding_right]" value="<?php echo esc_attr( $settings['text_padding_right'] ); ?>" class="small-text"> px</td>
				</tr>
				<tr>
					<th scope="row"><label for="bpg-padding-bottom"><?php esc_html_e( 'Bottom padding (px)', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-padding-bottom" type="number" min="0" max="200" name="bpg_settings[text_padding_bottom]" value="<?php echo esc_attr( $settings['text_padding_bottom'] ); ?>" class="small-text"> px</td>
				</tr>
				<tr>
					<th scope="row"><label for="bpg-padding-left"><?php esc_html_e( 'Left padding (px)', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-padding-left" type="number" min="0" max="200" name="bpg_settings[text_padding_left]" value="<?php echo esc_attr( $settings['text_padding_left'] ); ?>" class="small-text"> px</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Read More button dimensions', 'bilingual-post-grid' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Set either dimension to 0 to let the button size itself to its text and padding.', 'bilingual-post-grid' ); ?></p>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bpg-button-width"><?php esc_html_e( 'Button width (px)', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-button-width" type="number" min="0" max="1000" name="bpg_settings[button_width]" value="<?php echo esc_attr( $settings['button_width'] ); ?>" class="small-text"> px</td>
				</tr>
				<tr>
					<th scope="row"><label for="bpg-button-height"><?php esc_html_e( 'Button height (px)', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-button-height" type="number" min="0" max="300" name="bpg_settings[button_height]" value="<?php echo esc_attr( $settings['button_height'] ); ?>" class="small-text"> px</td>
				</tr>
				<tr>
					<th scope="row"><label for="bpg-button-space-above"><?php esc_html_e( 'Space above button (px)', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-button-space-above" type="number" min="0" max="200" name="bpg_settings[button_space_above]" value="<?php echo esc_attr( $settings['button_space_above'] ); ?>" class="small-text"> px</td>
				</tr>
				<tr>
					<th scope="row"><label for="bpg-button-font-size"><?php esc_html_e( 'Button font size (px)', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-button-font-size" type="number" min="6" max="48" name="bpg_settings[button_font_size]" value="<?php echo esc_attr( $settings['button_font_size'] ); ?>" class="small-text"> px</td>
				</tr>
				<tr>
					<th scope="row"><label for="bpg-button-font-weight"><?php esc_html_e( 'Button font weight', 'bilingual-post-grid' ); ?></label></th>
					<td>
						<select id="bpg-button-font-weight" name="bpg_settings[button_font_weight]">
							<?php foreach ( array( 100, 200, 300, 400, 500, 600, 700, 800, 900 ) as $weight ) : ?>
								<option value="<?php echo esc_attr( $weight ); ?>" <?php selected( (int) $settings['button_font_weight'], $weight ); ?>><?php echo esc_html( $weight ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>

			<h2><?php esc_html_e( 'Card border and shadow', 'bilingual-post-grid' ); ?></h2>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="bpg-border-width"><?php esc_html_e( 'Border width (px)', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-border-width" type="number" min="0" max="20" name="bpg_settings[border_width]" value="<?php echo esc_attr( $settings['border_width'] ); ?>" class="small-text"> px</td>
				</tr>
				<tr>
					<th scope="row"><label for="bpg-border-color"><?php esc_html_e( 'Border color', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-border-color" type="color" name="bpg_settings[border_color]" value="<?php echo esc_attr( $settings['border_color'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="bpg-border-radius"><?php esc_html_e( 'Border radius (px)', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-border-radius" type="number" min="0" max="100" name="bpg_settings[border_radius]" value="<?php echo esc_attr( $settings['border_radius'] ); ?>" class="small-text"> px</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Drop shadow', 'bilingual-post-grid' ); ?></th>
					<td>
						<label><input type="checkbox" name="bpg_settings[shadow_enabled]" value="1" <?php checked( $settings['shadow_enabled'], 1 ); ?>> <?php esc_html_e( 'Enable card drop shadow', 'bilingual-post-grid' ); ?></label>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="bpg-shadow-x"><?php esc_html_e( 'Shadow horizontal offset (px)', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-shadow-x" type="number" min="-50" max="50" name="bpg_settings[shadow_x]" value="<?php echo esc_attr( $settings['shadow_x'] ); ?>" class="small-text"> px</td>
				</tr>
				<tr>
					<th scope="row"><label for="bpg-shadow-y"><?php esc_html_e( 'Shadow vertical offset (px)', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-shadow-y" type="number" min="-50" max="50" name="bpg_settings[shadow_y]" value="<?php echo esc_attr( $settings['shadow_y'] ); ?>" class="small-text"> px</td>
				</tr>
				<tr>
					<th scope="row"><label for="bpg-shadow-blur"><?php esc_html_e( 'Shadow blur (px)', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-shadow-blur" type="number" min="0" max="100" name="bpg_settings[shadow_blur]" value="<?php echo esc_attr( $settings['shadow_blur'] ); ?>" class="small-text"> px</td>
				</tr>
				<tr>
					<th scope="row"><label for="bpg-shadow-spread"><?php esc_html_e( 'Shadow spread (px)', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-shadow-spread" type="number" min="-50" max="50" name="bpg_settings[shadow_spread]" value="<?php echo esc_attr( $settings['shadow_spread'] ); ?>" class="small-text"> px</td>
				</tr>
				<tr>
					<th scope="row"><label for="bpg-shadow-color"><?php esc_html_e( 'Shadow color', 'bilingual-post-grid' ); ?></label></th>
					<td><input id="bpg-shadow-color" type="text" name="bpg_settings[shadow_color]" value="<?php echo esc_attr( $settings['shadow_color'] ); ?>" class="regular-text" placeholder="rgba(0,0,0,0.12)"></td>
				</tr>
			</table>

			<?php submit_button( __( 'Save Bilingual Post Grid Settings', 'bilingual-post-grid' ) ); ?>
		</form>

		<hr />
		<h2><?php esc_html_e( 'Shortcode examples', 'bilingual-post-grid' ); ?></h2>
		<p><?php esc_html_e( 'Add the shortcode to a page or post using a Shortcode block. You can place more than one grid on the same page. Each shortcode can have its own category, number of posts per page, sorting, and pagination. All appearance settings above remain global.', 'bilingual-post-grid' ); ?></p>
		<h3><?php esc_html_e( 'Default grid', 'bilingual-post-grid' ); ?></h3>
		<pre><code><?php echo esc_html( '[bilingual_post_grid]' ); ?></code></pre>
		<h3><?php esc_html_e( 'Multiple independent grids', 'bilingual-post-grid' ); ?></h3>
		<pre><code><?php echo esc_html( "[bilingual_post_grid category=\"news\" posts_per_page=\"9\" sort_by=\"modified\"]\n[bilingual_post_grid category=\"reviews\" posts_per_page=\"6\" sort_by=\"date\"]\n[bilingual_post_grid category=\"tutorials\" posts_per_page=\"12\" sort_by=\"modified\"]" ); ?></code></pre>
		<ul>
			<li><code>category</code>: category slug; use commas to specify multiple category slugs. This overrides the global included-category selection for that grid.</li>
			<li><code>posts_per_page</code>: number of posts shown by that grid before its pagination. If omitted, the grid uses columns multiplied by rows (9 by default).</li>
			<li><code>sort_by</code>: use <code>modified</code> for last modified date or <code>date</code> for original publication date. If omitted, the global Post order setting is used.</li>
		</ul>
		<p class="description"><?php esc_html_e( 'Pagination is independent for each grid. If your site uses page caching, make sure query-string parameters are not discarded for paginated grid URLs.', 'bilingual-post-grid' ); ?></p>
	</div>
	<?php
}

/**
 * Add global CSS custom properties for the front-end grid.
 */
function bpg_enqueue_dynamic_styles() {
	$settings = bpg_get_settings();

	$shadow = 'none';
	if ( ! empty( $settings['shadow_enabled'] ) ) {
		$shadow = sprintf(
			'%dpx %dpx %dpx %dpx %s',
			(int) $settings['shadow_x'],
			(int) $settings['shadow_y'],
			(int) $settings['shadow_blur'],
			(int) $settings['shadow_spread'],
			$settings['shadow_color']
		);
	}

	$button_width  = (int) $settings['button_width'] > 0 ? (int) $settings['button_width'] . 'px' : 'auto';
	$button_height = (int) $settings['button_height'] > 0 ? (int) $settings['button_height'] . 'px' : 'auto';

	$css = sprintf(
		'.bpg-grid{row-gap:%1$dpx!important;column-gap:%2$dpx!important;align-items:stretch!important}
		.bpg-card{height:auto!important;min-height:%3$dpx!important;box-sizing:border-box;display:flex;flex-direction:column;border:%4$dpx solid %5$s!important;border-radius:%6$dpx!important;box-shadow:%7$s!important;overflow:visible!important}
		.bpg-card__image-link{flex:0 0 auto}
		.bpg-card__body{display:flex;flex:1 1 auto;min-height:0;flex-direction:column;border-radius:inherit;padding:%8$dpx %9$dpx %10$dpx %11$dpx!important}
		.bpg-card__title{font-size:%12$dpx!important;margin-top:%13$dpx!important;margin-bottom:%14$dpx!important}
		.bpg-card__excerpt{font-size:%15$dpx!important}
		.bpg-card__category{font-size:%16$dpx!important}
		.bpg-card__read-more{margin-top:%17$dpx!important;width:%18$s;height:%19$s;min-height:%19$s;flex:0 0 auto;box-sizing:border-box;display:inline-flex;align-items:center;justify-content:center;padding:6px 10px!important;white-space:nowrap;line-height:1.25; font-size:%20$dpx!important;font-weight:%21$d!important}
		.bpg-card__date{font-size:%22$dpx!important;flex:0 0 auto;margin-bottom:0!important}
		.bpg-card__body>.bpg-card__read-more{margin-top:auto!important}',
		(int) $settings['row_gap'],
		(int) $settings['column_gap'],
		(int) $settings['card_height'],
		(int) $settings['border_width'],
		esc_attr( $settings['border_color'] ),
		(int) $settings['border_radius'],
		$shadow,
		(int) $settings['text_padding_top'],
		(int) $settings['text_padding_right'],
		(int) $settings['text_padding_bottom'],
		(int) $settings['text_padding_left'],
		(int) $settings['title_font'],
		(int) $settings['title_space_top'],
		(int) $settings['title_space_bottom'],
		(int) $settings['excerpt_font'],
		(int) $settings['category_font'],
		(int) $settings['button_space_above'],
		$button_width,
		$button_height,
		(int) $settings['button_font_size'],
		(int) $settings['button_font_weight'],
		(int) $settings['date_font']
	);

	wp_add_inline_style( 'bpg-grid', $css );
}



/**
 * Render the grid. Used by both the shortcode and dynamic Gutenberg block.
 */
function bpg_render_grid( $raw_atts = array() ) {
	$atts = shortcode_atts(
		array(
			'columns'          => 3,
			'rows'             => 3,
			'excerpt_words'    => 20,
			'show_image'       => 'true',
			'show_excerpt'     => 'true',
			'show_read_more'   => 'true',
			'show_category'    => 'true',
			'show_date'        => 'true',
			'heading'          => '',
			'post_type'        => 'post',
			'category'         => '',
			'posts_per_page'   => '',
			'sort_by'          => '',
		),
		$raw_atts,
		'bilingual_post_grid'
	);

	$columns = max( 1, min( 9, absint( $atts['columns'] ) ) );
	$rows    = max( 1, min( 9, absint( $atts['rows'] ) ) );
	$words   = max( 1, min( 100, absint( $atts['excerpt_words'] ) ) );

	$per_page = absint( $atts['posts_per_page'] );
	if ( ! $per_page ) {
		$per_page = $columns * $rows;
	}
	$per_page = max( 1, min( 100, $per_page ) );

	$show_image     = filter_var( $atts['show_image'], FILTER_VALIDATE_BOOLEAN );
	$show_excerpt   = filter_var( $atts['show_excerpt'], FILTER_VALIDATE_BOOLEAN );
	$show_read_more = filter_var( $atts['show_read_more'], FILTER_VALIDATE_BOOLEAN );
	$show_category  = filter_var( $atts['show_category'], FILTER_VALIDATE_BOOLEAN );
	$show_date      = filter_var( $atts['show_date'], FILTER_VALIDATE_BOOLEAN );
	$heading        = sanitize_text_field( $atts['heading'] );

	$post_type = sanitize_key( $atts['post_type'] );
	if ( ! post_type_exists( $post_type ) || ! is_post_type_viewable( $post_type ) ) {
		$post_type = 'post';
	}

	/*
	 * Give each rendered grid its own pagination query parameter. The
	 * instance number follows the order in which grids appear on the page,
	 * allowing multiple shortcodes to paginate independently.
	 */
	static $grid_instance = 0;
	++$grid_instance;
	$page_arg = 'bpg_page_' . $grid_instance;
	$current_page = isset( $_GET[ $page_arg ] ) ? absint( wp_unslash( $_GET[ $page_arg ] ) ) : 1;
	$current_page = max( 1, $current_page );

	$settings = bpg_get_settings();
	$global_sort_by = isset( $settings['sort_by'] ) && in_array( $settings['sort_by'], array( 'modified', 'date' ), true )
		? $settings['sort_by']
		: 'modified';
	$requested_sort = sanitize_key( $atts['sort_by'] );
	$sort_by = in_array( $requested_sort, array( 'modified', 'date' ), true )
		? $requested_sort
		: $global_sort_by;

	// A shortcode category overrides the global category inclusion list.
	$has_shortcode_category = '' !== trim( (string) $atts['category'] );

	$query_args = array(
		'post_type'           => $post_type,
		'post_status'         => 'publish',
		'posts_per_page'      => $per_page,
		'paged'               => $current_page,
		'orderby'             => $sort_by,
		'order'               => 'DESC',
		'ignore_sticky_posts' => true,
		'no_found_rows'       => false,
	);

	if ( $has_shortcode_category && 'post' === $post_type ) {
		$category_slugs = array_map( 'sanitize_title', explode( ',', (string) $atts['category'] ) );
		$category_slugs = array_values( array_filter( $category_slugs ) );
		if ( ! empty( $category_slugs ) ) {
			$query_args['category_name'] = implode( ',', $category_slugs );
		}
	}

	$global_category_ids = isset( $settings['included_categories'] ) && is_array( $settings['included_categories'] )
		? array_values( array_filter( array_map( 'absint', $settings['included_categories'] ) ) )
		: array();
	if ( ! $has_shortcode_category && ! empty( $global_category_ids ) && 'post' === $post_type ) {
		$query_args['category__in'] = $global_category_ids;
	}

	$grid_query = new WP_Query( $query_args );

	wp_enqueue_style(
		'bpg-grid',
		BPG_URL . 'assets/grid.css',
		array(),
		BPG_VERSION
	);
	bpg_enqueue_dynamic_styles();

	ob_start();

	echo '<div class="bpg-grid-wrap">';
	if ( '' !== $heading ) {
		echo '<h2 class="bpg-archive-heading">' . esc_html( $heading ) . '</h2>';
	}
	if ( $grid_query->have_posts() ) {
		printf(
			'<div class="bpg-grid bpg-columns-%1$d" style="--bpg-columns:%1$d;--bpg-columns-medium:%2$d;--bpg-columns-tablet:%3$d;--bpg-columns-mobile:%4$d">',
			(int) $columns,
			(int) min( $columns, 6 ),
			(int) min( $columns, 4 ),
			(int) min( $columns, 2 )
		);

		while ( $grid_query->have_posts() ) {
			$grid_query->the_post();

			$post_id  = get_the_ID();
			$title    = get_the_title( $post_id );
			$permalink = get_permalink( $post_id );

			/*
			 * Translate the full formatted excerpt/content before trimming.
			 * Trimming first can create a different string from the one
			 * TranslatePress has stored, leaving some excerpts in English.
			 */
			$translated_excerpt = bpg_get_translated_excerpt( $post_id, $words );

			$translated_title = bpg_translate_text( $title );

			echo '<article class="bpg-card">';

			if ( $show_image && has_post_thumbnail( $post_id ) ) {
				echo '<a class="bpg-card__image-link" href="' . esc_url( $permalink ) . '" tabindex="-1" aria-hidden="true">';
				echo get_the_post_thumbnail(
					$post_id,
					'large',
					array(
						'class'    => 'bpg-card__image',
						'loading'  => 'lazy',
						'decoding' => 'async',
					)
				);
				echo '</a>';
			}

			echo '<div class="bpg-card__body">';

			if ( $show_category ) {
				$categories = get_the_category( $post_id );
				if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
					$category_name = bpg_translate_text( $categories[0]->name );
					echo '<div class="bpg-card__category">' . bpg_translation_markup( $category_name ) . '</div>';
				}
			}

			echo '<h2 class="bpg-card__title"><a href="' . esc_url( $permalink ) . '">';
			echo bpg_translation_markup( $translated_title );
			echo '</a></h2>';

			if ( $show_excerpt && '' !== trim( $translated_excerpt ) ) {
				echo '<div class="bpg-card__excerpt">';
				echo esc_html( $translated_excerpt );
				echo '</div>';
			}

			if ( $show_read_more ) {
				echo '<a class="bpg-card__read-more" href="' . esc_url( $permalink ) . '">';
				echo bpg_translation_markup( bpg_get_read_more_label() );
				echo '</a>';
			}

			if ( $show_date ) {
				$date_text = get_the_date( get_option( 'date_format' ), $post_id );
				/* Keep the WordPress-formatted date intact; TranslatePress can mistakenly match short date strings to unrelated post content. */
				$category_suffix = '';
				if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
					$category_suffix = ' / ' . $categories[0]->name;
				}
				echo '<div class="bpg-card__date">' . bpg_translation_markup( $date_text ) . esc_html( $category_suffix ) . '</div>';
			}

			echo '</div></article>';
		}

		echo '</div>';

		$total_pages = (int) $grid_query->max_num_pages;
		if ( $total_pages > 1 ) {
			$base_url = remove_query_arg( $page_arg );
			$base_url = add_query_arg( $page_arg, '%#%', $base_url );
			$base_url = str_replace( '%25%23%25', '%#%', $base_url );

			$pagination = paginate_links(
				array(
					'base'      => esc_url_raw( $base_url ),
					'format'    => '',
					'current'   => $current_page,
					'total'     => $total_pages,
					'type'      => 'list',
					'prev_text' => bpg_translation_markup( bpg_translate_text( '« Previous' ) ),
					'next_text' => bpg_translation_markup( bpg_translate_text( 'Next »' ) ),
				)
			);

			if ( $pagination ) {
				echo '<nav class="bpg-pagination" aria-label="' . esc_attr__( 'Posts pagination', 'bilingual-post-grid' ) . '">';
				echo wp_kses_post( $pagination );
				echo '</nav>';
			}
		}
	} else {
		echo '<p class="bpg-no-posts">' . esc_html__( 'No posts found.', 'bilingual-post-grid' ) . '</p>';
	}

	echo '</div>';

	wp_reset_postdata();

	return ob_get_clean();
}

/**
 * Shortcode: [bilingual_post_grid]
 * Multiple instances use separate pagination parameters and query settings.
 */
function bpg_register_shortcode() {
	add_shortcode( 'bilingual_post_grid', 'bpg_render_grid' );
}
add_action( 'init', 'bpg_register_shortcode' );

/**
 * Dynamic Gutenberg block render callback.
 */
function bpg_render_block( $attributes ) {
	return bpg_render_grid(
		array(
			'columns'        => isset( $attributes['columns'] ) ? $attributes['columns'] : 3,
			'rows'           => isset( $attributes['rows'] ) ? $attributes['rows'] : 3,
			'excerpt_words'  => isset( $attributes['excerptWords'] ) ? $attributes['excerptWords'] : 20,
			'show_image'     => ! empty( $attributes['showImage'] ) ? 'true' : 'false',
			'show_excerpt'   => ! empty( $attributes['showExcerpt'] ) ? 'true' : 'false',
			'show_read_more' => ! empty( $attributes['showReadMore'] ) ? 'true' : 'false',
			'show_category'  => ! empty( $attributes['showCategory'] ) ? 'true' : 'false',
			'show_date'      => ! empty( $attributes['showDate'] ) ? 'true' : 'false',
			'heading'        => isset( $attributes['heading'] ) ? $attributes['heading'] : '',
		)
	);
}

/**
 * Register the editor script and dynamic block.
 */
function bpg_register_block() {
	wp_register_script(
		'bpg-block-editor',
		BPG_URL . 'block.js',
		array(
			'wp-blocks',
			'wp-element',
			'wp-components',
			'wp-block-editor',
			'wp-server-side-render',
		),
		BPG_VERSION,
		true
	);

	register_block_type(
		'bilingual-post-grid/posts',
		array(
			'api_version'     => 3,
			'editor_script'   => 'bpg-block-editor',
			'render_callback' => 'bpg_render_block',
			'attributes'      => array(
				'columns' => array(
					'type'    => 'number',
					'default' => 3,
				),
				'rows' => array(
					'type'    => 'number',
					'default' => 3,
				),
				'excerptWords' => array(
					'type'    => 'number',
					'default' => 20,
				),
				'showImage' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showExcerpt' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showReadMore' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showCategory' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'showDate' => array(
					'type'    => 'boolean',
					'default' => true,
				),
				'heading' => array(
					'type'    => 'string',
					'default' => '',
				),
			),
			'supports' => array(
				'html' => false,
			),
		)
	);
}
add_action( 'init', 'bpg_register_block' );
