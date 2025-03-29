<?php
/**
 * Plugin Name: Cookie Blocker
 * Plugin URI: https://github.com.com/cmcnulty/wp-cookie-blocker
 * Description: Block unwanted cookies from third-party plugins using custom regex patterns
 * Version: 1.0.10
 * Author: Charles McNulty
 * Author URI: https://github.com.com/cmcnulty
 * Text Domain: cookie-blocker
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class WP_Cookie_Blocker {

    /**
     * Plugin version
     */
    const VERSION = '1.0.9';

    /**
     * Option name for storing settings
     */
    const OPTION_NAME = 'wp_cookie_blocker_settings';

    /**
     * Default settings
     */
    private $default_settings = [
        'patterns' => [
            // Empty by default - no cookies blocked until configured
        ],
        'enable_logging' => true
    ];

    /**
     * Current settings
     */
    private $settings;

    /**
     * Initialize the plugin
     */
    public function __construct() {
        // Load settings
        $this->settings = get_option(self::OPTION_NAME, $this->default_settings);

        // Register activation hook
        register_activation_hook(__FILE__, [$this, 'activate']);

        // Frontend: Early script injection with -100 priority
        add_action('wp_head', [$this, 'print_cookie_blocker'], -100);

        // Admin: Add settings page
        add_action('admin_menu', [$this, 'add_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);

        // Add settings link on plugin page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_settings_link']);
    }

    /**
     * Activation hook
     */
    public function activate() {
        // Add default settings if they don't exist
        if (!get_option(self::OPTION_NAME)) {
            update_option(self::OPTION_NAME, $this->default_settings);
        }
    }

    /**
     * Print the cookie blocker script directly in the head
     */
    public function print_cookie_blocker() {
        // Only if we have patterns to block
        $active_patterns = $this->get_active_patterns();
        if (empty($active_patterns)) {
            return;
        }

        // Prepare JavaScript settings
        $js_settings = wp_json_encode([
            'patterns' => array_map(function($item) {
                return $item['pattern'];
            }, $active_patterns),
            'enableLogging' => !empty($this->settings['enable_logging'])
        ]);

        $print_settings = "window.wpCookieBlocker = " . $js_settings . ";";

        // Get the script content using WordPress filesystem API
        $script_content = $this->get_script_content();
        if (empty($script_content)) {
            return;
        }

        $script_content = $js_settings . $script_content;

        // Register an empty script just so we can attach our inline script to it
        wp_register_script(
            'cookie-blocker-placeholder',
            null, // No actual file
            [],
            self::VERSION,
            false // Not in footer
        );

        // Add our settings as data to be used by the script
        wp_localize_script('cookie-blocker-placeholder', 'wpCookieBlocker', [
            'patterns' => array_map(function($item) {
                return $item['pattern'];
            }, $active_patterns),
            'enableLogging' => !empty($this->settings['enable_logging'])
        ]);

        // Add the script content as an inline script
        wp_add_inline_script('cookie-blocker-placeholder', $script_content, 'after');

        // Enqueue the script (which will output both the data and the inline script)
        wp_enqueue_script('cookie-blocker-placeholder');

        // Set script to load with highest priority
        global $wp_scripts;
        if (isset($wp_scripts->registered['cookie-blocker-placeholder'])) {
            $wp_scripts->registered['cookie-blocker-placeholder']->extra['group'] = 0;
        }
    }

    /**
     * Get the script content using WordPress filesystem API
     */
    private function get_script_content() {
        // Set up WP Filesystem
        if (!function_exists('WP_Filesystem')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        // Initialize the WP filesystem
        global $wp_filesystem;
        if (!WP_Filesystem()) {
            return '';
        }

        // Get the script content
        $path = plugin_dir_path(__FILE__) . 'js/cookie-blocker.js';
        if ($wp_filesystem->exists($path)) {
            return $wp_filesystem->get_contents($path);
        }

        return '';
    }

    /**
     * Get active patterns
     */
    private function get_active_patterns() {
        if (empty($this->settings['patterns'])) {
            return [];
        }

        return array_filter($this->settings['patterns'], function($pattern) {
            return !empty($pattern['enabled']) && !empty($pattern['pattern']);
        });
    }

    /**
     * Add settings page to admin menu
     */
    public function add_settings_page() {
        add_options_page(
            __('Cookie Blocker Settings', 'cookie-blocker'),
            __('Cookie Blocker', 'cookie-blocker'),
            'manage_options',
            'cookie-blocker',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'wp_cookie_blocker',        // Option group
            self::OPTION_NAME,          // Option name
            array(                      // Args array instead of callback
                'type'              => 'array',
                'description'       => 'Cookie blocker settings',
                'sanitize_callback' => array($this, 'validate_settings'),
                'default'           => $this->default_settings
            )
        );
    }

    /**
     * Validate and sanitize settings
     */
    public function validate_settings($input) {
        $output = [];

        // Validate patterns
        $output['patterns'] = [];
        if (!empty($input['patterns']) && is_array($input['patterns'])) {
            foreach ($input['patterns'] as $pattern) {
                if (empty($pattern['pattern'])) {
                    continue;
                }

                $output['patterns'][] = [
                    'pattern' => sanitize_text_field($pattern['pattern']),
                    'enabled' => !empty($pattern['enabled']),
                    'description' => sanitize_text_field($pattern['description'] ?? '')
                ];
            }
        }

        // Validate logging option
        $output['enable_logging'] = !empty($input['enable_logging']);

        return $output;
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Cookie Blocker Settings', 'cookie-blocker'); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('wp_cookie_blocker'); ?>

                <h2><?php esc_html_e('Cookie Patterns to Block', 'cookie-blocker'); ?></h2>
                <p><?php esc_html_e('Add regex patterns to match cookie names. Examples:', 'cookie-blocker'); ?></p>

                <ul style="margin-left: 20px; list-style-type: disc;">
                    <li><code>^wp-dark-mode-</code> - Match cookies that start with "wp-dark-mode-"</li>
                    <li><code>^wordpress_</code> - Match cookies that start with "wordpress_"</li>
                    <li><code>session$</code> - Match cookies that end with "session"</li>
                    <li><code>/^_ga_/i</code> - Match cookies that start with "_ga_" (case insensitive)</li>
                </ul>

                <table class="form-table" id="cookie-blocker-patterns">
                    <thead>
                        <tr>
                            <th style="width: 50px;"><?php esc_html_e('Enabled', 'cookie-blocker'); ?></th>
                            <th><?php esc_html_e('Pattern (regex)', 'cookie-blocker'); ?></th>
                            <th><?php esc_html_e('Description', 'cookie-blocker'); ?></th>
                            <th style="width: 60px;"><?php esc_html_e('Action', 'cookie-blocker'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $patterns = !empty($this->settings['patterns']) ? $this->settings['patterns'] : [['pattern' => '', 'enabled' => true, 'description' => '']];
                    if (empty($patterns)) {
                        $patterns = [['pattern' => '', 'enabled' => true, 'description' => '']];
                    }
                    foreach ($patterns as $index => $pattern) :
                    ?>
                        <tr class="pattern-row">
                            <td>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_NAME); ?>[patterns][<?php echo esc_attr($index); ?>][enabled]" value="1" <?php checked(!empty($pattern['enabled'])); ?> />
                            </td>
                            <td>
                                <input type="text" name="<?php echo esc_attr(self::OPTION_NAME); ?>[patterns][<?php echo esc_attr($index); ?>][pattern]" value="<?php echo esc_attr($pattern['pattern']); ?>" class="regular-text" placeholder="e.g., ^wp-dark-mode-" />
                            </td>
                            <td>
                                <input type="text" name="<?php echo esc_attr(self::OPTION_NAME); ?>[patterns][<?php echo esc_attr($index); ?>][description]" value="<?php echo esc_attr($pattern['description'] ?? ''); ?>" class="regular-text" placeholder="e.g., WP Dark Mode cookies" />
                            </td>
                            <td>
                                <button type="button" class="button remove-pattern" <?php echo (count($patterns) <= 1) ? 'style="display:none;"' : ''; ?>>&times;</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <p>
                    <button type="button" class="button add-pattern"><?php esc_html_e('Add Pattern', 'cookie-blocker'); ?></button>
                </p>

                <h2><?php esc_html_e('Advanced Settings', 'cookie-blocker'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Console Logging', 'cookie-blocker'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_NAME); ?>[enable_logging]" value="1" <?php checked(!empty($this->settings['enable_logging'])); ?> />
                                <?php esc_html_e('Enable console logging for debugging', 'cookie-blocker'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Add pattern
            $('.add-pattern').on('click', function() {
                var index = $('.pattern-row').length;
                var newRow = $('.pattern-row').first().clone();

                // Update field names and clear values
                newRow.find('input[type="text"]').val('');
                newRow.find('input[type="checkbox"]').prop('checked', true);
                newRow.find('input').each(function() {
                    var name = $(this).attr('name');
                    $(this).attr('name', name.replace(/\[\d+\]/, '[' + index + ']'));
                });

                // Show remove button
                newRow.find('.remove-pattern').show();

                // Add to table
                $('#cookie-blocker-patterns tbody').append(newRow);

                // Show all remove buttons if we have more than one row
                if ($('.pattern-row').length > 1) {
                    $('.remove-pattern').show();
                }
            });

            // Remove pattern
            $('#cookie-blocker-patterns').on('click', '.remove-pattern', function() {
                $(this).closest('tr').remove();

                // Hide remove button on last row
                if ($('.pattern-row').length <= 1) {
                    $('.remove-pattern').hide();
                }

                // Reindex remaining rows
                $('.pattern-row').each(function(index) {
                    $(this).find('input').each(function() {
                        var name = $(this).attr('name');
                        $(this).attr('name', name.replace(/\[\d+\]/, '[' + index + ']'));
                    });
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Add settings link to plugin page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="options-general.php?page=cookie-blocker">' . __('Settings', 'cookie-blocker') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

// Initialize the plugin
new WP_Cookie_Blocker();