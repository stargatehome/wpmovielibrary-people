<?php
/**
 * WPMovieLibrary Edit People Class extension.
 *
 * @package   WPMovieLibrary-People
 * @author    Charlie MERLAND <charlie@caercam.org>
 * @license   GPL-3.0
 * @link      http://www.caercam.org/
 * @copyright 2014 CaerCam.org
 */

if ( ! class_exists( 'WPMOLY_Edit_People' ) ) :

	class WPMOLY_Edit_People extends WPMovieLibrary_People_Admin {

		/**
		 * People Metadata
		 *
		 * @since    2.1.4
		 * @var      array
		 */
		protected $metadata = array();

		/**
		 * Constructor
		 *
		 * @since    1.0
		 */
		public function __construct() {

			if ( ! is_admin() )
				return false;

			$this->init();

			$this->register_hook_callbacks();
		}

		/**
		 * Initializes variables
		 *
		 * @since    1.0
		 */
		public function init() {

			global $wpmolyp;

			$this->metadata = $wpmolyp->metadata;
			$this->api = new WPMOLYP_TMDb();
		}

		/**
		 * Register callbacks for actions and filters
		 * 
		 * @since    1.0
		 */
		public function register_hook_callbacks() {

			add_action( 'admin_enqueue_scripts', array( $this, 'pre_admin_enqueue_scripts' ), 9 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 15 );

			// Bulk/quick edit
			add_filter( 'bulk_post_updated_messages', array( $this, 'people_bulk_updated_messages' ), 10, 2 );

			// Metabox
			add_filter( 'wpmoly_filter_metaboxes', array( $this, 'add_meta_box' ), 10 );

			// Post edit
			add_filter( 'post_updated_messages', array( $this, 'people_updated_messages' ), 10, 1 );
			add_action( 'save_post_people', array( $this, 'save_person' ), 10, 3 );
		}

		/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
		 *
		 *                        Scripts & Styles
		 * 
		 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

		/**
		 * Enqueue required media scripts and styles
		 * 
		 * @since    2.0
		 * 
		 * @param    string    $hook_suffix The current admin page.
		 */
		public function pre_admin_enqueue_scripts( $hook_suffix ) {

			if ( ( 'post.php' != $hook_suffix && 'post-new.php' != $hook_suffix ) || 'people' != get_post_type() )
				return false;

			wp_enqueue_media();
			wp_enqueue_script( 'media' );

			wp_register_script( 'select2-sortable-js', ReduxFramework::$_url . 'assets/js/vendor/select2.sortable.min.js', array( 'jquery' ), WPMOLY_VERSION, true );
			wp_register_script( 'select2-js', ReduxFramework::$_url . 'assets/js/vendor/select2/select2.min.js', array( 'jquery', 'select2-sortable-js' ), WPMOLY_VERSION, true );
			wp_enqueue_script( 'field-select-js', ReduxFramework::$_url . 'inc/fields/select/field_select.min.js', array( 'jquery', 'select2-js' ), WPMOLY_VERSION, true );
			wp_enqueue_style( 'select2-css', ReduxFramework::$_url . 'assets/js/vendor/select2/select2.css', array(), WPMOLY_VERSION, 'all' );
			wp_enqueue_style( 'redux-field-select-css', ReduxFramework::$_url . 'inc/fields/select/field_select.css', WPMOLY_VERSION, true );
		}

		/**
		 * Enqueue required media scripts and styles
		 * 
		 * @since    2.0
		 * 
		 * @param    string    $hook_suffix The current admin page.
		 */
		public function admin_enqueue_scripts( $hook_suffix ) {

			if ( ( 'post.php' != $hook_suffix && 'post-new.php' != $hook_suffix ) || 'people' != get_post_type() )
				return false;

			wp_enqueue_script( WPMOLYP_SLUG . '-people-editor-models-js', WPMOLYP_URL . '/assets/js/admin/wpmoly-people-editor-models.js', array( 'jquery' ), WPMOLYP_VERSION, true );
			wp_enqueue_script( WPMOLYP_SLUG . '-people-editor-views-js', WPMOLYP_URL . '/assets/js/admin/wpmoly-people-editor-views.js', array( 'jquery' ), WPMOLYP_VERSION, true );
		}

		/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
		 *
		 *                          Callbacks
		 * 
		 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

		

		/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
		 *
		 *                       Updated Messages
		 * 
		 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

		/**
		 * Add message support for movies in Post Editor.
		 * 
		 * @since    2.1.4
		 * 
		 * @param    array    $messages Default Post update messages
		 * 
		 * @return   array    Updated Post update messages
		 */
		public function people_updated_messages( $messages ) {

			global $post;
			$post_ID = $post->ID;

			$new_messages = array(
				'people' => array(
					1  => sprintf( __( 'People updated. <a href="%s">View people</a>', 'wpmovielibrary-people' ), esc_url( get_permalink( $post_ID ) ) ),
					2  => __( 'Custom field updated.', 'wpmovielibrary-people' ) ,
					3  => __( 'Custom field deleted.', 'wpmovielibrary-people' ),
					4  => __( 'People updated.', 'wpmovielibrary-people' ),
					5  => isset( $_GET['revision'] ) ? sprintf( __( 'People restored to revision from %s', 'wpmovielibrary-people' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
					6  => sprintf( __( 'People published. <a href="%s">View people</a>', 'wpmovielibrary-people' ), esc_url( get_permalink( $post_ID ) ) ),
					7  => __( 'People saved.' ),
					8  => sprintf( __( 'People submitted. <a target="_blank" href="%s">Preview people</a>', 'wpmovielibrary-people' ), esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) ),
					9  => sprintf( __( 'People scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview people</a>', 'wpmovielibrary-people' ), date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink( $post_ID ) ) ),
					10 => sprintf( __( 'People draft updated. <a target="_blank" href="%s">Preview people</a>', 'wpmovielibrary-people' ), esc_url( add_query_arg( 'preview', 'true', get_permalink( $post_ID ) ) ) ),
					11 => __( 'Successfully converted to people.', 'wpmovielibrary-people' )
				)
			);

			$messages = array_merge( $messages, $new_messages );

			return $messages;
		}

		/**
		 * Add message support for people in Post Editor bulk edit.
		 * 
		 * @since    2.1.4
		 * 
		 * @param    array    $messages Default Post bulk edit messages
		 * 
		 * @return   array    Updated Post bulk edit messages
		 */
		public function people_bulk_updated_messages( $bulk_messages, $bulk_counts ) {

			$new_messages = array(
				'people' => array(
					'updated'   => _n( '%s people updated.', '%s people updated.', $bulk_counts['updated'], 'wpmovielibrary-people' ),
					'locked'    => _n( '%s people not updated, somebody is editing it.', '%s people not updated, somebody is editing them.', $bulk_counts['locked'], 'wpmovielibrary-people' ),
					'deleted'   => _n( '%s people permanently deleted.', '%s people permanently deleted.', $bulk_counts['deleted'], 'wpmovielibrary-people' ),
					'trashed'   => _n( '%s people moved to the Trash.', '%s people moved to the Trash.', $bulk_counts['trashed'], 'wpmovielibrary-people' ),
					'untrashed' => _n( '%s people restored from the Trash.', '%s people restored from the Trash.', $bulk_counts['untrashed'], 'wpmovielibrary-people' ),
				)
			);

			$messages = array_merge( $bulk_messages, $new_messages );

			return $messages;
		}

		/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
		 *
		 *                     "All Movies" WP List Table
		 * 
		 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

		


		/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
		 *
		 *                             Metabox
		 * 
		 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

		/**
		 * Register Metabox to WPMovieLibrary
		 * 
		 * @since    1.0
		 * 
		 * @param    object    $post Current Post object
		 * @param    array     $args Metabox parameters
		 */
		public function add_meta_box( $metaboxes ) {

			$new_metaboxes = array(
				'people' => array(
					'wpmoly-people' => array(
						'title'         => __( 'WordPress Movie Library', 'wpmovielibrary' ),
						'callback'      => 'WPMOLY_Edit_People::metabox',
						'screen'        => 'people',
						'context'       => 'normal',
						'priority'      => 'high',
						'callback_args' => array(
							'panels' => array(
								'preview' => array(
									'title'    => __( 'Preview', 'wpmovielibrary' ),
									'icon'     => 'wpmolicon icon-actor-alt',
									'callback' => 'WPMOLY_Edit_People::render_preview_panel'
								),

								'meta' => array(
									'title'    => __( 'Metadata', 'wpmovielibrary' ),
									'icon'     => 'wpmolicon icon-meta',
									'callback' => 'WPMOLY_Edit_People::render_meta_panel'
								),

								'filmography' => array(
									'title'    => __( 'Filmography', 'wpmovielibrary' ),
									'icon'     => 'wpmolicon icon-list',
									'callback' => 'WPMOLY_Edit_People::render_filmography_panel'
								),

								'images' => array(
									'title'    => __( 'Portrait', 'wpmovielibrary' ),
									'icon'     => 'wpmolicon icon-hat',
									'callback' => 'WPMOLY_Edit_People::render_images_panel'
								),

								'photos' => array(
									'title'    => __( 'Images', 'wpmovielibrary' ),
									'icon'     => 'wpmolicon icon-images-alt-2',
									'callback' => 'WPMOLY_Edit_People::render_images_panel'
								)
							)
						)
					)
				)
			);

			$metaboxes = array_merge( $metaboxes, $new_metaboxes );

			return $metaboxes;
		}

		/**
		 * Movie Metabox content callback.
		 * 
		 * @since    1.0
		 * 
		 * @param    object    $post Current Post object
		 * @param    array     $args Metabox parameters
		 */
		public static function metabox( $post, $args = array() ) {

			$defaults = array(
				'panels' => array()
			);
			$args = wp_parse_args( $args['args'], $defaults );

			$tabs   = array();
			$panels = array();

			foreach ( $args['panels'] as $id => $panel ) {

				if ( ! is_callable( $panel['callback'] ) )
					continue;

				$is_active = ( ( 'preview' == $id && ! $empty ) || ( 'meta' == $id && $empty ) );
				//$is_active = ( 'meta' == $id );
				$tabs[ $id ] = array(
					'title'  => $panel['title'],
					'icon'   => $panel['icon'],
					'active' => $is_active ? ' active' : ''
				);
				$panels[ $id ] = array( 
					'active'  => $is_active ? ' active' : '',
					'content' => call_user_func_array( $panel['callback'], array( $post->ID ) )
				);
			}

			$attributes = array(
				'tabs'   => $tabs,
				'panels' => $panels
			);

			echo self::render_admin_template( 'metabox/metabox.php', $attributes );
		}

		/**
		 * Movie Metabox Preview Panel.
		 * 
		 * Display a Metabox panel to preview metadata.
		 * 
		 * @since    2.0
		 * 
		 * @param    int    Current Post ID
		 * 
		 * @return   string    Panel HTML Markup
		 */
		private static function render_preview_panel( $post_id ) {

			// TODO: filter default thumbnail
			$thumbnail = get_the_post_thumbnail( $post_id, 'medium' );
			if ( '' == $thumbnail )
				$thumbnail = '<img src="https://image.tmdb.org/t/p/w185/jdRmHrG0TWXGhs4tO6TJNSoL25T.jpg" alt="" />';
				//$thumbnail = '<img src="' . WPMOLYP_URL . '/assets/img/no-profile.jpg" alt="" />';

			$preview = array(
				'name'       => 'Matthew McConaughey',
				'age'        => '45',
				'jobs'       => 'Actor, Producer',
				'birthplace' => 'Uvalde - Texas - USA',
				'biography'  => 'Matthew David McConaughey (born November 4, 1969) is an American actor. After a series of minor roles in the early 1990s, McConaughey gained notice for his breakout role in Dazed and Confused (1993). It was in this role that he first conceived the idea of his catch-phrase "Well alright, alright." He then appeared in films such as A Time to Kill, Contact, U-571, Tiptoes, Sahara, and We Are Marshall. McConaughey is best known more recently for his performances as a leading man in the romantic comedies The Wedding Planner, How to Lose a Guy in 10 Days, Failure to Launch, Ghosts of Girlfriends Past and Fool\'s Gold.<br />Description above from the Wikipedia article Matthew McConaughey, licensed under CC-BY-SA, full list of contributors on Wikipedia.'
			);

			$attributes = compact( 'thumbnail', 'preview' );
			
			$panel = self::render_admin_template( 'metabox/panels/panel-preview.php', $attributes );

			return $panel;
		}

		/**
		 * Movie Metabox Meta Panel.
		 * 
		 * Display a Metabox panel to download movie metadata.
		 * 
		 * @since    2.0
		 * 
		 * @param    int    Current Post ID
		 * 
		 * @return   string    Panel HTML Markup
		 */
		private static function render_meta_panel( $post_id ) {

			global $wpmolyp;

			$metas     = $wpmolyp->metadata;
			$languages = WPMOLY_Settings::get_supported_languages();
			$metadata  = wpmoly_get_movie_meta( $post_id );
			$metadata  = wpmoly_filter_empty_array( $metadata );

			$attributes = array(
				'languages' => $languages,
				'metas'     => $metas,
				'metadata'  => $metadata
			);

			$panel = self::render_admin_template( 'metabox/panels/panel-meta.php', $attributes );

			return $panel;
		}

		/**
		 * People Metabox Filmography Panel.
		 * 
		 * @since    1.0
		 * 
		 * @param    int    Current Post ID
		 * 
		 * @return   string    Panel HTML Markup
		 */
		private static function render_filmography_panel( $post_id ) {

			$attributes = array();

			$panel = self::render_admin_template( 'metabox/panels/panel-filmography.php', $attributes );

			return $panel;
		}

		/**
		 * Movie Images Metabox Panel.
		 * 
		 * Display a Metabox panel to download movie images.
		 * 
		 * @since    2.0
		 * 
		 * @param    int    Current Post ID
		 * 
		 * @return   string    Panel HTML Markup
		 */
		private static function render_images_panel( $post_id ) {

			/*$attributes = array(
				'nonce'   => wpmoly_nonce_field( 'upload-movie-image', $referer = false ),
				'images'  => WPMOLY_Media::get_movie_imported_images(),
				'version' => ( version_compare( $wp_version, '4.0', '>=' ) ? 4 : 0 )
			);

			$panel = self::render_admin_template( 'metabox/panels/panel-images.php', $attributes  );*/

			return $panel = '';
		}

		/**
		 * Movie Posters Metabox Panel.
		 * 
		 * Display a Metabox panel to download movie posters.
		 * 
		 * @since    2.0
		 * 
		 * @param    int    Current Post ID
		 * 
		 * @return   string    Panel HTML Markup
		 */
		private static function render_posters_panel( $post_id ) {

			/*global $wp_version;

			$attributes = array(
				'posters' => WPMOLY_Media::get_movie_imported_posters(),
				'version' => ( version_compare( $wp_version, '4.0', '>=' ) ? 4 : 0 )
			);

			$panel = self::render_admin_template( 'metabox/panels/panel-posters.php', $attributes  );*/

			return $panel = '';
		}


		/** * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
		 *
		 *                             Save data
		 * 
		 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

		/**
		 * Save movie metadata.
		 * 
		 * @since    1.3
		 * 
		 * @param    int      $post_id ID of the current Post
		 * @param    array    $details Movie details: media, status, rating
		 * 
		 * @return   int|object    WP_Error object is anything went wrong, true else
		 */
		public static function save_movie_meta( $post_id, $movie_meta, $clean = true ) {

			$post = get_post( $post_id );
			if ( ! $post || 'movie' != get_post_type( $post ) )
				return new WP_Error( 'invalid_post', __( 'Error: submitted post is not a movie.', 'wpmovielibrary-people' ) );

			$movie_meta = self::validate_meta( $movie_meta );
			unset( $movie_meta['post_id'] );

			foreach ( $movie_meta as $slug => $meta )
				$update = update_post_meta( $post_id, "_wpmoly_movie_{$slug}", $meta );

			if ( false !== $clean )
				WPMOLY_Cache::clean_transient( 'clean', $force = true );

			return $post_id;
		}

		/**
		 * Filter the Movie Metadata submitted when saving a post to
		 * avoid storing unexpected data to the database.
		 * 
		 * The Metabox array makes a distinction between pure metadata
		 * and crew data, so we filter them separately. If the data slug
		 * is valid, the value is escaped and added to the return array.
		 * 
		 * @since    1.0
		 * 
		 * @param    array    $data The Movie Metadata to filter
		 * 
		 * @return   array    The filtered Metadata
		 */
		private static function validate_meta( $data ) {

			

			return $data;
		}

		/**
		 * Remove person meta.
		 * 
		 * @since    1.2
		 * 
		 * @param    int      $post_id ID of the current Post
		 * 
		 * @return   boolean  Always return true
		 */
		public static function empty_person_meta( $post_id ) {

			delete_post_meta( $post_id, '_wpmoly_person_data' );

			return true;
		}

		/**
		 * Save TMDb fetched data.
		 *
		 * @since    1.0
		 * 
		 * @param    int        $post_ID ID of the current Post
		 * @param    object     $post Post Object of the current Post
		 * @param    boolean    $queue Queued movie?
		 * @param    array      $movie_meta Movie Metadata to save with the post
		 * 
		 * @return   int|WP_Error
		 */
		public function save_person( $post_ID, $post, $meta = null ) {

			if ( ! current_user_can( 'edit_post', $post_ID ) )
				return new WP_Error( __( 'You are not allowed to edit posts.', 'wpmovielibrary-people' ) );

			if ( ! $post = get_post( $post_ID ) || 'people' != get_post_type( $post ) )
				return new WP_Error( sprintf( __( 'Posts with #%s is invalid or is not a person.', 'wpmovielibrary-people' ), $post_ID ) );

			if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
				return $post_ID;

			//print_r( $_POST ); die();

			return $post_ID;
		}

		/**
		 * Prepares sites to use the plugin during single or network-wide activation
		 *
		 * @since    1.0
		 *
		 * @param    bool    $network_wide
		 */
		public function activate( $network_wide ) {}

		/**
		 * Rolls back activation procedures when de-activating the plugin
		 *
		 * @since    1.0
		 */
		public function deactivate() {}
		
	}
	
endif;