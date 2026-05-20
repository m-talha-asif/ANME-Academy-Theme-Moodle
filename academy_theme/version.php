<?php
defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026042807; // Today's date + 00
$plugin->requires  = 2022112800; // Requires Moodle 4.1+
$plugin->component = 'theme_academy_theme'; 
$plugin->dependencies = [
    'theme_boost' => 2022112800 // We are a child of Boost
];