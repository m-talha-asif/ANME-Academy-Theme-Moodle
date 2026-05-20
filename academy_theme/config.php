<?php
defined('MOODLE_INTERNAL') || die();

$THEME->name = 'academy_theme';

// We declare Boost as our parent. If Moodle can't find a file in our theme, it looks in Boost.
$THEME->parents = ['boost']; 

$THEME->sheets = [];
$THEME->editor_sheets = [];
$THEME->enable_dock = false;

// This allows us to override core HTML renderers later
$THEME->rendererfactory = 'theme_overridden_renderer_factory';

// Tells Moodle to load our custom SCSS file after loading Boost's SCSS
$THEME->extrascsscallback = 'theme_academy_theme_get_extra_scss';

// NEW: Tell Moodle to use a custom layout file for the front page
$THEME->layouts = [
    'frontpage' => [
        'file' => 'frontpage.php',
        'regions' => ['side-pre'],
        'defaultregion' => 'side-pre',
    ],
];