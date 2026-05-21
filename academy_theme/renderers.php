<?php
defined('MOODLE_INTERNAL') || die();

class theme_academy_theme_core_renderer extends \theme_boost\output\core_renderer {
    
    // =========================================================================
    // 1. GLOBAL TEMPLATE INTERCEPTOR (FOOTER, COURSE BANNER & CATEGORY TREE)
    // =========================================================================
    public function render_from_template($templatename, $context) {
        global $PAGE, $DB, $COURSE, $USER, $CFG;

        $layouts_with_footer = [
            'theme_boost/footer', 
            'theme_academy_theme/footer',
            'theme_boost/drawers', 
            'theme_academy_theme/drawers',
            'theme_academy_theme/frontpage'
        ];

        if (in_array($templatename, $layouts_with_footer)) {
            
            if ($context instanceof \templatable) {
                $context = $context->export_for_template($this);
            }
            if (is_object($context)) {
                $context = (array) $context;
            }

            // --- A. FOOTER RENDERING LOGIC ---
            static $footervars = null;
            if ($footervars === null) {
                $col1_html = '';
                for ($i = 1; $i <= 6; $i++) {
                    $text = get_config('theme_academy_theme', 'footer_col1_text'.$i);
                    $url = get_config('theme_academy_theme', 'footer_col1_url'.$i);
                    if (!empty(trim($text)) && !empty(trim($url))) {
                        $col1_html .= '<li><a href="'.s($url).'" target="_blank" rel="noopener noreferrer">'.s($text).'</a></li>';
                    }
                }

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

            $context = array_merge($context, $footervars);

            // --- B. COURSE BANNER INTERCEPTOR LOGIC ---
            if ($templatename === 'theme_boost/drawers' || $templatename === 'theme_academy_theme/drawers') {
                
                $is_course_page = ($PAGE->pagelayout === 'course' && $COURSE->id != SITEID);
                $context['is_custom_course_page'] = $is_course_page;

                if ($is_course_page) {
                    $coursecontext = context_course::instance($COURSE->id);
                    $context['course_title'] = format_string($COURSE->fullname, true, ['context' => $coursecontext]);
                    
                    $summary = format_text($COURSE->summary, $COURSE->summaryformat, ['context' => $coursecontext]);
                    $context['course_summary_full'] = $summary;
                    $context['course_excerpt'] = shorten_text(strip_tags($summary), 200); 

                    $category = $DB->get_record('course_categories', ['id' => $COURSE->category], 'name');
                    $context['course_category'] = $category ? format_string($category->name) : 'General';
                    
                    // Direct File Storage API fetch (Safe regardless of enrolment page context)
                    $course_thumbnail = 'https://picsum.photos/1200/400'; 
                    $fs = get_file_storage();
                    $files = $fs->get_area_files($coursecontext->id, 'course', 'overviewfiles', false, 'sortorder DESC', false);
                    foreach ($files as $file) {
                        if ($file->is_valid_image()) {
                            $course_thumbnail = moodle_url::make_pluginfile_url(
                                $file->get_contextid(), $file->get_component(), $file->get_filearea(), 
                                null, $file->get_filepath(), $file->get_filename()
                            )->out(false);
                            break;
                        }
                    }
                    $context['course_thumbnail'] = $course_thumbnail;
                    
                    $context['is_editing'] = $PAGE->user_is_editing();

                    // ====== CAPABILITY SAFE ZONE ======
                    $is_enrolled = is_enrolled($coursecontext, $USER->id);
                    $is_admin_or_teacher = has_capability('moodle/course:update', $coursecontext);
                    $has_full_access = $is_enrolled || $is_admin_or_teacher;

                    $context['hasprogress'] = false;
                    $context['progress'] = 0;
                    $context['is_completed'] = false;
                    $context['is_in_progress'] = false;
                    $context['last_accessed_date'] = 'Never accessed';
                    $context['count_lessons'] = 0;
                    $context['count_topics'] = 0;
                    $context['count_quizzes'] = 0;
                    $context['course_content_sections'] = [];
                    $context['course_topics'] = [];
                    $context['has_certificate'] = false;
                    $context['certificate_unlocked'] = false;

                    $context['instructor_name'] = "Academy Team";
                    $context['instructor_img'] = "";
                    $sql_teacher = "SELECT u.* FROM {user} u
                                    JOIN {role_assignments} ra ON ra.userid = u.id
                                    JOIN {role} r ON r.id = ra.roleid
                                    WHERE ra.contextid = :ctx AND r.shortname IN ('editingteacher', 'teacher')";
                    if ($teacher = $DB->get_record_sql($sql_teacher, ['ctx' => $coursecontext->id], IGNORE_MULTIPLE)) {
                        $context['instructor_name'] = fullname($teacher);
                        $userpicture = new user_picture($teacher);
                        $userpicture->size = 100;
                        $context['instructor_img'] = $userpicture->get_url($PAGE)->out(false);
                    }

                    $context['enrolled_avatars'] = [];
                    $sql_users = "SELECT u.* FROM {user} u 
                                  JOIN {user_enrolments} ue ON ue.userid = u.id 
                                  JOIN {enrol} e ON e.id = ue.enrolid 
                                  WHERE e.courseid = :cid";
                    $sample_users = $DB->get_records_sql($sql_users, ['cid' => $COURSE->id], 0, 3);
                    foreach ($sample_users as $su) {
                        $supic = new user_picture($su);
                        $supic->size = 100;
                        $context['enrolled_avatars'][] = [
                            'url' => $supic->get_url($PAGE)->out(false),
                            'name' => fullname($su)
                        ];
                    }

                    $enrolled_count = $DB->count_records_sql("SELECT COUNT(DISTINCT ue.userid) FROM {user_enrolments} ue JOIN {enrol} e ON e.id = ue.enrolid WHERE e.courseid = :cid", ['cid' => $COURSE->id]);
                    $context['enrolled_count'] = $enrolled_count;
                    $context['has_multiple_enrolled'] = ($enrolled_count > 3);
                    $context['extra_enrolled_count'] = max(0, $enrolled_count - 3);
                    $context['participants_url'] = (new moodle_url('/user/index.php', ['id' => $COURSE->id]))->out(false);
                    $context['course_date'] = userdate($COURSE->startdate, '%B %Y');

                    if ($has_full_access) {
                        require_once($CFG->libdir . '/completionlib.php');
                        $completion = new completion_info($COURSE);
                        
                        if ($is_enrolled && $completion->is_enabled()) {
                            $context['hasprogress'] = true;
                            $progresspercentage = \core_completion\progress::get_course_progress_percentage($COURSE);
                            $progress = ($progresspercentage !== null) ? round($progresspercentage) : 0;
                            
                            $context['progress'] = $progress;
                            $context['is_completed'] = ($progress == 100);
                            $context['is_in_progress'] = ($progress > 0 && $progress < 100);

                            $timeaccess = $DB->get_field('user_lastaccess', 'timeaccess', ['userid' => $USER->id, 'courseid' => $COURSE->id]);
                            if ($timeaccess) {
                                $context['last_accessed_date'] = 'Last accessed on ' . userdate($timeaccess, '%b %d, %Y');
                            }
                        }

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
                                    
                                    if ($cm->uservisible || $cm->is_visible_on_course_page()) {
                                        $total_in_section++;
                                        
                                        if (in_array($cm->modname, $valid_lesson_types)) { $lessoncount++; }
                                        if ($cm->modname === 'quiz') { $quizcount++; }

                                        $is_completed = false;
                                        if ($completion->is_enabled($cm) != COMPLETION_TRACKING_NONE) {
                                            $completion_data = $completion->get_data($cm, true);
                                            if ($completion_data->completionstate == COMPLETION_COMPLETE || $completion_data->completionstate == COMPLETION_COMPLETE_PASS) {
                                                $is_completed = true;
                                                $completed_in_section++;
                                            }
                                        }

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
                                            'is_locked' => !$cm->uservisible 
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

                        if (!empty($modinfo->instances['customcert']) || !empty($modinfo->instances['certificate'])) {
                            $context['has_certificate'] = true;
                            if ($context['progress'] == 100) {
                                $context['certificate_unlocked'] = true;
                            }
                        }

                        $timeline = [];
                        foreach ($custom_sections as $index => $sec) {
                            $timeline[] = [
                                'id' => $sec['id'],
                                'num' => $index + 1,
                                'title' => $sec['title'],
                                'modcount' => $sec['topics_count'] . ' items',
                                'is_completed' => ($sec['progress_percent'] == 100),
                                'section_num' => $index + 1 
                            ];
                        }
                        $context['course_topics'] = $timeline;
                    }

                    if ($is_enrolled) {
                        $context['course_status'] = ($context['progress'] == 100) ? 'Completed' : (($context['progress'] > 0) ? 'In Progress' : 'Not Started');
                        $context['continue_btn_text'] = ($context['progress'] > 0) ? 'Continue Course' : 'Start Course';
                        $context['continue_url'] = (new moodle_url('/course/view.php', ['id' => $COURSE->id]))->out(false);
                    } else {
                        $context['course_status'] = 'Not Enrolled';
                        $context['continue_btn_text'] = 'View Course Details';
                        $context['continue_url'] = (new moodle_url('/enrol/index.php', ['id' => $COURSE->id]))->out(false);
                    }

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
                            
                            $reviews = $DB->get_records('local_academy_reviews', ['courseid' => $COURSE->id], 'timecreated DESC', '*', 0, 3);
                            $recent_reviews = [];
                            foreach ($reviews as $rev) {
                                $ru = $DB->get_record('user', ['id' => $rev->userid]);
                                if (!$ru) continue; 
                                
                                $rupic = new user_picture($ru);
                                $rupic->size = 100;
                                
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

                            $overall_stars = '';
                            $rating = round($stats->avgrating * 2) / 2;
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

                    if (empty($context['stars_html'])) {
                        $context['stars_html'] = '<i class="fa fa-star-o text-muted"></i><i class="fa fa-star-o text-muted"></i><i class="fa fa-star-o text-muted"></i><i class="fa fa-star-o text-muted"></i><i class="fa fa-star-o text-muted"></i>';
                    }

                    $use_dummy_data = get_config('theme_academy_theme', 'enable_dummy_data') == 1;

                    if ($use_dummy_data) {
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
                }
            }
        }

        // Render the Moodle Core HTML cleanly
        $html = parent::render_from_template($templatename, $context);

        // --- C. HTML INTERCEPTOR: SURGICAL CLASS REPLACEMENT ---
        // Added is_string() check to fix str_replace deprecation warnings
        if (is_string($html) && ($templatename === 'core_course/category_tree' || $templatename === 'core_course/coursecategory')) {
            if ($PAGE->pagetype === 'course-index-category' || strpos($_SERVER['REQUEST_URI'], '/course/index.php') !== false) {
                
                $modified_html = preg_replace_callback('/class=["\'](.*?)["\']/i', function ($matches) {
                    $classArray = array_filter(explode(' ', $matches[1]));
                    
                    if (in_array('collapse', $classArray) && !in_array('collapsed', $classArray)) {
                        if (!in_array('show', $classArray)) {
                            $classArray[] = 'show';
                        }
                        $classArray = array_diff($classArray, ['notloaded', 'hidden']);
                    }
                    
                    if (in_array('collapsed', $classArray)) {
                        $classArray = array_diff($classArray, ['collapsed']);
                    }
                    
                    return 'class="' . implode(' ', $classArray) . '"';
                }, $html);

                if ($modified_html !== null) {
                    $html = $modified_html;
                }

                $html = str_replace('aria-expanded="false"', 'aria-expanded="true"', $html);
                $html = str_replace("aria-expanded='false'", "aria-expanded='true'", $html);
                
                $html = str_replace('data-expanded="false"', 'data-expanded="true"', $html);
                $html = str_replace("data-expanded='false'", "data-expanded='true'", $html);
                $html = str_replace('data-expanded="0"', 'data-expanded="1"', $html);
            }
        }

        return $html;
    }

    // =========================================================================
    // 2. ADMIN ONLY - VIEW TOGGLE 
    // =========================================================================
    public function standard_after_main_region_html() {
        $html = parent::standard_after_main_region_html();
        global $PAGE;
        
        if ($PAGE->pagetype === 'course-index-category' || strpos($_SERVER['REQUEST_URI'], '/course/index.php') !== false) {
            $html .= '
            <script>
            document.addEventListener("DOMContentLoaded", function() {
                setTimeout(function() {
                    const coursesContainers = document.querySelectorAll(".course_category_tree .courses, .category-browse .courses");
                    
                    if (coursesContainers.length > 0 && !document.querySelector(".academy-view-toggle")) {
                        
                        const savedView = localStorage.getItem("academyCourseView") || "grid";
                        
                        coursesContainers.forEach(container => {
                            if (savedView === "list") {
                                container.classList.add("academy-list-view");
                            }
                        });

                        const firstContainer = coursesContainers[0];
                        const toggleHtml = `
                            <div class="academy-view-toggle d-flex justify-content-end mb-3">
                                <div class="btn-group shadow-sm" role="group" style="border-radius: 8px; overflow: hidden;">
                                    <button type="button" class="btn btn-outline-secondary ${savedView === "grid" ? "active academy-primary-bg text-white" : "bg-white"}" id="btn-view-grid" title="Grid View" style="border-color: #E2E8F0;">
                                        <i class="fa fa-th"></i>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary ${savedView === "list" ? "active academy-primary-bg text-white" : "bg-white"}" id="btn-view-list" title="List View" style="border-color: #E2E8F0;">
                                        <i class="fa fa-list"></i>
                                    </button>
                                </div>
                            </div>
                        `;
                        firstContainer.insertAdjacentHTML("beforebegin", toggleHtml);

                        const btnGrid = document.getElementById("btn-view-grid");
                        const btnList = document.getElementById("btn-view-list");

                        btnGrid.addEventListener("click", function(e) {
                            e.preventDefault();
                            coursesContainers.forEach(container => container.classList.remove("academy-list-view"));
                            localStorage.setItem("academyCourseView", "grid");
                            
                            btnGrid.classList.add("active", "academy-primary-bg", "text-white");
                            btnGrid.classList.remove("bg-white");
                            btnList.classList.remove("active", "academy-primary-bg", "text-white");
                            btnList.classList.add("bg-white");
                        });

                        btnList.addEventListener("click", function(e) {
                            e.preventDefault();
                            coursesContainers.forEach(container => container.classList.add("academy-list-view"));
                            localStorage.setItem("academyCourseView", "list");
                            
                            btnList.classList.add("active", "academy-primary-bg", "text-white");
                            btnList.classList.remove("bg-white");
                            btnGrid.classList.remove("active", "academy-primary-bg", "text-white");
                            btnGrid.classList.add("bg-white");
                        });
                    }
                }, 400);
            });
            </script>';
        }
        
        return $html;
    }

    protected function render_user_picture(\user_picture $userpicture) {
        $userpicture->size = 100;
        return parent::render_user_picture($userpicture);
    }
}

// =========================================================================
// COURSE CATEGORY OVERRIDES
// =========================================================================
global $CFG;
require_once($CFG->dirroot . '/course/renderer.php');

class theme_academy_theme_core_course_renderer extends \core_course_renderer {

    // =========================================================================
    // NEW: SURGICAL OVERRIDE OF CATEGORY CONTENT TO FORCE COURSES
    // =========================================================================
    protected function coursecat_category_content(\coursecat_helper $chelper, $coursecat, $depth) {
        $html = '';

        if ($coursecat->get_courses_count() > 0) {
            $courses = $coursecat->get_courses(['limit' => 0]);
            if (!empty($courses)) {
                $html .= html_writer::start_tag('div', ['class' => 'courses']);
                foreach ($courses as $course) {
                    $html .= $this->coursecat_coursebox($chelper, $course);
                }
                $html .= html_writer::end_tag('div');
            }
        }

        if ($coursecat->get_children_count() > 0) {
            $chelper->set_subcat_depth(0); 
            $html .= $this->coursecat_subcategories($chelper, $coursecat, $depth);
        }

        return $html;
    }

    // =========================================================================
    // EXPAND CATEGORIES ON LOAD
    // =========================================================================
    // protected function coursecat_category(\coursecat_helper $chelper, $coursecat, $depth) {
    //     global $PAGE;
        
    //     if ($PAGE->pagetype === 'course-index-category' || strpos($_SERVER['REQUEST_URI'], '/course/index.php') !== false) {
    //         $chelper->set_show_courses(0); 
    //         $chelper->set_subcat_depth(0);  
    //     }

    //     $html = parent::coursecat_category($chelper, $coursecat, $depth);

    //     // Added is_string() check to fix str_replace deprecation warnings
    //     // if (is_string($html) && ($PAGE->pagetype === 'course-index-category' || strpos($_SERVER['REQUEST_URI'], '/course/index.php') !== false)) {
            
    //     //     $modified_html = preg_replace_callback('/class=["\'](.*?)["\']/i', function ($matches) {
    //     //         $classArray = array_filter(explode(' ', $matches[1]));
                
    //     //         if (in_array('collapse', $classArray) && !in_array('collapsed', $classArray)) {
    //     //             if (!in_array('show', $classArray)) {
    //     //                 $classArray[] = 'show'; 
    //     //             }
    //     //             $classArray = array_diff($classArray, ['notloaded', 'hidden']);
    //     //         }
                
    //     //         if (in_array('collapsed', $classArray)) {
    //     //             $classArray = array_diff($classArray, ['collapsed']);
    //     //         }
                
    //     //         return 'class="' . implode(' ', $classArray) . '"';
    //     //     }, $html);

    //     //     if ($modified_html !== null) {
    //     //         $html = $modified_html;
    //     //     }

    //     //     $html = str_replace('aria-expanded="false"', 'aria-expanded="true"', $html);
    //     //     $html = str_replace("aria-expanded='false'", "aria-expanded='true'", $html);
            
    //     //     $html = str_replace('data-expanded="false"', 'data-expanded="true"', $html);
    //     //     $html = str_replace("data-expanded='false'", "data-expanded='true'", $html);
    //     //     $html = str_replace('data-expanded="0"', 'data-expanded="1"', $html);
    //     // }

    //     return $html;
    // }

    // =========================================================================
    // EXISTING: CUSTOM COURSE CARDS
    // =========================================================================
    protected function coursecat_coursebox(\coursecat_helper $chelper, $course, $additionalclasses = '') {
        global $CFG, $USER, $DB;
        require_once($CFG->libdir . '/completionlib.php');

        $context = \context_course::instance($course->id);
        
        $data = new stdClass();
        $data->id = $course->id;
        $data->fullname = format_string($course->fullname, true, ['context' => $context]);
        $data->viewurl = (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        
        // [CRITICAL FIX]: Directly query File Storage instead of using object methods.
        // This prevents the "Call to undefined method stdClass::get_course_overviewfiles()" 
        // error when Moodle passes a raw database row instead of a rich object.
        $data->courseimage = 'https://picsum.photos/600/300';
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', false, 'sortorder DESC', false);
        
        foreach ($files as $file) {
            if ($file->is_valid_image()) {
                $data->courseimage = moodle_url::make_pluginfile_url(
                    $file->get_contextid(), $file->get_component(), $file->get_filearea(), 
                    null, $file->get_filepath(), $file->get_filename()
                )->out(false);
                break;
            }
        }

        $category = $DB->get_record('course_categories', ['id' => $course->category], 'name');
        $data->coursecategory = $category ? format_string($category->name) : '';

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

        $card_html = $this->render_from_template('core_course/coursecard', $data);

        return html_writer::div($card_html, 'coursebox academy-coursebox-override ' . $additionalclasses, [
            'data-courseid' => $course->id,
            'data-type' => '1'
        ]);
    }
}