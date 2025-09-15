<?php
/**
 * Working Signals Manager - Using Query Parameter Approach
 */

if (!defined('ABSPATH')) {
    exit;
}

class Working_Signals_Manager {
    
    public function __construct() {
        add_filter('woocommerce_account_menu_items', [$this, 'add_menu_item']);
        add_action('woocommerce_account_content', [$this, 'maybe_show_signals_content']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
        
        // Handle menu item clicks
        add_filter('woocommerce_get_endpoint_url', [$this, 'signals_endpoint_url'], 10, 4);
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
    
    public function signals_endpoint_url($url, $endpoint, $value, $permalink) {
        if ($endpoint === 'signals') {
            return wc_get_account_endpoint_url('dashboard') . '?signals=1';
        }
        return $url;
    }
    
    public function maybe_show_signals_content() {
        // Check if we're viewing the signals section
        if (isset($_GET['signals']) || (isset($_GET['page']) && $_GET['page'] === 'signals')) {
            $this->signals_content();
            return;
        }
        
        // Check if current URL contains signals
        $current_url = $_SERVER['REQUEST_URI'];
        if (strpos($current_url, '/signals') !== false) {
            $this->signals_content();
            return;
        }
    }
    
    public function signals_content() {
        error_log('Signals content called - user ID: ' . get_current_user_id());
        
        if (!$this->user_has_access()) {
            $this->show_paywall();
            return;
        }
        
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
        
        // For now, allow all logged-in users
        return true;
    }
    
    public function show_paywall() {
        ?>
        <div class="signals-paywall" dir="rtl" style="text-align: center; padding: 40px; background: #fff; border: 2px solid #0073aa; border-radius: 10px; margin: 20px 0;">
            <h2 style="color: #0073aa; margin-bottom: 15px;">גישה לאיתותים</h2>
            <p>נדרש מנוי פעיל לצפייה באיתותים</p>
            <a href="<?php echo wc_get_page_permalink('shop'); ?>" class="button" style="background: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 15px;">הצטרפות למנוי</a>
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
        <div class="signals-dashboard" dir="rtl" style="font-family: Arial, sans-serif;">
            <h2 style="color: #0073aa; border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin-bottom: 20px;">
                איתותים - <?php echo $hebrew_months[$month] . ' ' . $year; ?>
            </h2>
            
            <div class="month-navigation" style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center;">
                <select onchange="navigateMonth()" id="month-select" style="padding: 8px 12px; margin: 0 5px; border: 1px solid #ddd; border-radius: 3px; font-size: 14px;">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php selected($month, $i); ?>>
                            <?php echo $hebrew_months[$i]; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                
                <select onchange="navigateMonth()" id="year-select" style="padding: 8px 12px; margin: 0 5px; border: 1px solid #ddd; border-radius: 3px; font-size: 14px;">
                    <?php for ($y = 2020; $y <= date('Y') + 2; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php selected($year, $y); ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <?php if ($signals->have_posts()): ?>
                <div style="overflow-x: auto;">
                    <table class="signals-table" style="width: 100%; border-collapse: collapse; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-radius: 5px; overflow: hidden;">
                        <thead>
                            <tr style="background: #0073aa; color: white;">
                                <th style="padding: 12px; border: 1px solid #ddd; text-align: center; font-weight: bold;">מס׳ נייר</th>
                                <th style="padding: 12px; border: 1px solid #ddd; text-align: center; font-weight: bold;">שם הנייר</th>
                                <th style="padding: 12px; border: 1px solid #ddd; text-align: center; font-weight: bold;">תאריך האיתות</th>
                                <th style="padding: 12px; border: 1px solid #ddd; text-align: center; font-weight: bold;">שער הקניה</th>
                                <th style="padding: 12px; border: 1px solid #ddd; text-align: center; font-weight: bold;">יעד מחיר</th>
                                <th style="padding: 12px; border: 1px solid #ddd; text-align: center; font-weight: bold;">מחיר סטופ לוס</th>
                                <th style="padding: 12px; border: 1px solid #ddd; text-align: center; font-weight: bold;">שער נוכחי</th>
                                <th style="padding: 12px; border: 1px solid #ddd; text-align: center; font-weight: bold;">תאריך הסגירה</th>
                                <th style="padding: 12px; border: 1px solid #ddd; text-align: center; font-weight: bold;">אחוז רווח או הפסד</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $row_count = 0;
                            while ($signals->have_posts()): 
                                $signals->the_post(); 
                                $row_count++;
                                
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
                                    $profit_color = $value > 0 ? 'color: green; font-weight: bold;' : ($value < 0 ? 'color: red; font-weight: bold;' : '');
                                }
                                
                                $row_bg = $row_count % 2 == 0 ? 'background-color: #f9f9f9;' : 'background-color: white;';
                            ?>
                                <tr style="<?php echo $row_bg; ?>" onmouseover="this.style.backgroundColor='#f0f8ff'" onmouseout="this.style.backgroundColor='<?php echo $row_count % 2 == 0 ? '#f9f9f9' : 'white'; ?>'">
                                    <td style="padding: 10px; border: 1px solid #ddd; text-align: center;"><?php echo esc_html($security_number); ?></td>
                                    <td style="padding: 10px; border: 1px solid #ddd; text-align: center; font-weight: bold;"><?php echo esc_html($security_name); ?></td>
                                    <td style="padding: 10px; border: 1px solid #ddd; text-align: center;"><?php echo esc_html($signal_date); ?></td>
                                    <td style="padding: 10px; border: 1px solid #ddd; text-align: center;"><?php echo esc_html($buy_price); ?></td>
                                    <td style="padding: 10px; border: 1px solid #ddd; text-align: center;"><?php echo esc_html($target_price); ?></td>
                                    <td style="padding: 10px; border: 1px solid #ddd; text-align: center;"><?php echo esc_html($stop_loss_price); ?></td>
                                    <td style="padding: 10px; border: 1px solid #ddd; text-align: center;"><?php echo esc_html($current_price); ?></td>
                                    <td style="padding: 10px; border: 1px solid #ddd; text-align: center;"><?php echo esc_html($close_date); ?></td>
                                    <td style="padding: 10px; border: 1px solid #ddd; text-align: center; <?php echo $profit_color; ?>"><?php echo $profit_loss ?: '—'; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; background: #f9f9f9; border-radius: 5px; margin: 20px 0;">
                    <h3 style="color: #666;">לא נמצאו איתותים</h3>
                    <p>לא נמצאו איתותים עבור <?php echo $hebrew_months[$month] . ' ' . $year; ?></p>
                </div>
            <?php endif; ?>
            
            <script>
            function navigateMonth() {
                const month = document.getElementById('month-select').value;
                const year = document.getElementById('year-select').value;
                const currentUrl = window.location.href.split('?')[0];
                window.location.href = currentUrl + '?signals=1&month=' + month + '&year=' + year;
            }
            </script>
        </div>
        <?php
        
        wp_reset_postdata();
    }
    
    public function enqueue_styles() {
        if (is_account_page()) {
            wp_enqueue_style(
                'signals-working',
                get_template_directory_uri() . '/assets/css/signals-working.css',
                [],
                '1.0.0'
            );
        }
    }
}

new Working_Signals_Manager();
