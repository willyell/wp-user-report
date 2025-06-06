<?php
/*
Plugin Name: User Registrations Summary
Description: Provides an admin report and shortcode to summarise new user registrations by day and hour, with charts and daily email summary, using a trusted email sender, and GitHub auto-updates.
Version: 1.5.1
Author: William Yell
*/

if (!defined('ABSPATH')) exit;

// Ensure Plugin Update Checker is included
require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5p4\PucFactory;

// Set up the update checker
$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/willyell/wp-user-report/', // GitHub owner/repo
    __FILE__,                                      // Full path to this main plugin file
    'user-registrations-summary'                   // Plugin slug
);
// v5p4 automatically checks the default branch; no setBranch() needed

class URS_Summary {
    const CRON_HOOK = 'urs_daily_summary';

    public function __construct() {
        add_action('admin_menu', array($this, 'add_menu'));
        add_shortcode('registration_summary', array($this, 'shortcode_summary'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('init', array($this, 'schedule_cron'));
        add_action(self::CRON_HOOK, array($this, 'send_daily_summary'));
    }

    public function add_menu() {
        add_users_page(
            'Registration Summary',
            'Registration Summary',
            'list_users',
            'registration-summary',
            array($this, 'render_report')
        );
    }

    public function enqueue_scripts($hook) {
        if ($hook !== 'users_page_registration-summary') return;
        wp_enqueue_script('chartjs', 'https://cdn.jsdelivr.net/npm/chart.js', array(), null, true);
    }

    private function get_daily_data($days = null) {
        global $wpdb;
        if ($days) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT DATE(user_registered) AS reg_date, COUNT(*) AS count
                 FROM {$wpdb->users}
                 WHERE user_registered >= DATE_SUB(CURDATE(), INTERVAL %d DAY)
                 GROUP BY reg_date
                 ORDER BY reg_date ASC",
                $days
            ));
        }
        return $wpdb->get_results(
            "SELECT DATE(user_registered) AS reg_date, COUNT(*) AS count
             FROM {$wpdb->users}
             GROUP BY reg_date
             ORDER BY reg_date DESC"
        );
    }

    private function get_hourly_data($hours = 24) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT DATE_FORMAT(user_registered, '%%Y-%%m-%%d %%H:00:00') AS hour_block, COUNT(*) AS count
             FROM {$wpdb->users}
             WHERE user_registered >= DATE_SUB(NOW(), INTERVAL %d HOUR)
             GROUP BY hour_block
             ORDER BY hour_block ASC",
            $hours
        ));
    }

    public function render_report() {
        // Daily data
        $daily = $this->get_daily_data(7);
        $daily_labels = wp_list_pluck($daily, 'reg_date');
        $daily_counts = wp_list_pluck($daily, 'count');

        // Hourly data
        $hourly = $this->get_hourly_data(24);
        $hour_labels = wp_list_pluck($hourly, 'hour_block');
        $hour_counts = wp_list_pluck($hourly, 'count');

        echo '<div class="wrap"><h1>User Registrations Summary</h1>';

        // Daily chart
        echo '<h2>Last 7 Days</h2>';
        echo '<div style="width:600px; height:300px;"><canvas id="urs_daily_chart" width="600" height="300"></canvas></div>';

        // Hourly chart
        echo '<h2>Last 24 Hours</h2>';
        echo '<div style="width:600px; height:300px;"><canvas id="urs_hourly_chart" width="600" height="300"></canvas></div>';

        // Daily table reversed (latest first)
        $daily_rows = array_reverse($daily);
        echo '<h3>Daily Registrations</h3>';
        echo '<table class="widefat fixed striped"><thead><tr><th>Date</th><th>Registrations</th></tr></thead><tbody>';
        foreach ($daily_rows as $row) {
            printf('<tr><td>%s</td><td>%d</td></tr>', esc_html($row->reg_date), intval($row->count));
        }
        echo '</tbody></table>';

        // Optional: Hourly table reversed (latest first)
        $hourly_rows = array_reverse($hourly);
        echo '<h3>Hourly Registrations</h3>';
        echo '<table class="widefat fixed striped"><thead><tr><th>Hour</th><th>Registrations</th></tr></thead><tbody>';
        foreach ($hourly_rows as $row) {
            printf('<tr><td>%s</td><td>%d</td></tr>', esc_html($row->hour_block), intval($row->count));
        }
        echo '</tbody></table>';

        echo '</div>';
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            // Daily chart
            var ctxDaily = document.getElementById('urs_daily_chart').getContext('2d');
            new Chart(ctxDaily, {
                type: 'bar',
                data: {
                    labels: <?php echo wp_json_encode($daily_labels); ?>,
                    datasets: [{ label: 'Registrations', data: <?php echo wp_json_encode($daily_counts); ?> }]
                },
                options: { responsive: true, maintainAspectRatio: true }
            });

            // Hourly chart
            var ctxHourly = document.getElementById('urs_hourly_chart').getContext('2d');
            new Chart(ctxHourly, {
                type: 'bar',
                data: {
                    labels: <?php echo wp_json_encode($hour_labels); ?>,
                    datasets: [{ label: 'Registrations', data: <?php echo wp_json_encode($hour_counts); ?> }]
                },
                options: { responsive: true, maintainAspectRatio: true }
            });
        });
        </script>
        <?php
    }

    public function shortcode_summary($atts) {
        $atts = shortcode_atts(array('days' => 7), $atts, 'registration_summary');
        $results = $this->get_daily_data(intval($atts['days']));
        $output = '<table><tr><th>Date</th><th>Registrations</th></tr>';
        $rows = array_reverse($results);
        foreach ($rows as $row) {
            $output .= sprintf('<tr><td>%s</td><td>%d</td></tr>', esc_html($row->reg_date), intval($row->count));
        }
        $output .= '</table>';
        return $output;
    }

    public function schedule_cron() {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(strtotime('00:30:00'), 'daily', self::CRON_HOOK);
        }
    }

    public function send_daily_summary() {
        $daily       = $this->get_daily_data(7);
        $labels      = wp_list_pluck($daily, 'reg_date');
        $counts      = wp_list_pluck($daily, 'count');
        $config      = array(
            'type' => 'bar',
            'data' => array('labels' => $labels, 'datasets' => array(array('label' => 'Registrations', 'data' => $counts)))
        );
        $chart_url   = 'https://quickchart.io/chart?c=' . urlencode(json_encode($config));
        $yesterday   = date('Y-m-d', strtotime('yesterday'));
        $yester_count = 0;
        foreach ($daily as $row) {
            if ($row->reg_date === $yesterday) {
                $yester_count = intval($row->count);
                break;
            }
        }

        // Ensure trusted from address
        $from_email = 'no-reply@' . wp_parse_url(home_url(), PHP_URL_HOST);
        $from_name  = get_bloginfo('name');
        add_filter('wp_mail_from', function() use ($from_email) { return $from_email; });
        add_filter('wp_mail_from_name', function() use ($from_name)  { return $from_name; });

        $subject = 'Daily Registration Summary';
        $message = '<h1>' . esc_html($from_name) . '</h1>';
        $message .= '<p>Registrations in last 7 days:</p>';
        $message .= '<p><img src="' . esc_url($chart_url) . '" alt="Chart" /></p>';
        $message .= '<p>New registrations yesterday (' . esc_html($yesterday) . '): ' . esc_html($yester_count) . '</p>';
        $headers = array('Content-Type: text/html; charset=UTF-8');

        wp_mail(get_option('admin_email'), $subject, $message, $headers);
        remove_all_filters('wp_mail_from');
        remove_all_filters('wp_mail_from_name');
    }
}

new URS_Summary();
?>
