<?php
/**
 * Admin class
 *
 * @author Wordpress
 */

if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'EXMSPL_Admin' ) ) {
    
    /**
     * Handle the backend of the practitioner locator
     *
     */
	class EXMSPL_Admin {

        /**
         * @var EXMSPL_Metaboxes
         */
        public $metaboxes;
        
        /**
         * @var EXMSPL_Geocode
         */
        public $geocode;
    
        /**
         * @var EXMSPL_Notices
         */
        public $notices;

        /**
         * @var EXMSPL_Settings
         */
        public $settings_page;

        /**
         * Class constructor
         */
		function __construct() {

            $this->includes();

            add_action( 'init',                                 array( $this, 'init' ) );
            add_action( 'admin_menu',                           array( $this, 'create_admin_menu' ) );
            add_action( 'admin_init',                           array( $this, 'setting_warnings' ) );
            add_action( 'delete_post',                          array( $this, 'maybe_delete_autoload_transient' ) );
            add_action( 'wp_trash_post',                        array( $this, 'maybe_delete_autoload_transient' ) );
            add_action( 'untrash_post',                         array( $this, 'maybe_delete_autoload_transient' ) );
            add_action( 'admin_enqueue_scripts',                array( $this, 'admin_scripts' ) );
            add_filter( 'plugin_action_links_' . EXMSPL_BASENAME, array( $this, 'add_action_links' ), 10, 2 );
            add_filter( 'admin_footer_text',                    array( $this, 'admin_footer_text' ), 1 );
            add_action( 'wp_loaded',                            array( $this, 'disable_setting_notices' ) );
		}

        /**
         * Include the required files.
         *
         * @return void
         */
        public function includes() {
            require_once( EXMSPL_PLUGIN_DIR . 'admin/class-shortcode-generator.php' );
            require_once( EXMSPL_PLUGIN_DIR . 'admin/class-notices.php' );
            require_once( EXMSPL_PLUGIN_DIR . 'admin/class-metaboxes.php' ); 
            require_once( EXMSPL_PLUGIN_DIR . 'admin/class-geocode.php' );
            require_once( EXMSPL_PLUGIN_DIR . 'admin/class-settings.php' );
            // require_once( EXMSPL_PLUGIN_DIR . 'admin/data-export.php' );
		}
        
        /**
         * Init the classes.
         *
         * @return void
         */
		public function init() {
            $this->notices       = new EXMSPL_Notices();
            $this->metaboxes     = new EXMSPL_Metaboxes();
            $this->geocode       = new EXMSPL_Geocode();
            $this->settings_page = new EXMSPL_Settings();
		}
                
        /**
         * Check if we need to show warnings after 
         * the user installed the plugin.
         *
         * @todo move to class-notices?
         * @return void
         */
		public function setting_warnings() {
            
            global $current_user, $exmspl_settings;
            
            $this->setting_warning = array();
                         
            // The fields settings field to check for data.
            $warnings = array(
                'start_latlng'    => 'location',
                'api_browser_key' => 'key'
            );
            
            if ( ( current_user_can( 'install_plugins' ) ) && is_admin() ) {
                foreach ( $warnings as $setting_name => $warning ) {
                    if ( empty( $exmspl_settings[$setting_name] ) && !get_user_meta( $current_user->ID, 'exmspl_disable_' . $warning . '_warning' ) ) {
                        if ( $warning == 'key' ) {
                            $this->setting_warning[$warning] = sprintf( __( "You need to create API keys for Google Maps before you can use the practitioner locator! %sDismiss%s", "exmspl" ), "<a href='" . esc_url( wp_nonce_url( add_query_arg( 'exmspl-notice', 'key' ), 'exmspl_notices_nonce', '_exmspl_notice_nonce' ) ) . "'>", "</a>" );
                        } else {
                            $this->setting_warning[$warning] = sprintf( __( "Before adding the [exmspl] shortcode to a page, please don't forget to define a start point on the %ssettings%s page. %sDismiss%s", "exmspl" ), "<a href='" . admin_url( 'edit.php?post_type=exmspl_practitioners&page=exmspl_settings' ) . "'>", "</a>", "<a href='" . esc_url( wp_nonce_url( add_query_arg( 'exmspl-notice', 'location' ), 'exmspl_notices_nonce', '_exmspl_notice_nonce' ) ) . "'>", "</a>" );
                        }
                    }
                }
                
                if ( $this->setting_warning ) {
                    add_action( 'admin_notices', array( $this, 'show_warning' ) );
                }
            }
		}

       /**
        * Show the admin warnings
        * 
        * @return void
        */
        public function show_warning() {
            foreach ( $this->setting_warning as $k => $warning ) {
                echo "<div id='message' class='error'><p>" . $warning .  "</p></div>";
            }
        }
        
        /**
         * Disable notices about the plugin settings.
         * 
         * @todo move to class-notices?
         * @return void
         */
        public function disable_setting_notices() {
            
            global $current_user;
            
            if ( isset( $_GET['exmspl-notice'] ) && isset( $_GET['_exmspl_notice_nonce'] ) ) {

                if ( !wp_verify_nonce( $_GET['_exmspl_notice_nonce'], 'exmspl_notices_nonce' ) ) {
                    wp_die( __( 'Security check failed. Please reload the page and try again.', 'exmspl' ) );
                }
                
                $notice = sanitize_text_field( $_GET['exmspl-notice'] );
                
                add_user_meta( $current_user->ID, 'exmspl_disable_' . $notice . '_warning', 'true', true );
            }
        }
        
        /**
         * Add the admin menu pages.
         *
         * @return void
         */
		public function create_admin_menu() {
            
            $sub_menus = apply_filters( 'exmspl_sub_menu_items', array(
                    array(
                        'page_title'  => __( 'Settings', 'exmspl' ),
                        'menu_title'  => __( 'Settings', 'exmspl' ),
                        'caps'        => 'manage_exmspl_settings',
                        'menu_slug'   => 'exmspl_settings',
                        'function'    => array( $this, 'load_template' )
                    )
                )
            );
      
            if ( count( $sub_menus ) ) {
                foreach ( $sub_menus as $sub_menu ) {
                    add_submenu_page( 'edit.php?post_type=exmspl_practitioners', $sub_menu['page_title'], $sub_menu['menu_title'], $sub_menu['caps'], $sub_menu['menu_slug'], $sub_menu['function'] );
                }
            }            
        }

        /**
         * Load the correct page template.
         *
         * @return void
         */
        public function load_template() {
            
            switch ( $_GET['page'] ) {
                case 'exmspl_settings':
                    require 'templates/map-settings.php';
                break;
            }
        }

        /**
         * Check if we need to delete the autoload transient.
         * 
         * This is called when a post it saved, deleted, trashed or untrashed.
         * 
         * @return void
         */
        public function maybe_delete_autoload_transient( $post_id ) {
            
            global $exmspl_settings;
            
            if ( isset( $exmspl_settings['autoload'] ) && $exmspl_settings['autoload'] && get_post_type( $post_id ) == 'exmspl_practitioners' ) {
				$this->delete_autoload_transient(); 
            }
        }
        
        /**
         * Delete the transients that are used on the front-end 
         * if the autoload option is enabled.
         * 
         * The transient names used by the practitioner locator are partly dynamic. 
         * They always start with exmspl_autoload_, followed by the number of 
         * practitioners to load and ends with the language code.
         * 
         * So you get exmspl_autoload_20_de if the language is set to German
         * and 20 practitioners are set to show on page load. 
         * 
         * The language code has to be included in case a multilingual plugin is used.
         * Otherwise it can happen the user switches to Spanish, 
         * but ends up seeing the practitioner data in the wrong language.
         * 
         * @return void
         */
        public function delete_autoload_transient() {
            
            global $wpdb;
            
            $option_names = $wpdb->get_results( "SELECT option_name AS transient_name FROM " . $wpdb->options . " WHERE option_name LIKE ('\_transient\_exmspl\_autoload\_%')" );

            if ( $option_names ) {
                foreach ( $option_names as $option_name ) {
                    $transient_name = str_replace( "_transient_", "", $option_name->transient_name );

                    delete_transient( $transient_name );
                }
            }
        }
        
        // /**
        //  * Check if we can use a font for the plugin icon.
        //  * 
        //  * This is supported by WP 3.8 or higher
        //  *
        //  * @return void
        //  */
        // private function check_icon_font_usage() {
                        
        //     global $wp_version;

        //     if ( ( version_compare( $wp_version, '3.8', '>=' ) == TRUE ) ) {
        //         $min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
                
        //         wp_enqueue_style( 'exmspl-admin-38', plugins_url( '/css/style-3.8'. $min .'.css', __FILE__ ), false );
        //     } 
        // }
                
        /**
         * The text messages used in exmspl-admin.js.
         *
         * @return array $admin_js_l10n The texts used in the exmspl-admin.js
         */
        public function admin_js_l10n() {
            
            $admin_js_l10n = array(
                'noAddress'       => __( 'Cannot determine the address at this location.', 'exmspl' ),
                'geocodeFail'     => __( 'Geocode was not successful for the following reason', 'exmspl' ),
                'securityFail'    => __( 'Security check failed, reload the page and try again.', 'exmspl' ),
                'requiredFields'  => __( 'Please fill in all the required practitioner details.', 'exmspl' ),
                'missingGeoData'  => __( 'The map preview requires all the location details.', 'exmspl' ),
                'closedDate'      => __( 'Closed', 'exmspl' ),
                'styleError'      => __( 'The code for the map style is invalid.', 'exmspl' ),
                'browserKeyError' => sprintf( __( 'There\'s a problem with the provided browser key.','exmspl' ), '<br><br>' ),
                'dismissNotice'   => __( 'Dismiss this notice.', 'exmspl' )
            );

            return $admin_js_l10n;
        }
        
        /**
         * Plugin settings that are used in the exmspl-admin.js.
         *
         * @return array $settings_js The settings used in the exmspl-admin.js
         */
        public function js_settings() {
            
            global $exmspl_settings;

            $js_settings = array(
                'hourFormat'     => $exmspl_settings['editor_hour_format'],
                'defaultLatLng'  => $this->get_default_lat_lng(),
                'defaultZoom'    => 6,
                'mapType'        => $exmspl_settings['editor_map_type'],
                'requiredFields' => array( 'address', 'city', 'country' ),
                'ajaxurl'        => exmspl_get_ajax_url()
            );

            return apply_filters( 'exmspl_admin_js_settings', $js_settings );
        }

        /**
         * Get the coordinates that are used to
         * show the map on the settings page.
         *
         * @return string $startLatLng The start coordinates
         */
        public function get_default_lat_lng() {

            global $exmspl_settings;

            $startLatLng = $exmspl_settings['start_latlng'];

            // If no start coordinates exists, then set the default to Pune, India.
            if ( !$startLatLng ) {
                $startLatLng = '18.520430,73.856743';
            }

            return $startLatLng;
        }

        /**
         * Add the required admin script.
         *
         * @return void
         */
		public function admin_scripts() {

            $min = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min'; 
            
            // Always load the main js admin file to make sure the "dismiss" link in the location notice works.
            wp_enqueue_script( 'exmspl-admin-js', plugins_url( '/js/exmspl-admin'. $min .'.js', __FILE__ ), array( 'jquery' ), EXMSPL_VERSION_NUM, true );				

            // $this->maybe_show_pointer();
            // $this->check_icon_font_usage();
            
            // Only enqueue the rest of the css/js files if we are on a page that belongs to the practitioner locator.
            if ( ( get_post_type() == 'exmspl_practitioners' ) || ( isset( $_GET['post_type'] ) && ( $_GET['post_type'] == 'exmspl_practitioners' ) ) ) {
                
                // Make sure no other Google Map scripts can interfere with the one from the practitioner locator.
                exmspl_deregister_other_gmaps();
                
                wp_enqueue_style( 'jquery-style', '//ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/themes/smoothness/jquery-ui.css' );
                wp_enqueue_style( 'exmspl-admin-css', plugins_url( '/css/style'. $min .'.css', __FILE__ ), false );
                
                wp_enqueue_media();
                wp_enqueue_script( 'jquery-ui-dialog' );
                wp_enqueue_script( 'exmspl-gmap', ( '//maps.google.com/maps/api/js' . exmspl_get_gmap_api_params( 'browser_key' ) ), false, EXMSPL_VERSION_NUM, true );

                wp_enqueue_script( 'exmspl-queue', plugins_url( '/js/ajax-queue'. $min .'.js', __FILE__ ), array( 'jquery' ), EXMSPL_VERSION_NUM, true ); 
                wp_enqueue_script( 'exmspl-retina', plugins_url( '/js/retina'. $min .'.js', __FILE__ ), array( 'jquery' ), EXMSPL_VERSION_NUM, true ); 
                                
                wp_localize_script( 'exmspl-admin-js', 'exmsplL10n',     $this->admin_js_l10n() );
                wp_localize_script( 'exmspl-admin-js', 'exmsplSettings', $this->js_settings() );
            }
        }

        /**
         * Add link to the plugin action row.
         *
         * @param  array  $links The existing action links
         * @param  string $file  The file path of the current plugin
         * @return array  $links The modified links
         */
        public function add_action_links( $links, $file ) {
            
            if ( strpos( $file, 'practitioner-locator.php' ) !== false ) {
                $settings_link = '<a href="' . admin_url( 'edit.php?post_type=exmspl_practitioners&page=exmspl_settings' ) . '" title="View Practitioner Locator Settings">' . __( 'Settings', 'exmspl' ) . '</a>';
                array_unshift( $links, $settings_link );
            }

            return $links;
        }
        
        /**
         * Change the footer text on the settings page.
         *
         * @param  string $text The current footer text
         * @return string $text Either the original or modified footer text
         */
        public function admin_footer_text( $text ) {
            
            $current_screen = get_current_screen();
            
            // Only modify the footer text if we are on the settings page of the wp practitioner locator.
            if ( isset( $current_screen->id ) && $current_screen->id == 'exmspl_practitioners_page_exmspl_settings' ) {
                $text = sprintf( __( 'Powered by wordpress', 'exmspl' ) );
            }
            
            return $text;
        }
    }
	
	$GLOBALS['exmspl_admin'] = new EXMSPL_Admin();
}