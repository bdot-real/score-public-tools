<?php
/**
 * Plugin Name: Score Tracking
 * Plugin URI:  https://github.com/3halveslabs/score-tracking
 * Description: Tracks user activity (pageviews, clicks, forms, scroll depth, videos) and sends payloads to a SCORE endpoint.
 * Version:     1.0.0
 * Author:      3 Halves Labs
 * License:     GPL2+
 * Text Domain: score-tracking
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class Score_Tracking_Plugin {
    const OPTION_KEY = 'score_tracking_settings';

    public function __construct() {
        add_action( 'admin_menu',       [ $this, 'add_settings_page' ] );
        add_action( 'admin_init',       [ $this, 'register_settings' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_tracker' ] );
    }

    /**
     * Add settings page under Settings menu
     */
    public function add_settings_page() {
        add_options_page(
            __( 'Score Tracking', 'score-tracking' ),
            __( 'Score Tracking', 'score-tracking' ),
            'manage_options',
            'score-tracking',
            [ $this, 'render_settings_page' ]
        );
    }

    /**
     * Register plugin settings and fields
     */
    public function register_settings() {
        register_setting( self::OPTION_KEY, self::OPTION_KEY, [ 'sanitize_callback' => [ $this, 'sanitize_options' ] ] );

        add_settings_section(
            'score_tracking_section',
            __( 'Tracker Configuration', 'score-tracking' ),
            '__return_false',
            'score-tracking'
        );

        add_settings_field(
            'site_id',
            __( 'Score Site ID', 'score-tracking' ),
            [ $this, 'field_site_id' ],
            'score-tracking',
            'score_tracking_section'
        );

        add_settings_field(
            'api_endpoint',
            __( 'API Endpoint URL', 'score-tracking' ),
            [ $this, 'field_api_endpoint' ],
            'score-tracking',
            'score_tracking_section'
        );
    }

    /**
     * Sanitize settings input
     */
    public function sanitize_options( $input ) {
        return [
            'site_id'    => sanitize_text_field( $input['site_id'] ?? '' ),
            'api_endpoint' => esc_url_raw( $input['api_endpoint'] ?? '' ),
        ];
    }

    /**
     * Render Site ID field
     */
    public function field_site_id() {
        $opts = get_option( self::OPTION_KEY, [] );
        printf(
            '<input type="text" name="%1$s[site_id]" value="%2$s" class="regular-text" />',
            esc_attr( self::OPTION_KEY ),
            esc_attr( $opts['site_id'] ?? '' )
        );
    }

    /**
     * Render API Endpoint field
     */
    public function field_api_endpoint() {
        $opts = get_option( self::OPTION_KEY, [] );
        printf(
            '<input type="url" name="%1$s[api_endpoint]" value="%2$s" class="regular-text" />',
            esc_attr( self::OPTION_KEY ),
            esc_url( $opts['api_endpoint'] ?? '' )
        );
    }

    /**
     * Output the settings page HTML
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Score Tracking Settings', 'score-tracking' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( self::OPTION_KEY );
                do_settings_sections( 'score-tracking' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Enqueue tracking script and localize config
     */
    public function enqueue_tracker() {
        $opts = get_option( self::OPTION_KEY, [] );
        if ( empty( $opts['site_id'] ) || empty( $opts['api_endpoint'] ) ) {
            return; // not configured yet
        }

        wp_register_script(
            'score-tracker',
            plugin_dir_url( __FILE__ ) . 'js/score-tracker.js',
            [],
            '1.0.0',
            true
        );

        // Prepare user info
        $user_info = [];
        if ( is_user_logged_in() ) {
            $user    = wp_get_current_user();
            $user_info = [
                'userId'    => absint( $user->ID ),
                'userLogin' => sanitize_text_field( $user->user_login ),
                'userEmail' => sanitize_email( $user->user_email ),
            ];
        }

        wp_localize_script( 'score-tracker', 'ScoreConfig', [
            'siteId'      => esc_js( $opts['site_id'] ),
            'endpoint'    => esc_url_raw( $opts['api_endpoint'] ),
            'userInfo'    => $user_info,
        ] );
        wp_enqueue_script( 'score-tracker' );
    }
}

// Initialize plugin
new Score_Tracking_Plugin();