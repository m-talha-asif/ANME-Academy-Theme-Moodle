<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/adminlib.php');

if ($ADMIN->fulltree) {
    $settings = new theme_boost_admin_settingspage_tabs('themesettingacademy_theme', get_string('configtitle', 'theme_academy_theme'));
    
    // ==========================================
    // Fetch Global Active Users (For Hero, Spotlight & Shoutouts)
    // ==========================================
    $useroptions = [0 => 'None / Disabled'];
    global $DB;
    if ($DB) {
        $active_users = $DB->get_records('user', ['deleted' => 0, 'suspended' => 0], 'firstname ASC, lastname ASC', 'id, firstname, lastname, middlename, alternatename, firstnamephonetic, lastnamephonetic');
        if ($active_users) {
            foreach ($active_users as $u) {
                $useroptions[$u->id] = fullname($u);
            }
        }
    }

    // ==========================================
    // 0. GENERAL SETTINGS (COLORS)
    // ==========================================
    $generalpage = new admin_settingpage('theme_academy_theme_general', get_string('generalsettings', 'theme_academy_theme'));

    // Primary Color Picker
    $generalpage->add(new admin_setting_configcolourpicker(
        'theme_academy_theme/primarycolor',
        get_string('primarycolor', 'theme_academy_theme'),
        get_string('primarycolor_desc', 'theme_academy_theme'),
        '#1B365D', // The default Navy
        null
    ));

    // Secondary Color Picker
    $generalpage->add(new admin_setting_configcolourpicker(
        'theme_academy_theme/secondarycolor',
        get_string('secondarycolor', 'theme_academy_theme'),
        get_string('secondarycolor_desc', 'theme_academy_theme'),
        '#ED8936', // The default Orange
        null
    ));

    $generalpage->add(new admin_setting_configcheckbox(
        'theme_academy_theme/enable_dummy_data',
        get_string('enable_dummy_data', 'theme_academy_theme'),
        get_string('enable_dummy_data_desc', 'theme_academy_theme'),
        1 // Default to 1 (on)
    ));

    $settings->add($generalpage);

    // ==========================================
    // 1. HERO SETTINGS
    // ==========================================
    $page = new admin_settingpage('theme_academy_theme_hero', get_string('herosettings', 'theme_academy_theme'));

    for ($i = 1; $i <= 5; $i++) {
        $marker = '<span class="academy-slide-marker" data-slide="'.$i.'"></span>';
        $page->add(new admin_setting_heading('theme_academy_theme_slide'.$i, 'Slide '.$i, $marker));
        
        $page->add(new admin_setting_configtext('theme_academy_theme/slide'.$i.'_title', 'Title (e.g., A Message from our GM)', 'Keep it short. Maximum 60 characters.', ''));
        
        // Removed Max Character note
        $page->add(new admin_setting_configtextarea('theme_academy_theme/slide'.$i.'_quote', 'Quote Text', '', ''));
        
        // Hero Select User Dropdown
        $settingname = 'theme_academy_theme/slide'.$i.'_userid';
        $title = 'Select Staff Member';
        $description = 'Choose the staff member for this slide. Their name, role, and Moodle profile picture will be loaded automatically.';
        $page->add(new admin_setting_configselect($settingname, $title, $description, 0, $useroptions));
        
        $page->add(new admin_setting_configstoredfile('theme_academy_theme/slide'.$i.'_video_url', 'Upload Video File (MP4 format - Optional)', '', 'slide'.$i.'_video_url', 0, ['accepted_types' => 'video']));
    }

    $page->add(new admin_setting_heading('theme_academy_theme_btn_wrapper', '', '<div id="academy-add-slide-wrapper"></div>'));

    $jshack = <<<HTML
    <style>
        .form-shortname { display: none !important; }
        .form-defaultinfo { display: none !important; }
    </style>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(function() {
            var maxSlides = 5;
            var visibleSlides = 1;

            // Enforce max-length 60 on all Title text inputs
            document.querySelectorAll('input[type="text"][name*="slide"][name*="_title"]').forEach(function(input) {
                input.setAttribute('maxlength', '60');
            });

            for (var i = maxSlides; i >= 1; i--) {
                var hasData = false;
                var inputs = document.querySelectorAll('select[name*="slide' + i + '_"], input[type="text"][name*="slide' + i + '_"], textarea[name*="slide' + i + '_"]');
                for (var j = 0; j < inputs.length; j++) {
                    if (inputs[j].value && inputs[j].value.trim() !== "" && inputs[j].value !== "0") {
                        hasData = true; break;
                    }
                }
                if (hasData) { visibleSlides = i; break; }
            }

            function toggleSlide(slideNum, show) {
                var displayStyle = show ? '' : 'none';
                var marker = document.querySelector('.academy-slide-marker[data-slide="' + slideNum + '"]');
                if (marker) {
                    var headingRow = marker.closest('.form-item') || marker.closest('.row');
                    if (headingRow) headingRow.style.display = displayStyle;
                }
                var allSettingWrappers = document.querySelectorAll('.form-item, .row');
                allSettingWrappers.forEach(function(wrapper) {
                    if (wrapper.id && wrapper.id.indexOf('slide' + slideNum + '_') !== -1) {
                        wrapper.style.display = displayStyle;
                    }
                });
            }

            function renderSlides() {
                for (var i = 2; i <= maxSlides; i++) {
                    toggleSlide(i, i <= visibleSlides);
                }
                var btnMarker = document.getElementById('academy-add-slide-wrapper');
                if (btnMarker) {
                    var btnRow = btnMarker.closest('.form-item') || btnMarker.closest('.row') || btnMarker.parentElement;
                    if (btnRow) {
                        btnRow.style.display = (visibleSlides >= maxSlides) ? 'none' : '';
                    }
                }
            }

            var btnContainer = document.getElementById('academy-add-slide-wrapper');
            if (btnContainer && !document.getElementById('custom-add-slide-btn')) {
                btnContainer.innerHTML = '<div class="row" style="margin-top: 15px; margin-bottom: 35px;"><div class="col-sm-3 col-md-3"></div><div class="col-sm-9 col-md-9"><button type="button" id="custom-add-slide-btn" class="btn btn-secondary" style="font-weight: 600; padding: 8px 16px; border-radius: 6px;"><i class="fa fa-plus" style="margin-right: 6px;"></i> Add Another Slide</button></div></div>';
                var btn = document.getElementById('custom-add-slide-btn');
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (visibleSlides < maxSlides) {
                        visibleSlides++;
                        renderSlides();
                    }
                });
            }
            renderSlides();
        }, 500); 
    });
    </script>
    HTML;
    $page->add(new admin_setting_heading('theme_academy_theme_js_hack', '', $jshack));
    $settings->add($page);

    // ==========================================
    // 2. QUICK LINKS SETTINGS
    // ==========================================
    $qlpage = new admin_settingpage('theme_academy_theme_quicklinks', get_string('quicklinks_heading', 'theme_academy_theme'));
    
    $qlpage->add(new admin_setting_configtext('theme_academy_theme/ql1_url', get_string('ql1_url', 'theme_academy_theme'), get_string('ql_url_desc', 'theme_academy_theme'), '', PARAM_RAW));
    $qlpage->add(new admin_setting_configtext('theme_academy_theme/ql2_url', get_string('ql2_url', 'theme_academy_theme'), get_string('ql_url_desc', 'theme_academy_theme'), '', PARAM_RAW));
    $qlpage->add(new admin_setting_configtext('theme_academy_theme/ql3_url', get_string('ql3_url', 'theme_academy_theme'), get_string('ql_url_desc', 'theme_academy_theme'), '', PARAM_RAW));
    $qlpage->add(new admin_setting_configtext('theme_academy_theme/ql4_url', get_string('ql4_url', 'theme_academy_theme'), get_string('ql_url_desc', 'theme_academy_theme'), '', PARAM_RAW));
    $qlpage->add(new admin_setting_configtext('theme_academy_theme/ql5_url', get_string('ql5_url', 'theme_academy_theme'), get_string('ql_url_desc', 'theme_academy_theme'), '', PARAM_RAW));

    $settings->add($qlpage);

    // ==========================================
    // 3. FOOTER SETTINGS
    // ==========================================
    $footerpage = new admin_settingpage('theme_academy_theme_footer', get_string('footersettings', 'theme_academy_theme'));

    // --- Column 1 ---
    $footerpage->add(new admin_setting_heading('theme_academy_theme_footer_col1', get_string('footer_col1_heading', 'theme_academy_theme'), ''));
    for ($i = 1; $i <= 6; $i++) {
        $footerpage->add(new admin_setting_configtext('theme_academy_theme/footer_col1_text'.$i, get_string('footer_link_text', 'theme_academy_theme', $i), 'Maximum 30 characters.', ''));
        $footerpage->add(new admin_setting_configtext('theme_academy_theme/footer_col1_url'.$i, get_string('footer_link_url', 'theme_academy_theme', $i), '', ''));
    }
    $footerpage->add(new admin_setting_heading('theme_academy_theme_btn_col1', '', '<div id="academy-add-link-col1-wrapper"></div>'));

    // --- Column 2 ---
    $footerpage->add(new admin_setting_heading('theme_academy_theme_footer_col2', get_string('footer_col2_heading', 'theme_academy_theme'), ''));
    for ($i = 1; $i <= 6; $i++) {
        $footerpage->add(new admin_setting_configtext('theme_academy_theme/footer_col2_text'.$i, get_string('footer_link_text', 'theme_academy_theme', $i), 'Maximum 30 characters.', ''));
        $footerpage->add(new admin_setting_configtext('theme_academy_theme/footer_col2_url'.$i, get_string('footer_link_url', 'theme_academy_theme', $i), '', ''));
    }
    $footerpage->add(new admin_setting_heading('theme_academy_theme_btn_col2', '', '<div id="academy-add-link-col2-wrapper"></div>'));

    // --- HR Contact ---
    $footerpage->add(new admin_setting_heading('theme_academy_theme_footer_hr', get_string('footer_hr_heading', 'theme_academy_theme'), ''));
    $footerpage->add(new admin_setting_configtext('theme_academy_theme/footer_phone', get_string('footer_phone', 'theme_academy_theme'), '', ''));
    $footerpage->add(new admin_setting_configtext('theme_academy_theme/footer_email', get_string('footer_email', 'theme_academy_theme'), '', '', PARAM_EMAIL));
    $footerpage->add(new admin_setting_configtext('theme_academy_theme/footer_website', get_string('footer_website', 'theme_academy_theme'), '', ''));
    $footerpage->add(new admin_setting_configtextarea('theme_academy_theme/footer_address', get_string('footer_address', 'theme_academy_theme'), 'Maximum 100 characters.', '', PARAM_RAW));

    // --- Social Links ---
    $footerpage->add(new admin_setting_heading('theme_academy_theme_footer_social', get_string('footer_social_heading', 'theme_academy_theme'), ''));
    $footerpage->add(new admin_setting_configtext('theme_academy_theme/footer_youtube', get_string('footer_youtube', 'theme_academy_theme'), '', ''));
    $footerpage->add(new admin_setting_configtext('theme_academy_theme/footer_instagram', get_string('footer_instagram', 'theme_academy_theme'), '', ''));
    $footerpage->add(new admin_setting_configtext('theme_academy_theme/footer_facebook', get_string('footer_facebook', 'theme_academy_theme'), '', ''));
    $footerpage->add(new admin_setting_configtext('theme_academy_theme/footer_linkedin', get_string('footer_linkedin', 'theme_academy_theme'), '', ''));

    // --- Copyright ---
    $footerpage->add(new admin_setting_heading('theme_academy_theme_footer_bottom', get_string('footer_bottom_heading', 'theme_academy_theme'), ''));
    $footerpage->add(new admin_setting_configtextarea('theme_academy_theme/footer_copyright', get_string('footer_copyright', 'theme_academy_theme'), 'Maximum 200 characters.', ''));

    $footerjshack = <<<HTML
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(function() {
            
            // 1. Enforce max-length of 30 characters on Footer Display Text inputs
            document.querySelectorAll('input[type="text"][name*="footer_col"][name*="_text"]').forEach(function(input) {
                input.setAttribute('maxlength', '30');
            });

            // 2. Enforce max-length of 100 characters on Physical Address (Textarea)
            var addressInput = document.querySelector('textarea[name*="footer_address"]');
            if (addressInput) {
                addressInput.setAttribute('maxlength', '100');
            }

            // 3. Enforce max-length of 200 characters on Copyright Text (Input string)
            var copyrightInput = document.querySelector('input[type="text"][name*="footer_copyright"]');
            if (copyrightInput) {
                copyrightInput.setAttribute('maxlength', '200');
            }

            // 4. NEW: Enforce valid Email format via HTML5 validation
            var emailInput = document.querySelector('input[name*="footer_email"]');
            if (emailInput) {
                // Changing the input type to 'email' triggers the browser's native email validation
                emailInput.setAttribute('type', 'email');
            }

            // Dynamic "Add Another Link" logic
            function initDynamicFooterColumn(colPrefix, btnWrapperId) {
                var maxLinks = 6;
                var visibleLinks = 1;
                for (var i = maxLinks; i >= 1; i--) {
                    var textInput = document.querySelector('input[type="text"][name*="' + colPrefix + '_text' + i + '"]');
                    var urlInput = document.querySelector('input[type="text"][name*="' + colPrefix + '_url' + i + '"]');
                    if ((textInput && textInput.value && textInput.value.trim() !== "") || 
                        (urlInput && urlInput.value && urlInput.value.trim() !== "" && urlInput.value.trim() !== "#")) {
                        visibleLinks = i;
                        break;
                    }
                }
                function toggleLink(linkNum, show) {
                    var displayStyle = show ? '' : 'none';
                    var allSettingWrappers = document.querySelectorAll('.form-item, .row');
                    allSettingWrappers.forEach(function(wrapper) {
                        if (wrapper.id && (wrapper.id.indexOf(colPrefix + '_text' + linkNum) !== -1 || wrapper.id.indexOf(colPrefix + '_url' + linkNum) !== -1)) {
                            wrapper.style.display = displayStyle;
                        }
                    });
                }
                function renderLinks() {
                    for (var i = 2; i <= maxLinks; i++) {
                        toggleLink(i, i <= visibleLinks);
                    }
                    var btnMarker = document.getElementById(btnWrapperId);
                    if (btnMarker) {
                        var btnRow = btnMarker.closest('.form-item') || btnMarker.closest('.row') || btnMarker.parentElement;
                        if (btnRow) {
                            btnRow.style.display = (visibleLinks >= maxLinks) ? 'none' : '';
                        }
                    }
                }
                var btnContainer = document.getElementById(btnWrapperId);
                if (btnContainer && !document.getElementById('custom-add-btn-' + colPrefix)) {
                    btnContainer.innerHTML = '<div class="row" style="margin-top: 5px; margin-bottom: 25px;"><div class="col-sm-3 col-md-3"></div><div class="col-sm-9 col-md-9"><button type="button" id="custom-add-btn-' + colPrefix + '" class="btn btn-secondary" style="font-weight: 600; padding: 6px 12px; border-radius: 6px;"><i class="fa fa-plus" style="margin-right: 6px;"></i> Add Another Link</button></div></div>';
                    var btn = document.getElementById('custom-add-btn-' + colPrefix);
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        if (visibleLinks < maxLinks) {
                            visibleLinks++;
                            renderLinks();
                        }
                    });
                }
                renderLinks();
            }
            initDynamicFooterColumn('footer_col1', 'academy-add-link-col1-wrapper');
            initDynamicFooterColumn('footer_col2', 'academy-add-link-col2-wrapper');
        }, 500); 
    });
    </script>
    HTML;
    $footerpage->add(new admin_setting_heading('theme_academy_theme_footer_js_hack', '', $footerjshack));
    $settings->add($footerpage);

    // ==========================================
    // 4. EMPLOYEE SPOTLIGHT SETTINGS
    // ==========================================
    $spotlightpage = new admin_settingpage('theme_academy_theme_spotlight', 'Employee Spotlight');
    
    for ($i = 1; $i <= 10; $i++) {
        $marker = '<span class="academy-spotlight-marker" data-spotlight="'.$i.'"></span>';
        $spotlightpage->add(new admin_setting_heading('theme_academy_theme_spotlight'.$i, 'Spotlight '.$i, $marker));
        
        $settingname = 'theme_academy_theme/spotlight'.$i.'_userid';
        $spotlightpage->add(new admin_setting_configselect($settingname, 'Select Employee', 'Choose the employee for this spotlight. Their name, role, and Moodle profile picture will be loaded automatically.', 0, $useroptions));
        
        $spotlightpage->add(new admin_setting_configtext('theme_academy_theme/spotlight'.$i.'_quote', 'Short Quote','Maximum 50 characters.', ''));
        $spotlightpage->add(new admin_setting_configtextarea('theme_academy_theme/spotlight'.$i.'_desc', 'Description','Maximum 200 characters.', ''));
    }

    $spotlightpage->add(new admin_setting_heading('theme_academy_theme_spotlight_btn_wrapper', '', '<div id="academy-add-spotlight-wrapper"></div>'));

    $spotlightjshack = <<<HTML
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(function() {
            
            // NEW: Enforce max-length of 50 characters on Short Quotes
            document.querySelectorAll('textarea[name*="spotlight"][name*="_quote"]').forEach(function(textarea) {
                textarea.setAttribute('maxlength', '50');
            });

            // NEW: Enforce max-length of 200 characters on Descriptions
            document.querySelectorAll('textarea[name*="spotlight"][name*="_desc"]').forEach(function(textarea) {
                textarea.setAttribute('maxlength', '200');
            });

            var maxSpotlights = 10;
            var visibleSpotlights = 1;

            for (var i = maxSpotlights; i >= 1; i--) {
                var hasData = false;
                var inputs = document.querySelectorAll('select[name*="spotlight' + i + '_"], input[type="text"][name*="spotlight' + i + '_"], textarea[name*="spotlight' + i + '_"]');
                for (var j = 0; j < inputs.length; j++) {
                    if (inputs[j].value && inputs[j].value.trim() !== "" && inputs[j].value !== "0") {
                        hasData = true; break;
                    }
                }
                if (hasData) { visibleSpotlights = i; break; }
            }

            function toggleSpotlight(num, show) {
                var displayStyle = show ? '' : 'none';
                var marker = document.querySelector('.academy-spotlight-marker[data-spotlight="' + num + '"]');
                if (marker) {
                    var headingRow = marker.closest('.form-item') || marker.closest('.row');
                    if (headingRow) headingRow.style.display = displayStyle;
                }
                var allSettingWrappers = document.querySelectorAll('.form-item, .row');
                allSettingWrappers.forEach(function(wrapper) {
                    if (wrapper.id && wrapper.id.indexOf('spotlight' + num + '_') !== -1) {
                        wrapper.style.display = displayStyle;
                    }
                });
            }

            function renderSpotlights() {
                for (var i = 2; i <= maxSpotlights; i++) {
                    toggleSpotlight(i, i <= visibleSpotlights);
                }
                var btnMarker = document.getElementById('academy-add-spotlight-wrapper');
                if (btnMarker) {
                    var btnRow = btnMarker.closest('.form-item') || btnMarker.closest('.row') || btnMarker.parentElement;
                    if (btnRow) {
                        btnRow.style.display = (visibleSpotlights >= maxSpotlights) ? 'none' : '';
                    }
                }
            }

            var btnContainer = document.getElementById('academy-add-spotlight-wrapper');
            if (btnContainer && !document.getElementById('custom-add-spotlight-btn')) {
                btnContainer.innerHTML = '<div class="row" style="margin-top: 15px; margin-bottom: 35px;"><div class="col-sm-3 col-md-3"></div><div class="col-sm-9 col-md-9"><button type="button" id="custom-add-spotlight-btn" class="btn btn-secondary" style="font-weight: 600; padding: 8px 16px; border-radius: 6px;"><i class="fa fa-plus" style="margin-right: 6px;"></i> Add Another Spotlight</button></div></div>';
                var btn = document.getElementById('custom-add-spotlight-btn');
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (visibleSpotlights < maxSpotlights) {
                        visibleSpotlights++;
                        renderSpotlights();
                    }
                });
            }
            renderSpotlights();
        }, 500); 
    });
    </script>
    HTML;
    
    $spotlightpage->add(new admin_setting_heading('theme_academy_theme_spotlight_js_hack', '', $spotlightjshack));

    // ==========================================
    // 5. GLOBAL JS HACK: REMEMBER TAB STATE
    // ==========================================
    $tab_memory_js = <<<HTML
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(function() {
            if (window.location.hash) {
                var activeTab = document.querySelector('.nav-tabs a[href="' + window.location.hash + '"]');
                if (activeTab) { activeTab.click(); }
            }
            var form = document.querySelector('form#adminsettings');
            if (form && window.location.hash) {
                form.setAttribute('action', form.getAttribute('action').split('#')[0] + window.location.hash);
            }
            var tabs = document.querySelectorAll('.nav-tabs .nav-link, .nav-tabs a');
            tabs.forEach(function(tab) {
                tab.addEventListener('click', function(e) {
                    var hash = this.getAttribute('href');
                    if (hash && hash.startsWith('#')) {
                        history.replaceState(null, null, hash);
                        if (form) {
                            form.setAttribute('action', form.getAttribute('action').split('#')[0] + hash);
                        }
                    }
                });
            });
        }, 300);
    });
    </script>
    HTML;
    $spotlightpage->add(new admin_setting_heading('theme_academy_theme_tab_memory', '', $tab_memory_js));
    $settings->add($spotlightpage);

    // ==========================================
    // 6. WELCOME NEW HIRES SETTINGS
    // ==========================================
    $newhirespage = new admin_settingpage('theme_academy_theme_newhires', 'Welcome New Hires');
    $newhirespage->add(new admin_setting_heading('theme_academy_theme_newhires_heading', 'New Hires Selection', 'Toggle which of the recently created accounts should appear in the "Welcome New Hires" block on the front page. (Showing the 30 most recent accounts to preserve server performance).'));

    if ($DB) {
        $recentusers = $DB->get_records_sql("
            SELECT id, firstname, lastname, email, middlename, alternatename, firstnamephonetic, lastnamephonetic 
            FROM {user} 
            WHERE deleted = 0 AND suspended = 0 AND id > 2 
            ORDER BY timecreated DESC 
            LIMIT 30
        ");
        
        if ($recentusers) {
            foreach ($recentusers as $u) {
                $fullname = fullname($u);
                $settingname = 'theme_academy_theme/newhire_show_' . $u->id;
                $title = $fullname . ' (' . $u->email . ')';
                
                $newhirespage->add(new admin_setting_configcheckbox(
                    $settingname, 
                    $title, 
                    'Show ' . $fullname . ' on the front page', 
                    0 
                ));
            }
        } else {
            $newhirespage->add(new admin_setting_heading('theme_academy_theme_newhires_none', 'No users found', ''));
        }
    }
    $settings->add($newhirespage);

    // ==========================================
    // 7. SHOUT-OUTS SETTINGS
    // ==========================================
    $shoutoutpage = new admin_settingpage('theme_academy_theme_shoutouts', 'Shout-outs');
    
    for ($i = 1; $i <= 6; $i++) {
        $marker = '<span class="academy-shoutout-marker" data-shoutout="'.$i.'"></span>';
        $shoutoutpage->add(new admin_setting_heading('theme_academy_theme_shoutout'.$i, 'Shout-out '.$i, $marker));
        
        $settingname = 'theme_academy_theme/shoutout'.$i.'_userid';
        $shoutoutpage->add(new admin_setting_configselect($settingname, 'Select Employee', 'Choose the employee for this shout-out. Their name, role, and Moodle profile picture will be loaded automatically.', 0, $useroptions));
        
        $shoutoutpage->add(new admin_setting_configtextarea('theme_academy_theme/shoutout'.$i.'_message', 'Message', 'Maximum 250 characters.', ''));
$shoutoutpage->add(new admin_setting_configtext('theme_academy_theme/shoutout'.$i.'_tags', 'Hashtags', 'Maximum 30 characters (e.g., #teamwork #excellence).', ''));
    }

    $shoutoutpage->add(new admin_setting_heading('theme_academy_theme_shoutout_btn_wrapper', '', '<div id="academy-add-shoutout-wrapper"></div>'));

    $shoutoutjshack = <<<HTML
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        setTimeout(function() {

            // NEW: Enforce max-length of 250 characters on Shout-out Messages
            document.querySelectorAll('textarea[name*="shoutout"][name*="_message"]').forEach(function(textarea) {
                textarea.setAttribute('maxlength', '250');
            });

            // NEW: Enforce max-length of 30 characters on Shout-out Hashtags
            document.querySelectorAll('input[type="text"][name*="shoutout"][name*="_tags"]').forEach(function(input) {
                input.setAttribute('maxlength', '30');
            });

            var maxShoutouts = 6;
            var visibleShoutouts = 1;

            for (var i = maxShoutouts; i >= 1; i--) {
                var hasData = false;
                var inputs = document.querySelectorAll('select[name*="shoutout' + i + '_"], input[type="text"][name*="shoutout' + i + '_"], textarea[name*="shoutout' + i + '_"]');
                for (var j = 0; j < inputs.length; j++) {
                    if (inputs[j].value && inputs[j].value.trim() !== "" && inputs[j].value !== "0") {
                        hasData = true; break;
                    }
                }
                if (hasData) { visibleShoutouts = i; break; }
            }

            function toggleShoutout(num, show) {
                var displayStyle = show ? '' : 'none';
                var marker = document.querySelector('.academy-shoutout-marker[data-shoutout="' + num + '"]');
                if (marker) {
                    var headingRow = marker.closest('.form-item') || marker.closest('.row');
                    if (headingRow) headingRow.style.display = displayStyle;
                }
                var allSettingWrappers = document.querySelectorAll('.form-item, .row');
                allSettingWrappers.forEach(function(wrapper) {
                    if (wrapper.id && wrapper.id.indexOf('shoutout' + num + '_') !== -1) {
                        wrapper.style.display = displayStyle;
                    }
                });
            }

            function renderShoutouts() {
                for (var i = 2; i <= maxShoutouts; i++) {
                    toggleShoutout(i, i <= visibleShoutouts);
                }
                var btnMarker = document.getElementById('academy-add-shoutout-wrapper');
                if (btnMarker) {
                    var btnRow = btnMarker.closest('.form-item') || btnMarker.closest('.row') || btnMarker.parentElement;
                    if (btnRow) {
                        btnRow.style.display = (visibleShoutouts >= maxShoutouts) ? 'none' : '';
                    }
                }
            }

            var btnContainer = document.getElementById('academy-add-shoutout-wrapper');
            if (btnContainer && !document.getElementById('custom-add-shoutout-btn')) {
                btnContainer.innerHTML = '<div class="row" style="margin-top: 15px; margin-bottom: 35px;"><div class="col-sm-3 col-md-3"></div><div class="col-sm-9 col-md-9"><button type="button" id="custom-add-shoutout-btn" class="btn btn-secondary" style="font-weight: 600; padding: 8px 16px; border-radius: 6px;"><i class="fa fa-plus" style="margin-right: 6px;"></i> Add Another Shout-out</button></div></div>';
                var btn = document.getElementById('custom-add-shoutout-btn');
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (visibleShoutouts < maxShoutouts) {
                        visibleShoutouts++;
                        renderShoutouts();
                    }
                });
            }
            renderShoutouts();
        }, 500); 
    });
    </script>
    HTML;
    $shoutoutpage->add(new admin_setting_heading('theme_academy_theme_shoutout_js_hack', '', $shoutoutjshack));
    $settings->add($shoutoutpage);

    // ==========================================
    // 8. FRONTPAGE BLOCKS VISIBILITY
    // ==========================================
    $blockspage = new admin_settingpage('theme_academy_theme_block_visibility', 'Block Visibility');
    
    // --- 1. LOGGED-IN USERS ---
    $blockspage->add(new admin_setting_heading('theme_academy_theme_block_visibility_in_heading', 'Logged-In Users', 'Choose which blocks to display on the front page for authenticated users.'));
    
    $blockspage->add(new admin_setting_configcheckbox('theme_academy_theme/show_learning_programs', 'Learning Programs', 'Show the Learning Programs block', 1));
    $blockspage->add(new admin_setting_configcheckbox('theme_academy_theme/show_news_events', 'News & Events', 'Show the News & Events block', 1));
    $blockspage->add(new admin_setting_configcheckbox('theme_academy_theme/show_employee_spotlight', 'Employee Spotlight', 'Show the Employee Spotlight block', 1));
    $blockspage->add(new admin_setting_configcheckbox('theme_academy_theme/show_shoutouts', 'Shout-outs', 'Show the Shout-outs block', 1));
    $blockspage->add(new admin_setting_configcheckbox('theme_academy_theme/show_quick_links', 'Quick Links', 'Show the Quick Links block', 1));
    $blockspage->add(new admin_setting_configcheckbox('theme_academy_theme/show_birthdays', 'Upcoming Birthdays & Anniversaries', 'Show the Birthdays and Anniversaries block', 1));
    $blockspage->add(new admin_setting_configcheckbox('theme_academy_theme/show_new_hires', 'Welcome New Hires', 'Show the Welcome New Hires block', 1));
    
    // NEW: Top Performer Toggle (Logged In)
    $blockspage->add(new admin_setting_configcheckbox('theme_academy_theme/show_top_performer', 'Top Performer', 'Show the Top Performer (Leaderboard) block', 1));

    // --- 2. LOGGED-OUT / GUEST USERS ---
    $blockspage->add(new admin_setting_heading('theme_academy_theme_block_visibility_out_heading', '<br><br>Logged-Out / Guest Users', 'Choose which blocks to display on the front page for visitors who are not logged in.'));
    
    $blockspage->add(new admin_setting_configcheckbox('theme_academy_theme/show_loggedout_learning_programs', 'Learning Programs', 'Show the Learning Programs block', 0));
    $blockspage->add(new admin_setting_configcheckbox('theme_academy_theme/show_loggedout_news_events', 'News & Events', 'Show the News & Events block', 1));
    $blockspage->add(new admin_setting_configcheckbox('theme_academy_theme/show_loggedout_employee_spotlight', 'Employee Spotlight', 'Show the Employee Spotlight block', 1));
    $blockspage->add(new admin_setting_configcheckbox('theme_academy_theme/show_loggedout_shoutouts', 'Shout-outs', 'Show the Shout-outs block', 1));
    $blockspage->add(new admin_setting_configcheckbox('theme_academy_theme/show_loggedout_quick_links', 'Quick Links', 'Show the Quick Links block', 0));
    $blockspage->add(new admin_setting_configcheckbox('theme_academy_theme/show_loggedout_birthdays', 'Upcoming Birthdays & Anniversaries', 'Show the Birthdays and Anniversaries block', 0));
    $blockspage->add(new admin_setting_configcheckbox('theme_academy_theme/show_loggedout_new_hires', 'Welcome New Hires', 'Show the Welcome New Hires block', 1));
    
    // NEW: Top Performer Toggle (Logged Out)
    $blockspage->add(new admin_setting_configcheckbox('theme_academy_theme/show_loggedout_top_performer', 'Top Performer', 'Show the Top Performer (Leaderboard) block', 0));
    
    $settings->add($blockspage);
}