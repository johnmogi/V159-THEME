<?php
/**
 * Archive template for Signal CPT
 * RTL Hebrew Signals Archive with month/year filtering
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Get current month and year from URL or default to current
$current_month = isset($_GET['month']) ? intval($_GET['month']) : date('n');
$current_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');

// Hebrew month names
$hebrew_months = [
    1 => 'ינואר', 2 => 'פברואר', 3 => 'מרץ', 4 => 'אפריל',
    5 => 'מאי', 6 => 'יוני', 7 => 'יולי', 8 => 'אוגוסט',
    9 => 'ספטמבר', 10 => 'אוקטובר', 11 => 'נובמבר', 12 => 'דצמבר'
];

// Calculate previous and next month/year
$prev_month = $current_month - 1;
$prev_year = $current_year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $current_month + 1;
$next_year = $current_year;
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
                $current_year . '-' . sprintf('%02d', $current_month) . '-01',
                $current_year . '-' . sprintf('%02d', $current_month) . '-31'
            ],
            'compare' => 'BETWEEN',
            'type' => 'DATE'
        ]
    ],
    'meta_key' => 'signal_date',
    'orderby' => 'meta_value',
    'order' => 'DESC'
]);

function calculate_profit_loss($buy_price, $current_price) {
    if (empty($buy_price) || empty($current_price) || !is_numeric($buy_price) || !is_numeric($current_price)) {
        return '';
    }
    
    $profit_loss = (($current_price - $buy_price) / $buy_price) * 100;
    return number_format($profit_loss, 2) . '%';
}

function format_date_hebrew($date_string) {
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

function get_profit_loss_class($percentage_string) {
    if (empty($percentage_string)) return '';
    
    $value = floatval(str_replace('%', '', $percentage_string));
    if ($value > 0) return 'profit-positive';
    if ($value < 0) return 'profit-negative';
    return 'profit-neutral';
}
?>

<div class="signals-archive" dir="rtl">
    <div class="container">
        <!-- Archive Header -->
        <div class="signals-header">
            <div class="month-navigation">
                <a href="?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="nav-arrow">‹</a>
                
                <div class="month-selectors">
                    <select id="month-selector" onchange="navigateToMonth()">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php selected($current_month, $i); ?>>
                                <?php echo $hebrew_months[$i]; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                    
                    <select id="year-selector" onchange="navigateToMonth()">
                        <?php for ($year = date('Y') - 5; $year <= date('Y') + 2; $year++): ?>
                            <option value="<?php echo $year; ?>" <?php selected($current_year, $year); ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <a href="?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="nav-arrow">›</a>
            </div>
            
            <h1 class="archive-title"><?php echo $hebrew_months[$current_month] . ' ' . $current_year; ?></h1>
        </div>

        <?php if ($signals_query->have_posts()): ?>
            <!-- Top Summary Table -->
            <div class="signals-summary-table">
                <table class="signals-table">
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
                        <?php while ($signals_query->have_posts()): $signals_query->the_post(); 
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
                                $profit_loss_display = calculate_profit_loss($buy_price, $current_price);
                            }
                            
                            $profit_loss_class = get_profit_loss_class($profit_loss_display);
                            
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
                            <tr class="signal-row <?php echo empty($close_date) ? 'signal-open' : 'signal-closed'; ?>">
                                <td>
                                    <a href="<?php the_permalink(); ?>" class="security-number-link">
                                        <?php echo esc_html($security_number); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($security_name); ?></td>
                                <td><?php echo format_date_hebrew($signal_date); ?></td>
                                <td><?php echo esc_html($buy_price); ?></td>
                                <td>
                                    <?php echo esc_html($target_price); ?>
                                    <?php if ($target_reached): ?>
                                        <span class="badge target-reached">יעד הושג</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo esc_html($stop_loss_price); ?>
                                    <?php if ($stop_activated): ?>
                                        <span class="badge stop-activated">סטופ הופעל</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($current_price); ?></td>
                                <td><?php echo format_date_hebrew($close_date); ?></td>
                                <td class="profit-loss <?php echo $profit_loss_class; ?>">
                                    <?php echo $profit_loss_display ?: '—'; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Running List -->
            <div class="signals-running-list">
                <h2>רשימת עסקאות</h2>
                <div class="running-list-table">
                    <table class="signals-table running-table">
                        <thead>
                            <tr>
                                <th>מס׳</th>
                                <th>שם הנייר</th>
                                <th>תאריך פתיחה</th>
                                <th>מחיר קניה</th>
                                <th>יעד</th>
                                <th>סטופ</th>
                                <th>מחיר נוכחי</th>
                                <th>סגירה</th>
                                <th>רווח/הפסד %</th>
                                <th>סטטוס</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $signals_query->rewind_posts();
                            while ($signals_query->have_posts()): $signals_query->the_post(); 
                                $security_number = get_field('security_number');
                                $security_name = get_field('security_name');
                                $signal_date = get_field('signal_date');
                                $buy_price = get_field('buy_price');
                                $target_price = get_field('target_price');
                                $stop_loss_price = get_field('stop-loss_price');
                                $current_price = get_field('current_price');
                                $close_date = get_field('close_date');
                                $profit_loss_stored = get_field('%_profitloss');
                                
                                $profit_loss_display = $profit_loss_stored;
                                if (empty($profit_loss_display) && empty($close_date)) {
                                    $profit_loss_display = calculate_profit_loss($buy_price, $current_price);
                                }
                                
                                $profit_loss_class = get_profit_loss_class($profit_loss_display);
                                $status = empty($close_date) ? 'פתוח' : 'סגור';
                            ?>
                                <tr class="running-row <?php echo empty($close_date) ? 'signal-open' : 'signal-closed'; ?>">
                                    <td>
                                        <a href="<?php the_permalink(); ?>">
                                            <?php echo esc_html($security_number); ?>
                                        </a>
                                    </td>
                                    <td><?php echo esc_html($security_name); ?></td>
                                    <td><?php echo format_date_hebrew($signal_date); ?></td>
                                    <td><?php echo esc_html($buy_price); ?></td>
                                    <td><?php echo esc_html($target_price); ?></td>
                                    <td><?php echo esc_html($stop_loss_price); ?></td>
                                    <td><?php echo esc_html($current_price); ?></td>
                                    <td><?php echo format_date_hebrew($close_date); ?></td>
                                    <td class="profit-loss <?php echo $profit_loss_class; ?>">
                                        <?php echo $profit_loss_display ?: '—'; ?>
                                    </td>
                                    <td class="status <?php echo empty($close_date) ? 'status-open' : 'status-closed'; ?>">
                                        <?php echo $status; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        <?php else: ?>
            <div class="no-signals">
                <p>לא נמצאו איתותים עבור <?php echo $hebrew_months[$current_month] . ' ' . $current_year; ?></p>
            </div>
        <?php endif; ?>

        <?php wp_reset_postdata(); ?>
    </div>
</div>

<script>
function navigateToMonth() {
    const month = document.getElementById('month-selector').value;
    const year = document.getElementById('year-selector').value;
    window.location.href = '?month=' + month + '&year=' + year;
}
</script>

<?php get_footer(); ?>
