<?php
/*
Plugin Name: User Registrations Summary
Description: Provides an admin report and shortcode to summarise new user registrations by day, with chart and daily email summary, using a trusted email sender, and GitHub auto-updates.
Version: 1.4
Author: William Yell
*/

if (!defined('ABSPATH')) exit;

// Ensure Plugin Update Checker is included
require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5p4\PucFactory;

// Set up the update checker
$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/willyell/wp-user-report', // GitHub owner/repo
    __FILE__,                                       // Full path to this main plugin file
    'user-registrations-summary'                    // Plugin slug
);

// If you hit GitHub API rate limits (403 errors), supply a personal access token:
if ( defined( 'GITHUB_UPDATER_TOKEN' ) && GITHUB_UPDATER_TOKEN ) {
    $updateChecker->setAuthentication( GITHUB_UPDATER_TOKEN );
}
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

    private function get_data($days = null) {
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

    public function render_report() {
        // Get last 7 days data in ascending order for chart
        $results = $this->get_data(7);
        $labels = wp_list_pluck($results, 'reg_date');
        $counts = wp_list_pluck($results, 'count');

        echo '<div class="wrap"><h1>User Registrations by Day</h1>';
        echo '<div style="width:600px; height:300px;"><canvas id="urs_chart" width="600" height="300"></canvas></div>';

        // Reverse the results for table: latest day first
        $rows = array_reverse($results);

        echo '<table class="widefat fixed striped"><thead><tr><th>Date</th><th>Registrations</th></tr></thead><tbody>';
        foreach ($rows as $row) {
            printf('<tr><td>%s</td><td>%d</td></tr>', esc_html($row->reg_date), intval($row->count));
        }
        echo '</tbody></table></div>';
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            var ctx = document.getElementById('urs_chart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo wp_json_encode($labels); ?>,
                    datasets: [{ label: 'Registrations', data: <?php echo wp_json_encode($counts); ?> }]
                },
                options: { responsive: true, maintainAspectRatio: true }
            });
        });
        </script>
        <?php
    }

    public function shortcode_summary($atts) {
        $atts = shortcode_atts(array('days' => 7), $atts, 'registration_summary');
        $results = $this->get_data(intval($atts['days']));
        $output = '<table><tr><th>Date</th><th>Registrations</th></tr>';
        // Show latest first
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
        $results      = $this->get_data(7);
        $labels       = wp_list_pluck($results, 'reg_date');
        $counts       = wp_list_pluck($results, 'count');
        $config       = array(
            'type' => 'bar',
            'data' => array('labels' => $labels, 'datasets' => array(array('label' => 'Registrations', 'data' => $counts)))
        );
        $chart_url    = 'https://quickchart.io/chart?c=' . urlencode(json_encode($config));
        $yesterday    = date('Y-m-d', strtotime('yesterday'));
        $yester_count = 0;
        foreach ($results as $row) {
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

        // Remove filters to avoid affecting other emails
        remove_all_filters('wp_mail_from');
        remove_all_filters('wp_mail_from_name');
    }
}

new URS_Summary();
?>
