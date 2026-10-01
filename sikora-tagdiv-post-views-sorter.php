<?php
/**
 * Plugin Name:       Sikora TagDiv Post Views Sorter
 * Description:       Makes the TagDiv Newspaper theme's "Views" column on the WordPress admin Posts page sortable.
 * Version:           1.0.1
 * Author:            <a href="https://SikoraCollective.com/">Sikora Collective</a>
 * Requires at least: 5.0
 * Requires PHP:      7.0
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 *
 * How it works
 * ------------
 * The TagDiv Newspaper theme adds a "Views" column to the admin Posts list and
 * stores each post's view count in the post meta key `post_views_count`. The
 * theme does not register that column as sortable. This plugin:
 *
 *   1. Finds the theme's Views column on the Posts list screen.
 *   2. Registers it as sortable, with descending order on the first click.
 *   3. When the list is sorted by that column, orders the query results by the
 *      numeric value of `post_views_count` (posts with no count are treated as 0).
 *
 * The plugin only reads data. It never writes to the database: it creates no
 * options, tables, or post meta, and has no activation/uninstall routines.
 *
 * @package SikoraTagDivPostViewsSorter
 */

// Abort if this file is called directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Makes the TagDiv Newspaper "Views" admin column sortable.
 */
final class Sikora_TagDiv_Post_Views_Sorter {

	/**
	 * Post meta key in which the TagDiv Newspaper theme stores the total view count.
	 *
	 * @var string
	 */
	const META_KEY = 'post_views_count';

	/**
	 * Column key the TagDiv Newspaper theme uses for its "Views" column.
	 *
	 * @var string
	 */
	const THEME_COLUMN_KEY = 'td_post_views';

	/**
	 * Value used for the `orderby` query argument when sorting by views.
	 * Prefixed so it cannot collide with WordPress core or other plugins.
	 *
	 * @var string
	 */
	const ORDERBY = 'sikora_td_views';

	/**
	 * Registers the plugin's hooks. Everything runs in the admin only.
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'current_screen', array( __CLASS__, 'register_screen_hooks' ) );
	}

	/**
	 * On the Posts list screen, hooks in sortable columns and ORDER BY filtering.
	 *
	 * @param WP_Screen $screen The current admin screen.
	 * @return void
	 */
	public static function register_screen_hooks( $screen ) {
		if ( ! $screen instanceof WP_Screen || 'edit' !== $screen->base || 'post' !== $screen->post_type ) {
			return;
		}

		// Run last so nothing registered after us can drop the Views column again.
		add_filter( "manage_{$screen->id}_sortable_columns", array( __CLASS__, 'add_sortable_column' ), PHP_INT_MAX );
		add_filter( 'posts_orderby', array( __CLASS__, 'filter_posts_orderby' ), 10, 2 );
	}

	/**
	 * Finds the key of the theme's Views column among the given columns.
	 *
	 * Checked in order:
	 *   1. The theme's own key (`td_post_views`).
	 *   2. A column whose visible label is "Views" (HTML, icons and extra
	 *      whitespace are ignored), in case the key differs between versions.
	 *   3. A column whose key has a "view"/"views" segment (for example
	 *      `post_views`), not a substring match like `preview`.
	 *
	 * @param array $columns Column key => column label.
	 * @return string|null The column key, or null if no Views column exists.
	 */
	private static function find_views_column( $columns ) {
		if ( isset( $columns[ self::THEME_COLUMN_KEY ] ) ) {
			return self::THEME_COLUMN_KEY;
		}

		foreach ( $columns as $key => $label ) {
			$text = strtolower( trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $label ) ) ) );
			if ( 'views' === $text || 'post views' === $text ) {
				return $key;
			}
		}

		foreach ( array_keys( $columns ) as $key ) {
			$key = (string) $key;
			// Match whole key segments only (avoids false positives like "preview").
			if ( preg_match( '/(^|_)(post_)?views?($|_)/i', $key ) ) {
				return $key;
			}
		}

		return null;
	}

	/**
	 * Marks the Views column as sortable.
	 *
	 * The column list is read from get_column_headers(), which returns the
	 * final columns after every column filter (`manage_posts_columns`,
	 * `manage_post_posts_columns` and `manage_edit-post_columns`) has run, so
	 * the column is found no matter which of those the theme uses.
	 *
	 * The `true` second element tells WordPress to sort descending the first
	 * time the column header is clicked.
	 *
	 * @param array $sortable Column key => orderby value (or [orderby, desc_first]).
	 * @return array
	 */
	public static function add_sortable_column( $sortable ) {
		$screen = get_current_screen();
		if ( ! $screen ) {
			return $sortable;
		}

		$column_key = self::find_views_column( get_column_headers( $screen ) );
		if ( null !== $column_key ) {
			$sortable[ $column_key ] = array( self::ORDERBY, true );
		}

		return $sortable;
	}

	/**
	 * Replaces the ORDER BY clause when the Posts list is sorted by views.
	 *
	 * A correlated subquery reads the view count instead of a JOIN, so posts
	 * without a view count stay in the list (sorted as 0) and no duplicate rows
	 * can appear. Ties are broken by publish date, then ID. Order defaults to
	 * descending unless `order=asc` is requested.
	 *
	 * @param string   $orderby The ORDER BY clause of the query.
	 * @param WP_Query $query   The query being run.
	 * @return string
	 */
	public static function filter_posts_orderby( $orderby, $query ) {
		global $wpdb, $pagenow;

		$post_type = $query->get( 'post_type' );

		if (
			'edit.php' !== $pagenow
			|| ! $query->is_admin
			|| ! $query->is_main_query()
			|| ! current_user_can( 'edit_posts' )
			|| self::ORDERBY !== $query->get( 'orderby' )
			|| ( ! empty( $post_type ) && array( 'post' ) !== (array) $post_type )
		) {
			return $orderby;
		}

		$order = 'ASC' === strtoupper( (string) $query->get( 'order' ) ) ? 'ASC' : 'DESC';

		$views = $wpdb->prepare(
			"COALESCE( ( SELECT MAX( CAST( pm.meta_value AS UNSIGNED ) ) FROM {$wpdb->postmeta} AS pm WHERE pm.post_id = {$wpdb->posts}.ID AND pm.meta_key = %s ), 0 )",
			self::META_KEY
		);

		return "{$views} {$order}, {$wpdb->posts}.post_date {$order}, {$wpdb->posts}.ID {$order}";
	}
}

Sikora_TagDiv_Post_Views_Sorter::init();
