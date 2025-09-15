<?php
/**
 * Single Signal Post Template
 * RTL Hebrew layout for individual signal posts
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Helper functions
function format_date_hebrew($date_string) {
    if (empty($date_string)) return '';
    $date = DateTime::createFromFormat('d/m/Y', $date_string);
    if (!$date) {
        $date = DateTime::createFromFormat('Y-m-d', $date_string);
    }
    if ($date) {
        return $date->format('d.m.Y');
    }
    return $date_string;
}

function calculate_profit_loss($buy_price, $current_price) {
    if (empty($buy_price) || empty($current_price) || !is_numeric($buy_price) || !is_numeric($current_price)) {
        return '';
    }
    
    $profit_loss = (($current_price - $buy_price) / $buy_price) * 100;
    return number_format($profit_loss, 2) . '%';
}

function get_profit_loss_class($percentage_string) {
    if (empty($percentage_string)) return '';
    
    $value = floatval(str_replace('%', '', $percentage_string));
    if ($value > 0) return 'profit-positive';
    if ($value < 0) return 'profit-negative';
    return 'profit-neutral';
}

while (have_posts()) : the_post();
    // Get ACF fields
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
    
    // Get signal date for archive link
    $signal_date_obj = DateTime::createFromFormat('d/m/Y', $signal_date);
    if (!$signal_date_obj) {
        $signal_date_obj = DateTime::createFromFormat('Y-m-d', $signal_date);
    }
    $archive_month = $signal_date_obj ? $signal_date_obj->format('n') : date('n');
    $archive_year = $signal_date_obj ? $signal_date_obj->format('Y') : date('Y');
?>

<div class="single-signal" dir="rtl">
    <div class="container">
        <!-- Back to Archive Link -->
        <div class="back-to-archive">
            <a href="<?php echo home_url('/signal/?month=' . $archive_month . '&year=' . $archive_year); ?>" class="back-link">
                ← חזרה לארכיון איתותים
            </a>
        </div>

        <!-- Signal Header -->
        <header class="signal-header">
            <h1 class="signal-title">
                איתות מס׳ <?php echo esc_html($security_number); ?> - <?php echo esc_html($security_name); ?>
            </h1>
            
            <div class="signal-status">
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
        </header>

        <!-- Signal Details -->
        <div class="signal-details">
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
                        <span class="value"><?php echo format_date_hebrew($signal_date); ?></span>
                    </div>
                    <?php if (!empty($close_date)): ?>
                    <div class="info-item">
                        <label>תאריך סגירה:</label>
                        <span class="value"><?php echo format_date_hebrew($close_date); ?></span>
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
        </div>

        <!-- Signal Content -->
        <?php if (get_the_content()): ?>
        <div class="signal-content">
            <h2>פרטים נוספים</h2>
            <div class="content">
                <?php the_content(); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Signal Performance Chart Placeholder -->
        <div class="signal-performance">
            <h2>ביצועי האיתות</h2>
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

        <!-- Navigation -->
        <div class="signal-navigation">
            <div class="nav-links">
                <?php
                $prev_post = get_previous_post();
                $next_post = get_next_post();
                ?>
                
                <?php if ($prev_post): ?>
                <div class="nav-previous">
                    <a href="<?php echo get_permalink($prev_post); ?>" class="nav-link">
                        <span class="nav-direction">← איתות קודם</span>
                        <span class="nav-title"><?php echo get_field('security_name', $prev_post->ID); ?></span>
                    </a>
                </div>
                <?php endif; ?>
                
                <?php if ($next_post): ?>
                <div class="nav-next">
                    <a href="<?php echo get_permalink($next_post); ?>" class="nav-link">
                        <span class="nav-direction">איתות הבא →</span>
                        <span class="nav-title"><?php echo get_field('security_name', $next_post->ID); ?></span>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php endwhile; ?>

<?php get_footer(); ?>
