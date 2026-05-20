<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

global $DB, $USER, $CFG, $OUTPUT, $PAGE, $SITE;

$use_dummy_data = get_config('theme_academy_theme', 'enable_dummy_data') == 1;

require_once($CFG->libdir . '/behat/lib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/completionlib.php');

$addblockbutton = $OUTPUT->addblockbutton();

if (isloggedin()) {
    $courseindexopen = (get_user_preferences('drawer-open-index', true) == true);
    $blockdraweropen = (get_user_preferences('drawer-open-block') == true);
} else {
    $courseindexopen = false;
    $blockdraweropen = false;
}

if (defined('BEHAT_SITE_RUNNING') && get_user_preferences('behat_keep_drawer_closed') != 1) {
    $blockdraweropen = true;
}

$extraclasses = ['uses-drawers'];
if ($courseindexopen) { $extraclasses[] = 'drawer-open-index'; }

$blockshtml = $OUTPUT->blocks('side-pre');
$hasblocks = (strpos($blockshtml, 'data-block=') !== false || !empty($addblockbutton));
if (!$hasblocks) { $blockdraweropen = false; }
$courseindex = core_course_drawer();
if (!$courseindex) { $courseindexopen = false; }

$bodyattributes = $OUTPUT->body_attributes($extraclasses);
$forceblockdraweropen = $OUTPUT->firstview_fakeblocks();

$secondarynavigation = false;
$overflow = '';
if ($PAGE->has_secondary_navigation()) {
    $tablistnav = $PAGE->has_tablist_secondary_navigation();
    $moremenu = new \core\navigation\output\more_menu($PAGE->secondarynav, 'nav-tabs', true, $tablistnav);
    $secondarynavigation = $moremenu->export_for_template($OUTPUT);
    $overflowdata = $PAGE->secondarynav->get_overflow_menu_data();
    if (!is_null($overflowdata)) {
        $overflow = $overflowdata->export_for_template($OUTPUT);
    }
}

$primary = new core\navigation\output\primary($PAGE);
$renderer = $PAGE->get_renderer('core');
$primarymenu = $primary->export_for_template($renderer);
$buildregionmainsettings = !$PAGE->include_region_main_settings_in_header_actions() && !$PAGE->has_secondary_navigation();
$regionmainsettingsmenu = $buildregionmainsettings ? $OUTPUT->region_main_settings_menu() : false;

$header = $PAGE->activityheader;
$headercontent = $header->export_for_template($renderer);

$frontpagecontext = context_course::instance(SITEID);

// --- 1. DYNAMIC HERO SLIDES FETCHING LOGIC ---
$heroslides = [];
$default_titles = ['', 'A Message from our GM', 'A Message from our Director of L&D', 'A Message from our CTO', 'Slide 4', 'Slide 5'];
$default_names = ['', 'Lammert Fopma', 'Sarah Jenkins', 'David Chen', 'Jane Doe', 'John Smith'];
$default_roles = ['', 'General Manager, ANME Academy', 'Head of Learning & Development', 'Chief Technology Officer', 'Manager', 'Lead'];
$default_imgs = ['', 'https://picsum.photos/60', 'https://picsum.photos/61', 'https://picsum.photos/62', 'https://picsum.photos/63', 'https://picsum.photos/64'];
$default_quotes = [
    '', 
    "Change happens when you extend your reach. Your comfort zone is your enemy. If you play it safe, you will never become all that you are destined to be. Change is uncomfortable, but it's helpful!",
    "Continuous learning is the fuel for innovation. Equip yourself with the right knowledge today, and there are absolutely no limits to what you can achieve tomorrow. Dive in and explore!",
    "Technology evolves at lightning speed. Our commitment is to keep you ahead of the curve through practical, hands-on experiences. Take advantage of these resources to elevate your skills.",
    "Default quote 4",
    "Default quote 5"
];

$is_first_load = get_config('theme_academy_theme', 'slide1_quote') === false;

for ($i = 1; $i <= 5; $i++) {
    $title = get_config('theme_academy_theme', "slide{$i}_title");
    $quote = get_config('theme_academy_theme', "slide{$i}_quote");
    $userid = get_config('theme_academy_theme', "slide{$i}_userid");
    
    if (!$is_first_load && empty(trim($title)) && empty(trim($quote)) && empty($userid)) { continue; }

    // Fallbacks for first load
    $name = $is_first_load && $i <= 3 ? $default_names[$i] : '';
    $role = $is_first_load && $i <= 3 ? $default_roles[$i] : '';
    $profile_img = $is_first_load && $i <= 3 ? $default_imgs[$i] : '';
    
    // Dynamically fetch user if selected
    if (!empty($userid)) {
        $u = $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0], '*', IGNORE_MISSING);
        if ($u) {
            $name = fullname($u);
            
            $userroles = get_user_roles($frontpagecontext, $u->id, true);
            $rolenames = [];
            if ($userroles) {
                foreach ($userroles as $userrole) {
                    $rolenames[] = role_get_name($userrole, $frontpagecontext);
                }
            }
            $role = !empty($rolenames) ? implode(', ', $rolenames) : 'Student';
            
            $userpicture = new user_picture($u);
            $userpicture->size = 512;
            $profile_img = $userpicture->get_url($PAGE)->out(false);
        }
    }

    $videourl_file = $PAGE->theme->setting_file_url("slide{$i}_video_url", "slide{$i}_video_url");
    if ($videourl_file instanceof moodle_url) {
        $video_url = $videourl_file->out(false);
    } elseif (is_string($videourl_file) && trim($videourl_file) !== '') {
        $video_url = $videourl_file;
    } else {
        $video_url = '';
    }

    $heroslides[] = [
        'id' => $i,
        'title' => ($title !== false && trim($title) !== '') ? $title : ($is_first_load && $i <= 3 ? $default_titles[$i] : ''),
        'quote' => ($quote !== false && trim($quote) !== '') ? $quote : ($is_first_load && $i <= 3 ? $default_quotes[$i] : ''),
        'name' => $name,
        'role' => format_string($role),
        'profile_img' => $profile_img,
        'video_url' => $video_url 
    ];
}

// --- 2. DYNAMIC COURSE FETCHING LOGIC ---
$mycourses = [];
if (isloggedin() && !isguestuser()) {
    $courses = enrol_get_my_courses('*');
    foreach ($courses as $course) {
        $ctx = context_course::instance($course->id);
        
        $exporter = new \core_course\external\course_summary_exporter($course, ['context' => $ctx]);
        $course_data = $exporter->export($renderer);
        $courseimage = $course_data->courseimage;

        $timeaccess = $DB->get_field('user_lastaccess', 'timeaccess', ['userid' => $USER->id, 'courseid' => $course->id]);
        if ($timeaccess) {
            $lastaccess = 'Last activity on ' . userdate($timeaccess, '%B %d, %y');
        } else {
            $lastaccess = 'Not accessed yet';
        }

        $hasprogress = false;
        $progress = 0;
        $completion = new completion_info($course);
        if ($completion->is_enabled()) {
            $hasprogress = true;
            $progresspercentage = \core_completion\progress::get_course_progress_percentage($course);
            $progress = ($progresspercentage !== null) ? round($progresspercentage) : 0;
        }

        $modinfo = get_fast_modinfo($course);
        $lessoncount = 0;
        
        $valid_lesson_types = [
            'lesson', 'page', 'book', 'scorm', 'h5pactivity', 'assign', 
            'ytvideo', 'videotracker', 'completiontimed', 
            'resource', 'imscp', 'lti', 'bigbluebuttonbn'
        ];
        
        foreach ($modinfo->get_cms() as $cm) {
            // Count it if it's accessible OR visible on the page (even if locked)
            if (($cm->uservisible || $cm->is_visible_on_course_page()) && in_array($cm->modname, $valid_lesson_types)) {
                $lessoncount++;
            }
        }
        $lessons_text = $lessoncount == 1 ? '1 Lesson' : $lessoncount . ' Lessons';

        $mycourses[] = [
            'id' => $course->id,
            'fullname' => format_string($course->fullname, true, ['context' => $ctx]),
            'viewurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
            'courseimage' => $courseimage,
            'hasprogress' => $hasprogress,
            'progress' => $progress,
            'is_completed' => ($progress == 100),
            'is_in_progress' => ($progress > 0 && $progress < 100),
            'lessonscount' => $lessons_text,
            'lastaccessdate' => $lastaccess
        ];
    }
}

if ($use_dummy_data) {
    $mycourses = [
        [
            'id' => 1, 'fullname' => 'Leadership Excellence Masterclass', 'viewurl' => '#',
            'courseimage' => 'https://picsum.photos/id/1015/600/400', 'hasprogress' => true,
            'progress' => 45, 'is_completed' => false, 'is_in_progress' => true,
            'lessonscount' => '12 Lessons', 'lastaccessdate' => 'Last activity on Today'
        ],
        [
            'id' => 2, 'fullname' => 'Workplace Health & Safety 2026', 'viewurl' => '#',
            'courseimage' => 'https://picsum.photos/id/1027/600/400', 'hasprogress' => true,
            'progress' => 100, 'is_completed' => true, 'is_in_progress' => false,
            'lessonscount' => '5 Lessons', 'lastaccessdate' => 'Last activity on Yesterday'
        ],
        [
            'id' => 3, 'fullname' => 'Advanced Communication Skills', 'viewurl' => '#',
            'courseimage' => 'https://picsum.photos/id/1043/600/400', 'hasprogress' => false,
            'progress' => 0, 'is_completed' => false, 'is_in_progress' => false,
            'lessonscount' => '8 Lessons', 'lastaccessdate' => 'Not accessed yet'
        ]
    ];
}

// --- 3. DYNAMIC CALENDAR EVENTS LOGIC (Birthdays, Anniversaries, etc.) ---
$upcoming_events = [];
$timestart = time();
$timeend = $timestart + (DAYSECS * 365);
$userid = $USER->id; 

$sql = "SELECT id, name, timestart, timeduration 
        FROM {event} 
        WHERE timestart >= :timestart 
        AND timestart <= :timeend 
        AND (eventtype = 'site' OR (eventtype = 'user' AND userid = :userid)) 
        ORDER BY timestart ASC 
        LIMIT 10"; 
        
$events = $DB->get_records_sql($sql, [
    'timestart' => $timestart, 
    'timeend' => $timeend, 
    'userid' => $userid
]);

foreach ($events as $event) {
    $is_birthday = (stripos($event->name, 'birthday') !== false);
    $is_anniversary = (stripos($event->name, 'anniversary') !== false);

    if (!$is_birthday && !$is_anniversary) {
        continue;
    }
    
    $bg_class = $is_birthday ? 'bg-purple-light' : 'bg-yellow-light';
    $icon_class = $is_birthday ? 'fa-birthday-cake' : 'fa-star';
    
    $day_name = strtoupper(date('D, j', $event->timestart));
    $month_year = date('M Y', $event->timestart);
    
    $start_time_str = date('H:i', $event->timestart);
    if ($start_time_str === '00:00' && ($event->timeduration == 0 || $event->timeduration == DAYSECS)) {
        $time_text = 'All day';
    } else {
        $end_time = $event->timestart + $event->timeduration;
        $time_text = date('g:i a', $event->timestart);
        if ($event->timeduration > 0) {
            $time_text .= ' - ' . date('g:i a', $end_time);
        }
    }

    $upcoming_events[] = [
        'bg_class' => $bg_class,
        'icon_class' => $icon_class,
        'day_name' => $day_name,
        'month_year' => $month_year,
        'time_text' => $time_text,
        'event_name' => format_string($event->name)
    ];

    if (count($upcoming_events) >= 10) { break; }
}

$current_date_display = 'Today ' . userdate(time(), '%e %B, %Y');

if ($use_dummy_data) {
    $upcoming_events = [
        ['bg_class' => 'bg-purple-light', 'icon_class' => 'fa-birthday-cake', 'day_name' => 'MON, 12', 'month_year' => 'Oct 2026', 'time_text' => 'All day', 'event_name' => 'Sarah Jenkins Birthday'],
        ['bg_class' => 'bg-yellow-light', 'icon_class' => 'fa-star', 'day_name' => 'THU, 15', 'month_year' => 'Oct 2026', 'time_text' => 'All day', 'event_name' => 'David Chen 5-Year Anniversary']
    ];
}

// --- 4. DYNAMIC NEWS & EVENTS LOGIC ---
$news_events = [];
$timestart_news = time() - (DAYSECS * 365); 
$timeend_news = time() + (DAYSECS * 365); 

$sql_news = "SELECT id, name, description, timestart 
             FROM {event} 
             WHERE timestart >= :timestart 
             AND timestart <= :timeend 
             AND eventtype = 'site' 
             ORDER BY timestart DESC";
        
$allevents = $DB->get_records_sql($sql_news, [
    'timestart' => $timestart_news, 
    'timeend' => $timeend_news
]);

if ($allevents) {
    foreach ($allevents as $event) {
        $raw_title = $event->name;
        $is_valid = false;
        $item_type = 'news';
        $clean_title = $raw_title;

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
        $bg_size = 'cover'; 
        
        if ($item_type === 'event') {
            $image_url = "data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 140 90%22%3E%3Crect width=%22140%22 height=%2290%22 fill=%22%23F3F4F6%22/%3E%3Cg transform=%22translate(50, 25)%22 fill=%22none%22 stroke=%22%239CA3AF%22 stroke-width=%223%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Crect x=%222%22 y=%226%22 width=%2236%22 height=%2228%22 rx=%223%22/%3E%3Cpath d=%22M10 2v8m20-8v8m-26 8h36%22/%3E%3C/g%3E%3C/svg%3E";
        } else {
            $image_url = "data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 140 90%22%3E%3Crect width=%22140%22 height=%2290%22 fill=%22%23F3F4F6%22/%3E%3Cg transform=%22translate(50, 25)%22 fill=%22none%22 stroke=%22%239CA3AF%22 stroke-width=%223%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22%3E%3Cpath d=%22M2 10h36v24H2zm0 8h36m-28 6h20m-20 6h12%22/%3E%3C/g%3E%3C/svg%3E";
        }
        
        $eventcontext = context_course::instance(SITEID);
        
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
        
        $news_events[] = [
            'image_url' => $image_url,
            'bg_size' => $bg_size,
            'title' => format_string($clean_title),
            'url' => (new moodle_url('/theme/academy_theme/news.php', ['id' => $event->id]))->out(false),
            'link_text' => 'Read More'
        ];

        if (count($news_events) >= 4) { break; }
    }
}

if ($use_dummy_data) {
    $news_events = [
        ['image_url' => 'https://picsum.photos/id/117/300/200', 'bg_size' => 'cover', 'title' => 'Annual General Meeting Highlights', 'url' => '#', 'link_text' => 'Read More'],
        ['image_url' => 'https://picsum.photos/id/119/300/200', 'bg_size' => 'cover', 'title' => 'Q3 Performance Review Schedule Released', 'url' => '#', 'link_text' => 'Read More']
    ];
}

// --- 4.5 DYNAMIC EMPLOYEE SPOTLIGHT LOGIC ---
$employee_spotlights = [];
for ($i = 1; $i <= 10; $i++) {
    $userid = get_config('theme_academy_theme', "spotlight{$i}_userid");
    if (empty($userid)) { continue; } 
    
    $u = $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0], '*', IGNORE_MISSING);
    if (!$u) { continue; }

    $userpicture = new user_picture($u);
    $userpicture->size = 512; 
    $profile_pic_url = $userpicture->get_url($PAGE)->out(false);
    
    $userroles = get_user_roles($frontpagecontext, $u->id, true);
    $rolenames = [];
    if ($userroles) {
        foreach ($userroles as $userrole) {
            $rolenames[] = role_get_name($userrole, $frontpagecontext);
        }
    }
    $role = !empty($rolenames) ? implode(', ', $rolenames) : 'Student';

    $raw_quote = trim(get_config('theme_academy_theme', "spotlight{$i}_quote"));
    
    $employee_spotlights[] = [
        'name' => fullname($u), 
        'role' => format_string($role), 
        'quote' => !empty($raw_quote) ? format_string($raw_quote) : false, 
        'description' => format_text(get_config('theme_academy_theme', "spotlight{$i}_desc")),
        'image_url' => $profile_pic_url 
    ];
}

// --- 5. DYNAMIC NEW HIRES LOGIC ---
$new_hires = [];
$themeconfigs = get_config('theme_academy_theme');
$selected_user_ids = [];

foreach ($themeconfigs as $key => $value) {
    if (strpos($key, 'newhire_show_') === 0 && $value == 1) {
        $userid = str_replace('newhire_show_', '', $key);
        $selected_user_ids[] = (int)$userid;
    }
}

if (!empty($selected_user_ids)) {
    list($insql, $inparams) = $DB->get_in_or_equal($selected_user_ids);
    $users = $DB->get_records_select('user', "id $insql", $inparams, 'timecreated DESC');
    
    foreach ($users as $u) {
        $userpicture = new user_picture($u);
        $userpicture->size = 512;
        $profile_pic_url = $userpicture->get_url($PAGE)->out(false);
        
        $userroles = get_user_roles($frontpagecontext, $u->id, true);
        $rolenames = [];
        if ($userroles) {
            foreach ($userroles as $userrole) {
                $rolenames[] = role_get_name($userrole, $frontpagecontext);
            }
        }
        $role = !empty($rolenames) ? implode(', ', $rolenames) : 'Student';

        $new_hires[] = [
            'name' => fullname($u),
            'role' => format_string($role),
            'profile_image' => $profile_pic_url,
            'message_url' => (new moodle_url('/message/index.php', ['id' => $u->id]))->out(false)
        ];
    }
}

if ($use_dummy_data) {
    $new_hires = [
        ['name' => 'Emily Rodriguez', 'role' => 'Marketing Specialist', 'profile_image' => 'https://picsum.photos/id/1011/100/100', 'message_url' => '#'],
        ['name' => 'Michael Chang', 'role' => 'Software Engineer', 'profile_image' => 'https://picsum.photos/id/1012/100/100', 'message_url' => '#'],
        ['name' => 'Jessica Taylor', 'role' => 'HR Coordinator', 'profile_image' => 'https://picsum.photos/id/1025/100/100', 'message_url' => '#']
    ];
}

// --- 5.5 DYNAMIC SHOUT-OUTS LOGIC ---
$shoutouts = [];
for ($i = 1; $i <= 6; $i++) {
    $userid = get_config('theme_academy_theme', "shoutout{$i}_userid");
    if (empty($userid)) { continue; } 
    
    $u = $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0], '*', IGNORE_MISSING);
    if (!$u) { continue; }

    $userpicture = new user_picture($u);
    $userpicture->size = 512; 
    $profile_pic_url = $userpicture->get_url($PAGE)->out(false);
    
    $userroles = get_user_roles($frontpagecontext, $u->id, true);
    $rolenames = [];
    if ($userroles) {
        foreach ($userroles as $userrole) {
            $rolenames[] = role_get_name($userrole, $frontpagecontext);
        }
    }
    $role = !empty($rolenames) ? implode(', ', $rolenames) : 'Student';

    $shoutouts[] = [
        'name' => fullname($u), 
        'role' => format_string($role), 
        'message' => format_string(get_config('theme_academy_theme', "shoutout{$i}_message")),
        'tags' => format_string(get_config('theme_academy_theme', "shoutout{$i}_tags")),
        'image_url' => $profile_pic_url 
    ];
}

// --- 7. DYNAMIC GRADE LEADERBOARD (Native Moodle Grades) ---
$grade_leaderboard = [];

if (isloggedin()) {
    try {
        // Step 1: Calculate the Average Grade for all users
        $sql = "SELECT gg.userid, AVG((gg.finalgrade / gi.grademax) * 100) AS average_grade
                FROM {grade_grades} gg
                JOIN {grade_items} gi ON gi.id = gg.itemid
                WHERE gg.finalgrade IS NOT NULL
                  AND gi.grademax > 0
                  AND gi.itemtype = 'mod' 
                GROUP BY gg.userid
                ORDER BY average_grade DESC
                LIMIT 3"; // Limit reduced to Top 3
                
        $top_students = $DB->get_records_sql($sql);
        
        if ($top_students) {
            $rank = 1;
            foreach ($top_students as $userid => $data) {
                // Get the user's details
                $u = $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0], '*', IGNORE_MISSING);
                if (!$u) continue; // Skip if user is deleted

                // Get profile picture safely (FORCED TO 512px FOR CRISPNESS)
                $userpicture = new user_picture($u);
                $userpicture->size = 512;
                $profile_pic = $userpicture->get_url($PAGE)->out(false);
                
                // Format the average
                $avg_grade = round($data->average_grade, 1);
                
                // Step 2: Fetch the single most recent grade they received for context
                $recent_sql = "SELECT gi.itemname, gg.finalgrade, gi.grademax, gg.timemodified
                               FROM {grade_grades} gg
                               JOIN {grade_items} gi ON gi.id = gg.itemid
                               WHERE gg.userid = :userid
                                 AND gg.finalgrade IS NOT NULL
                                 AND gi.itemtype = 'mod'
                                 AND gi.itemname IS NOT NULL
                               ORDER BY gg.timemodified DESC
                               LIMIT 1";
                $recent_grade = $DB->get_record_sql($recent_sql, ['userid' => $userid]);

                // Default fallbacks if recent grade name is missing
                $recent_action = "Completed an activity";
                $recent_score = "";
                $time_ago = "recently";

                if ($recent_grade) {
                    $recent_action = format_string($recent_grade->itemname);
                    $recent_score = round($recent_grade->finalgrade, 1) . "/" . round($recent_grade->grademax, 0);
                    
                    // Calculate time ago
                    $time_diff = time() - $recent_grade->timemodified;
                    if ($time_diff < 60) { $time_ago = 'just now'; }
                    elseif ($time_diff < 3600) { $time_ago = floor($time_diff / 60) . ' mins ago'; }
                    elseif ($time_diff < 86400) { $time_ago = floor($time_diff / 3600) . ' hours ago'; }
                    else { $time_ago = floor($time_diff / 86400) . ' days ago'; }
                }

                // Styling logic based on rank
                $is_first = ($rank === 1);

                $grade_leaderboard[] = [
                    'rank' => $rank,
                    'firstname' => format_string($u->firstname),
                    'profile_img' => $profile_pic,
                    'average_grade' => $avg_grade,
                    'recent_action' => $recent_action,
                    'recent_score' => $recent_score,
                    'time_ago' => $time_ago,
                    'bg_color' => $is_first ? '#FFFBEB' : '#EEF2FF',
                    'icon_bg' => $is_first ? '#FDE68A' : '#D9F99D',
                    'icon' => $is_first ? 'fa-trophy' : 'fa-line-chart',
                    'text_color' => $is_first ? '#B45309' : '#4B5563'
                ];
                $rank++;
            }
        }
    } catch (Exception $e) {
        // Silently catch database errors
    }
}

if ($use_dummy_data) {
    $grade_leaderboard = [
        ['rank' => 1, 'firstname' => 'Alex', 'profile_img' => 'https://picsum.photos/id/1005/100/100', 'average_grade' => 98.5, 'recent_action' => 'Final Assessment', 'recent_score' => '100/100', 'time_ago' => '2 hours ago', 'bg_color' => '#FFFBEB', 'icon_bg' => '#FDE68A', 'icon' => 'fa-trophy', 'text_color' => '#B45309'],
        ['rank' => 2, 'firstname' => 'Jordan', 'profile_img' => 'https://picsum.photos/id/1009/100/100', 'average_grade' => 94.2, 'recent_action' => 'Midterm Quiz', 'recent_score' => '95/100', 'time_ago' => '1 day ago', 'bg_color' => '#EEF2FF', 'icon_bg' => '#D9F99D', 'icon' => 'fa-line-chart', 'text_color' => '#4B5563'],
        ['rank' => 3, 'firstname' => 'Taylor', 'profile_img' => 'https://picsum.photos/id/1014/100/100', 'average_grade' => 91.0, 'recent_action' => 'Safety Protocol Test', 'recent_score' => '90/100', 'time_ago' => '3 days ago', 'bg_color' => '#EEF2FF', 'icon_bg' => '#D9F99D', 'icon' => 'fa-line-chart', 'text_color' => '#4B5563']
    ];
}

// --- 8. BUILD TEMPLATE CONTEXT ---

// --- DETERMINE VISIBILITY PREFIX ---
// If the user is logged out or browsing as a guest, we use the 'show_loggedout_' prefix
$is_guest = !isloggedin() || isguestuser();
$prefix = $is_guest ? 'show_loggedout_' : 'show_';

$templatecontext = [
    'sitename' => format_string($SITE->shortname, true, ['context' => context_course::instance(SITEID), "escape" => false]),
    'firstname' => isloggedin() ? $USER->firstname : 'Visitor',

    'ql1_url' => get_config('theme_academy_theme', 'ql1_url'),
    'ql2_url' => get_config('theme_academy_theme', 'ql2_url'),
    'ql3_url' => get_config('theme_academy_theme', 'ql3_url'),
    'ql4_url' => get_config('theme_academy_theme', 'ql4_url'),
    'ql5_url' => get_config('theme_academy_theme', 'ql5_url'),
    
    'hero_slides' => $heroslides,
    'has_multiple_slides' => count($heroslides) > 1,

    'employee_spotlights' => $employee_spotlights,
    'shoutouts' => $shoutouts, 
    
    'my_courses' => $mycourses,
    'upcoming_events' => $upcoming_events,
    'current_date_display' => $current_date_display,
    'news_events' => $news_events, 
    
    'new_hires' => $new_hires,
    'has_multiple_new_hires' => count($new_hires) > 2,

    'grade_leaderboard' => $grade_leaderboard,

    // Block Visibility Toggles
    'show_learning_programs' => get_config('theme_academy_theme', $prefix . 'learning_programs') !== '0',
    'show_news_events' => get_config('theme_academy_theme', $prefix . 'news_events') !== '0',
    'show_employee_spotlight' => get_config('theme_academy_theme', $prefix . 'employee_spotlight') !== '0',
    'show_shoutouts' => get_config('theme_academy_theme', $prefix . 'shoutouts') !== '0',
    'show_quick_links' => get_config('theme_academy_theme', $prefix . 'quick_links') !== '0',
    'show_birthdays' => get_config('theme_academy_theme', $prefix . 'birthdays') !== '0',
    'show_new_hires' => get_config('theme_academy_theme', $prefix . 'new_hires') !== '0',
    'show_top_performer' => get_config('theme_academy_theme', $prefix . 'top_performer') !== '0',
    
    'output' => $OUTPUT,
    'sidepreblocks' => $blockshtml,
    'hasblocks' => $hasblocks,
    'bodyattributes' => $bodyattributes,
    'courseindexopen' => $courseindexopen,
    'blockdraweropen' => $blockdraweropen,
    'courseindex' => $courseindex,
    'primarymoremenu' => $primarymenu['moremenu'],
    'secondarymoremenu' => $secondarynavigation ?: false,
    'mobileprimarynav' => $primarymenu['mobileprimarynav'],
    'usermenu' => $primarymenu['user'],
    'langmenu' => $primarymenu['lang'],
    'forceblockdraweropen' => $forceblockdraweropen,
    'regionmainsettingsmenu' => $regionmainsettingsmenu,
    'hasregionmainsettingsmenu' => !empty($regionmainsettingsmenu),
    'overflow' => $overflow,
    'headercontent' => $headercontent,
    'addblockbutton' => $addblockbutton
];

echo $OUTPUT->render_from_template('theme_academy_theme/frontpage', $templatecontext);