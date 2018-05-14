<?php
/**
 * Plugin Name: myCRED for WP Postratings
 * Plugin URI: http://mycred.me
 * Description: Allows you to reward users points for rating posts or receiving post ratings.
 * Version: 1.0.2
 * Tags: mycred, points, rate, post
 * Author: Gabriel S Merovingi
 * Author URI: http://www.merovingi.com
 * Author Email: support@mycred.me
 * Requires at least: WP 4.0
 * Tested up to: WP 4.7.4
 * Text Domain: mycred_wp_postrate
 * Domain Path: /lang
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
if ( ! class_exists( 'myCRED_WP_Postratings' ) ) :
	final class myCRED_WP_Postratings {

		// Plugin Version
		public $version             = '1.0.2';

		// Instnace
		protected static $_instance = NULL;

		// Current session
		public $session             = NULL;

		public $slug                = '';
		public $domain              = '';
		public $plugin              = NULL;
		public $plugin_name         = '';
		protected $update_url       = 'http://mycred.me/api/plugins/';

		/**
		 * Setup Instance
		 * @since 1.0
		 * @version 1.0
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Not allowed
		 * @since 1.0
		 * @version 1.0
		 */
		public function __clone() { _doing_it_wrong( __FUNCTION__, 'Cheatin&#8217; huh?', '1.0' ); }

		/**
		 * Not allowed
		 * @since 1.0
		 * @version 1.0
		 */
		public function __wakeup() { _doing_it_wrong( __FUNCTION__, 'Cheatin&#8217; huh?', '1.0' ); }

		/**
		 * Define
		 * @since 1.0
		 * @version 1.0
		 */
		private function define( $name, $value, $definable = true ) {
			if ( ! defined( $name ) )
				define( $name, $value );
		}

		/**
		 * Require File
		 * @since 1.0
		 * @version 1.0
		 */
		public function file( $required_file ) {
			if ( file_exists( $required_file ) )
				require_once $required_file;
		}

		/**
		 * Construct
		 * @since 1.0
		 * @version 1.0
		 */
		public function __construct() {

			$this->slug        = 'mycred-wp-postratings';
			$this->plugin      = plugin_basename( __FILE__ );
			$this->domain      = 'mycred_wp_postrate';
			$this->plugin_name = 'myCRED for WP-PostRatings';

			$this->define_constants();
			$this->plugin_updates();

			add_filter( 'mycred_setup_hooks',    array( $this, 'register_hook' ) );
			add_action( 'mycred_init',           array( $this, 'load_textdomain' ) );
			add_action( 'mycred_all_references', array( $this, 'add_badge_support' ) );
			add_action( 'mycred_load_hooks',    'mycred_load_wp_postrating_hook' );

		}

		/**
		 * Define Constants
		 * @since 1.0
		 * @version 1.0
		 */
		public function define_constants() {

			$this->define( 'MYCRED_WP_POSTRATE_SLUG', $this->slug );
			$this->define( 'MYCRED_DEFAULT_TYPE_KEY', 'mycred_default' );

		}

		/**
		 * Includes
		 * @since 1.0
		 * @version 1.0
		 */
		public function includes() { }

		/**
		 * Load Textdomain
		 * @since 1.0
		 * @version 1.0
		 */
		public function load_textdomain() {

			// Load Translation
			$locale = apply_filters( 'plugin_locale', get_locale(), $this->domain );

			load_textdomain( $this->domain, WP_LANG_DIR . '/' . $this->slug . '/' . $this->domain . '-' . $locale . '.mo' );
			load_plugin_textdomain( $this->domain, false, dirname( $this->plugin ) . '/lang/' );

		}

		/**
		 * Plugin Updates
		 * @since 1.0
		 * @version 1.0
		 */
		public function plugin_updates() {

			add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'check_for_plugin_update' ), 340 );
			add_filter( 'plugins_api',                           array( $this, 'plugin_api_call' ), 340, 3 );
			add_filter( 'plugin_row_meta',                       array( $this, 'plugin_view_info' ), 340, 3 );

		}

		/**
		 * Register Hook
		 * @since 1.0
		 * @version 1.0
		 */
		public function register_hook( $installed ) {

			if ( ! function_exists( 'postratings_init' ) ) return $installed;

			$installed['wppostratings'] = array(
				'title'       => __( 'WP-PostRatings', $this->domain ),
				'description' => __( 'Allows you to reward users points for rating posts or receiving post ratings', $this->domain ),
				'callback'    => array( 'myCRED_Hook_WPPostratings' )
			);

			return $installed;

		}

		/**
		 * Add Badge Support
		 * @since 1.0
		 * @version 1.0.1
		 */
		public function add_badge_support( $references ) {

			if ( ! function_exists( 'postratings_init' ) ) return $references;

			$references['rated_post']      = __( 'Rating Content (WP-PostRatings)', $this->domain );
			$references['received_rating'] = __( 'Receiving A Rating (WP-PostRatings)', $this->domain );

			return $references;

		}

		/**
		 * Plugin Update Check
		 * @since 1.0
		 * @version 1.0
		 */
		public function check_for_plugin_update( $checked_data ) {

			global $wp_version;

			if ( empty( $checked_data->checked ) )
				return $checked_data;

			$args = array(
				'slug'    => $this->slug,
				'version' => $this->version,
				'site'    => site_url()
			);
			$request_string = array(
				'body'       => array(
					'action'     => 'version', 
					'request'    => serialize( $args ),
					'api-key'    => md5( get_bloginfo( 'url' ) )
				),
				'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' )
			);

			// Start checking for an update
			$response = wp_remote_post( $this->update_url, $request_string );

			if ( ! is_wp_error( $response ) ) {

				$result = maybe_unserialize( $response['body'] );

				if ( is_object( $result ) && ! empty( $result ) )
					$checked_data->response[ $this->slug . '/' . $this->slug . '.php' ] = $result;

			}

			return $checked_data;

		}

		/**
		 * Plugin View Info
		 * @since 1.0
		 * @version 1.0
		 */
		public function plugin_view_info( $plugin_meta, $file, $plugin_data ) {

			if ( $file != $this->plugin ) return $plugin_meta;

			$plugin_meta[] = sprintf( '<a href="%s" class="thickbox" aria-label="%s" data-title="%s">%s</a>',
				esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $this->slug .
				'&TB_iframe=true&width=600&height=550' ) ),
				esc_attr( __( 'More information about this plugin', $this->domain ) ),
				esc_attr( $this->plugin_name ),
				__( 'View details', $this->domain )
			);

			return $plugin_meta;

		}

		/**
		 * Plugin New Version Update
		 * @since 1.0
		 * @version 1.0
		 */
		public function plugin_api_call( $result, $action, $args ) {

			global $wp_version;

			if ( ! isset( $args->slug ) || ( $args->slug != $this->slug ) )
				return $result;

			// Get the current version
			$args = array(
				'slug'    => $this->slug,
				'version' => $this->version,
				'site'    => site_url()
			);
			$request_string = array(
				'body'       => array(
					'action'     => 'info', 
					'request'    => serialize( $args ),
					'api-key'    => md5( get_bloginfo( 'url' ) )
				),
				'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo( 'url' )
			);

			$request = wp_remote_post( $this->update_url, $request_string );

			if ( ! is_wp_error( $request ) )
				$result = maybe_unserialize( $request['body'] );

			return $result;

		}

	}
endif;

function mycred_wp_post_ratings_plugin() {
	return myCRED_WP_Postratings::instance();
}
mycred_wp_post_ratings_plugin();

/**
 * WP Post Ratings Hook
 * @since 1.0
 * @version 1.0
 */
if ( ! function_exists( 'mycred_load_wp_postrating_hook' ) ) :
	function mycred_load_wp_postrating_hook() {

		if ( class_exists( 'myCRED_Hook_WPPostratings' ) || ! function_exists( 'postratings_init' ) ) return;

		class myCRED_Hook_WPPostratings extends myCRED_Hook {

			/**
			 * Construct
			 */
			function __construct( $hook_prefs, $type = MYCRED_DEFAULT_TYPE_KEY ) {

				parent::__construct( array(
					'id'       => 'wp-postratings',
					'defaults' => array(
						'rate'    => array(
							'creds' => 1,
							'log'   => '%plural% for new post rating'
						),
						'rated' => array(
							'creds' => 1,
							'log'   => '%plural% for getting rated'
						),
						'self'  => 0
					)
				), $hook_prefs, $type );

			}

			/**
			 * Run
			 * @since 1.0
			 * @version 1.0
			 */
			public function run() {

				if ( $this->prefs['rate']['creds'] != 0 || $this->prefs['rated']['creds'] != 0 )
					add_action( 'rate_post', array( $this, 'new_rating' ), 10, 3 );

			}

			/**
			 * New Rating
			 * @since 1.0
			 * @version 1.0
			 */
			public function new_rating( $user_id, $post_id, $rating ) {

				$data = array( 'ref_type' => 'post', 'rating' => $rating );

				// Award rating
				if ( $this->prefs['rate']['creds'] != 0 ) {

					if ( is_user_logged_in() && ! $this->core->exclude_user( $user_id ) ) {

						if ( ! $this->has_entry( 'rated_post', $post_id, $user_id ) )
							$this->core->add_creds(
								'rated_post',
								$user_id,
								$this->prefs['rate']['creds'],
								$this->prefs['rate']['log'],
								$post_id,
								$data,
								$this->mycred_type
							);

					}

				}

				// Award beign rated
				if ( $this->prefs['rated']['creds'] != 0 ) {

					// Get post author
					$post      = get_post( $post_id );
					$author_id = $post->post_author;

					// No award for our own rating
					if ( $user_id == $author_id && apply_filters( 'mycred_wp_postreview_self', false ) === false ) return;

					// Check for exclusion
					if ( $this->core->exclude_user( $author_id ) ) return;

					// Make sure this is unique event
					$data['rated_by'] = $user_id;
					if ( $this->core->has_entry( 'received_rating', $post_id, $author_id, $data, $this->mycred_type ) ) return;

					// Execute
					$this->core->add_creds(
						'received_rating',
						$author_id,
						$this->prefs['rated']['creds'],
						$this->prefs['rated']['log'],
						$post_id,
						$data,
						$this->mycred_type
					);

				}

			}

			/**
			 * Preferences
			 * @since 1.0
			 * @version 1.0
			 */
			public function preferences() {

				$prefs = $this->prefs;

?>
<label class="subheader" for="<?php echo $this->field_id( array( 'rate' => 'creds' ) ); ?>"><?php _e( 'Rating Content', 'mycred_wp_postrate' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'rate' => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'rate' => 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['rate']['creds'] ); ?>" size="8" /></div>
	</li>
</ol>
<label class="subheader" for="<?php echo $this->field_id( array( 'rate' => 'log' ) ); ?>"><?php _e( 'Log Template', 'mycred_wp_postrate' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'rate' => 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'rate' => 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['rate']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general', 'post' ) ); ?></span>
	</li>
</ol>
<label class="subheader" for="<?php echo $this->field_id( array( 'rated' => 'creds' ) ); ?>"><?php _e( 'Receiving A Rating', 'mycred_wp_postrate' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'rated' => 'creds' ) ); ?>" id="<?php echo $this->field_id( array( 'rated' => 'creds' ) ); ?>" value="<?php echo $this->core->number( $prefs['rated']['creds'] ); ?>" size="8" /></div>
	</li>
</ol>
<label class="subheader" for="<?php echo $this->field_id( array( 'rated' => 'log' ) ); ?>"><?php _e( 'Log Template', 'mycred_wp_postrate' ); ?></label>
<ol>
	<li>
		<div class="h2"><input type="text" name="<?php echo $this->field_name( array( 'rated' => 'log' ) ); ?>" id="<?php echo $this->field_id( array( 'rated' => 'log' ) ); ?>" value="<?php echo esc_attr( $prefs['rated']['log'] ); ?>" class="long" /></div>
		<span class="description"><?php echo $this->available_template_tags( array( 'general', 'post' ) ); ?></span>
	</li>
</ol>
<?php

			}

		}

	}
endif;
