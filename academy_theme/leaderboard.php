<?php
// This file is part of Moodle - http://moodle.org/

// Include Moodle's core configuration
require_once('../../config.php');
require_once($CFG->dirroot . '/course/lib.php');

global $DB, $PAGE, $OUTPUT, $USER;

// Set up the page context and URL
$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/theme/academy_theme/leaderboard.php'));
$PAGE->set_title('Academy Leaderboard');
$PAGE->set_heading('Academy Leaderboard');
$PAGE->set_pagelayout('standard');

// Ensure the user is logged in to view the leaderboard
require_login();

// --- 1. FETCH ALL STUDENTS AND CALCULATE AVERAGES ---
$sql = "SELECT gg.userid, AVG((gg.finalgrade / gi.grademax) * 100) AS average_grade
        FROM {grade_grades} gg
        JOIN {grade_items} gi ON gi.id = gg.itemid
        WHERE gg.finalgrade IS NOT NULL
          AND gi.grademax > 0
          AND gi.itemtype = 'mod' 
        GROUP BY gg.userid
        ORDER BY average_grade DESC";
        
$top_students = $DB->get_records_sql($sql);
$leaderboard_data = [];

if ($top_students) {
    $rank = 1;
    foreach ($top_students as $userid => $data) {
        $u = $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'suspended' => 0], '*', IGNORE_MISSING);
        if (!$u) continue; 

        // Get profile picture safely
        $userpicture = new user_picture($u);
        $userpicture->size = 512;
        $profile_pic = $userpicture->get_url($PAGE)->out(false);
        
        $avg_grade = round($data->average_grade, 1);
        
        // --- 2. FETCH MOST RECENT ACTIVITY FOR CONTEXT ---
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

        $recent_action = "Completed an activity";
        $recent_score = "";
        $time_ago = "recently";

        if ($recent_grade) {
            $recent_action = format_string($recent_grade->itemname);
            $recent_score = round($recent_grade->finalgrade, 1) . "/" . round($recent_grade->grademax, 0);
            
            $time_diff = time() - $recent_grade->timemodified;
            if ($time_diff < 60) { $time_ago = 'just now'; }
            elseif ($time_diff < 3600) { $time_ago = floor($time_diff / 60) . ' mins ago'; }
            elseif ($time_diff < 86400) { $time_ago = floor($time_diff / 3600) . ' hours ago'; }
            else { $time_ago = floor($time_diff / 86400) . ' days ago'; }
        }

        // --- 3. BUILD THE DATA ARRAY ---
        $leaderboard_data[] = [
            'userid' => $userid,
            'rank' => $rank,
            'fullname' => fullname($u),
            'profile_img' => $profile_pic,
            'average_grade' => $avg_grade,
            'recent_action' => $recent_action,
            'recent_score' => $recent_score,
            'time_ago' => $time_ago
        ];
        $rank++;
    }
}

echo $OUTPUT->header();
?>

<div class="container-fluid px-3 px-md-0 mx-auto mt-4 mb-5" style="max-width: 900px;">
    
    <div class="card p-5 shadow-sm border-0 academy-lb-header mb-4" style="border-radius: 12px; color: white;">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-center text-center text-md-start">
            <div>
                <div class="badge bg-warning text-dark mb-3 px-3 py-2 rounded-pill" style="font-weight: 600; font-size: 0.9rem;"><i class="fa fa-trophy me-2"></i>Top Performers</div>
                <h2 class="fw-bold mb-2 text-white">Global Leaderboard</h2>
                <p class="mb-0" style="opacity: 0.85; font-size: 1.05rem;">Ranking based on average module scores across all learning programs.</p>
            </div>
            <div class="mt-4 mt-md-0 text-center">
                <div class="fw-bold" style="font-size: 3rem; line-height: 1;"><?= count($leaderboard_data) ?></div>
                <div style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; opacity: 0.8;">Ranked Learners</div>
            </div>
        </div>
    </div>

    <div class="list-group shadow-sm border-0" style="border-radius: 12px; overflow: hidden;">
        
        <?php if (!empty($leaderboard_data)): ?>
            <?php foreach ($leaderboard_data as $student): ?>
                
                <?php 
                    $is_current_user = ($student['userid'] == $USER->id); 
                    $is_first = ($student['rank'] === 1);
                    $is_second = ($student['rank'] === 2);
                    $is_third = ($student['rank'] === 3);
                ?>

                <div class="list-group-item d-flex flex-column flex-md-row align-items-md-center p-4 border-bottom border-light <?= $is_current_user ? 'academy-highlight-row' : '' ?>">
                    
                    <div class="d-flex align-items-center mb-3 mb-md-0 me-md-4">
                        <div class="lb-rank-badge d-flex justify-content-center align-items-center shadow-sm" style="width: 45px; height: 45px; border-radius: 50%; font-weight: 900; font-size: 1.2rem; background-color: <?= $is_first ? '#FDE68A' : ($is_second ? '#E2E8F0' : ($is_third ? '#FED7AA' : '#F1F5F9')) ?>; color: <?= $is_first ? '#B45309' : ($is_second ? '#475569' : ($is_third ? '#C2410C' : '#64748B')) ?>;">
                            <?= $student['rank'] ?>
                        </div>
                        <img src="<?= $student['profile_img'] ?>" class="rounded-circle ms-3 shadow-sm" style="width: 60px; height: 60px; object-fit: cover; border: 3px solid <?= $is_first ? '#FDE68A' : 'transparent' ?>;" alt="<?= $student['fullname'] ?>">
                    </div>

                    <div class="flex-grow-1">
                        <h5 class="mb-1 fw-bold <?= $is_current_user ? 'academy-accent-text' : 'text-dark' ?>">
                            <?= $student['fullname'] ?> 
                            <?php if($is_current_user): ?><span class="badge academy-accent-bg text-white ms-2" style="font-size: 0.7rem; vertical-align: middle;">YOU</span><?php endif; ?>
                        </h5>
                        
                        <div class="d-flex flex-wrap align-items-center text-muted mt-2" style="font-size: 0.85rem;">
                            <div class="me-3 mb-1"><i class="fa fa-history me-1"></i> <?= $student['recent_action'] ?></div>
                            <div class="me-3 mb-1"><i class="fa fa-bullseye me-1"></i> Scored <?= $student['recent_score'] ?></div>
                            <div class="mb-1"><i class="fa fa-clock-o me-1"></i> <?= $student['time_ago'] ?></div>
                        </div>
                    </div>

                    <div class="lb-score-box ms-md-4 mt-3 mt-md-0 text-md-end text-start">
                        <div style="font-size: 0.8rem; text-transform: uppercase; font-weight: 700; opacity: 0.7; margin-bottom: -2px;">Overall Score</div>
                        <div class="fw-bold <?= $is_first ? 'text-dark' : '' ?>" style="font-size: 1.6rem; letter-spacing: -0.5px;">
                            <?= $student['average_grade'] ?><span style="font-size: 1rem;">%</span>
                        </div>
                    </div>

                </div>

            <?php endforeach; ?>
        <?php else: ?>
            
            <div class="alert alert-light text-center py-5 shadow-sm rounded-3" style="border: 2px dashed #E2E8F0;">
                <i class="fa fa-bar-chart text-muted mb-3" style="font-size: 3rem; opacity: 0.5;"></i>
                <h4 class="fw-bold text-dark">No Data Available Yet</h4>
                <p class="text-muted mb-0">The leaderboard will automatically update as soon as grades are recorded.</p>
            </div>

        <?php endif; ?>

    </div>
    
    <div class="text-center mt-5">
        <a href="<?= $CFG->wwwroot ?>" class="btn btn-outline-secondary px-4 py-2 rounded-pill fw-bold shadow-sm"><i class="fa fa-home me-2"></i> Return to Dashboard</a>
    </div>

</div>

<?php echo $OUTPUT->footer(); ?>