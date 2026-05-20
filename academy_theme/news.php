<?php
// This file is a custom News Reader for the Academy Theme
require_once('../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_login(); // Ensure the user is logged in

// Get the specific event ID from the URL
$id = required_param('id', PARAM_INT);

// Fetch that specific event from the database
$event = $DB->get_record('event', ['id' => $id], '*', MUST_EXIST);

// Set up the Moodle page layout
$PAGE->set_url('/theme/academy_theme/news.php', ['id' => $id]);
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');

// 1. Check the original title to see if this is a News item or an Event
$page_heading = 'Announcement'; // Default fallback
if (stripos($event->name, 'News:') !== false) {
    $page_heading = 'News';
} elseif (stripos($event->name, 'Event:') !== false) {
    $page_heading = 'Event';
}

// 2. Clean the title for the article body
$clean_title = trim(str_ireplace(['News:', 'Event:'], '', $event->name));
$PAGE->set_title($clean_title);

// 3. Set the top-left page header to 'News' or 'Event' instead of the Site Name
$PAGE->set_heading($page_heading);

echo $OUTPUT->header();
?>

<div class="container-fluid px-3 px-md-0 mx-auto mt-5 mb-5" style="max-width: 1300px;">
    <div class="card p-5 shadow-sm border-0 academy-news-card" style="border-radius: 12px;">
        <h1 class="mb-3"><strong><?php echo format_string($clean_title); ?></strong></h1>
        <p class="text-muted mb-4" style="font-size: 0.95rem;">
            <i class="fa fa-calendar-o me-2 academy-accent-text"></i> Published on <?php echo userdate($event->timestart, '%A, %B %d, %Y'); ?>
        </p>
        
        <hr class="academy-accent-bg mb-4" style="border: 0; height: 4px; width: 60px; opacity: 1; border-radius: 2px;">

        <div class="news-content" style="font-size: 1.05rem; line-height: 1.8; color: #334155;">
            <?php 
                // Format the description properly, converting Moodle file URLs to actual images
                $eventcontext = context_course::instance(SITEID);
                $formatted_text = file_rewrite_pluginfile_urls(
                    $event->description, 
                    'pluginfile.php', 
                    $eventcontext->id, 
                    'calendar', 
                    'event_description', 
                    $event->id
                );
                
                // Output the safe, formatted HTML
                echo format_text($formatted_text, FORMAT_HTML); 
            ?>
        </div>
        
        <div class="mt-5 pt-4 border-top border-light d-flex justify-content-between align-items-center">
            <a href="<?php echo new moodle_url('/theme/academy_theme/news_list.php'); ?>" class="btn btn-outline-secondary px-4 rounded-pill shadow-sm" style="font-weight: 600;">
                <i class="fa fa-arrow-left me-2"></i> Back to all News
            </a>
        </div>
    </div>
</div>

<?php echo $OUTPUT->footer(); ?>