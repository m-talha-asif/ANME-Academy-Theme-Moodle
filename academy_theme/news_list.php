<?php
// This file is a custom News & Events Archive for the Academy Theme
require_once('../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_login(); // Ensure the user is logged in

// Set up the standard Moodle page layout
$PAGE->set_url('/theme/academy_theme/news_list.php');
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title('All News & Events');
$PAGE->set_heading('News & Events');

echo $OUTPUT->header();

// 1. Query the database for the 50 most recent site events, sorted by date (newest first)
$sql_news = "SELECT id, name, description, timestart 
             FROM {event} 
             WHERE eventtype = 'site' 
             ORDER BY timestart DESC 
             LIMIT 50";
        
$allevents = $DB->get_records_sql($sql_news);

$list_items = [];
$eventcontext = context_course::instance(SITEID);

if ($allevents) {
    foreach ($allevents as $event) {
        $raw_title = $event->name;
        $is_valid = false;
        $item_type = 'news';
        
        // 2. Filter out normal calendar events; only keep "News:" or "Event:"
        if (stripos($raw_title, 'News:') !== false) {
            $is_valid = true;
            $item_type = 'news';
            $clean_title = str_ireplace('News:', '', $raw_title);
        } elseif (stripos($raw_title, 'Event:') !== false) {
            $is_valid = true;
            $item_type = 'event';
            $clean_title = str_ireplace('Event:', '', $raw_title);
        }

        if (!$is_valid) { continue; }

        $clean_title = trim($clean_title);
        
        // 3. Fallback SVG Thumbnails based on type
        if ($item_type === 'event') {
            $image_url = "data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 140 90%22%3E%3Crect width=%22140%22 height=%2290%22 fill=%22%23F3F4F6%22/%3E%3Cg transform=%22translate(50, 25)%22 fill=%22none%22 stroke=%22%239CA3AF%22 stroke-width=%223%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Crect x=%222%22 y=%226%22 width=%2236%22 height=%2228%22 rx=%223%22/%3E%3Cpath d=%22M10 2v8m20-8v8m-26 8h36%22/%3E%3C/g%3E%3C/svg%3E";
            $bg_size = 'cover';
        } else {
            $image_url = "data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 140 90%22%3E%3Crect width=%22140%22 height=%2290%22 fill=%22%23F3F4F6%22/%3E%3Cg transform=%22translate(50, 25)%22 fill=%22none%22 stroke=%22%239CA3AF%22 stroke-width=%223%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Cpath d=%22M2 10h36v24H2zm0 8h36m-28 6h20m-20 6h12%22/%3E%3C/g%3E%3C/svg%3E";
            $bg_size = 'cover';
        }
        
        // 4. Try to extract the first image from the HTML description
        $formatted_description = file_rewrite_pluginfile_urls(
            $event->description, 
            'pluginfile.php', 
            $eventcontext->id, 
            'calendar', 
            'event_description', 
            $event->id
        );
        
        if (preg_match('/<img[^>]+src=(["\'])(.*?)\1/i', $formatted_description, $matches)) {
            $image_url = html_entity_decode($matches[2]); 
            $bg_size = 'contain'; 
        }
        
        $list_items[] = [
            'id' => $event->id,
            'title' => format_string($clean_title),
            'date' => userdate($event->timestart, '%B %d, %Y'),
            'image_url' => $image_url,
            'bg_size' => $bg_size
        ];
    }
}
?>

<div class="container-fluid px-3 px-md-0 mx-auto mt-4 mb-5" style="max-width: 1000px;">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0 fw-bold academy-primary-text">News & Events Archives</h2>
        <span class="badge bg-secondary rounded-pill px-3 py-2 shadow-sm"><?php echo count($list_items); ?> Items</span>
    </div>

    <?php if (empty($list_items)): ?>
        <div class="alert alert-light text-center py-5 shadow-sm border-0" style="border-radius: 12px;">
            <i class="fa fa-newspaper-o text-muted mb-3" style="font-size: 3rem; opacity: 0.5;"></i>
            <h4 class="fw-bold text-dark">No News Found</h4>
            <p class="text-muted mb-0">Check back later for the latest updates and events.</p>
        </div>
    <?php else: ?>
        <div class="list-group shadow-sm border-0" style="border-radius: 12px; overflow: hidden;">
            <?php foreach ($list_items as $item): ?>
                
                <a href="<?php echo new moodle_url('/theme/academy_theme/news.php', ['id' => $item['id']]); ?>" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center p-3 border-bottom border-light academy-list-item" style="gap: 20px;">
                    
                    <div class="rounded shadow-sm" style="width: 160px; height: 100px; flex-shrink: 0; overflow: hidden; border: 1px solid #E2E8F0; background-image: url('<?php echo $item['image_url']; ?>'); background-size: <?php echo $item['bg_size']; ?>; background-position: center; background-repeat: no-repeat; background-color: #F3F4F6;">
                    </div>
                    
                    <div>
                        <h5 class="mb-2 fw-bold text-dark item-title" style="transition: color 0.2s ease;"><?php echo $item['title']; ?></h5>
                        <small class="text-muted" style="font-size: 0.9rem;">
                            <i class="fa fa-calendar-o me-2 academy-accent-text"></i>Published on <?php echo $item['date']; ?>
                        </small>
                    </div>
                </a>
                
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php echo $OUTPUT->footer(); ?>