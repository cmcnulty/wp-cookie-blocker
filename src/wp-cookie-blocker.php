<?php
/**
 * Plugin Name: WP Cookie Blocker
 * Plugin URI: https://github.com.com/cmcnulty/wp-cookie-blocker
 * Description: Block unwanted cookies from third-party plugins using custom regex patterns
 * Version: 1.0.2
 * Author: Charles McNulty
 * Author URI: https://yourwebsite.com
 * Text Domain: wp-cookie-blocker
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
    const VERSION = '1.0.2';

    /**
     * Option name for storing settings
     */
    const OPTION_NAME = 'wp_cookie_blocker_settings';

    /**
     * Default settings
     */
    private $default_settings = [
        'patterns' => [
            ['pattern' => 'wp-dark-mode-', 'enabled' => true, 'description' => 'WP Dark Mode cookies']
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
        $js_settings = json_encode([
            'patterns' => array_map(function($item) {
                return $item['pattern'];
            }, $active_patterns),
            'enableLogging' => !empty($this->settings['enable_logging'])
        ]);

        // Output the inline script
        echo "<!-- WP Cookie Blocker - Start -->\n";
        echo "<script id=\"wp-cookie-blocker-inline\">\n";

        // Add settings object
        echo "window.wpCookieBlocker = " . $js_settings . ";\n";

        // Include the script content
        readfile(plugin_dir_path(__FILE__) . 'js/cookie-blocker.js');

        echo "\n</script>\n";
        echo "<!-- WP Cookie Blocker - End -->\n";
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
            __('Cookie Blocker Settings', 'wp-cookie-blocker'),
            __('Cookie Blocker', 'wp-cookie-blocker'),
            'manage_options',
            'wp-cookie-blocker',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Register settings
     */
    public function register_settings() {
        register_setting(
            'wp_cookie_blocker',
            self::OPTION_NAME,
            [$this, 'validate_settings']
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

        // If no patterns were added, keep at least one empty pattern
        if (empty($output['patterns'])) {
            $output['patterns'] = [
                ['pattern' => '', 'enabled' => true, 'description' => '']
            ];
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
            <h1><?php _e('Cookie Blocker Settings', 'wp-cookie-blocker'); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields('wp_cookie_blocker'); ?>

                <h2><?php _e('Cookie Patterns to Block', 'wp-cookie-blocker'); ?></h2>
                <p><?php _e('Add patterns to match cookie names. Use prefixes (e.g. "wordpress-") or regular expressions (e.g. "^wordpress-.*").', 'wp-cookie-blocker'); ?></p>

                <table class="form-table" id="wp-cookie-blocker-patterns">
                    <thead>
                        <tr>
                            <th style="width: 50px;"><?php _e('Enabled', 'wp-cookie-blocker'); ?></th>
                            <th><?php _e('Pattern', 'wp-cookie-blocker'); ?></th>
                            <th><?php _e('Description', 'wp-cookie-blocker'); ?></th>
                            <th style="width: 60px;"><?php _e('Action', 'wp-cookie-blocker'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $patterns = !empty($this->settings['patterns']) ? $this->settings['patterns'] : [['pattern' => '', 'enabled' => true, 'description' => '']];
                    foreach ($patterns as $index => $pattern) :
                    ?>
                        <tr class="pattern-row">
                            <td>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_NAME); ?>[patterns][<?php echo $index; ?>][enabled]" value="1" <?php checked(!empty($pattern['enabled'])); ?> />
                            </td>
                            <td>
                                <input type="text" name="<?php echo esc_attr(self::OPTION_NAME); ?>[patterns][<?php echo $index; ?>][pattern]" value="<?php echo esc_attr($pattern['pattern']); ?>" class="regular-text" placeholder="e.g., wordpress-" />
                            </td>
                            <td>
                                <input type="text" name="<?php echo esc_attr(self::OPTION_NAME); ?>[patterns][<?php echo $index; ?>][description]" value="<?php echo esc_attr($pattern['description'] ?? ''); ?>" class="regular-text" placeholder="e.g., WordPress cookies" />
                            </td>
                            <td>
                                <button type="button" class="button remove-pattern" <?php echo (count($patterns) <= 1) ? 'style="display:none;"' : ''; ?>>&times;</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <p>
                    <button type="button" class="button add-pattern"><?php _e('Add Pattern', 'wp-cookie-blocker'); ?></button>
                </p>

                <h2><?php _e('Advanced Settings', 'wp-cookie-blocker'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Console Logging', 'wp-cookie-blocker'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_NAME); ?>[enable_logging]" value="1" <?php checked(!empty($this->settings['enable_logging'])); ?> />
                                <?php _e('Enable console logging for debugging', 'wp-cookie-blocker'); ?>
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
                $('#wp-cookie-blocker-patterns tbody').append(newRow);

                // Show all remove buttons if we have more than one row
                if ($('.pattern-row').length > 1) {
                    $('.remove-pattern').show();
                }
            });

            // Remove pattern
            $('#wp-cookie-blocker-patterns').on('click', '.remove-pattern', function() {
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
        $settings_link = '<a href="options-general.php?page=wp-cookie-blocker">' . __('Settings', 'wp-cookie-blocker') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

// Initialize the plugin
new WP_Cookie_Blocker();