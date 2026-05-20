<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Serves any files associated with the theme settings.
 */
function theme_academy_theme_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = array()) {
    if ($context->contextlevel == CONTEXT_SYSTEM) {
        $theme = theme_config::load('academy_theme');
        // Add file areas for logos, hero slides, or spotights here if needed
        if (strpos($filearea, 'logo') === 0 || strpos($filearea, 'slide') === 0) {
            return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
        }
    }
    send_file_not_found();
}

/**
 * Injects dynamic SCSS variables from theme settings before loading custom.scss
 */
function theme_academy_theme_get_extra_scss($theme) {
    global $CFG;
    
    // Fetch dynamic colors from settings, fallback to your default brand colors if empty
    $primarycolor = get_config('theme_academy_theme', 'primarycolor');
    $primarycolor = !empty($primarycolor) ? $primarycolor : '#1B365D';
    
    $secondarycolor = get_config('theme_academy_theme', 'secondarycolor');
    $secondarycolor = !empty($secondarycolor) ? $secondarycolor : '#ED8936';

    // NEW: URL-encode the hex color so it doesn't break the SVG background
    $secondary_svg = str_replace('#', '%23', $secondarycolor);

    // 1. Declare the settings as SCSS variables
    $scss = '
        $primary-navy: ' . $primarycolor . ';
        $accent-orange: ' . $secondarycolor . ';
        $accent-orange-svg: \'' . $secondary_svg . '\'; 
    ';

    // 2. Load the physical custom.scss file
    $scssfile = $CFG->dirroot . '/theme/academy_theme/scss/custom.scss';
    if (file_exists($scssfile)) {
        $scss .= file_get_contents($scssfile);
    }
    
    return $scss;
}