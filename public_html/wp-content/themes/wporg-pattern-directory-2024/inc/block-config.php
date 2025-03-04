<?php
/**
 * Set up configuration for dynamic blocks.
 */

namespace WordPressdotorg\Theme\Pattern_Directory_2024\Block_Config;

use WP_Block_Supports;
use function WordPressdotorg\Theme\Pattern_Directory_2024\get_patterns_count;

add_action( 'init', __NAMESPACE__ . '\register_block_bindings' );
add_filter( 'wporg_query_total_label', __NAMESPACE__ . '\update_query_total_label', 10, 2 );
add_filter( 'wporg_query_filter_options_category', __NAMESPACE__ . '\get_category_options' );
add_filter( 'wporg_query_filter_options_curation', __NAMESPACE__ . '\get_curation_options' );
add_filter( 'wporg_query_filter_options_sort', __NAMESPACE__ . '\get_sort_options' );
add_filter( 'wporg_query_filter_options_status', __NAMESPACE__ . '\get_status_options' );
add_action( 'wporg_query_filter_in_form', __NAMESPACE__ . '\inject_other_filters' );
add_filter( 'wporg_block_navigation_menus', __NAMESPACE__ . '\add_site_navigation_menus' );
add_filter( 'render_block_core/query-title', __NAMESPACE__ . '\update_archive_title', 10, 3 );
add_filter( 'wporg_block_site_breadcrumbs', __NAMESPACE__ . '\update_site_breadcrumbs' );
add_filter( 'render_block_data', __NAMESPACE__ . '\modify_pattern_include' );

function register_block_bindings() {
	register_block_bindings_source(
		'wporg-pattern/edit-label',
		array(
			'label' => __( 'Edit label', 'wporg-patterns' ),
			'uses_context' => [ 'postId' ],
			'get_value_callback' => function( $args, $block ) {
				$post_id = $block->context['postId'];
				/* translators: %s: Post title. Only visible to screen readers. */
				return sprintf(
					__( 'Edit <span class="screen-reader-text">"%s"</span>', 'wporg-patterns' ),
					get_the_title( $post_id )
				);
			}
		)
	);

	register_block_bindings_source(
		'wporg-pattern/edit-url',
		array(
			'label' => __( 'Edit link', 'wporg-patterns' ),
			'uses_context' => [ 'postId' ],
			'get_value_callback' => function( $args, $block ) {
				$post_id = $block->context['postId'];
				return site_url( "pattern/$post_id/edit/" );
			}
		)
	);
}

/**
 * Get a list of the currently-applied filters.
 *
 * @param boolean $include_search Whether the result should include the search term.
 *
 * @return array
 */
function get_applied_filter_list( $include_search = true ) {
	global $wp_query;
	$terms = [];
	$taxes = [
		'pattern-categories' => 'wporg-pattern-category',
	];
	foreach ( $taxes as $query_var => $taxonomy ) {
		if ( ! isset( $wp_query->query[ $query_var ] ) ) {
			continue;
		}
		$values = (array) $wp_query->query[ $query_var ];
		foreach ( $values as $value ) {
			$key = ( 'cat' === $query_var ) ? 'id' : 'slug';
			$term = get_term_by( $key, $value, $taxonomy );
			if ( $term ) {
				$terms[] = $term;
			}
		}
	}
	if ( $include_search && isset( $wp_query->query['s'] ) ) {
		$terms[] = array( 'name' => $wp_query->query['s'] );
	}
	return $terms;
}

/**
 * Get the destination for query-filter submission based on the current page.
 *
 * @return string
 */
function get_filter_action_url() {
	global $wp;
	if ( is_page( 'favorites' ) || is_page( 'my-patterns' ) || is_author() ) {
		return home_url( $wp->request );
	}
	return home_url( '/archives/' );
}

/**
 * Update the query total label to reflect "patterns" found.
 *
 * @param string $label       The maybe-pluralized label to use, a result of `_n()`.
 * @param int    $found_posts The number of posts to use for determining pluralization.
 * @return string Updated string with total placeholder.
 */
function update_query_total_label( $label, $found_posts ) {
	if ( is_front_page() ) {
		// Override the current query count, instead display the total number of patterns.
		$count = get_patterns_count();

		/* translators: %s: the result count. */
		return sprintf( _n( '%s pattern', '%s patterns', $count, 'wporg' ), number_format_i18n( $count ) );
	}
	/* translators: %s: the result count. */
	return _n( '%s pattern', '%s patterns', $found_posts, 'wporg' );
}

/**
 * Get the list of categories for the filters.
 *
 * @param array $options The options for this filter.
 * @return array New list of category options.
 */
function get_category_options( $options ) {
	global $wp_query;

	$args = array(
		'taxonomy' => 'wporg-pattern-category',
		'orderby' => 'name',
	);
	$categories = get_terms( $args );

	$selected = isset( $wp_query->query['pattern-categories'] ) ? (array) $wp_query->query['pattern-categories'] : array();

	$count = count( $selected );
	$label = sprintf(
		/* translators: The dropdown label for filtering, %s is the selected term count. */
		_n( 'Categories <span>%s</span>', 'Categories <span>%s</span>', $count, 'wporg' ),
		$count
	);

	return array(
		'label' => $label,
		'title' => __( 'Categories', 'wporg' ),
		'key' => 'pattern-categories',
		'action' => get_filter_action_url(),
		'options' => array_combine( wp_list_pluck( $categories, 'slug' ), wp_list_pluck( $categories, 'name' ) ),
		'selected' => $selected,
	);
}

/**
 * Provide a list of curation options.
 *
 * @param array $options The options for this filter.
 * @return array New list of curation options.
 */
function get_curation_options( $options ) {
	global $wp_query;
	$current = strtolower( $wp_query->get( 'curation' ) );

	$label = __( 'Filter by', 'wporg' );
	switch ( $current ) {
		case 'community':
			$label = __( 'Filter by: Community', 'wporg' );
			break;
		case 'core':
			$label = __( 'Filter by: Curated', 'wporg' );
			break;
	}

	// Show the correct filters on the front page.
	if ( is_front_page() ) {
		$current = 'core';
		$label = __( 'Filter by: Curated', 'wporg' );
	}

	return array(
		'label' => $label,
		'title' => __( 'Filter', 'wporg' ),
		'key' => 'curation',
		'action' => get_filter_action_url(),
		'options' => array(
			'community' => __( 'Community', 'wporg' ),
			'core' => __( 'Curated', 'wporg' ),
		),
		'selected' => [ $current ],
	);
}

/**
 * Provide a list of sort options.
 *
 * @param array $options The options for this filter.
 * @return array New list of sort options.
 */
function get_sort_options( $options ) {
	global $wp_query;
	$orderby = strtolower( $wp_query->get( 'orderby' ) );
	$order = strtolower( $wp_query->get( 'order' ) );
	$sort = $orderby . '_' . $order;

	$label = __( 'Sort', 'wporg' );
	switch ( $sort ) {
		case 'date_desc':
			$label = __( 'Sort: Newest', 'wporg' );
			break;
		case 'date_asc':
			$label = __( 'Sort: Oldest', 'wporg' );
			break;
	}

	// Popular is a special case since it's not a true "order" value.
	if ( 'meta_value_num' === $orderby && 'wporg-pattern-favorites' === $wp_query->get( 'meta_key' ) ) {
		$label = __( 'Sort: Popular', 'wporg' );
	}

	// Show the correct filters on the front page.
	if ( is_front_page() ) {
		$sort = 'favorite_count_desc';
		$label = __( 'Sort: Popular', 'wporg' );
	}

	$options = array(
		'date_desc' => __( 'Newest', 'wporg' ),
		'date_asc' => __( 'Oldest', 'wporg' ),
	);

	// These pages don't support sorting by favorite count.
	if ( ! is_page( [ 'my-patterns', 'favorites' ] ) ) {
		$options = array_merge( [ 'favorite_count_desc' => __( 'Popular', 'wporg' ) ], $options );
	}

	return array(
		'label' => $label,
		'title' => __( 'Sort', 'wporg' ),
		'key' => 'orderby',
		'action' => get_filter_action_url(),
		'options' => $options,
		'selected' => [ $sort ],
	);
}

/**
 * Provide a list of post status options.
 *
 * @param array $options The options for this filter.
 * @return array New list of status options.
 */
function get_status_options( $options ) {
	global $wp_query;
	$selected = isset( $wp_query->query['status'] ) ? (array) $wp_query->query['status'] : array();

	$count = count( $selected );
	$label = sprintf(
		/* translators: The dropdown label for filtering, %s is the selected term count. */
		_n( 'Status <span>%s</span>', 'Status <span>%s</span>', $count, 'wporg' ),
		$count
	);

	return array(
		'label' => $label,
		'title' => __( 'Status', 'wporg' ),
		'key' => 'status',
		'action' => get_filter_action_url(),
		'options' => array(
			'draft' => __( 'Draft', 'wporg' ),
			'pending' => __( 'Pending Review', 'wporg' ),
			'publish' => __( 'Published', 'wporg' ),
		),
		'selected' => $selected,
	);
}

/**
 * Add in the other existing filters as hidden inputs in the filter form.
 *
 * Enables combining filters by building up the correct URL on submit,
 * for example patterns using a tag, a category, and matching a search term:
 *   ?tag[]=cuisine&cat[]=3&s=wordpress`
 *
 * @param string $key The key for the current filter.
 */
function inject_other_filters( $key ) {
	global $wp_query;

	// Multiple-select query parameters.
	$query_vars = [ 'pattern-categories' ];
	foreach ( $query_vars as $query_var ) {
		if ( ! isset( $wp_query->query[ $query_var ] ) ) {
			continue;
		}
		if ( $key === $query_var ) {
			continue;
		}
		$values = (array) $wp_query->query[ $query_var ];
		foreach ( $values as $value ) {
			printf( '<input type="hidden" name="%s[]" value="%s" />', esc_attr( $query_var ), esc_attr( $value ) );
		}
	}

	// Single-select query parameters.
	$query_vars = [ 'order', 'orderby', 'curation' ];
	foreach ( $query_vars as $query_var ) {
		if ( ! isset( $wp_query->query[ $query_var ] ) ) {
			continue;
		}
		if ( $key === $query_var ) {
			continue;
		}
		$values = (array) $wp_query->query[ $query_var ];
		foreach ( $values as $value ) {
			printf( '<input type="hidden" name="%s" value="%s" />', esc_attr( $query_var ), esc_attr( $value ) );
		}
	}

	if ( is_front_page() ) {
		if ( $key !== 'curation' ) {
			printf( '<input type="hidden" name="curation" value="core" />' );
		}
		if ( $key !== 'orderby' ) {
			printf( '<input type="hidden" name="orderby" value="favorite_count_desc" />' );
		}
	}

	// Pass through search query.
	if ( isset( $wp_query->query['s'] ) ) {
		printf( '<input type="hidden" name="s" value="%s" />', esc_attr( $wp_query->query['s'] ) );
	}
}

/**
 * Provide a list of local navigation menus.
 */
function add_site_navigation_menus( $menus ) {
	$menu = array();

	$menu[] = array(
		'label' => __( 'Favorites', 'wporg-patterns' ),
		'url' => '/favorites/',
	);
	if ( is_user_logged_in() ) {
		$menu[] = array(
			'label' => __( 'My Patterns', 'wporg-patterns' ),
			'url' => '/my-patterns/',
		);
	}
	$menu[] = array(
		'label' => __( 'New Pattern', 'wporg-patterns' ),
		'url' => '/new-pattern/',
	);
	return array(
		'main' => $menu,
	);
}

/**
 * Update the archive title for all filter views.
 *
 * @param string   $block_content The block content.
 * @param array    $block         The full block, including name and attributes.
 * @param WP_Block $instance      The block instance.
 */
function update_archive_title( $block_content, $block, $instance ) {
	global $wp_query;
	$attributes = $block['attrs'];

	if ( isset( $attributes['type'] ) && 'filter' === $attributes['type'] ) {
		// Skip output if there are no results. The `query-no-results` has an h1.
		if ( ! $wp_query->found_posts ) {
			return '';
		}

		$term_names = get_applied_filter_list();
		if ( ! empty( $term_names ) ) {
			$term_names = wp_list_pluck( $term_names, 'name' );
			// translators: %s list of terms used for filtering.
			$title = sprintf( __( 'Patterns: %s', 'wporg' ), implode( ', ', $term_names ) );
		} else {
			$author = isset( $wp_query->query['author_name'] ) ? get_user_by( 'slug', $wp_query->query['author_name'] ) : false;
			if ( $author ) {
				$title = sprintf( __( 'Author: %s', 'wporg' ), $author->display_name );
			} else {
				$title = __( 'All patterns', 'wporg' );
			}
		}

		$tag_name           = isset( $attributes['level'] ) ? 'h' . (int) $attributes['level'] : 'h1';
		$align_class_name   = empty( $attributes['textAlign'] ) ? '' : "has-text-align-{$attributes['textAlign']}";

		// Required to prevent `block_to_render` from being null in `get_block_wrapper_attributes`.
		$parent = WP_Block_Supports::$block_to_render;
		WP_Block_Supports::$block_to_render = $block;
		$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => $align_class_name ) );
		WP_Block_Supports::$block_to_render = $parent;

		return sprintf(
			'<%1$s %2$s>%3$s</%1$s>',
			$tag_name,
			$wrapper_attributes,
			$title
		);
	}
	return $block_content;
}

/**
 * Update the breadcrumbs to the current page.
 */
function update_site_breadcrumbs( $breadcrumbs ) {
	global $wp_query;

	// Build up the breadcrumbs from scratch.
	$breadcrumbs = array(
		array(
			'url' => home_url(),
			'title' => __( 'Home', 'wporg' ),
		),
	);

	if ( is_page() || is_single() ) {
		$breadcrumbs[] = array(
			'url' => false,
			'title' => get_the_title(),
		);
		return $breadcrumbs;
	}

	if ( is_search() ) {
		$breadcrumbs[] = array(
			'url' => home_url( '/archives/' ),
			'title' => __( 'All patterns', 'wporg' ),
		);
		$breadcrumbs[] = array(
			'url' => false,
			'title' => __( 'Search results', 'wporg' ),
		);
		return $breadcrumbs;
	}

	// `is_home` matches the "posts page", the All Patterns page.
	// `is_archive` matches any core archive (category, date, etc).
	if ( is_home() || is_archive() ) {
		// Get the current applied filters (except search, handled above).
		$term_names = get_applied_filter_list( false );
		$author = isset( $wp_query->query['author_name'] ) ? get_user_by( 'slug', $wp_query->query['author_name'] ) : false;

		$breadcrumbs[] = array(
			'url' => home_url( '/archives/' ),
			'title' => __( 'All patterns', 'wporg' ),
		);

		if ( $author ) {
			$breadcrumbs[] = array(
				'url' => get_author_posts_url( $author->ID ),
				'title' => sprintf( __( 'Author: %s', 'wporg' ), $author->display_name ),
			);
		}

		if ( $term_names ) {
			$term_names = wp_list_pluck( $term_names, 'name' );
			$breadcrumbs[] = array(
				'url' => false,
				// translators: %s list of terms used for filtering.
				'title' => implode( ', ', $term_names ),
			);
		}
	}

	// Last item should be "current", no URL.
	$breadcrumbs[count($breadcrumbs) - 1]['url'] = false;

	return $breadcrumbs;
}

/**
 * Update header template based on current query.
 *
 * @param array $parsed_block The block being rendered.
 *
 * @return array The updated block.
 */
function modify_pattern_include( $parsed_block ) {
	if ( 'core/pattern' !== $parsed_block['blockName'] || empty( $parsed_block['attrs']['slug'] ) ) {
		return $parsed_block;
	}

	if (
		'wporg-pattern-directory-2024/single-pattern' === $parsed_block['attrs']['slug'] &&
		get_current_user_id() === get_the_author_meta( 'ID' )
	) {
			$parsed_block['attrs']['slug'] = 'wporg-pattern-directory-2024/single-my-pattern';
	}

	if (
		'wporg-pattern-directory-2024/grid-mine' === $parsed_block['attrs']['slug'] &&
		! get_current_user_id()
	) {
			$parsed_block['attrs']['slug'] = 'wporg-pattern-directory-2024/logged-out-patterns';
	}

	if (
		'wporg-pattern-directory-2024/grid-favorites' === $parsed_block['attrs']['slug'] &&
		! get_current_user_id()
	) {
			$parsed_block['attrs']['slug'] = 'wporg-pattern-directory-2024/logged-out-favorites';
	}

	return $parsed_block;
}
