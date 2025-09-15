<?php
/**
 * Simple Signals Manager - Direct WooCommerce My Account Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class Simple_Signals_Manager {
    
    public function __construct() {
        add_action('init', [$this, 'add_endpoints'], 0);
        add_filter('woocommerce_account_menu_items', [$this, 'add_menu_item']);
        add_action('woocommerce_account_signals_endpoint', [$this, 'signals_content']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
        
        // Hook into template redirect to handle the endpoint
        add_action('template_redirect', [$this, 'handle_signals_endpoint']);
    }
    
    public function add_endpoints() {
        add_rewrite_endpoint('signals', EP_ROOT | EP_PAGES);
        
        // Also register with WooCommerce
        if (function_exists('wc_get_account_endpoint_url')) {
            // Add to WooCommerce query vars
            add_filter('woocommerce_get_query_vars', function($query_vars) {
                $query_vars['signals'] = 'signals';
                return $query_vars;
            });
        }
    }
    
    public function add_menu_item($items) {
        $new_items = [];
        foreach ($items as $key => $item) {
            $new_items[$key] = $item;
            if ($key === 'dashboard') {
                $new_items['signals'] = 'איתותים';
            }
        }
        return $new_items;
    }
    
    public function handle_signals_endpoint() {
        global $wp_query;
        
        // Check if we're on the My Account page and signals endpoint
        if (is_account_page() && isset($wp_query->query_vars['signals'])) {
            error_log('Signals endpoint detected via template_redirect');
            
            // Remove default WooCommerce content
            remove_action('woocommerce_account_content', 'woocommerce_account_content');
            
            // Add our content
            add_action('woocommerce_account_content', [$this, 'signals_content']);
        }
    }
    
    public function signals_content() {
        // Debug logging
        error_log('Signals endpoint called - user ID: ' . get_current_user_id());
        
        if (!$this->user_has_access()) {
            error_log('User does not have access, showing paywall');
            $this->show_paywall();
            return;
        }
        
        error_log('User has access, showing dashboard');
        $this->show_signals_dashboard();
    }
    
    public function user_has_access() {
        if (!is_user_logged_in()) {
            return false;
        }
        
        // Admin always has access
        if (current_user_can('manage_options')) {
            return true;
        }
        
        // For now, allow all logged-in users (you can add subscription check later)
        return true;
    }
    
    public function show_paywall() {
        ?>
        <div class="signals-paywall" dir="rtl" style="text-align: center; padding: 40px;">
            <h2>גישה לאיתותים</h2>
            <p>נדרש מנוי פעיל לצפייה באיתותים</p>
            <a href="<?php echo wc_get_page_permalink('shop'); ?>" class="button">הצטרפות למנוי</a>
        </div>
        <?php
    }
    
    public function show_signals_dashboard() {
        // Get current month/year
        $month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
        $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        
        // Hebrew months
        $hebrew_months = [
            1 => 'ינואר', 2 => 'פברואר', 3 => 'מרץ', 4 => 'אפריל',
            5 => 'מאי', 6 => 'יוני', 7 => 'יולי', 8 => 'אוגוסט',
            9 => 'ספטמבר', 10 => 'אוקטובר', 11 => 'נובמבר', 12 => 'דצמבר'
        ];
        
        // Query signals
        $signals = new WP_Query([
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
            <h2>איתותים - <?php echo $hebrew_months[$month] . ' ' . $year; ?></h2>
            
            <div class="month-navigation" style="margin: 20px 0; text-align: center;">
                <select onchange="navigateMonth()" id="month-select">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php selected($month, $i); ?>>
                            <?php echo $hebrew_months[$i]; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                
                <select onchange="navigateMonth()" id="year-select">
                    <?php for ($y = 2020; $y <= date('Y') + 2; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php selected($year, $y); ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <?php if ($signals->have_posts()): ?>
                <table class="signals-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #0073aa; color: white;">
                            <th style="padding: 10px; border: 1px solid #ddd;">מס׳ נייר</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">שם הנייר</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">תאריך האיתות</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">שער הקניה</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">יעד מחיר</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">מחיר סטופ לוס</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">שער נוכחי</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">תאריך הסגירה</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">אחוז רווח או הפסד</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($signals->have_posts()): $signals->the_post(); 
                            $security_number = get_field('security_number');
                            $security_name = get_field('security_name');
                            $signal_date = get_field('signal_date');
                            $buy_price = get_field('buy_price');
                            $target_price = get_field('target_price');
                            $stop_loss_price = get_field('stop-loss_price');
                            $current_price = get_field('current_price');
                            $close_date = get_field('close_date');
                            $profit_loss = get_field('%_profitloss');
                            
                            // Calculate live profit/loss if not stored
                            if (empty($profit_loss) && empty($close_date) && is_numeric($buy_price) && is_numeric($current_price)) {
                                $calc = (($current_price - $buy_price) / $buy_price) * 100;
                                $profit_loss = number_format($calc, 2) . '%';
                            }
                            
                            $profit_color = '';
                            if (!empty($profit_loss)) {
                                $value = floatval(str_replace('%', '', $profit_loss));
                                $profit_color = $value > 0 ? 'color: green;' : ($value < 0 ? 'color: red;' : '');
                            }
                        ?>
                            <tr>
                                <td style="padding: 8px; border: 1px solid #ddd; text-align: center;"><?php echo esc_html($security_number); ?></td>
                                <td style="padding: 8px; border: 1px solid #ddd; text-align: center;"><?php echo esc_html($security_name); ?></td>
                                <td style="padding: 8px; border: 1px solid #ddd; text-align: center;"><?php echo esc_html($signal_date); ?></td>
                                <td style="padding: 8px; border: 1px solid #ddd; text-align: center;"><?php echo esc_html($buy_price); ?></td>
                                <td style="padding: 8px; border: 1px solid #ddd; text-align: center;"><?php echo esc_html($target_price); ?></td>
                                <td style="padding: 8px; border: 1px solid #ddd; text-align: center;"><?php echo esc_html($stop_loss_price); ?></td>
                                <td style="padding: 8px; border: 1px solid #ddd; text-align: center;"><?php echo esc_html($current_price); ?></td>
                                <td style="padding: 8px; border: 1px solid #ddd; text-align: center;"><?php echo esc_html($close_date); ?></td>
                                <td style="padding: 8px; border: 1px solid #ddd; text-align: center; <?php echo $profit_color; ?>"><?php echo $profit_loss ?: '—'; ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; padding: 40px;">לא נמצאו איתותים עבור <?php echo $hebrew_months[$month] . ' ' . $year; ?></p>
            <?php endif; ?>
            
            <script>
            function navigateMonth() {
                const month = document.getElementById('month-select').value;
                const year = document.getElementById('year-select').value;
                window.location.href = '<?php echo wc_get_account_endpoint_url('signals'); ?>?month=' + month + '&year=' + year;
            }
            </script>
        </div>
        <?php
        
        wp_reset_postdata();
    }
    
    public function enqueue_styles() {
        if (is_account_page()) {
            wp_enqueue_style(
                'signals-simple',
                get_template_directory_uri() . '/assets/css/signals-simple.css',
                [],
                '1.0.0'
            );
        }
    }
}

new Simple_Signals_Manager();
