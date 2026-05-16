<?php
/**
 * Plugin Name: Codex Blog Abilities
 * Description: Exposes guarded WordPress administration abilities to the WordPress MCP Adapter.
 * Version: 0.2.0
 * Author: Codex
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.9
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const CODEX_BLOG_ABILITIES_PLUGIN_FILE = 'codex-blog-abilities/codex-blog-abilities.php';

add_action(
	'wp_abilities_api_categories_init',
	static function() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			'codex-blog-admin',
			array(
				'label'       => __( 'Codex Blog Admin', 'codex-blog-abilities' ),
				'description' => __( 'Administrative blog operations exposed to trusted MCP clients.', 'codex-blog-abilities' ),
			)
		);
	}
);

add_action(
	'wp_abilities_api_init',
	static function() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		codex_blog_register_ability(
			'get-site-info',
			'Get Site Info',
			'Read basic WordPress site, theme, and current authenticated user information.',
			null,
			'codex_blog_get_site_info',
			static function() {
				return current_user_can( 'read' );
			},
			true
		);

		codex_blog_register_ability(
			'list-post-types',
			'List Post Types',
			'List registered post types and their key capabilities.',
			codex_blog_schema(
				array(
					'include_non_public' => array(
						'type'        => 'boolean',
						'description' => 'Whether to include non-public post types.',
					),
				)
			),
			'codex_blog_list_post_types',
			static function() {
				return current_user_can( 'edit_posts' );
			},
			true
		);

		codex_blog_register_ability(
			'list-posts',
			'List Posts',
			'Query posts, pages, or custom post types with pagination and basic filters.',
			codex_blog_schema(
				array(
					'post_type' => array(
						'type'        => 'string',
						'description' => 'Post type slug. Defaults to post.',
					),
					'status'    => array(
						'type'        => 'string',
						'description' => 'Post status such as publish, draft, pending, private, trash, or any.',
					),
					'search'    => array(
						'type'        => 'string',
						'description' => 'Search phrase.',
					),
					'page'      => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => 'Page number. Defaults to 1.',
					),
					'per_page'  => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'maximum'     => 100,
						'description' => 'Results per page. Defaults to 10.',
					),
					'orderby'   => array(
						'type'        => 'string',
						'description' => 'Order by field. Common values: date, modified, title, ID, menu_order.',
					),
					'order'     => array(
						'type'        => 'string',
						'enum'        => array( 'ASC', 'DESC', 'asc', 'desc' ),
						'description' => 'Sort order.',
					),
				)
			),
			'codex_blog_list_posts',
			static function( $input ) {
				$args      = codex_blog_input( $input );
				$post_type = codex_blog_post_type_from_input( $args );
				$type_obj  = get_post_type_object( $post_type );
				return $type_obj && current_user_can( $type_obj->cap->edit_posts );
			},
			true
		);

		codex_blog_register_ability(
			'get-post',
			'Get Post',
			'Read one post, page, attachment, or custom post type item by ID.',
			codex_blog_schema(
				array(
					'id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => 'Post ID.',
					),
				),
				array( 'id' )
			),
			'codex_blog_get_post',
			static function( $input ) {
				$args = codex_blog_input( $input );
				$post = get_post( (int) $args['id'] );
				return $post && current_user_can( 'edit_post', $post->ID );
			},
			true
		);

		codex_blog_register_ability(
			'create-post',
			'Create Post',
			'Create a post, page, or custom post type item. Publishing requires the relevant publish capability.',
			codex_blog_schema(
				array(
					'post_type' => array( 'type' => 'string' ),
					'title'     => array( 'type' => 'string' ),
					'content'   => array( 'type' => 'string' ),
					'excerpt'   => array( 'type' => 'string' ),
					'status'    => array(
						'type' => 'string',
						'enum' => array( 'draft', 'pending', 'publish', 'private' ),
					),
					'slug'      => array( 'type' => 'string' ),
					'parent_id' => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
					'featured_media_id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => 'Attachment ID to set as the featured image.',
					),
					'terms'     => array(
						'type'                 => 'object',
						'description'          => 'Object keyed by taxonomy slug. Values are arrays of term IDs, slugs, or names.',
						'additionalProperties' => array(
							'type'  => 'array',
							'items' => array(
								'type' => array( 'integer', 'string' ),
							),
						),
					),
				),
				array( 'title' )
			),
			'codex_blog_create_post',
			'codex_blog_can_create_post',
			false
		);

		codex_blog_register_ability(
			'update-post',
			'Update Post',
			'Update fields and taxonomy terms for an existing post, page, or custom post type item.',
			codex_blog_schema(
				array(
					'id'        => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'title'     => array( 'type' => 'string' ),
					'content'   => array( 'type' => 'string' ),
					'excerpt'   => array( 'type' => 'string' ),
					'status'    => array(
						'type' => 'string',
						'enum' => array( 'draft', 'pending', 'publish', 'private', 'trash' ),
					),
					'slug'      => array( 'type' => 'string' ),
					'parent_id' => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
					'featured_media_id' => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => 'Attachment ID to set as the featured image.',
					),
					'terms'     => array(
						'type'                 => 'object',
						'additionalProperties' => array(
							'type'  => 'array',
							'items' => array(
								'type' => array( 'integer', 'string' ),
							),
						),
					),
				),
				array( 'id' )
			),
			'codex_blog_update_post',
			'codex_blog_can_update_post',
			false
		);

		codex_blog_register_ability(
			'delete-post',
			'Delete Post',
			'Trash or permanently delete a post, page, attachment, or custom post type item.',
			codex_blog_schema(
				array(
					'id'    => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'force' => array(
						'type'        => 'boolean',
						'description' => 'If true, permanently delete. Otherwise move to trash when possible.',
					),
				),
				array( 'id' )
			),
			'codex_blog_delete_post',
			static function( $input ) {
				$args = codex_blog_input( $input );
				return ! empty( $args['id'] ) && current_user_can( 'delete_post', (int) $args['id'] );
			},
			false,
			true
		);

		codex_blog_register_ability(
			'schedule-post',
			'Schedule Post',
			'Schedule an existing post, page, or custom post type item for future publication using the site timezone by default.',
			codex_blog_schema(
				array(
					'id'       => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'date'     => array(
						'type'        => 'string',
						'description' => 'Future date/time. ISO 8601 is recommended; date-only strings are interpreted in the provided timezone or site timezone.',
					),
					'timezone' => array(
						'type'        => 'string',
						'description' => 'Optional timezone such as Europe/Berlin. Defaults to the site timezone.',
					),
				),
				array( 'id', 'date' )
			),
			'codex_blog_schedule_post',
			'codex_blog_can_schedule_post',
			false
		);

		codex_blog_register_ability(
			'upload-media-from-url',
			'Upload Media From URL',
			'Download a remote media file into the WordPress media library and optionally attach metadata.',
			codex_blog_schema(
				array(
					'url'         => array(
						'type'        => 'string',
						'format'      => 'uri',
						'description' => 'Remote media URL to sideload.',
					),
					'post_id'     => array(
						'type'        => 'integer',
						'minimum'     => 1,
						'description' => 'Optional parent post ID.',
					),
					'title'       => array( 'type' => 'string' ),
					'alt_text'    => array( 'type' => 'string' ),
					'caption'     => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
				),
				array( 'url' )
			),
			'codex_blog_upload_media_from_url',
			static function() {
				return current_user_can( 'upload_files' );
			},
			false
		);

		codex_blog_register_ability(
			'set-featured-image',
			'Set Featured Image',
			'Set an existing image attachment as the featured image for a post, page, or custom post type item.',
			codex_blog_schema(
				array(
					'post_id'       => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'attachment_id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
				array( 'post_id', 'attachment_id' )
			),
			'codex_blog_set_featured_image',
			'codex_blog_can_set_featured_image',
			false
		);

		codex_blog_register_ability(
			'set-featured-image-from-url',
			'Set Featured Image From URL',
			'Download a remote image, add it to the media library, and set it as the featured image for a post.',
			codex_blog_schema(
				array(
					'post_id'     => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'url'         => array(
						'type'   => 'string',
						'format' => 'uri',
					),
					'title'       => array( 'type' => 'string' ),
					'alt_text'    => array( 'type' => 'string' ),
					'caption'     => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
				),
				array( 'post_id', 'url' )
			),
			'codex_blog_set_featured_image_from_url',
			'codex_blog_can_set_featured_image_from_url',
			false
		);

		codex_blog_register_ability(
			'remove-featured-image',
			'Remove Featured Image',
			'Remove the featured image from a post, page, or custom post type item.',
			codex_blog_schema(
				array(
					'post_id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
				array( 'post_id' )
			),
			'codex_blog_remove_featured_image',
			static function( $input ) {
				$args = codex_blog_input( $input );
				return ! empty( $args['post_id'] ) && current_user_can( 'edit_post', (int) $args['post_id'] );
			},
			false
		);

		codex_blog_register_ability(
			'get-seo-meta',
			'Get SEO Meta',
			'Read SEO metadata for a post, including All in One SEO data when available and known post meta fallbacks.',
			codex_blog_schema(
				array(
					'post_id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
				array( 'post_id' )
			),
			'codex_blog_get_seo_meta',
			static function( $input ) {
				$args = codex_blog_input( $input );
				return ! empty( $args['post_id'] ) && current_user_can( 'edit_post', (int) $args['post_id'] );
			},
			true
		);

		codex_blog_register_ability(
			'update-seo-meta',
			'Update SEO Meta',
			'Update SEO title, description, canonical URL, keywords, social metadata, and robots directives where supported.',
			codex_blog_schema(
				array(
					'post_id'             => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'title'               => array( 'type' => 'string' ),
					'description'         => array( 'type' => 'string' ),
					'canonical_url'       => array(
						'type'   => 'string',
						'format' => 'uri',
					),
					'keywords'            => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
					'focus_keyphrase'     => array( 'type' => 'string' ),
					'og_title'            => array( 'type' => 'string' ),
					'og_description'      => array( 'type' => 'string' ),
					'og_image_url'        => array(
						'type'   => 'string',
						'format' => 'uri',
					),
					'twitter_title'       => array( 'type' => 'string' ),
					'twitter_description' => array( 'type' => 'string' ),
					'twitter_image_url'   => array(
						'type'   => 'string',
						'format' => 'uri',
					),
					'robots'              => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'noindex'  => array( 'type' => 'boolean' ),
							'nofollow' => array( 'type' => 'boolean' ),
						),
					),
				),
				array( 'post_id' )
			),
			'codex_blog_update_seo_meta',
			static function( $input ) {
				$args = codex_blog_input( $input );
				return ! empty( $args['post_id'] ) && current_user_can( 'edit_post', (int) $args['post_id'] );
			},
			false
		);

		codex_blog_register_ability(
			'audit-post-seo',
			'Audit Post SEO',
			'Run a deterministic SEO content audit for a post: metadata length, featured image, headings, links, terms, slug, and word count.',
			codex_blog_schema(
				array(
					'post_id' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
				array( 'post_id' )
			),
			'codex_blog_audit_post_seo',
			static function( $input ) {
				$args = codex_blog_input( $input );
				return ! empty( $args['post_id'] ) && current_user_can( 'edit_post', (int) $args['post_id'] );
			},
			true
		);

		codex_blog_register_ability(
			'list-terms',
			'List Terms',
			'List taxonomy terms.',
			codex_blog_schema(
				array(
					'taxonomy'   => array( 'type' => 'string' ),
					'hide_empty' => array( 'type' => 'boolean' ),
					'search'     => array( 'type' => 'string' ),
					'page'       => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'per_page'   => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 100,
					),
				),
				array( 'taxonomy' )
			),
			'codex_blog_list_terms',
			static function() {
				return current_user_can( 'edit_posts' );
			},
			true
		);

		codex_blog_register_ability(
			'create-term',
			'Create Term',
			'Create a taxonomy term.',
			codex_blog_schema(
				array(
					'taxonomy'    => array( 'type' => 'string' ),
					'name'        => array( 'type' => 'string' ),
					'slug'        => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
					'parent_id'   => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
				),
				array( 'taxonomy', 'name' )
			),
			'codex_blog_create_term',
			'codex_blog_can_manage_terms',
			false
		);

		codex_blog_register_ability(
			'update-term',
			'Update Term',
			'Update a taxonomy term.',
			codex_blog_schema(
				array(
					'taxonomy'    => array( 'type' => 'string' ),
					'term_id'     => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'name'        => array( 'type' => 'string' ),
					'slug'        => array( 'type' => 'string' ),
					'description' => array( 'type' => 'string' ),
					'parent_id'   => array(
						'type'    => 'integer',
						'minimum' => 0,
					),
				),
				array( 'taxonomy', 'term_id' )
			),
			'codex_blog_update_term',
			'codex_blog_can_manage_terms',
			false
		);

		codex_blog_register_ability(
			'delete-term',
			'Delete Term',
			'Delete a taxonomy term.',
			codex_blog_schema(
				array(
					'taxonomy' => array( 'type' => 'string' ),
					'term_id'  => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
				array( 'taxonomy', 'term_id' )
			),
			'codex_blog_delete_term',
			'codex_blog_can_manage_terms',
			false,
			true
		);

		codex_blog_register_ability(
			'list-comments',
			'List Comments',
			'List comments with moderation status filters.',
			codex_blog_schema(
				array(
					'status'   => array(
						'type'        => 'string',
						'description' => 'Comment status: all, approve, hold, spam, trash.',
					),
					'post_id'  => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'page'     => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 100,
					),
				)
			),
			'codex_blog_list_comments',
			static function() {
				return current_user_can( 'moderate_comments' );
			},
			true
		);

		codex_blog_register_ability(
			'update-comment',
			'Update Comment',
			'Update a comment moderation status or content.',
			codex_blog_schema(
				array(
					'id'      => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'status'  => array(
						'type' => 'string',
						'enum' => array( 'approve', 'hold', 'spam', 'trash' ),
					),
					'content' => array( 'type' => 'string' ),
				),
				array( 'id' )
			),
			'codex_blog_update_comment',
			static function() {
				return current_user_can( 'moderate_comments' );
			},
			false
		);

		codex_blog_register_ability(
			'delete-comment',
			'Delete Comment',
			'Trash or permanently delete a comment.',
			codex_blog_schema(
				array(
					'id'    => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'force' => array( 'type' => 'boolean' ),
				),
				array( 'id' )
			),
			'codex_blog_delete_comment',
			static function() {
				return current_user_can( 'moderate_comments' );
			},
			false,
			true
		);

		codex_blog_register_ability(
			'list-media',
			'List Media',
			'List media library attachments.',
			codex_blog_schema(
				array(
					'search'   => array( 'type' => 'string' ),
					'mime_type' => array( 'type' => 'string' ),
					'page'     => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
					'per_page' => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 100,
					),
				)
			),
			'codex_blog_list_media',
			static function() {
				return current_user_can( 'upload_files' );
			},
			true
		);

		codex_blog_register_ability(
			'list-plugins',
			'List Plugins',
			'List installed plugins and activation status.',
			null,
			'codex_blog_list_plugins',
			static function() {
				return current_user_can( 'activate_plugins' );
			},
			true
		);

		codex_blog_register_ability(
			'activate-plugin',
			'Activate Plugin',
			'Activate an installed plugin by plugin file path.',
			codex_blog_schema(
				array(
					'plugin_file' => array(
						'type'        => 'string',
						'description' => 'Plugin file path, for example akismet/akismet.php.',
					),
				),
				array( 'plugin_file' )
			),
			'codex_blog_activate_plugin',
			static function() {
				return current_user_can( 'activate_plugins' );
			},
			false
		);

		codex_blog_register_ability(
			'deactivate-plugin',
			'Deactivate Plugin',
			'Deactivate an installed plugin by plugin file path. Blocks deactivating MCP-critical plugins unless allow_critical is true.',
			codex_blog_schema(
				array(
					'plugin_file'    => array( 'type' => 'string' ),
					'allow_critical' => array(
						'type'        => 'boolean',
						'description' => 'Allow deactivating mcp-adapter or codex-blog-abilities.',
					),
				),
				array( 'plugin_file' )
			),
			'codex_blog_deactivate_plugin',
			static function() {
				return current_user_can( 'activate_plugins' );
			},
			false
		);

		codex_blog_register_ability(
			'get-options',
			'Get Options',
			'Read selected safe WordPress options. If names is omitted, reads a common admin allowlist.',
			codex_blog_schema(
				array(
					'names' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
				)
			),
			'codex_blog_get_options',
			static function() {
				return current_user_can( 'manage_options' );
			},
			true
		);

		codex_blog_register_ability(
			'update-options',
			'Update Options',
			'Update selected safe WordPress options from a strict allowlist.',
			codex_blog_schema(
				array(
					'updates' => array(
						'type'                 => 'object',
						'additionalProperties' => true,
					),
				),
				array( 'updates' )
			),
			'codex_blog_update_options',
			static function() {
				return current_user_can( 'manage_options' );
			},
			false
		);
	}
);

function codex_blog_register_ability( $slug, $label, $description, $input_schema, $callback, $permission_callback, $readonly, $destructive = false ) {
	$args = array(
		'label'               => __( $label, 'codex-blog-abilities' ),
		'description'         => __( $description, 'codex-blog-abilities' ),
		'category'            => 'codex-blog-admin',
		'output_schema'       => codex_blog_output_schema(),
		'execute_callback'    => $callback,
		'permission_callback' => $permission_callback,
		'meta'                => array(
			'mcp'         => array(
				'public' => true,
			),
			'annotations' => array(
				'readonly'    => (bool) $readonly,
				'destructive' => (bool) $destructive,
				'idempotent'  => false,
			),
		),
	);

	if ( null !== $input_schema ) {
		$args['input_schema'] = $input_schema;
	}

	wp_register_ability( 'codex-blog/' . $slug, $args );
}

function codex_blog_schema( $properties, $required = array() ) {
	return array(
		'type'                 => 'object',
		'properties'           => $properties,
		'required'             => $required,
		'additionalProperties' => false,
	);
}

function codex_blog_output_schema() {
	return array(
		'type'                 => 'object',
		'additionalProperties' => true,
	);
}

function codex_blog_input( $input ) {
	if ( is_object( $input ) ) {
		$input = get_object_vars( $input );
	}

	if ( ! is_array( $input ) ) {
		return array();
	}

	foreach ( $input as $key => $value ) {
		if ( is_object( $value ) ) {
			$input[ $key ] = codex_blog_input( $value );
		} elseif ( is_array( $value ) ) {
			$input[ $key ] = codex_blog_deep_normalize( $value );
		}
	}

	return $input;
}

function codex_blog_deep_normalize( $value ) {
	if ( is_object( $value ) ) {
		return codex_blog_input( $value );
	}

	if ( ! is_array( $value ) ) {
		return $value;
	}

	foreach ( $value as $key => $child ) {
		$value[ $key ] = codex_blog_deep_normalize( $child );
	}

	return $value;
}

function codex_blog_limit( $value, $default, $min, $max ) {
	$value = (int) $value;
	if ( $value < $min ) {
		$value = $default;
	}

	return min( $max, max( $min, $value ) );
}

function codex_blog_post_type_from_input( $args ) {
	$post_type = isset( $args['post_type'] ) ? sanitize_key( $args['post_type'] ) : 'post';
	return $post_type ? $post_type : 'post';
}

function codex_blog_post_to_array( WP_Post $post, $include_content = false ) {
	$data = array(
		'id'            => (int) $post->ID,
		'post_type'     => $post->post_type,
		'status'        => $post->post_status,
		'title'         => get_the_title( $post ),
		'slug'          => $post->post_name,
		'author_id'     => (int) $post->post_author,
		'date'          => get_post_time( DATE_ATOM, false, $post ),
		'modified'      => get_post_modified_time( DATE_ATOM, false, $post ),
		'link'          => get_permalink( $post ),
		'parent_id'     => (int) $post->post_parent,
		'comment_count' => (int) $post->comment_count,
		'featured_image' => codex_blog_get_featured_image( $post->ID ),
	);

	if ( $include_content ) {
		$data['content'] = $post->post_content;
		$data['excerpt'] = $post->post_excerpt;
		$data['terms']   = codex_blog_get_post_terms( $post->ID );
	}

	return $data;
}

function codex_blog_get_post_terms( $post_id ) {
	$result     = array();
	$taxonomies = get_object_taxonomies( get_post_type( $post_id ) );

	foreach ( $taxonomies as $taxonomy ) {
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			$result[ $taxonomy ] = array();
			continue;
		}

		$result[ $taxonomy ] = array_map(
			static function( WP_Term $term ) {
				return array(
					'id'   => (int) $term->term_id,
					'name' => $term->name,
					'slug' => $term->slug,
				);
			},
			$terms
		);
	}

	return $result;
}

function codex_blog_get_featured_image( $post_id ) {
	$attachment_id = get_post_thumbnail_id( $post_id );

	if ( ! $attachment_id ) {
		return null;
	}

	return codex_blog_attachment_to_array( $attachment_id );
}

function codex_blog_get_site_info() {
	$current_user = wp_get_current_user();
	$theme        = wp_get_theme();

	return array(
		'name'         => get_bloginfo( 'name' ),
		'description'  => get_bloginfo( 'description' ),
		'url'          => site_url(),
		'home'         => home_url(),
		'wp_version'   => get_bloginfo( 'version' ),
		'active_theme' => array(
			'name'    => $theme->get( 'Name' ),
			'version' => $theme->get( 'Version' ),
		),
		'current_user' => array(
			'id'         => (int) $current_user->ID,
			'login'      => $current_user->user_login,
			'display'    => $current_user->display_name,
			'roles'      => $current_user->roles,
			'capability' => array(
				'edit_posts'       => current_user_can( 'edit_posts' ),
				'manage_options'   => current_user_can( 'manage_options' ),
				'activate_plugins' => current_user_can( 'activate_plugins' ),
			),
		),
	);
}

function codex_blog_list_post_types( $input ) {
	$args               = codex_blog_input( $input );
	$include_non_public = ! empty( $args['include_non_public'] );
	$post_types         = get_post_types( array(), 'objects' );
	$result             = array();

	foreach ( $post_types as $post_type => $type_obj ) {
		if ( ! $include_non_public && ! $type_obj->public ) {
			continue;
		}

		$result[] = array(
			'name'         => $post_type,
			'label'        => $type_obj->label,
			'public'       => (bool) $type_obj->public,
			'show_ui'      => (bool) $type_obj->show_ui,
			'hierarchical' => (bool) $type_obj->hierarchical,
			'capabilities' => array(
				'edit_posts'    => $type_obj->cap->edit_posts,
				'publish_posts' => $type_obj->cap->publish_posts,
				'delete_posts'  => $type_obj->cap->delete_posts,
			),
		);
	}

	return array( 'post_types' => $result );
}

function codex_blog_list_posts( $input ) {
	$args      = codex_blog_input( $input );
	$post_type = codex_blog_post_type_from_input( $args );
	$status    = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'any';
	$page      = codex_blog_limit( $args['page'] ?? 1, 1, 1, 100000 );
	$per_page  = codex_blog_limit( $args['per_page'] ?? 10, 10, 1, 100 );

	$query_args = array(
		'post_type'      => $post_type,
		'post_status'    => $status ? $status : 'any',
		'paged'          => $page,
		'posts_per_page' => $per_page,
		'orderby'        => isset( $args['orderby'] ) ? sanitize_key( $args['orderby'] ) : 'date',
		'order'          => isset( $args['order'] ) && 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC',
	);

	if ( ! empty( $args['search'] ) ) {
		$query_args['s'] = sanitize_text_field( $args['search'] );
	}

	$query = new WP_Query( $query_args );

	return array(
		'page'        => $page,
		'per_page'    => $per_page,
		'total'       => (int) $query->found_posts,
		'total_pages' => (int) $query->max_num_pages,
		'posts'       => array_map(
			static function( WP_Post $post ) {
				return codex_blog_post_to_array( $post, false );
			},
			$query->posts
		),
	);
}

function codex_blog_get_post( $input ) {
	$args = codex_blog_input( $input );
	$post = get_post( (int) $args['id'] );

	if ( ! $post ) {
		return new WP_Error( 'codex_blog_not_found', 'Post not found.' );
	}

	return array( 'post' => codex_blog_post_to_array( $post, true ) );
}

function codex_blog_can_create_post( $input ) {
	$args      = codex_blog_input( $input );
	$post_type = codex_blog_post_type_from_input( $args );
	$type_obj  = get_post_type_object( $post_type );

	if ( ! $type_obj || ! current_user_can( $type_obj->cap->edit_posts ) ) {
		return false;
	}

	$status = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'draft';
	if ( in_array( $status, array( 'publish', 'private' ), true ) && ! current_user_can( $type_obj->cap->publish_posts ) ) {
		return false;
	}

	return true;
}

function codex_blog_create_post( $input ) {
	$args      = codex_blog_input( $input );
	$post_type = codex_blog_post_type_from_input( $args );
	$status    = isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'draft';

	$postarr = array(
		'post_type'    => $post_type,
		'post_status'  => $status,
		'post_title'   => sanitize_text_field( $args['title'] ?? '' ),
		'post_content' => isset( $args['content'] ) ? wp_kses_post( $args['content'] ) : '',
		'post_excerpt' => isset( $args['excerpt'] ) ? wp_kses_post( $args['excerpt'] ) : '',
	);

	if ( isset( $args['slug'] ) ) {
		$postarr['post_name'] = sanitize_title( $args['slug'] );
	}

	if ( isset( $args['parent_id'] ) ) {
		$postarr['post_parent'] = (int) $args['parent_id'];
	}

	$post_id = wp_insert_post( $postarr, true );
	if ( is_wp_error( $post_id ) ) {
		return $post_id;
	}

	$terms_result = codex_blog_apply_terms( $post_id, $args['terms'] ?? array() );
	if ( is_wp_error( $terms_result ) ) {
		return $terms_result;
	}

	if ( ! empty( $args['featured_media_id'] ) ) {
		$featured_result = codex_blog_set_featured_image_for_post( $post_id, (int) $args['featured_media_id'] );
		if ( is_wp_error( $featured_result ) ) {
			return $featured_result;
		}
	}

	return array(
		'success' => true,
		'post'    => codex_blog_post_to_array( get_post( $post_id ), true ),
	);
}

function codex_blog_can_update_post( $input ) {
	$args = codex_blog_input( $input );
	if ( empty( $args['id'] ) || ! current_user_can( 'edit_post', (int) $args['id'] ) ) {
		return false;
	}

	if ( ! empty( $args['status'] ) && in_array( sanitize_key( $args['status'] ), array( 'publish', 'private' ), true ) ) {
		$post     = get_post( (int) $args['id'] );
		$type_obj = $post ? get_post_type_object( $post->post_type ) : null;
		return $type_obj && current_user_can( $type_obj->cap->publish_posts );
	}

	return true;
}

function codex_blog_update_post( $input ) {
	$args = codex_blog_input( $input );
	$post = get_post( (int) $args['id'] );

	if ( ! $post ) {
		return new WP_Error( 'codex_blog_not_found', 'Post not found.' );
	}

	$postarr = array( 'ID' => $post->ID );
	if ( array_key_exists( 'title', $args ) ) {
		$postarr['post_title'] = sanitize_text_field( $args['title'] );
	}
	if ( array_key_exists( 'content', $args ) ) {
		$postarr['post_content'] = wp_kses_post( $args['content'] );
	}
	if ( array_key_exists( 'excerpt', $args ) ) {
		$postarr['post_excerpt'] = wp_kses_post( $args['excerpt'] );
	}
	if ( array_key_exists( 'status', $args ) ) {
		$postarr['post_status'] = sanitize_key( $args['status'] );
	}
	if ( array_key_exists( 'slug', $args ) ) {
		$postarr['post_name'] = sanitize_title( $args['slug'] );
	}
	if ( array_key_exists( 'parent_id', $args ) ) {
		$postarr['post_parent'] = (int) $args['parent_id'];
	}

	$result = wp_update_post( $postarr, true );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	$terms_result = codex_blog_apply_terms( $post->ID, $args['terms'] ?? array() );
	if ( is_wp_error( $terms_result ) ) {
		return $terms_result;
	}

	if ( ! empty( $args['featured_media_id'] ) ) {
		$featured_result = codex_blog_set_featured_image_for_post( $post->ID, (int) $args['featured_media_id'] );
		if ( is_wp_error( $featured_result ) ) {
			return $featured_result;
		}
	}

	return array(
		'success' => true,
		'post'    => codex_blog_post_to_array( get_post( $post->ID ), true ),
	);
}

function codex_blog_delete_post( $input ) {
	$args  = codex_blog_input( $input );
	$id    = (int) $args['id'];
	$force = ! empty( $args['force'] );
	$post  = get_post( $id );

	if ( ! $post ) {
		return new WP_Error( 'codex_blog_not_found', 'Post not found.' );
	}

	$result = $force ? wp_delete_post( $id, true ) : wp_trash_post( $id );
	if ( ! $result ) {
		return new WP_Error( 'codex_blog_delete_failed', 'The post could not be deleted.' );
	}

	return array(
		'success'         => true,
		'id'              => $id,
		'previous_status' => $post->post_status,
		'force'           => $force,
	);
}

function codex_blog_can_schedule_post( $input ) {
	$args = codex_blog_input( $input );
	$post = ! empty( $args['id'] ) ? get_post( (int) $args['id'] ) : null;

	if ( ! $post || ! current_user_can( 'edit_post', $post->ID ) ) {
		return false;
	}

	$type_obj = get_post_type_object( $post->post_type );
	return $type_obj && current_user_can( $type_obj->cap->publish_posts );
}

function codex_blog_schedule_post( $input ) {
	$args = codex_blog_input( $input );
	$post = get_post( (int) $args['id'] );

	if ( ! $post ) {
		return new WP_Error( 'codex_blog_not_found', 'Post not found.' );
	}

	$date = codex_blog_parse_datetime( $args['date'] ?? '', $args['timezone'] ?? '' );
	if ( is_wp_error( $date ) ) {
		return $date;
	}

	$site_timezone = wp_timezone();
	$now           = new DateTimeImmutable( 'now', $site_timezone );
	if ( $date <= $now ) {
		return new WP_Error( 'codex_blog_schedule_past_date', 'Scheduled date must be in the future.' );
	}

	$local = $date->setTimezone( $site_timezone )->format( 'Y-m-d H:i:s' );
	$gmt   = $date->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );

	$result = wp_update_post(
		array(
			'ID'            => $post->ID,
			'post_status'   => 'future',
			'post_date'     => $local,
			'post_date_gmt' => $gmt,
			'edit_date'     => true,
		),
		true
	);

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return array(
		'success' => true,
		'post'    => codex_blog_post_to_array( get_post( $post->ID ), true ),
	);
}

function codex_blog_parse_datetime( $date, $timezone ) {
	$date = is_string( $date ) ? trim( $date ) : '';
	if ( '' === $date ) {
		return new WP_Error( 'codex_blog_missing_date', 'A date value is required.' );
	}

	try {
		$tz = $timezone ? new DateTimeZone( sanitize_text_field( $timezone ) ) : wp_timezone();
		return new DateTimeImmutable( $date, $tz );
	} catch ( Exception $e ) {
		return new WP_Error( 'codex_blog_invalid_date', 'Invalid date or timezone: ' . $e->getMessage() );
	}
}

function codex_blog_can_set_featured_image( $input ) {
	$args = codex_blog_input( $input );
	return ! empty( $args['post_id'] ) && ! empty( $args['attachment_id'] ) && current_user_can( 'edit_post', (int) $args['post_id'] );
}

function codex_blog_can_set_featured_image_from_url( $input ) {
	$args = codex_blog_input( $input );
	return ! empty( $args['post_id'] ) && current_user_can( 'edit_post', (int) $args['post_id'] ) && current_user_can( 'upload_files' );
}

function codex_blog_upload_media_from_url( $input ) {
	$args          = codex_blog_input( $input );
	$attachment_id = codex_blog_sideload_attachment_from_url( $args );

	if ( is_wp_error( $attachment_id ) ) {
		return $attachment_id;
	}

	return array(
		'success'    => true,
		'attachment' => codex_blog_attachment_to_array( $attachment_id ),
	);
}

function codex_blog_set_featured_image( $input ) {
	$args   = codex_blog_input( $input );
	$result = codex_blog_set_featured_image_for_post( (int) $args['post_id'], (int) $args['attachment_id'] );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return array(
		'success'        => true,
		'post'           => codex_blog_post_to_array( get_post( (int) $args['post_id'] ), true ),
		'featured_image' => codex_blog_attachment_to_array( (int) $args['attachment_id'] ),
	);
}

function codex_blog_set_featured_image_from_url( $input ) {
	$args = codex_blog_input( $input );
	if ( empty( $args['post_id'] ) ) {
		return new WP_Error( 'codex_blog_missing_post', 'A post_id is required.' );
	}

	$args['post_id'] = (int) $args['post_id'];
	$attachment_id   = codex_blog_sideload_attachment_from_url( $args );
	if ( is_wp_error( $attachment_id ) ) {
		return $attachment_id;
	}

	$result = codex_blog_set_featured_image_for_post( $args['post_id'], $attachment_id );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return array(
		'success'        => true,
		'post'           => codex_blog_post_to_array( get_post( $args['post_id'] ), true ),
		'featured_image' => codex_blog_attachment_to_array( $attachment_id ),
	);
}

function codex_blog_remove_featured_image( $input ) {
	$args    = codex_blog_input( $input );
	$post_id = (int) $args['post_id'];
	$old_id  = get_post_thumbnail_id( $post_id );

	delete_post_thumbnail( $post_id );

	return array(
		'success'                    => true,
		'post'                       => codex_blog_post_to_array( get_post( $post_id ), true ),
		'previous_featured_image_id' => $old_id ? (int) $old_id : null,
	);
}

function codex_blog_sideload_attachment_from_url( $args ) {
	$url = isset( $args['url'] ) ? esc_url_raw( $args['url'] ) : '';
	if ( ! $url || ( function_exists( 'wp_http_validate_url' ) && ! wp_http_validate_url( $url ) ) ) {
		return new WP_Error( 'codex_blog_invalid_media_url', 'A valid remote media URL is required.' );
	}

	$post_id = ! empty( $args['post_id'] ) ? (int) $args['post_id'] : 0;
	if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
		return new WP_Error( 'codex_blog_forbidden_parent_post', 'Current user cannot edit the parent post.' );
	}

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$tmp = download_url( $url, 30 );
	if ( is_wp_error( $tmp ) ) {
		return $tmp;
	}

	$path = wp_parse_url( $url, PHP_URL_PATH );
	$name = $path ? wp_basename( $path ) : 'remote-media';
	$name = sanitize_file_name( $name ? $name : 'remote-media' );

	$file = array(
		'name'     => $name,
		'tmp_name' => $tmp,
	);

	$attachment_id = media_handle_sideload( $file, $post_id, isset( $args['description'] ) ? sanitize_textarea_field( $args['description'] ) : null );
	if ( is_wp_error( $attachment_id ) ) {
		@unlink( $tmp );
		return $attachment_id;
	}

	$metadata_result = codex_blog_update_attachment_fields( $attachment_id, $args );
	if ( is_wp_error( $metadata_result ) ) {
		return $metadata_result;
	}

	return $attachment_id;
}

function codex_blog_update_attachment_fields( $attachment_id, $args ) {
	$update = array( 'ID' => (int) $attachment_id );

	if ( array_key_exists( 'title', $args ) ) {
		$update['post_title'] = sanitize_text_field( $args['title'] );
	}
	if ( array_key_exists( 'caption', $args ) ) {
		$update['post_excerpt'] = sanitize_textarea_field( $args['caption'] );
	}
	if ( array_key_exists( 'description', $args ) ) {
		$update['post_content'] = sanitize_textarea_field( $args['description'] );
	}

	if ( count( $update ) > 1 ) {
		$result = wp_update_post( $update, true );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
	}

	if ( array_key_exists( 'alt_text', $args ) ) {
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $args['alt_text'] ) );
	}

	return true;
}

function codex_blog_attachment_to_array( $attachment_id ) {
	$attachment = get_post( (int) $attachment_id );

	if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
		return null;
	}

	$meta = wp_get_attachment_metadata( $attachment->ID );

	return array(
		'id'          => (int) $attachment->ID,
		'title'       => get_the_title( $attachment ),
		'mime_type'   => $attachment->post_mime_type,
		'url'         => wp_get_attachment_url( $attachment->ID ),
		'alt_text'    => get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ),
		'caption'     => $attachment->post_excerpt,
		'description' => $attachment->post_content,
		'parent_id'   => (int) $attachment->post_parent,
		'width'       => isset( $meta['width'] ) ? (int) $meta['width'] : null,
		'height'      => isset( $meta['height'] ) ? (int) $meta['height'] : null,
	);
}

function codex_blog_set_featured_image_for_post( $post_id, $attachment_id ) {
	$post       = get_post( (int) $post_id );
	$attachment = get_post( (int) $attachment_id );

	if ( ! $post ) {
		return new WP_Error( 'codex_blog_not_found', 'Post not found.' );
	}
	if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
		return new WP_Error( 'codex_blog_attachment_not_found', 'Attachment not found.' );
	}
	if ( ! wp_attachment_is_image( $attachment->ID ) ) {
		return new WP_Error( 'codex_blog_attachment_not_image', 'Featured image must be an image attachment.' );
	}
	if ( ! current_user_can( 'edit_post', $post->ID ) ) {
		return new WP_Error( 'codex_blog_forbidden_post', 'Current user cannot edit this post.' );
	}

	$result = set_post_thumbnail( $post->ID, $attachment->ID );
	if ( ! $result && (int) get_post_thumbnail_id( $post->ID ) !== (int) $attachment->ID ) {
		return new WP_Error( 'codex_blog_featured_image_failed', 'Featured image could not be set.' );
	}

	return true;
}

function codex_blog_apply_terms( $post_id, $terms_by_taxonomy ) {
	if ( empty( $terms_by_taxonomy ) ) {
		return true;
	}

	if ( ! is_array( $terms_by_taxonomy ) ) {
		return new WP_Error( 'codex_blog_invalid_terms', 'Terms must be an object keyed by taxonomy.' );
	}

	foreach ( $terms_by_taxonomy as $taxonomy => $terms ) {
		$taxonomy = sanitize_key( $taxonomy );
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return new WP_Error( 'codex_blog_invalid_taxonomy', 'Invalid taxonomy: ' . $taxonomy );
		}

		$tax_obj = get_taxonomy( $taxonomy );
		if ( ! current_user_can( $tax_obj->cap->assign_terms ) ) {
			return new WP_Error( 'codex_blog_forbidden_terms', 'Current user cannot assign terms for taxonomy: ' . $taxonomy );
		}

		$result = wp_set_object_terms( $post_id, $terms, $taxonomy, false );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
	}

	return true;
}

function codex_blog_get_seo_meta( $input ) {
	$args    = codex_blog_input( $input );
	$post_id = (int) $args['post_id'];

	if ( ! get_post( $post_id ) ) {
		return new WP_Error( 'codex_blog_not_found', 'Post not found.' );
	}

	return array(
		'seo' => codex_blog_read_seo_meta( $post_id ),
	);
}

function codex_blog_update_seo_meta( $input ) {
	$args    = codex_blog_input( $input );
	$post_id = (int) $args['post_id'];

	if ( ! get_post( $post_id ) ) {
		return new WP_Error( 'codex_blog_not_found', 'Post not found.' );
	}

	$updates = codex_blog_collect_seo_updates( $args );
	if ( empty( $updates ) ) {
		return array(
			'success' => true,
			'updated' => false,
			'seo'     => codex_blog_read_seo_meta( $post_id ),
		);
	}

	$aioseo_result = codex_blog_save_aioseo_meta( $post_id, $updates );
	if ( is_wp_error( $aioseo_result ) ) {
		return $aioseo_result;
	}

	codex_blog_update_fallback_seo_meta( $post_id, $updates );

	return array(
		'success' => true,
		'updated' => true,
		'source'  => $aioseo_result ? 'aioseo' : 'post_meta',
		'seo'     => codex_blog_read_seo_meta( $post_id ),
	);
}

function codex_blog_audit_post_seo( $input ) {
	$args    = codex_blog_input( $input );
	$post_id = (int) $args['post_id'];
	$post    = get_post( $post_id );

	if ( ! $post ) {
		return new WP_Error( 'codex_blog_not_found', 'Post not found.' );
	}

	$seo          = codex_blog_read_seo_meta( $post_id );
	$rendered     = do_blocks( $post->post_content );
	$text         = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( $rendered ) ) ) );
	$title        = ! empty( $seo['title'] ) ? $seo['title'] : get_the_title( $post );
	$description  = ! empty( $seo['description'] ) ? $seo['description'] : $post->post_excerpt;
	$link_counts  = codex_blog_count_links( $rendered );
	$h2_count     = (int) preg_match_all( '/<h2\b/i', $rendered );
	$terms        = codex_blog_get_post_terms( $post_id );
	$issues       = array();
	$title_length = function_exists( 'mb_strlen' ) ? mb_strlen( wp_strip_all_tags( $title ) ) : strlen( wp_strip_all_tags( $title ) );
	$desc_length  = function_exists( 'mb_strlen' ) ? mb_strlen( wp_strip_all_tags( $description ) ) : strlen( wp_strip_all_tags( $description ) );
	$word_count   = codex_blog_word_count( $text );

	if ( empty( $seo['title'] ) ) {
		$issues[] = 'SEO title is not customized.';
	} elseif ( $title_length < 30 || $title_length > 65 ) {
		$issues[] = 'SEO title length should usually be between 30 and 65 characters.';
	}

	if ( empty( $seo['description'] ) ) {
		$issues[] = 'Meta description is missing.';
	} elseif ( $desc_length < 70 || $desc_length > 160 ) {
		$issues[] = 'Meta description length should usually be between 70 and 160 characters.';
	}

	if ( ! get_post_thumbnail_id( $post_id ) ) {
		$issues[] = 'Featured image is missing.';
	}
	if ( $word_count < 300 ) {
		$issues[] = 'Content is shorter than 300 words.';
	}
	if ( 0 === $h2_count ) {
		$issues[] = 'No H2 headings found.';
	}
	if ( 0 === $link_counts['internal'] ) {
		$issues[] = 'No internal links found.';
	}
	if ( empty( $terms['category'] ) ) {
		$issues[] = 'No category assigned.';
	}
	if ( empty( $terms['post_tag'] ) ) {
		$issues[] = 'No tags assigned.';
	}
	if ( strlen( $post->post_name ) > 75 ) {
		$issues[] = 'Slug is longer than 75 characters.';
	}

	return array(
		'post_id' => $post_id,
		'metrics' => array(
			'title_length'             => $title_length,
			'meta_description_length'  => $desc_length,
			'word_count'               => $word_count,
			'h2_count'                 => (int) $h2_count,
			'internal_link_count'      => $link_counts['internal'],
			'external_link_count'      => $link_counts['external'],
			'has_featured_image'       => (bool) get_post_thumbnail_id( $post_id ),
			'has_custom_seo_title'     => ! empty( $seo['title'] ),
			'has_meta_description'     => ! empty( $seo['description'] ),
			'category_count'           => empty( $terms['category'] ) ? 0 : count( $terms['category'] ),
			'tag_count'                => empty( $terms['post_tag'] ) ? 0 : count( $terms['post_tag'] ),
			'slug_length'              => strlen( $post->post_name ),
		),
		'issues'  => $issues,
		'seo'     => $seo,
	);
}

function codex_blog_read_seo_meta( $post_id ) {
	$model = codex_blog_get_aioseo_post( $post_id );
	$seo   = array(
		'post_id'             => (int) $post_id,
		'source'              => $model ? 'aioseo' : 'post_meta',
		'title'               => null,
		'description'         => null,
		'canonical_url'       => null,
		'keywords'            => null,
		'focus_keyphrase'     => null,
		'og_title'            => null,
		'og_description'      => null,
		'og_image_url'        => null,
		'twitter_title'       => null,
		'twitter_description' => null,
		'twitter_image_url'   => null,
		'robots'              => array(
			'noindex'  => null,
			'nofollow' => null,
		),
	);

	if ( $model ) {
		$seo['title']               = codex_blog_object_property( $model, 'title' );
		$seo['description']         = codex_blog_object_property( $model, 'description' );
		$seo['canonical_url']       = codex_blog_object_property( $model, 'canonical_url' );
		$seo['keywords']            = codex_blog_object_property( $model, 'keywords' );
		$seo['og_title']            = codex_blog_object_property( $model, 'og_title' );
		$seo['og_description']      = codex_blog_object_property( $model, 'og_description' );
		$seo['og_image_url']        = codex_blog_object_property( $model, 'og_image_url' );
		$seo['twitter_title']       = codex_blog_object_property( $model, 'twitter_title' );
		$seo['twitter_description'] = codex_blog_object_property( $model, 'twitter_description' );
		$seo['twitter_image_url']   = codex_blog_object_property( $model, 'twitter_image_url' );
		$seo['robots']['noindex']   = codex_blog_bool_from_mixed( codex_blog_object_property( $model, 'robots_noindex' ) );
		$seo['robots']['nofollow']  = codex_blog_bool_from_mixed( codex_blog_object_property( $model, 'robots_nofollow' ) );
	}

	$seo['title']               = codex_blog_value_or_meta( $seo['title'], $post_id, array( '_aioseo_title', '_aioseop_title', '_yoast_wpseo_title' ) );
	$seo['description']         = codex_blog_value_or_meta( $seo['description'], $post_id, array( '_aioseo_description', '_aioseop_description', '_yoast_wpseo_metadesc' ) );
	$seo['canonical_url']       = codex_blog_value_or_meta( $seo['canonical_url'], $post_id, array( '_aioseo_canonical_url', '_aioseop_custom_link', '_yoast_wpseo_canonical' ) );
	$seo['keywords']            = codex_blog_value_or_meta( $seo['keywords'], $post_id, array( '_aioseo_keywords', '_aioseop_keywords', '_yoast_wpseo_metakeywords' ) );
	$seo['focus_keyphrase']     = codex_blog_value_or_meta( $seo['focus_keyphrase'], $post_id, array( '_aioseo_focus_keyphrase', '_yoast_wpseo_focuskw' ) );
	$seo['og_title']            = codex_blog_value_or_meta( $seo['og_title'], $post_id, array( '_aioseo_og_title', '_yoast_wpseo_opengraph-title' ) );
	$seo['og_description']      = codex_blog_value_or_meta( $seo['og_description'], $post_id, array( '_aioseo_og_description', '_yoast_wpseo_opengraph-description' ) );
	$seo['og_image_url']        = codex_blog_value_or_meta( $seo['og_image_url'], $post_id, array( '_aioseo_og_image_url', '_yoast_wpseo_opengraph-image' ) );
	$seo['twitter_title']       = codex_blog_value_or_meta( $seo['twitter_title'], $post_id, array( '_aioseo_twitter_title', '_yoast_wpseo_twitter-title' ) );
	$seo['twitter_description'] = codex_blog_value_or_meta( $seo['twitter_description'], $post_id, array( '_aioseo_twitter_description', '_yoast_wpseo_twitter-description' ) );
	$seo['twitter_image_url']   = codex_blog_value_or_meta( $seo['twitter_image_url'], $post_id, array( '_aioseo_twitter_image_url', '_yoast_wpseo_twitter-image' ) );

	if ( null === $seo['robots']['noindex'] ) {
		$seo['robots']['noindex'] = codex_blog_bool_from_mixed( codex_blog_first_post_meta( $post_id, array( '_aioseo_robots_noindex', '_aioseop_noindex', '_yoast_wpseo_meta-robots-noindex' ) ) );
	}
	if ( null === $seo['robots']['nofollow'] ) {
		$seo['robots']['nofollow'] = codex_blog_bool_from_mixed( codex_blog_first_post_meta( $post_id, array( '_aioseo_robots_nofollow', '_aioseop_nofollow', '_yoast_wpseo_meta-robots-nofollow' ) ) );
	}

	return $seo;
}

function codex_blog_get_aioseo_post( $post_id ) {
	if ( ! function_exists( 'aioseo' ) ) {
		return null;
	}

	try {
		$aioseo = aioseo();
		if ( is_object( $aioseo ) && isset( $aioseo->post ) && is_object( $aioseo->post ) && method_exists( $aioseo->post, 'getPost' ) ) {
			return $aioseo->post->getPost( $post_id );
		}
	} catch ( Throwable $e ) {
		return null;
	}

	return null;
}

function codex_blog_object_property( $object, $property ) {
	if ( ! is_object( $object ) ) {
		return null;
	}

	try {
		if ( property_exists( $object, $property ) ) {
			return $object->$property;
		}

		if ( method_exists( $object, '__get' ) ) {
			$value = $object->$property;
			return null === $value ? null : $value;
		}

		return null;
	} catch ( Throwable $e ) {
		return null;
	}
}

function codex_blog_value_or_meta( $value, $post_id, $meta_keys ) {
	if ( null !== $value && '' !== $value && array() !== $value ) {
		return $value;
	}

	return codex_blog_first_post_meta( $post_id, $meta_keys );
}

function codex_blog_first_post_meta( $post_id, $meta_keys ) {
	foreach ( $meta_keys as $meta_key ) {
		$value = get_post_meta( $post_id, $meta_key, true );
		if ( null !== $value && '' !== $value && array() !== $value ) {
			return $value;
		}
	}

	return null;
}

function codex_blog_collect_seo_updates( $args ) {
	$updates     = array();
	$text_fields = array(
		'title',
		'focus_keyphrase',
		'og_title',
		'twitter_title',
	);
	$textarea_fields = array(
		'description',
		'og_description',
		'twitter_description',
	);
	$url_fields = array(
		'canonical_url',
		'og_image_url',
		'twitter_image_url',
	);

	foreach ( $text_fields as $field ) {
		if ( array_key_exists( $field, $args ) ) {
			$updates[ $field ] = sanitize_text_field( $args[ $field ] );
		}
	}

	foreach ( $textarea_fields as $field ) {
		if ( array_key_exists( $field, $args ) ) {
			$updates[ $field ] = sanitize_textarea_field( $args[ $field ] );
		}
	}

	foreach ( $url_fields as $field ) {
		if ( array_key_exists( $field, $args ) ) {
			$updates[ $field ] = esc_url_raw( $args[ $field ] );
		}
	}

	if ( array_key_exists( 'keywords', $args ) ) {
		$keywords = is_array( $args['keywords'] ) ? $args['keywords'] : array( $args['keywords'] );
		$updates['keywords'] = implode( ', ', array_filter( array_map( 'sanitize_text_field', $keywords ) ) );
	}

	if ( ! empty( $args['robots'] ) && is_array( $args['robots'] ) ) {
		$updates['robots'] = array();
		if ( array_key_exists( 'noindex', $args['robots'] ) ) {
			$updates['robots']['noindex'] = (bool) $args['robots']['noindex'];
		}
		if ( array_key_exists( 'nofollow', $args['robots'] ) ) {
			$updates['robots']['nofollow'] = (bool) $args['robots']['nofollow'];
		}
	}

	return $updates;
}

function codex_blog_save_aioseo_meta( $post_id, $updates ) {
	$model = codex_blog_get_aioseo_post( $post_id );
	if ( ! $model ) {
		return false;
	}

	$property_map = array(
		'title'               => 'title',
		'description'         => 'description',
		'canonical_url'       => 'canonical_url',
		'keywords'            => 'keywords',
		'og_title'            => 'og_title',
		'og_description'      => 'og_description',
		'og_image_url'        => 'og_image_url',
		'twitter_title'       => 'twitter_title',
		'twitter_description' => 'twitter_description',
		'twitter_image_url'   => 'twitter_image_url',
	);

	try {
		foreach ( $property_map as $input_key => $property ) {
			if ( array_key_exists( $input_key, $updates ) ) {
				$model->$property = $updates[ $input_key ];
			}
		}

		if ( ! empty( $updates['robots'] ) ) {
			$model->robots_default = false;
			if ( array_key_exists( 'noindex', $updates['robots'] ) ) {
				$model->robots_noindex = (bool) $updates['robots']['noindex'];
			}
			if ( array_key_exists( 'nofollow', $updates['robots'] ) ) {
				$model->robots_nofollow = (bool) $updates['robots']['nofollow'];
			}
		}

		if ( method_exists( $model, 'save' ) ) {
			$model->save();
			return true;
		}
	} catch ( Throwable $e ) {
		return new WP_Error( 'codex_blog_aioseo_save_failed', 'AIOSEO metadata could not be saved: ' . $e->getMessage() );
	}

	return false;
}

function codex_blog_update_fallback_seo_meta( $post_id, $updates ) {
	$meta_map = array(
		'title'               => array( '_aioseo_title', '_aioseop_title', '_yoast_wpseo_title' ),
		'description'         => array( '_aioseo_description', '_aioseop_description', '_yoast_wpseo_metadesc' ),
		'canonical_url'       => array( '_aioseo_canonical_url', '_aioseop_custom_link', '_yoast_wpseo_canonical' ),
		'keywords'            => array( '_aioseo_keywords', '_aioseop_keywords', '_yoast_wpseo_metakeywords' ),
		'focus_keyphrase'     => array( '_aioseo_focus_keyphrase', '_yoast_wpseo_focuskw' ),
		'og_title'            => array( '_aioseo_og_title', '_yoast_wpseo_opengraph-title' ),
		'og_description'      => array( '_aioseo_og_description', '_yoast_wpseo_opengraph-description' ),
		'og_image_url'        => array( '_aioseo_og_image_url', '_yoast_wpseo_opengraph-image' ),
		'twitter_title'       => array( '_aioseo_twitter_title', '_yoast_wpseo_twitter-title' ),
		'twitter_description' => array( '_aioseo_twitter_description', '_yoast_wpseo_twitter-description' ),
		'twitter_image_url'   => array( '_aioseo_twitter_image_url', '_yoast_wpseo_twitter-image' ),
	);

	foreach ( $meta_map as $input_key => $meta_keys ) {
		if ( array_key_exists( $input_key, $updates ) ) {
			codex_blog_update_meta_aliases( $post_id, $meta_keys, $updates[ $input_key ] );
		}
	}

	if ( ! empty( $updates['robots'] ) ) {
		if ( array_key_exists( 'noindex', $updates['robots'] ) ) {
			codex_blog_update_meta_aliases( $post_id, array( '_aioseo_robots_noindex', '_aioseop_noindex', '_yoast_wpseo_meta-robots-noindex' ), $updates['robots']['noindex'] ? '1' : '0' );
		}
		if ( array_key_exists( 'nofollow', $updates['robots'] ) ) {
			codex_blog_update_meta_aliases( $post_id, array( '_aioseo_robots_nofollow', '_aioseop_nofollow', '_yoast_wpseo_meta-robots-nofollow' ), $updates['robots']['nofollow'] ? '1' : '0' );
		}
	}
}

function codex_blog_update_meta_aliases( $post_id, $meta_keys, $value ) {
	update_post_meta( $post_id, $meta_keys[0], $value );

	foreach ( array_slice( $meta_keys, 1 ) as $meta_key ) {
		if ( '' !== get_post_meta( $post_id, $meta_key, true ) ) {
			update_post_meta( $post_id, $meta_key, $value );
		}
	}
}

function codex_blog_bool_from_mixed( $value ) {
	if ( null === $value || '' === $value ) {
		return null;
	}
	if ( is_bool( $value ) ) {
		return $value;
	}
	if ( is_numeric( $value ) ) {
		return (bool) (int) $value;
	}

	$value = strtolower( sanitize_text_field( (string) $value ) );
	if ( in_array( $value, array( '1', 'yes', 'true', 'on' ), true ) ) {
		return true;
	}
	if ( in_array( $value, array( '0', 'no', 'false', 'off' ), true ) ) {
		return false;
	}

	return null;
}

function codex_blog_count_links( $html ) {
	$result = array(
		'internal' => 0,
		'external' => 0,
	);

	if ( ! preg_match_all( '/<a\s[^>]*href=[\"\']([^\"\']+)[\"\']/i', $html, $matches ) ) {
		return $result;
	}

	$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
	foreach ( $matches[1] as $href ) {
		$href = trim( html_entity_decode( $href ) );
		if ( '' === $href || '#' === $href[0] || 0 === strpos( $href, 'mailto:' ) || 0 === strpos( $href, 'tel:' ) ) {
			continue;
		}

		$host = wp_parse_url( $href, PHP_URL_HOST );
		if ( ! $host || $host === $home_host ) {
			$result['internal']++;
		} else {
			$result['external']++;
		}
	}

	return $result;
}

function codex_blog_word_count( $text ) {
	$text = trim( wp_strip_all_tags( $text ) );
	if ( '' === $text ) {
		return 0;
	}

	$words = preg_split( '/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
	return $words ? count( $words ) : 0;
}

function codex_blog_list_terms( $input ) {
	$args     = codex_blog_input( $input );
	$taxonomy = sanitize_key( $args['taxonomy'] );
	if ( ! taxonomy_exists( $taxonomy ) ) {
		return new WP_Error( 'codex_blog_invalid_taxonomy', 'Invalid taxonomy.' );
	}

	$page     = codex_blog_limit( $args['page'] ?? 1, 1, 1, 100000 );
	$per_page = codex_blog_limit( $args['per_page'] ?? 50, 50, 1, 100 );
	$query    = array(
		'taxonomy'   => $taxonomy,
		'hide_empty' => ! empty( $args['hide_empty'] ),
		'number'     => $per_page,
		'offset'     => ( $page - 1 ) * $per_page,
	);

	if ( ! empty( $args['search'] ) ) {
		$query['search'] = sanitize_text_field( $args['search'] );
	}

	$terms = get_terms( $query );
	if ( is_wp_error( $terms ) ) {
		return $terms;
	}

	return array(
		'terms' => array_map(
			static function( WP_Term $term ) {
				return array(
					'id'          => (int) $term->term_id,
					'taxonomy'    => $term->taxonomy,
					'name'        => $term->name,
					'slug'        => $term->slug,
					'description' => $term->description,
					'parent_id'   => (int) $term->parent,
					'count'       => (int) $term->count,
				);
			},
			$terms
		),
	);
}

function codex_blog_can_manage_terms( $input ) {
	$args     = codex_blog_input( $input );
	$taxonomy = isset( $args['taxonomy'] ) ? sanitize_key( $args['taxonomy'] ) : '';
	$tax_obj  = $taxonomy ? get_taxonomy( $taxonomy ) : null;
	return $tax_obj && current_user_can( $tax_obj->cap->manage_terms );
}

function codex_blog_create_term( $input ) {
	$args     = codex_blog_input( $input );
	$taxonomy = sanitize_key( $args['taxonomy'] );
	$termargs = array();

	foreach ( array( 'slug', 'description' ) as $field ) {
		if ( isset( $args[ $field ] ) ) {
			$termargs[ $field ] = 'slug' === $field ? sanitize_title( $args[ $field ] ) : sanitize_text_field( $args[ $field ] );
		}
	}

	if ( isset( $args['parent_id'] ) ) {
		$termargs['parent'] = (int) $args['parent_id'];
	}

	$result = wp_insert_term( sanitize_text_field( $args['name'] ), $taxonomy, $termargs );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return array(
		'success' => true,
		'term_id' => (int) $result['term_id'],
	);
}

function codex_blog_update_term( $input ) {
	$args     = codex_blog_input( $input );
	$taxonomy = sanitize_key( $args['taxonomy'] );
	$termargs = array();

	foreach ( array( 'name', 'slug', 'description' ) as $field ) {
		if ( isset( $args[ $field ] ) ) {
			$termargs[ $field ] = 'slug' === $field ? sanitize_title( $args[ $field ] ) : sanitize_text_field( $args[ $field ] );
		}
	}

	if ( isset( $args['parent_id'] ) ) {
		$termargs['parent'] = (int) $args['parent_id'];
	}

	$result = wp_update_term( (int) $args['term_id'], $taxonomy, $termargs );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return array(
		'success' => true,
		'term_id' => (int) $result['term_id'],
	);
}

function codex_blog_delete_term( $input ) {
	$args   = codex_blog_input( $input );
	$result = wp_delete_term( (int) $args['term_id'], sanitize_key( $args['taxonomy'] ) );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return array(
		'success' => (bool) $result,
		'deleted' => (bool) $result,
	);
}

function codex_blog_list_comments( $input ) {
	$args     = codex_blog_input( $input );
	$page     = codex_blog_limit( $args['page'] ?? 1, 1, 1, 100000 );
	$per_page = codex_blog_limit( $args['per_page'] ?? 20, 20, 1, 100 );

	$query = array(
		'status' => isset( $args['status'] ) ? sanitize_key( $args['status'] ) : 'all',
		'number' => $per_page,
		'offset' => ( $page - 1 ) * $per_page,
	);

	if ( ! empty( $args['post_id'] ) ) {
		$query['post_id'] = (int) $args['post_id'];
	}

	$comments = get_comments( $query );

	return array(
		'comments' => array_map(
			static function( WP_Comment $comment ) {
				return array(
					'id'         => (int) $comment->comment_ID,
					'post_id'    => (int) $comment->comment_post_ID,
					'author'     => $comment->comment_author,
					'author_url' => $comment->comment_author_url,
					'date'       => mysql2date( DATE_ATOM, $comment->comment_date_gmt, false ),
					'status'     => wp_get_comment_status( $comment ),
					'content'    => $comment->comment_content,
				);
			},
			$comments
		),
	);
}

function codex_blog_update_comment( $input ) {
	$args = codex_blog_input( $input );
	$id   = (int) $args['id'];

	if ( isset( $args['status'] ) ) {
		wp_set_comment_status( $id, sanitize_key( $args['status'] ) );
	}

	if ( isset( $args['content'] ) ) {
		$result = wp_update_comment(
			array(
				'comment_ID'      => $id,
				'comment_content' => wp_kses_post( $args['content'] ),
			)
		);

		if ( false === $result ) {
			return new WP_Error( 'codex_blog_comment_update_failed', 'Comment update failed.' );
		}
	}

	return array(
		'success' => true,
		'comment' => get_comment( $id, ARRAY_A ),
	);
}

function codex_blog_delete_comment( $input ) {
	$args   = codex_blog_input( $input );
	$result = wp_delete_comment( (int) $args['id'], ! empty( $args['force'] ) );

	return array(
		'success' => (bool) $result,
		'deleted' => (bool) $result,
	);
}

function codex_blog_list_media( $input ) {
	$args     = codex_blog_input( $input );
	$page     = codex_blog_limit( $args['page'] ?? 1, 1, 1, 100000 );
	$per_page = codex_blog_limit( $args['per_page'] ?? 20, 20, 1, 100 );

	$query_args = array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'paged'          => $page,
		'posts_per_page' => $per_page,
	);

	if ( ! empty( $args['search'] ) ) {
		$query_args['s'] = sanitize_text_field( $args['search'] );
	}

	if ( ! empty( $args['mime_type'] ) ) {
		$query_args['post_mime_type'] = sanitize_mime_type( $args['mime_type'] );
	}

	$query = new WP_Query( $query_args );

	return array(
		'total'       => (int) $query->found_posts,
		'total_pages' => (int) $query->max_num_pages,
		'media'       => array_map(
			static function( WP_Post $post ) {
				$attachment         = codex_blog_attachment_to_array( $post->ID );
				$attachment['date'] = get_post_time( DATE_ATOM, false, $post );
				return $attachment;
			},
			$query->posts
		),
	);
}

function codex_blog_list_plugins() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plugins = get_plugins();
	$result  = array();

	foreach ( $plugins as $plugin_file => $data ) {
		$result[] = array(
			'plugin_file' => $plugin_file,
			'name'        => $data['Name'] ?? $plugin_file,
			'version'     => $data['Version'] ?? '',
			'description' => wp_strip_all_tags( $data['Description'] ?? '' ),
			'active'      => is_plugin_active( $plugin_file ),
		);
	}

	return array( 'plugins' => $result );
}

function codex_blog_activate_plugin( $input ) {
	$args        = codex_blog_input( $input );
	$plugin_file = plugin_basename( sanitize_text_field( $args['plugin_file'] ) );

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( ! array_key_exists( $plugin_file, get_plugins() ) ) {
		return new WP_Error( 'codex_blog_plugin_not_found', 'Plugin not found.' );
	}

	$result = activate_plugin( $plugin_file );
	if ( is_wp_error( $result ) ) {
		return $result;
	}

	return array(
		'success'     => true,
		'plugin_file' => $plugin_file,
		'active'      => is_plugin_active( $plugin_file ),
	);
}

function codex_blog_deactivate_plugin( $input ) {
	$args        = codex_blog_input( $input );
	$plugin_file = plugin_basename( sanitize_text_field( $args['plugin_file'] ) );
	$critical    = array(
		CODEX_BLOG_ABILITIES_PLUGIN_FILE,
		'mcp-adapter/mcp-adapter.php',
	);

	if ( ! function_exists( 'deactivate_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( in_array( $plugin_file, $critical, true ) && empty( $args['allow_critical'] ) ) {
		return new WP_Error( 'codex_blog_critical_plugin', 'Refusing to deactivate an MCP-critical plugin unless allow_critical is true.' );
	}

	deactivate_plugins( $plugin_file );

	return array(
		'success'     => true,
		'plugin_file' => $plugin_file,
		'active'      => is_plugin_active( $plugin_file ),
	);
}

function codex_blog_option_allowlist() {
	return array(
		'blogname',
		'blogdescription',
		'admin_email',
		'start_of_week',
		'timezone_string',
		'date_format',
		'time_format',
		'posts_per_page',
		'default_category',
		'default_post_format',
		'default_comment_status',
		'default_ping_status',
		'comment_moderation',
		'comment_previously_approved',
		'permalink_structure',
	);
}

function codex_blog_get_options( $input ) {
	$args      = codex_blog_input( $input );
	$allowlist = codex_blog_option_allowlist();
	$names     = ! empty( $args['names'] ) && is_array( $args['names'] ) ? $args['names'] : $allowlist;
	$result    = array();

	foreach ( $names as $name ) {
		$name = sanitize_key( $name );
		if ( ! in_array( $name, $allowlist, true ) ) {
			continue;
		}
		$result[ $name ] = get_option( $name );
	}

	return array(
		'options'   => $result,
		'allowlist' => $allowlist,
	);
}

function codex_blog_update_options( $input ) {
	$args      = codex_blog_input( $input );
	$updates   = isset( $args['updates'] ) && is_array( $args['updates'] ) ? $args['updates'] : array();
	$allowlist = codex_blog_option_allowlist();
	$result    = array();

	foreach ( $updates as $name => $value ) {
		$name = sanitize_key( $name );
		if ( ! in_array( $name, $allowlist, true ) ) {
			$result[ $name ] = array(
				'updated' => false,
				'error'   => 'Option is not in the allowlist.',
			);
			continue;
		}

		$old_value = get_option( $name );
		$new_value = is_string( $value ) ? sanitize_text_field( $value ) : $value;
		$updated   = update_option( $name, $new_value );

		if ( 'permalink_structure' === $name ) {
			flush_rewrite_rules();
		}

		$result[ $name ] = array(
			'updated'       => (bool) $updated,
			'previous_value' => $old_value,
			'new_value'      => get_option( $name ),
		);
	}

	return array( 'options' => $result );
}
