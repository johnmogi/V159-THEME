<?php
/**
 * Working Signals Manager - Using Query Parameter Approach
 */

if (!defined('ABSPATH')) {
    exit;
}

class Working_Signals_Manager {
    
    public function __construct() {
        error_log('DEBUG: Working_Signals_Manager constructor called');
        
        // Hook into WordPress init with higher priority
        add_action('init', [$this, 'add_endpoints'], 5);
        add_filter('query_vars', [$this, 'add_query_vars']);
        
        // Hook into WooCommerce with proper timing
        add_action('woocommerce_init', [$this, 'init_woocommerce_hooks']);
        
        // Template hooks for proper content integration
        add_action('template_redirect', [$this, 'handle_signals_endpoint']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_styles']);
        
        // Hook into template to override content when needed
        add_filter('the_content', [$this, 'filter_account_content'], 20);
    }
    
    public function filter_account_content($content) {
        // Handle individual signal posts - redirect to subscription required or show content
        if (is_singular('signal')) {
            if (!$this->user_has_access()) {
                ob_start();
                $this->show_signal_paywall();
                return ob_get_clean();
            } else {
                // Show signal content with custom template
                ob_start();
                $this->show_single_signal_content();
                return ob_get_clean();
            }
        }
        
        return $content;
    }
    
    public function show_signal_paywall() {
        ?>
        <div class="signal-paywall" dir="rtl" style="text-align: center; padding: 40px; background: #fff; border: 2px solid #0073aa; border-radius: 10px; margin: 20px 0;">
            <h2 style="color: #0073aa; margin-bottom: 15px;">גישה לאיתות מוגבלת</h2>
            <p>נדרש מנוי פעיל לצפייה באיתותים בודדים</p>
            <a href="<?php echo wc_get_page_permalink('shop'); ?>" class="button" style="background: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 15px;">הצטרפות למנוי</a>
            <br><br>
            <a href="<?php echo home_url('/my-account/?signals=1'); ?>" style="color: #0073aa; text-decoration: none;">← חזרה לדף האיתותים הראשי</a>
        </div>
        <?php
    }
    
    public function show_single_signal_content() {
        global $post;
        
        $security_number = get_field('security_number');
        $security_name = get_field('security_name');
        $signal_date = get_field('signal_date');
        $buy_price = get_field('buy_price');
        $target_price = get_field('target_price');
        $stop_loss_price = get_field('stop-loss_price');
        $current_price = get_field('current_price');
        $close_date = get_field('close_date');
        $profit_loss = get_field('%_profitloss');
        
        if ($signal_date) {
            $signal_date = date('d/m/Y', strtotime($signal_date));
        }
        if ($close_date) {
            $close_date = date('d/m/Y', strtotime($close_date));
        }
        
        // Calculate live profit/loss if not stored
        if (empty($profit_loss) && empty($close_date) && is_numeric($buy_price) && is_numeric($current_price)) {
            $calc = (($current_price - $buy_price) / $buy_price) * 100;
            $profit_loss = number_format($calc, 2) . '%';
        }
        
        $profit_color = '';
        if (!empty($profit_loss)) {
            $value = floatval(str_replace('%', '', $profit_loss));
            $profit_color = $value >= 0 ? 'color: green; font-weight: bold;' : 'color: red; font-weight: bold;';
        }
        ?>
        
        <div class="single-signal-content" dir="rtl" style="font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto;">
            <div style="background: #f9f9f9; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
                <h1 style="color: #0073aa; margin-bottom: 10px;"><?php echo get_the_title(); ?></h1>
                <p style="color: #666; margin: 0;">פורסם ב: <?php echo get_the_date('d/m/Y'); ?></p>
            </div>
            
            <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <h2 style="color: #0073aa; border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin-bottom: 25px;">פרטי האיתות</h2>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px;">
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                        <strong style="color: #0073aa;">מס׳ נייר:</strong><br>
                        <span style="font-size: 18px;"><?php echo esc_html($security_number); ?></span>
                    </div>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                        <strong style="color: #0073aa;">שם הנייר:</strong><br>
                        <span style="font-size: 18px; font-weight: bold;"><?php echo esc_html($security_name); ?></span>
                    </div>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                        <strong style="color: #0073aa;">תאריך האיתות:</strong><br>
                        <span style="font-size: 18px;"><?php echo esc_html($signal_date); ?></span>
                    </div>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                        <strong style="color: #0073aa;">שער קנייה:</strong><br>
                        <span style="font-size: 18px; font-weight: bold;"><?php echo esc_html($buy_price); ?></span>
                    </div>
                    <div style="background: #e8f5e8; padding: 15px; border-radius: 5px;">
                        <strong style="color: green;">יעד מחיר:</strong><br>
                        <span style="font-size: 18px; color: green; font-weight: bold;"><?php echo esc_html($target_price); ?></span>
                    </div>
                    <div style="background: #ffe8e8; padding: 15px; border-radius: 5px;">
                        <strong style="color: red;">סטופ לוס:</strong><br>
                        <span style="font-size: 18px; color: red; font-weight: bold;"><?php echo esc_html($stop_loss_price); ?></span>
                    </div>
                </div>
                
                <div style="background: #f0f8ff; padding: 20px; border-radius: 10px; text-align: center;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                        <div>
                            <strong style="color: #0073aa;">מחיר נוכחי:</strong><br>
                            <span style="font-size: 24px; font-weight: bold;"><?php echo esc_html($current_price ?: '—'); ?></span>
                        </div>
                        <div>
                            <strong style="color: #0073aa;">תאריך סגירה:</strong><br>
                            <span style="font-size: 18px;"><?php echo esc_html($close_date ?: 'פעיל'); ?></span>
                        </div>
                        <div>
                            <strong style="color: #0073aa;">רווח/הפסד:</strong><br>
                            <span style="font-size: 24px; <?php echo $profit_color; ?>"><?php echo $profit_loss ?: '—'; ?></span>
                        </div>
                    </div>
                </div>
                
                <?php if ($post->post_content): ?>
                <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                    <h3 style="color: #0073aa;">פרטים נוספים:</h3>
                    <?php echo wpautop($post->post_content); ?>
                </div>
                <?php endif; ?>
                
                <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                    <?php echo '<div style="margin-top: 30px; text-align: center;">'; ?>
                    <?php echo '<a href="' . add_query_arg('signals', '1', wc_get_account_endpoint_url('dashboard')) . '" style="background: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block;">← חזרה לכל האיתוטים</a>'; ?>
                    <?php echo '</div>'; ?>
                    
                    <!-- Add DataTables CSS and JS -->
                    <?php echo '<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">'; ?>
                    <?php echo '<script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>'; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function add_menu_item($items) {
        $new_items = [];
        foreach ($items as $key => $item) {
            $new_items[$key] = $item;
            if ($key === 'dashboard') {
                $new_items['signals'] = 'איתותים';
            }
            // Remove redundant Subscriptions link
            if ($key === 'wps_subscriptions') {
                continue;
            }
        }
        return $new_items;
    }
    
    public function add_endpoints() {
        add_rewrite_endpoint('signals', EP_ROOT | EP_PAGES);
    }
    
    public function init_woocommerce_hooks() {
        error_log('DEBUG: WooCommerce hooks initialized');
        add_filter('woocommerce_account_menu_items', [$this, 'add_menu_item']);
        add_action('woocommerce_account_content', [$this, 'maybe_show_signals_content'], 5);
        add_action('woocommerce_account_signals_endpoint', [$this, 'signals_content']);
    }
    
    public function add_query_vars($vars) {
        $vars[] = 'signals';
        return $vars;
    }
    
    public function handle_signals_endpoint() {
        global $wp_query;
        
        // Only redirect direct signals URL, not query parameter version
        if (strpos($_SERVER['REQUEST_URI'], '/my-account/signals/') !== false && !isset($_GET['signals'])) {
            wp_redirect(home_url('/my-account/?signals=1'));
            exit;
        }
    }
    
    public function maybe_show_signals_content() {
        global $wp_query;
        
        // Only show on My Account page with signals parameter
        if (!is_account_page()) {
            return;
        }
        
        // Check if we're viewing the signals section via query parameter
        if (isset($_GET['signals'])) {
            $this->signals_content();
            // Prevent default content
            return true;
        }
        
        // Check if we're on the signals endpoint
        if (isset($wp_query->query_vars['signals'])) {
            $this->signals_content();
            return true;
        }
    }
    
    public function signals_content() {
        error_log('DEBUG: Signals content called - user ID: ' . get_current_user_id());
        
        // Output content directly without exit/die to integrate with WooCommerce
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
            <h2 style="color: #0073aa; margin-bottom: 15px;">גישה לאיתוטים</h2>
            <p>נדרש מנוי פעיל לצפייה באיתוטים</p>
            <a href="<?php echo wc_get_page_permalink('shop'); ?>" class="button" style="background: #0073aa; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 15px;">הצטרפות למנוי</a>
        </div>
        <?php
    }
    
    public function get_signals_data($month, $year) {
        // Debug logging
        error_log('DEBUG: Getting signals data for month: ' . $month . ', year: ' . $year);
        
        $args = [
            'post_type' => 'signal',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ];
        
        // Temporarily remove date filter to show all signals for debugging
        // if ($month && $year) {
        //     $start_date = $year . '-' . sprintf('%02d', $month) . '-01';
        //     $end_date = $year . '-' . sprintf('%02d', $month) . '-31';
        //     
        //     error_log('DEBUG: Date filter - Start: ' . $start_date . ', End: ' . $end_date);
        //     
        //     $args['meta_query'] = [
        //         [
        //             'key' => 'signal_date',
        //             'value' => [$start_date, $end_date],
        //             'compare' => 'BETWEEN',
        //             'type' => 'DATE'
        //         ]
        //     ];
        // }
        
        $query = new WP_Query($args);
        $signals = [];
        
        error_log('DEBUG: Query found ' . $query->found_posts . ' signal posts');
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                error_log('DEBUG: Processing signal post ID: ' . $post_id);
                
                $signal_date = get_field('signal_date', $post_id);
                if ($signal_date) {
                    $signal_date = date('d/m/Y', strtotime($signal_date));
                }
                
                $close_date = get_field('close_date', $post_id);
                if ($close_date) {
                    $close_date = date('d/m/Y', strtotime($close_date));
                }
                
                $signal_data = [
                    'security_number' => get_field('security_number', $post_id) ?: '',
                    'security_name' => get_field('security_name', $post_id) ?: get_the_title(),
                    'signal_date' => $signal_date ?: '',
                    'buy_price' => get_field('buy_price', $post_id) ?: '',
                    'target_price' => get_field('target_price', $post_id) ?: '',
                    'stop_loss_price' => get_field('stop-loss_price', $post_id) ?: '',
                    'current_price' => get_field('current_price', $post_id) ?: '',
                    'close_date' => $close_date ?: '',
                    'profit_loss' => get_field('%_profitloss', $post_id) ?: ''
                ];
                
                error_log('DEBUG: Signal data: ' . print_r($signal_data, true));
                $signals[] = $signal_data;
            }
        }
        wp_reset_postdata();
        
        error_log('DEBUG: Returning ' . count($signals) . ' signals');
        return $signals;
    }
    
    public function show_signals_dashboard() {
        $current_month = isset($_GET['month']) ? intval($_GET['month']) : 8; // Default to August where sample data exists
        $current_year = isset($_GET['year']) ? intval($_GET['year']) : 2025;
        
        // Get real signals from CPT
        $signals = $this->get_signals_data($current_month, $current_year);
        
        $hebrew_months = [
            1 => 'ינואר', 2 => 'פברואר', 3 => 'מרץ', 4 => 'אפריל',
            5 => 'מאי', 6 => 'יוני', 7 => 'יולי', 8 => 'אוגוסט',
            9 => 'ספטמבר', 10 => 'אוקטובר', 11 => 'נובמבר', 12 => 'דצמבר'
        ];
        
        // Query signals - simplified to show all signals for now
        $signals = new WP_Query([
            'post_type' => 'signal',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'date',
            'order' => 'DESC'
        ]);
        
        // Debug logging
        error_log('Signals query - Found: ' . $signals->found_posts . ' signals');
        
        ?>
        <div class="signals-dashboard" dir="rtl" style="font-family: Arial, sans-serif; max-width: 1200px; margin: 0 auto;">
            <h2 style="color: #0073aa; border-bottom: 2px solid #0073aa; padding-bottom: 10px; margin-bottom: 20px;">
                איתותים - <?php echo $hebrew_months[$current_month] . ' ' . $current_year; ?>
            </h2>
            
            <div class="month-navigation" style="background: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center;">
                <select onchange="navigateMonth()" id="month-select" style="padding: 8px 12px; margin: 0 5px; border: 1px solid #ddd; border-radius: 3px; font-size: 14px;">
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?php echo $i; ?>" <?php selected($current_month, $i); ?>>
                            <?php echo $hebrew_months[$i]; ?>
                        </option>
                    <?php endfor; ?>
                </select>
                
                <select onchange="navigateMonth()" id="year-select" style="padding: 8px 12px; margin: 0 5px; border: 1px solid #ddd; border-radius: 3px; font-size: 14px;">
                    <?php for ($y = 2020; $y <= date('Y') + 2; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php selected($current_year, $y); ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <?php if (!empty($signals)): ?>
                <div style="overflow-x: auto; margin: 20px 0;">
                    <table id="signals-table" class="signals-table" style="width: 100%; border-collapse: collapse; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border-radius: 5px; overflow: hidden; background: white;">
                        <thead>
                            <tr style="background: #0073aa !important; color: white !important;">
                                <th style="padding: 12px; border: 1px solid #0073aa; text-align: center; font-weight: bold; color: white !important;">מס׳ נייר</th>
                                <th style="padding: 12px; border: 1px solid #0073aa; text-align: center; font-weight: bold; color: white !important;">שם הנייר</th>
                                <th style="padding: 12px; border: 1px solid #0073aa; text-align: center; font-weight: bold; color: white !important;">תאריך האיתות</th>
                                <th style="padding: 12px; border: 1px solid #0073aa; text-align: center; font-weight: bold; color: white !important;">שער הקניה</th>
                                <th style="padding: 12px; border: 1px solid #0073aa; text-align: center; font-weight: bold; color: white !important;">יעד מחיר</th>
                                <th style="padding: 12px; border: 1px solid #0073aa; text-align: center; font-weight: bold; color: white !important;">מחיר סטופ לוס</th>
                                <th style="padding: 12px; border: 1px solid #0073aa; text-align: center; font-weight: bold; color: white !important;">שער נוכחי</th>
                                <th style="padding: 12px; border: 1px solid #0073aa; text-align: center; font-weight: bold; color: white !important;">תאריך הסגירה</th>
                                <th style="padding: 12px; border: 1px solid #0073aa; text-align: center; font-weight: bold; color: white !important;">אחוז רווח או הפסד</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $row_count = 0;
                            foreach ($signals as $signal): 
                                $row_count++;
                                
                                $security_number = isset($signal['security_number']) ? $signal['security_number'] : '';
                                $security_name = isset($signal['security_name']) ? $signal['security_name'] : '';
                                $signal_date = isset($signal['signal_date']) ? $signal['signal_date'] : '';
                                $buy_price = isset($signal['buy_price']) ? $signal['buy_price'] : '';
                                $target_price = isset($signal['target_price']) ? $signal['target_price'] : '';
                                $stop_loss_price = isset($signal['stop_loss_price']) ? $signal['stop_loss_price'] : '';
                                $current_price = isset($signal['current_price']) ? $signal['current_price'] : '';
                                $close_date = isset($signal['close_date']) ? $signal['close_date'] : '';
                                $profit_loss = isset($signal['profit_loss']) ? $signal['profit_loss'] : '';
                                
                                // Calculate live profit/loss if not stored
                                if (empty($profit_loss) && empty($close_date) && is_numeric($buy_price) && is_numeric($current_price)) {
                                    $calc = (($current_price - $buy_price) / $buy_price) * 100;
                                    $profit_loss = number_format($calc, 2) . '%';
                                }
                                
                                $profit_color = '';
                                if (!empty($profit_loss)) {
                                    $value = floatval(str_replace('%', '', $profit_loss));
                                    $profit_color = $value >= 0 ? 'color: green; font-weight: bold;' : 'color: red; font-weight: bold;';
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
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; background: #f9f9f9; border-radius: 5px; margin: 20px 0;">
                    <h3 style="color: #666;">לא נמצאו איתותים</h3>
                    <p>לא נמצאו איתותים עבור <?php echo $hebrew_months[$current_month] . ' ' . $current_year; ?></p>
                </div>
            <?php endif; ?>
            
            <!-- Add DataTables CSS and JS -->
            <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.css">
            <script type="text/javascript" charset="utf8" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.js"></script>
            <script>
            jQuery(document).ready(function($) {
                $('#signals-table').DataTable({
                    "language": {
                        "url": "//cdn.datatables.net/plug-ins/1.11.5/i18n/Hebrew.json"
                    },
                    "order": [[ 2, "desc" ]],
                    "pageLength": 25,
                    "responsive": true
                });
            });
            
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
