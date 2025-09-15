<?php
/**
 * Signals Manager - WooCommerce Subscription Gated Signals System
 * Handles My Account integration, subscription checks, and routing
 */

if (!defined('ABSPATH')) {
    exit;
}

class Signals_Manager {
    
    private $subscription_product_ids = []; // Will be set via admin or filter
    
    public function __construct() {
        add_action('init', [$this, 'init'], 0);
        add_filter('woocommerce_account_menu_items', [$this, 'add_signals_menu_item']);
        add_action('woocommerce_account_signals_endpoint', [$this, 'signals_dashboard_content']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_signals_styles']);
        
        // Add rewrite endpoints with higher priority
        add_action('init', [$this, 'add_rewrite_endpoints'], 1);
        add_action('template_redirect', [$this, 'handle_signals_routing']);
        
        // Force flush rewrite rules on activation
        register_activation_hook(__FILE__, 'flush_rewrite_rules');
    }
    
    public function init() {
        // Set subscription product IDs (can be filtered)
        $this->subscription_product_ids = apply_filters('signals_subscription_product_ids', []);
        
        // Add query vars
        add_filter('query_vars', [$this, 'add_query_vars']);
        
        // Add My Account endpoints properly
        if (class_exists('WooCommerce')) {
            add_rewrite_endpoint('signals', EP_ROOT | EP_PAGES);
            
            // Add to WooCommerce query vars
            add_filter('woocommerce_get_query_vars', [$this, 'add_wc_query_vars']);
        }
    }
    
    public function add_wc_query_vars($query_vars) {
        $query_vars['signals'] = 'signals';
        return $query_vars;
    }
    
    public function add_query_vars($vars) {
        $vars[] = 'signals';
        $vars[] = 'year';
        $vars[] = 'month';
        $vars[] = 'signal_slug';
        return $vars;
    }
    
    public function add_rewrite_endpoints() {
        // Add nested endpoints for signals
        add_rewrite_rule(
            '^my-account/signals/([0-9]{4})/([0-9]{1,2})/?$',
            'index.php?pagename=my-account&signals=1&year=$matches[1]&month=$matches[2]',
            'top'
        );
        
        add_rewrite_rule(
            '^my-account/signals/([^/]+)/?$',
            'index.php?pagename=my-account&signals=1&signal_slug=$matches[1]',
            'top'
        );
        
        // Main signals endpoint
        add_rewrite_rule(
            '^my-account/signals/?$',
            'index.php?pagename=my-account&signals=1',
            'top'
        );
    }
    
    public function handle_signals_routing() {
        if (!is_account_page()) {
            return;
        }
        
        global $wp_query;
        
        // Check if we're on the signals endpoint
        if (!isset($wp_query->query_vars['signals'])) {
            return;
        }
        
        // Handle year/month archive
        if (isset($wp_query->query_vars['year']) && isset($wp_query->query_vars['month'])) {
            $this->load_signals_archive();
            return;
        }
        
        // Handle single signal
        if (isset($wp_query->query_vars['signal_slug'])) {
            $this->load_single_signal();
            return;
        }
    }
    
    public function add_signals_menu_item($items) {
        // Insert signals menu item after dashboard
        $new_items = [];
        foreach ($items as $key => $item) {
            $new_items[$key] = $item;
            if ($key === 'dashboard') {
                $new_items['signals'] = 'איתותים';
            }
        }
        return $new_items;
    }
    
    public function signals_dashboard_content() {
        if (!is_user_logged_in()) {
            wp_redirect(wc_get_page_permalink('myaccount'));
            exit;
        }
        
        if (!$this->user_has_signals_access()) {
            $this->display_paywall();
            return;
        }
        
        $this->display_signals_dashboard();
    }
    
    public function user_has_signals_access($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        // Admins and editors always have access
        if (user_can($user_id, 'edit_posts')) {
            return true;
        }
        
        // Check if WooCommerce Subscriptions is active
        if (!class_exists('WC_Subscriptions')) {
            return false;
        }
        
        // If no subscription products are set, allow access (fallback)
        if (empty($this->subscription_product_ids)) {
            return true;
        }
        
        // Check for active subscriptions
        $subscriptions = wcs_get_users_subscriptions($user_id);
        
        foreach ($subscriptions as $subscription) {
            if (!in_array($subscription->get_status(), ['active', 'pending-cancel'])) {
                continue;
            }
            
            foreach ($subscription->get_items() as $item) {
                $product_id = $item->get_product_id();
                if (in_array($product_id, $this->subscription_product_ids)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    public function display_paywall() {
        ?>
        <div class="signals-paywall" dir="rtl">
            <div class="paywall-container">
                <h2>גישה לאיתותים</h2>
                <p class="paywall-description">
                    כדי לצפות באיתותי המסחר שלנו, נדרש מנוי פעיל. 
                    המנוי כולל גישה לכל האיתותים, עדכונים בזמן אמת וניתוחים מפורטים.
                </p>
                
                <div class="paywall-benefits">
                    <h3>מה כלול במנוי:</h3>
                    <ul>
                        <li>✓ גישה לכל איתותי המסחר</li>
                        <li>✓ עדכוני מחירים בזמן אמת</li>
                        <li>✓ ניתוח רווחיות מפורט</li>
                        <li>✓ ארכיון איתותים מלא</li>
                        <li>✓ התראות על יעדים וסטופים</li>
                    </ul>
                </div>
                
                <div class="paywall-cta">
                    <a href="<?php echo wc_get_page_permalink('shop'); ?>" class="button signals-subscribe-btn">
                        הצטרפות למנוי
                    </a>
                </div>
                
                <?php if (is_user_logged_in()): ?>
                <div class="paywall-account-status">
                    <p>יש לך כבר חשבון? <a href="<?php echo wc_get_account_endpoint_url('subscriptions'); ?>">צפה במנויים שלך</a></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    public function display_signals_dashboard() {
        // Get current month and year from URL or default to current
        $current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
        $current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        
        // Also check URL parameters from rewrite rules
        global $wp_query;
        if (isset($wp_query->query_vars['month'])) {
            $current_month = intval($wp_query->query_vars['month']);
        }
        if (isset($wp_query->query_vars['year'])) {
            $current_year = intval($wp_query->query_vars['year']);
        }
        
        $this->render_signals_archive_improved($current_year, $current_month);
    }
    
    public function render_signals_archive_improved($year, $month) {
        // Hebrew month names
        $hebrew_months = [
            1 => 'ינואר', 2 => 'פברואר', 3 => 'מרץ', 4 => 'אפריל',
            5 => 'מאי', 6 => 'יוני', 7 => 'יולי', 8 => 'אוגוסט',
            9 => 'ספטמבר', 10 => 'אוקטובר', 11 => 'נובמבר', 12 => 'דצמבר'
        ];
        
        // Calculate previous and next month/year
        $prev_month = $month - 1;
        $prev_year = $year;
        if ($prev_month < 1) {
            $prev_month = 12;
            $prev_year--;
        }
        
        $next_month = $month + 1;
        $next_year = $year;
        if ($next_month > 12) {
            $next_month = 1;
            $next_year++;
        }
        
        // Query signals for the selected month
        $signals_query = new WP_Query([
            'post_type' => 'signal',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'signal_date',
                    'value' => [
                        $year . '-' . sprintf('%02d', $month) . '-01',
                        $year . '-' . sprintf('%02d', $month) . '-31'
                    ],
                    'compare' => 'BETWEEN',
                    'type' => 'DATE'
                ]
            ],
            'meta_key' => 'signal_date',
            'orderby' => 'meta_value',
            'order' => 'DESC'
        ]);
        
        ?>
        <div class="signals-dashboard" dir="rtl">
            <div class="signals-header">
                <div class="date-navigation-container">
                    <div class="current-date">
                        <h2><?php echo $hebrew_months[$month] . ' ' . $year; ?></h2>
                    </div>
                    
                    <div class="navigation-controls">
                        <a href="<?php echo wc_get_account_endpoint_url('signals') . $prev_year . '/' . sprintf('%02d', $prev_month) . '/'; ?>" class="nav-btn prev-btn">
                            <span>‹</span>
                        </a>
                        
                        <div class="date-selectors">
                            <select id="month-selector" onchange="navigateToMonth()">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php selected($month, $i); ?>>
                                        <?php echo $hebrew_months[$i]; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            
                            <select id="year-selector" onchange="navigateToMonth()">
                                <?php for ($y = date('Y') - 5; $y <= date('Y') + 2; $y++): ?>
                                    <option value="<?php echo $y; ?>" <?php selected($year, $y); ?>>
                                        <?php echo $y; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <a href="<?php echo wc_get_account_endpoint_url('signals') . $next_year . '/' . sprintf('%02d', $next_month) . '/'; ?>" class="nav-btn next-btn">
                            <span>›</span>
                        </a>
                    </div>
                </div>
            </div>
            
            <?php if ($signals_query->have_posts()): ?>
                <div class="signals-table-container">
                    <?php $this->render_datatable_signals($signals_query); ?>
                </div>
            <?php else: ?>
                <div class="no-signals">
                    <p>לא נמצאו איתותים עבור <?php echo $hebrew_months[$month] . ' ' . $year; ?></p>
                    <?php $this->show_latest_signals_preview(); ?>
                </div>
            <?php endif; ?>
            
            <script>
            function navigateToMonth() {
                const month = document.getElementById('month-selector').value;
                const year = document.getElementById('year-selector').value;
                window.location.href = '<?php echo wc_get_account_endpoint_url('signals'); ?>' + year + '/' + month.padStart(2, '0') + '/';
            }
            </script>
        </div>
        <?php
        
        wp_reset_postdata();
    }
    
    public function render_datatable_signals($query) {
        ?>
        <table id="signals-datatable" class="signals-table display" style="width:100%">
            <thead>
                <tr>
                    <th>מס׳ נייר</th>
                    <th>שם הנייר</th>
                    <th>תאריך האיתות</th>
                    <th>שער הקניה</th>
                    <th>יעד מחיר</th>
                    <th>מחיר סטופ לוס</th>
                    <th>שער נוכחי</th>
                    <th>תאריך הסגירה</th>
                    <th>אחוז רווח או הפסד</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($query->have_posts()): $query->the_post(); 
                    $this->render_signal_row();
                endwhile; ?>
            </tbody>
        </table>
        
        <script>
        jQuery(document).ready(function($) {
            $('#signals-datatable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.7/i18n/he.json"
                },
                "order": [[ 2, "desc" ]], // Sort by date column
                "pageLength": 25,
                "responsive": true,
                "columnDefs": [
                    { "type": "date", "targets": [2, 7] },
                    { "type": "num", "targets": [3, 4, 5, 6] },
                    { "className": "text-center", "targets": "_all" }
                ]
            });
        });
        </script>
        <?php
    }
    
    public function render_signal_row() {
        $security_number = get_field('security_number');
        $security_name = get_field('security_name');
        $signal_date = get_field('signal_date');
        $buy_price = get_field('buy_price');
        $target_price = get_field('target_price');
        $stop_loss_price = get_field('stop-loss_price');
        $current_price = get_field('current_price');
        $close_date = get_field('close_date');
        $profit_loss_stored = get_field('%_profitloss');
        
        // Calculate live profit/loss if not stored and signal is open
        $profit_loss_display = $profit_loss_stored;
        if (empty($profit_loss_display) && empty($close_date)) {
            $profit_loss_display = $this->calculate_profit_loss($buy_price, $current_price);
        }
        
        $profit_loss_class = $this->get_profit_loss_class($profit_loss_display);
        $signal_url = wc_get_account_endpoint_url('signals') . get_post_field('post_name') . '/';
        
        ?>
        <tr class="signal-row <?php echo empty($close_date) ? 'signal-open' : 'signal-closed'; ?>">
            <td>
                <a href="<?php echo $signal_url; ?>" class="security-number-link">
                    <?php echo esc_html($security_number); ?>
                </a>
            </td>
            <td><?php echo esc_html($security_name); ?></td>
            <td><?php echo $this->format_date_hebrew($signal_date); ?></td>
            <td><?php echo esc_html($buy_price); ?></td>
            <td><?php echo esc_html($target_price); ?></td>
            <td><?php echo esc_html($stop_loss_price); ?></td>
            <td><?php echo esc_html($current_price); ?></td>
            <td><?php echo $this->format_date_hebrew($close_date); ?></td>
            <td class="profit-loss <?php echo $profit_loss_class; ?>">
                <?php echo $profit_loss_display ?: '—'; ?>
            </td>
        </tr>
        <?php
    }
    
    public function load_single_signal() {
        global $wp_query;
        $signal_slug = $wp_query->query_vars['signal_slug'];
        
        if (!$this->user_has_signals_access()) {
            wp_redirect(wc_get_account_endpoint_url('signals'));
            exit;
        }
        
        $signal = get_page_by_path($signal_slug, OBJECT, 'signal');
        if (!$signal) {
            wp_redirect(wc_get_account_endpoint_url('signals'));
            exit;
        }
        
        $this->render_single_signal($signal);
    }
    
    public function render_single_signal($signal) {
        global $post;
        $post = $signal;
        setup_postdata($post);
        
        // Get signal data
        $security_number = get_field('security_number');
        $security_name = get_field('security_name');
        $signal_date = get_field('signal_date');
        
        // Get archive link
        $signal_date_obj = DateTime::createFromFormat('d/m/Y', $signal_date);
        if (!$signal_date_obj) {
            $signal_date_obj = DateTime::createFromFormat('Y-m-d', $signal_date);
        }
        $archive_month = $signal_date_obj ? $signal_date_obj->format('n') : date('n');
        $archive_year = $signal_date_obj ? $signal_date_obj->format('Y') : date('Y');
        $archive_url = wc_get_account_endpoint_url('signals') . $archive_year . '/' . sprintf('%02d', $archive_month) . '/';
        
        ?>
        <div class="single-signal" dir="rtl">
            <div class="back-to-archive">
                <a href="<?php echo $archive_url; ?>" class="back-link">
                    ← חזרה לארכיון איתותים
                </a>
            </div>
            
            <div class="signal-header">
                <h2>איתות מס׳ <?php echo esc_html($security_number); ?> - <?php echo esc_html($security_name); ?></h2>
            </div>
            
            <div class="signal-details">
                <?php $this->render_signal_details($signal); ?>
            </div>
        </div>
        <?php
        
        wp_reset_postdata();
    }
    
    public function render_signal_details($signal) {
        // Get ACF fields
        $security_number = get_field('security_number', $signal->ID);
        $security_name = get_field('security_name', $signal->ID);
        $signal_date = get_field('signal_date', $signal->ID);
        $buy_price = get_field('buy_price', $signal->ID);
        $target_price = get_field('target_price', $signal->ID);
        $stop_loss_price = get_field('stop-loss_price', $signal->ID);
        $current_price = get_field('current_price', $signal->ID);
        $close_date = get_field('close_date', $signal->ID);
        $profit_loss_stored = get_field('%_profitloss', $signal->ID);
        
        // Calculate live profit/loss if not stored and signal is open
        $profit_loss_display = $profit_loss_stored;
        if (empty($profit_loss_display) && empty($close_date)) {
            $profit_loss_display = $this->calculate_profit_loss($buy_price, $current_price);
        }
        
        $profit_loss_class = $this->get_profit_loss_class($profit_loss_display);
        
        // Check for target/stop states
        $target_reached = false;
        $stop_activated = false;
        if (is_numeric($current_price) && is_numeric($target_price)) {
            $target_reached = floatval($current_price) >= floatval($target_price);
        }
        if (is_numeric($current_price) && is_numeric($stop_loss_price)) {
            $stop_activated = floatval($current_price) <= floatval($stop_loss_price);
        }
        
        ?>
        <div class="signal-status-badges">
            <?php if (empty($close_date)): ?>
                <span class="status-badge status-open">איתות פתוח</span>
            <?php else: ?>
                <span class="status-badge status-closed">איתות סגור</span>
            <?php endif; ?>
            
            <?php if ($target_reached): ?>
                <span class="status-badge target-reached">יעד הושג</span>
            <?php endif; ?>
            
            <?php if ($stop_activated): ?>
                <span class="status-badge stop-activated">סטופ הופעל</span>
            <?php endif; ?>
        </div>

        <div class="signal-info-grid">
            <div class="info-row">
                <div class="info-item">
                    <label>מספר נייר:</label>
                    <span class="value"><?php echo esc_html($security_number); ?></span>
                </div>
                <div class="info-item">
                    <label>שם הנייר:</label>
                    <span class="value"><?php echo esc_html($security_name); ?></span>
                </div>
            </div>

            <div class="info-row">
                <div class="info-item">
                    <label>תאריך האיתות:</label>
                    <span class="value"><?php echo $this->format_date_hebrew($signal_date); ?></span>
                </div>
                <?php if (!empty($close_date)): ?>
                <div class="info-item">
                    <label>תאריך סגירה:</label>
                    <span class="value"><?php echo $this->format_date_hebrew($close_date); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div class="info-row">
                <div class="info-item">
                    <label>שער קניה:</label>
                    <span class="value price"><?php echo esc_html($buy_price); ?></span>
                </div>
                <div class="info-item">
                    <label>יעד מחיר:</label>
                    <span class="value price"><?php echo esc_html($target_price); ?></span>
                </div>
            </div>

            <div class="info-row">
                <div class="info-item">
                    <label>מחיר סטופ לוס:</label>
                    <span class="value price"><?php echo esc_html($stop_loss_price); ?></span>
                </div>
                <div class="info-item">
                    <label>שער נוכחי:</label>
                    <span class="value price"><?php echo esc_html($current_price); ?></span>
                </div>
            </div>

            <?php if (!empty($profit_loss_display)): ?>
            <div class="info-row">
                <div class="info-item profit-loss-item">
                    <label>רווח/הפסד:</label>
                    <span class="value profit-loss <?php echo $profit_loss_class; ?>">
                        <?php echo $profit_loss_display; ?>
                    </span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <?php if (get_the_content($signal->ID)): ?>
        <div class="signal-content">
            <h3>פרטים נוספים</h3>
            <div class="content">
                <?php echo apply_filters('the_content', $signal->post_content); ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="signal-performance">
            <h3>ביצועי האיתות</h3>
            <div class="performance-summary">
                <div class="performance-item">
                    <span class="label">מחיר פתיחה:</span>
                    <span class="value"><?php echo esc_html($buy_price); ?></span>
                </div>
                
                <?php if (!empty($current_price)): ?>
                <div class="performance-item">
                    <span class="label">מחיר נוכחי:</span>
                    <span class="value"><?php echo esc_html($current_price); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($profit_loss_display)): ?>
                <div class="performance-item">
                    <span class="label">תשואה:</span>
                    <span class="value profit-loss <?php echo $profit_loss_class; ?>">
                        <?php echo $profit_loss_display; ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($target_price) && is_numeric($target_price) && is_numeric($buy_price)): ?>
                <div class="performance-item">
                    <span class="label">פוטנציאל יעד:</span>
                    <span class="value">
                        <?php 
                        $target_potential = ((floatval($target_price) - floatval($buy_price)) / floatval($buy_price)) * 100;
                        echo number_format($target_potential, 2) . '%';
                        ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    public function show_latest_signals_preview() {
        // Show a preview of the latest available signals when current month is empty
        $latest_signals = new WP_Query([
            'post_type' => 'signal',
            'posts_per_page' => 5,
            'meta_key' => 'signal_date',
            'orderby' => 'meta_value',
            'order' => 'DESC'
        ]);
        
        if ($latest_signals->have_posts()) {
            echo '<div class="latest-signals-preview">';
            echo '<h3>איתותים אחרונים:</h3>';
            $this->render_signals_table($latest_signals);
            echo '</div>';
        }
        
        wp_reset_postdata();
    }
    
    // Helper functions
    public function calculate_profit_loss($buy_price, $current_price) {
        if (empty($buy_price) || empty($current_price) || !is_numeric($buy_price) || !is_numeric($current_price)) {
            return '';
        }
        
        $profit_loss = (($current_price - $buy_price) / $buy_price) * 100;
        return number_format($profit_loss, 2) . '%';
    }
    
    public function format_date_hebrew($date_string) {
        if (empty($date_string)) return '';
        $date = DateTime::createFromFormat('d/m/Y', $date_string);
        if (!$date) {
            $date = DateTime::createFromFormat('Y-m-d', $date_string);
        }
        if ($date) {
            return $date->format('d.m.y');
        }
        return $date_string;
    }
    
    public function get_profit_loss_class($percentage_string) {
        if (empty($percentage_string)) return '';
        
        $value = floatval(str_replace('%', '', $percentage_string));
        if ($value > 0) return 'profit-positive';
        if ($value < 0) return 'profit-negative';
        return 'profit-neutral';
    }
    
    public function enqueue_signals_styles() {
        if (is_account_page()) {
            // Enqueue DataTables CSS and JS
            wp_enqueue_style(
                'datatables-css',
                'https://cdn.datatables.net/1.13.7/css/jquery.dataTables.min.css',
                [],
                '1.13.7'
            );
            
            wp_enqueue_script(
                'datatables-js',
                'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js',
                ['jquery'],
                '1.13.7',
                true
            );
            
            wp_enqueue_style(
                'signals-myaccount',
                HELLO_THEME_STYLE_URL . 'signals-myaccount.css',
                ['datatables-css'],
                HELLO_ELEMENTOR_VERSION
            );
        }
    }
}

// Initialize the Signals Manager
new Signals_Manager();
