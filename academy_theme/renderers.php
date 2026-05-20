<?php
defined('MOODLE_INTERNAL') || die();

class theme_academy_theme_core_renderer extends \theme_boost\output\core_renderer {
    
    // =========================================================================
    // 1. GLOBAL TEMPLATE INTERCEPTOR (FOOTER & COURSE BANNER)
    // =========================================================================
    public function render_from_template($templatename, $context) {
        global $PAGE, $DB, $COURSE, $USER, $CFG;
        
        if (is_object($context)) {
            $context = (array) $context;
        }

        // --- A. FOOTER RENDERING LOGIC ---
        static $footervars = null;
        if ($footervars === null) {
            
            // Build Column 1 HTML
            $col1_html = '';
            for ($i = 1; $i <= 6; $i++) {
                $text = get_config('theme_academy_theme', 'footer_col1_text'.$i);
                $url = get_config('theme_academy_theme', 'footer_col1_url'.$i);
                if (!empty(trim($text)) && !empty(trim($url))) {
                    $col1_html .= '<li><a href="'.s($url).'" target="_blank" rel="noopener noreferrer">'.s($text).'</a></li>';
                }
            }

            // Build Column 2 HTML
            $col2_html = '';
            for ($i = 1; $i <= 6; $i++) {
                $text = get_config('theme_academy_theme', 'footer_col2_text'.$i);
                $url = get_config('theme_academy_theme', 'footer_col2_url'.$i);
                if (!empty(trim($text)) && !empty(trim($url))) {
                    $col2_html .= '<li><a href="'.s($url).'" target="_blank" rel="noopener noreferrer">'.s($text).'</a></li>';
                }
            }

            $footervars = [
                'footer_col1_title' => 'Company',
                'footer_col1_links' => $col1_html,
                'footer_col2_title' => 'Useful Links',
                'footer_col2_links' => $col2_html,
                'footer_hr_title' => 'Contact Details',
                'footer_phone' => get_config('theme_academy_theme', 'footer_phone'),
                'footer_email' => get_config('theme_academy_theme', 'footer_email'),
                'footer_website' => get_config('theme_academy_theme', 'footer_website'),
                'footer_address' => format_text(get_config('theme_academy_theme', 'footer_address')),
                'footer_social_title' => 'Follow Us',
                'footer_youtube' => get_config('theme_academy_theme', 'footer_youtube'),
                'footer_instagram' => get_config('theme_academy_theme', 'footer_instagram'),
                'footer_facebook' => get_config('theme_academy_theme', 'footer_facebook'),
                'footer_linkedin' => get_config('theme_academy_theme', 'footer_linkedin'),
                'footer_copyright' => format_text(get_config('theme_academy_theme', 'footer_copyright')),
            ];
        }

        // Inject footer variables into any layout that includes the footer
        $layouts_with_footer = [
            'theme_boost/footer', 
            'theme_academy_theme/footer',
            'theme_boost/drawers', 
            'theme_academy_theme/drawers',
            'theme_academy_theme/frontpage'
        ];

        if (in_array($templatename, $layouts_with_footer)) {
            $context = array_merge($context, $footervars);
        }

        // --- B. COURSE BANNER INTERCEPTOR LOGIC ---
        if ($templatename === 'theme_boost/drawers' || $templatename === 'theme_academy_theme/drawers') {
            
            // Check if we are on a specific course page (not the front page, not the dashboard)
            $is_course_page = ($PAGE->pagelayout === 'course' && $COURSE->id != SITEID);
            $context['is_custom_course_page'] = $is_course_page;

            if ($is_course_page) {
                // 1. Basic Course Info
                $coursecontext = context_course::instance($COURSE->id);
                $context['course_title'] = format_string($COURSE->fullname, true, ['context' => $coursecontext]);
                
                // Get course summary, strip HTML for the excerpt, keep HTML for the full version
                $summary = format_text($COURSE->summary, $COURSE->summaryformat, ['context' => $coursecontext]);
                $context['course_summary_full'] = $summary;
                $context['course_excerpt'] = shorten_text(strip_tags($summary), 200); 

                // Get category
                $category = $DB->get_record('course_categories', ['id' => $COURSE->category], 'name');
                $context['course_category'] = $category ? format_string($category->name) : 'General';
                
                // Get course image
                $course_thumbnail = 'https://picsum.photos/1200/400'; // Default fallback
                $course = new core_course_list_element($COURSE);
                foreach ($course->get_course_overviewfiles() as $file) {
                    $isimage = $file->is_valid_image();
                    if ($isimage) {
                        $course_thumbnail = moodle_url::make_pluginfile_url(
                            $file->get_contextid(), $file->get_component(), $file->get_filearea(), 
                            null, $file->get_filepath(), $file->get_filename()
                        )->out(false);
                        break;
                    }
                }
                $context['course_thumbnail'] = $course_thumbnail;

                // 2. Editing Mode Check
                $context['is_editing'] = $PAGE->user_is_editing();

                // 3. Current User's Progress
                require_once($CFG->libdir . '/completionlib.php');
                $completion = new completion_info($COURSE);
                
                $context['hasprogress'] = false;
                $context['progress'] = 0;
                $context['is_completed'] = false;
                $context['is_in_progress'] = false;
                $context['last_accessed_date'] = 'Never accessed';
                
                if (isloggedin() && !isguestuser() && $completion->is_enabled()) {
                    $context['hasprogress'] = true;
                    $progresspercentage = \core_completion\progress::get_course_progress_percentage($COURSE);
                    $progress = ($progresspercentage !== null) ? round($progresspercentage) : 0;
                    
                    $context['progress'] = $progress;
                    $context['is_completed'] = ($progress == 100);
                    $context['is_in_progress'] = ($progress > 0 && $progress < 100);

                    // Get last access time
                    $timeaccess = $DB->get_field('user_lastaccess', 'timeaccess', ['userid' => $USER->id, 'courseid' => $COURSE->id]);
                    if ($timeaccess) {
                        $context['last_accessed_date'] = 'Last accessed on ' . userdate($timeaccess, '%b %d, %Y');
                    }
                }

                // 4. Content Counting & Sections
                $modinfo = get_fast_modinfo($COURSE);
                $sections = $modinfo->get_section_info_all();
                
                $lessoncount = 0;
                $quizcount = 0;
                $topiccount = 0;
                $custom_sections = [];
                $valid_lesson_types = ['lesson', 'page', 'book', 'scorm', 'h5pactivity', 'assign', 'ytvideo', 'videotracker', 'completiontimed', 'resource', 'imscp', 'lti', 'bigbluebuttonbn'];
                
                $section_num = 1;
                foreach ($sections as $section) {
                    
                    $topiccount++;
                    $section_name = get_section_name($COURSE, $section);
                    $section_activities = [];
                    $completed_in_section = 0;
                    $total_in_section = 0;

                    if (!empty($modinfo->sections[$section->section])) {
                        foreach ($modinfo->sections[$section->section] as $cmid) {
                            $cm = $modinfo->cms[$cmid];
                            
                            // Only count visible activities
                            if ($cm->uservisible || $cm->is_visible_on_course_page()) {
                                $total_in_section++;
                                
                                if (in_array($cm->modname, $valid_lesson_types)) { $lessoncount++; }
                                if ($cm->modname === 'quiz') { $quizcount++; }

                                // Check Completion
                                $is_completed = false;
                                if ($completion->is_enabled($cm) != COMPLETION_TRACKING_NONE) {
                                    $completion_data = $completion->get_data($cm, true);
                                    if ($completion_data->completionstate == COMPLETION_COMPLETE || $completion_data->completionstate == COMPLETION_COMPLETE_PASS) {
                                        $is_completed = true;
                                        $completed_in_section++;
                                    }
                                }

                                // Map FontAwesome icons based on module type
                                $icon = 'fa-file-text-o';
                                if ($cm->modname === 'quiz') $icon = 'fa-check-square-o';
                                if ($cm->modname === 'assign') $icon = 'fa-upload';
                                if ($cm->modname === 'scorm' || $cm->modname === 'h5pactivity') $icon = 'fa-cubes';
                                if ($cm->modname === 'forum') $icon = 'fa-comments-o';
                                if ($cm->modname === 'url' || $cm->modname === 'ytvideo') $icon = 'fa-play-circle-o';

                                $section_activities[] = [
                                    'title' => $cm->name,
                                    'icon' => $icon,
                                    'url' => $cm->uservisible ? (new moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]))->out(false) : false,
                                    'is_completed' => $is_completed,
                                    'is_locked' => !$cm->uservisible // Gray out if restricted
                                ];
                            }
                        }
                    }

                    $section_progress = ($total_in_section > 0) ? round(($completed_in_section / $total_in_section) * 100) : 0;

                    $custom_sections[] = [
                        'id' => $section->id,
                        'title' => $section_name,
                        'topics_count' => $total_in_section,
                        'completed_count' => $completed_in_section,
                        'progress_percent' => $section_progress,
                        'activities' => $section_activities,
                        'is_first' => ($section_num === 1)
                    ];
                    $section_num++;
                }

                $context['count_lessons'] = $lessoncount;
                $context['count_topics'] = $topiccount;
                $context['count_quizzes'] = $quizcount;
                $context['course_content_sections'] = $custom_sections;

                // 5. Instructor & Enrolled Users Logic
                $coursecontext = context_course::instance($COURSE->id);
                
                // Automatically grab all fields required for user pictures and full names, plus email
                $userfields = \core_user\fields::for_userpic()->including('email')->get_sql('u', false, '', '', false)->selects;
                
                // Fetch the enrolled users using the API-generated fields string
                $enrolled_users = get_enrolled_users($coursecontext, '', 0, $userfields, null, 0, 10);
                
                $instructor_name = "Academy Team";
                $instructor_img = "";
                $enrolled_avatars = [];
                $enrolled_count = 0;

                foreach ($enrolled_users as $user) {
                    $roles = get_user_roles($coursecontext, $user->id);
                    $is_teacher = false;
                    
                    foreach ($roles as $role) {
                        if ($role->shortname === 'editingteacher' || $role->shortname === 'teacher') {
                            $is_teacher = true;
                            break;
                        }
                    }

                    $userpicture = new user_picture($user);
                    $userpicture->size = 100; // Crisp avatar
                    $pic_url = $userpicture->get_url($PAGE)->out(false);

                    if ($is_teacher && empty($instructor_img)) {
                        $instructor_name = fullname($user);
                        $instructor_img = $pic_url;
                    } else {
                        if (count($enrolled_avatars) < 3) {
                            $enrolled_avatars[] = [
                                'url' => $pic_url,
                                'name' => fullname($user)
                            ];
                        }
                        $enrolled_count++;
                    }
                }

                // Total enrolled (approximate for the counter)
                $total_enrolled = count_enrolled_users($coursecontext);
                
                $context['instructor_name'] = $instructor_name;
                $context['instructor_img'] = $instructor_img;
                $context['course_date'] = userdate($COURSE->startdate, '%B %Y');
                $context['enrolled_avatars'] = $enrolled_avatars;
                $context['enrolled_count'] = $total_enrolled;
                $context['has_multiple_enrolled'] = ($total_enrolled > 3);
                $context['extra_enrolled_count'] = max(0, $total_enrolled - 3);
                $context['participants_url'] = (new moodle_url('/user/index.php', ['id' => $COURSE->id]))->out(false);

                // 6. Action Button State
                $context['course_status'] = ($context['progress'] == 100) ? 'Completed' : (($context['progress'] > 0) ? 'In Progress' : 'Not Started');
                $context['continue_btn_text'] = ($context['progress'] > 0) ? 'Continue Course' : 'Start Course';
                $context['continue_url'] = (new moodle_url('/course/view.php', ['id' => $COURSE->id]))->out(false);

                // 7. Certificate Check
                $context['has_certificate'] = false;
                $context['certificate_unlocked'] = false;
                
                // Simple logic: if there is a module named 'customcert' or 'certificate', show the badge
                if (!empty($modinfo->instances['customcert']) || !empty($modinfo->instances['certificate'])) {
                    $context['has_certificate'] = true;
                    if ($context['progress'] == 100) {
                        $context['certificate_unlocked'] = true;
                    }
                }

                // 8. Custom Moodle Course Reviews Integration
                $context['total_reviews'] = 0;
                $context['avg_rating'] = "0.0";
                $context['recent_reviews'] = [];
                
                $context['review_url'] = (new moodle_url('/local/academy_reviews/review.php', ['courseid' => $COURSE->id]))->out(false);

                if ($DB->get_manager()->table_exists('local_academy_reviews')) {
                    
                    $sql = "SELECT AVG(rating) as avgrating, COUNT(id) as total FROM {local_academy_reviews} WHERE courseid = :courseid";
                    $stats = $DB->get_record_sql($sql, ['courseid' => $COURSE->id]);
                    
                    if ($stats && $stats->total > 0) {
                        $context['total_reviews'] = $stats->total;
                        $context['avg_rating'] = number_format($stats->avgrating, 1);
                        
                        // Fetch latest 3 reviews
                        $reviews = $DB->get_records('local_academy_reviews', ['courseid' => $COURSE->id], 'timecreated DESC', '*', 0, 3);
                        $recent_reviews = [];
                        foreach ($reviews as $rev) {
                            $ru = $DB->get_record('user', ['id' => $rev->userid]);
                            if (!$ru) continue; // Skip if user was deleted
                            
                            $rupic = new user_picture($ru);
                            $rupic->size = 100;
                            
                            // Generate HTML stars
                            $stars_html = '';
                            for($i=1; $i<=5; $i++) {
                                if ($i <= $rev->rating) {
                                    $stars_html .= '<i class="fa fa-star text-warning"></i>';
                                } else {
                                    $stars_html .= '<i class="fa fa-star-o text-muted"></i>';
                                }
                            }

                            $time_diff = time() - $rev->timecreated;
                            if ($time_diff < 86400) { $time_ago = 'Today'; }
                            elseif ($time_diff < 172800) { $time_ago = 'Yesterday'; }
                            else { $time_ago = floor($time_diff / 86400) . ' days ago'; }

                            $recent_reviews[] = [
                                'user_name' => fullname($ru),
                                'user_pic' => $rupic->get_url($PAGE)->out(false),
                                'stars' => $stars_html,
                                'comment' => format_text($rev->reviewtext),
                                'time_ago' => $time_ago
                            ];
                        }
                        $context['recent_reviews'] = $recent_reviews;
                        $context['has_more_reviews'] = ($stats->total > 3);

                        // Generate overall stars for the top banner
                        $overall_stars = '';
                        $rating = round($stats->avgrating * 2) / 2; // Round to nearest 0.5
                        for($i=1; $i<=5; $i++) {
                            if ($rating >= $i) {
                                $overall_stars .= '<i class="fa fa-star academy-accent-text"></i>';
                            } elseif ($rating >= ($i - 0.5)) {
                                $overall_stars .= '<i class="fa fa-star-half-o academy-accent-text"></i>';
                            } else {
                                $overall_stars .= '<i class="fa fa-star-o text-muted"></i>';
                            }
                        }
                        $context['stars_html'] = $overall_stars;

                    }
                }

                // If no reviews exist, just hardcode some empty stars for layout
                if (empty($context['stars_html'])) {
                    $context['stars_html'] = '<i class="fa fa-star-o text-muted"></i><i class="fa fa-star-o text-muted"></i><i class="fa fa-star-o text-muted"></i><i class="fa fa-star-o text-muted"></i><i class="fa fa-star-o text-muted"></i>';
                }

                $use_dummy_data = get_config('theme_academy_theme', 'enable_dummy_data') == 1;

                if ($use_dummy_data) {
                    // FORCE Fake Instructor & Enrollments
                    $context['instructor_name'] = 'Demo Instructor';
                    $context['instructor_img'] = 'https://picsum.photos/id/1035/100/100';
                    $context['enrolled_avatars'] = [
                        ['url' => 'https://picsum.photos/id/1011/100/100', 'name' => 'Demo User 1'],
                        ['url' => 'https://picsum.photos/id/1012/100/100', 'name' => 'Demo User 2'],
                        ['url' => 'https://picsum.photos/id/1025/100/100', 'name' => 'Demo User 3']
                    ];
                    $context['enrolled_count'] = 125;
                    $context['has_multiple_enrolled'] = true;
                    $context['extra_enrolled_count'] = 122;

                    // FORCE Fake Reviews
                    $context['total_reviews'] = 24;
                    $context['avg_rating'] = "4.8";
                    $context['stars_html'] = '<i class="fa fa-star academy-accent-text"></i><i class="fa fa-star academy-accent-text"></i><i class="fa fa-star academy-accent-text"></i><i class="fa fa-star academy-accent-text"></i><i class="fa fa-star-half-o academy-accent-text"></i>';
                    $context['has_more_reviews'] = true;
                    
                    $context['recent_reviews'] = [
                        [
                            'user_name' => 'Demo Learner',
                            'user_pic' => 'https://picsum.photos/id/1005/100/100',
                            'stars' => '<i class="fa fa-star text-warning"></i><i class="fa fa-star text-warning"></i><i class="fa fa-star text-warning"></i><i class="fa fa-star text-warning"></i><i class="fa fa-star text-warning"></i>',
                            'comment' => 'This course was incredibly helpful and well structured. Highly recommended!',
                            'time_ago' => '2 days ago'
                        ],
                        [
                            'user_name' => 'Alex Smith',
                            'user_pic' => 'https://picsum.photos/id/1009/100/100',
                            'stars' => '<i class="fa fa-star text-warning"></i><i class="fa fa-star text-warning"></i><i class="fa fa-star text-warning"></i><i class="fa fa-star text-warning"></i><i class="fa fa-star-o text-muted"></i>',
                            'comment' => 'Great content, but I wish there were more practical exercises.',
                            'time_ago' => '1 week ago'
                        ]
                    ];
                }

                // Build Course Timeline for native scrollspy
                $timeline = [];
                foreach ($custom_sections as $index => $sec) {
                    $timeline[] = [
                        'id' => $sec['id'],
                        'num' => $index + 1,
                        'title' => $sec['title'],
                        'modcount' => $sec['topics_count'] . ' items',
                        'is_completed' => ($sec['progress_percent'] == 100),
                        'section_num' => $index + 1 // Native moodle section id
                    ];
                }
                $context['course_topics'] = $timeline;
            }
        }

        return parent::render_from_template($templatename, $context);
    }

    // =========================================================================
    // 2. ADMIN ONLY - PURGE CACHES BUTTON INJECTION & AUTO-EXPAND
    // =========================================================================
    public function standard_after_main_region_html() {
        $html = parent::standard_after_main_region_html();
        global $PAGE;
        
        // --- Auto-expand categories and courses on the Course Index page ---
        if ($PAGE->pagetype === 'course-index-category' || strpos($_SERVER['REQUEST_URI'], '/course/index.php') !== false) {
            $html .= '
            <script>
            document.addEventListener("DOMContentLoaded", function() {
                // Delay to ensure Moodle\'s core AMD modules have initialized
                setTimeout(function() {
                    
                    // 1. Expand all Categories
                    const expandBtn = document.querySelector("[data-action=\'expandall\']");
                    if (expandBtn && expandBtn.getAttribute("aria-expanded") !== "true") {
                        expandBtn.click();
                    } else {
                        const elements = document.querySelectorAll("a, button, span");
                        for (let el of elements) {
                            if (el.textContent.trim().toLowerCase() === "expand all") {
                                el.click();
                                break;
                            }
                        }
                    }

                }, 400);
            });
            </script>';
        }
        
        // Check if the user is a Site Administrator
        if (is_siteadmin()) {
            global $CFG;
            $sesskey = sesskey();
            $purgeurl = new moodle_url('/admin/purgecaches.php', ['confirm' => 1, 'sesskey' => $sesskey, 'returnurl' => $this->page->url->out_as_local_url(false)]);
            
            // Inject the floating button (No inline styles!)
            $html .= '
            <div class="academy-global-purge position-fixed d-print-none" style="bottom: 30px; left: 30px; z-index: 9999;">
                <a href="'.$purgeurl->out(false).'" class="d-flex align-items-center academy-primary-bg text-white shadow-lg" style="border-radius: 50px; padding: 10px 20px; font-weight: 600; border: none; transition: all 0.3s ease;">
                    <i class="fa fa-refresh fa-spin-hover me-2" style="font-size: 1.1rem;"></i> Purge Caches
                </a>
            </div>
            ';
        }
        
        return $html;
    }

    // =========================================================================
    // 3. FIX BLURRY AVATARS (FORCE HIGH-RES)
    // =========================================================================
    protected function render_user_picture(\user_picture $userpicture) {
        // Force Moodle to use the high-res 100px image instead of the tiny 35px default
        // This guarantees the navbar and participants avatars are perfectly crisp
        $userpicture->size = 100;
        return parent::render_user_picture($userpicture);
    }

}

// =========================================================================
// COURSE CATEGORY OVERRIDE - CONVERT LIST TO CARDS
// =========================================================================

// Ensure Moodle's core course renderer is loaded so we can extend it
global $CFG;
require_once($CFG->dirroot . '/course/renderer.php');

class theme_academy_theme_core_course_renderer extends \core_course_renderer {
    
    // Updated signature to match core_course_renderer exactly
    protected function coursecat_coursebox(\coursecat_helper $chelper, $course, $additionalclasses = '') {
        global $CFG, $USER, $DB;
        require_once($CFG->libdir . '/completionlib.php');

        $context = \context_course::instance($course->id);
        
        // 1. Prepare the data exactly as your coursecard.mustache expects it
        $data = new stdClass();
        $data->id = $course->id;
        $data->fullname = format_string($course->fullname, true, ['context' => $context]);
        $data->viewurl = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        
        // 2. Extract the Course Image
        $data->courseimage = 'https://picsum.photos/600/400'; // Default fallback
        foreach ($course->get_course_overviewfiles() as $file) {
            if ($file->is_valid_image()) {
                $data->courseimage = moodle_url::make_pluginfile_url(
                    $file->get_contextid(), $file->get_component(), $file->get_filearea(), 
                    null, $file->get_filepath(), $file->get_filename()
                )->out(false);
                break;
            }
        }

        // 3. Extract the Category Name
        $category = $DB->get_record('course_categories', ['id' => $course->category], 'name');
        $data->coursecategory = $category ? format_string($category->name) : '';

        // 4. Extract Progress and Last Accessed Data
        $data->hasprogress = false;
        $data->progress = 0;
        $data->lastaccessdate = 'Not accessed yet';

        if (isloggedin() && !isguestuser()) {
            $course_record = get_course($course->id);
            $completion = new completion_info($course_record);
            
            if ($completion->is_enabled()) {
                $data->hasprogress = true;
                $progresspercentage = \core_completion\progress::get_course_progress_percentage($course_record);
                $data->progress = ($progresspercentage !== null) ? round($progresspercentage) : 0;
            }

            $timeaccess = $DB->get_field('user_lastaccess', 'timeaccess', ['userid' => $USER->id, 'courseid' => $course->id]);
            if ($timeaccess) {
                $data->lastaccessdate = 'Last activity on ' . userdate($timeaccess, '%b %d, %y');
            }
        }

        // 5. Render your custom card template instead of the default list
        $card_html = $this->render_from_template('core_course/coursecard', $data);

        // 6. Wrap it in Moodle's native wrapper using the correct variable
        return html_writer::div($card_html, 'coursebox academy-coursebox-override ' . $additionalclasses, [
            'data-courseid' => $course->id,
            'data-type' => '1'
        ]);
    }
}