'use strict';

/**
 * Cron page.
 */
$(document).ready(function () {

    if (!$('body').hasClass('cron')) {
        return;
    }

    var $sidebar = $('#cron-sidebar');
    if (!$sidebar.length) {
        return;
    }

    var $help = $sidebar.find('.cron-help');
    var $recap = $sidebar.find('.cron-recap');
    var $recapConfig = $recap.find('.cron-recap-config');

    var stashRecap = function () {
        var $current = $recapConfig.children('.cron-task-config');
        if ($current.length) {
            $('.cron-task[data-task="' + $current.attr('data-task') + '"]').append($current);
        }
    };

    var showDefault = function () {
        stashRecap();
        $('.cron-task').removeClass('selected');
        $('.cron-task-head').attr('aria-pressed', 'false');
        $recap.prop('hidden', true);
        $help.prop('hidden', false);
    };

    var selectTask = function () {
        var $block = $(this).closest('.cron-task');
        // Clicking the already-open task closes it and shows the default view.
        if ($block.hasClass('selected')) {
            showDefault();
            return;
        }
        stashRecap();
        $('.cron-task').removeClass('selected');
        $('.cron-task-head').attr('aria-pressed', 'false');

        var $config = $('.cron-task[data-task="' + $block.attr('data-task') + '"] > .cron-task-config');
        $recap.find('.cron-recap-name').text($block.find('.cron-task-name').text());
        $recap.find('.cron-recap-module').text($block.find('.cron-task-module').text());
        $recap.find('.cron-recap-desc').text($block.find('.cron-task-desc').text());
        $recapConfig.append($config);

        $block.addClass('selected');
        $(this).attr('aria-pressed', 'true');
        $help.prop('hidden', true);
        $recap.prop('hidden', false);
    };

    // Reserve the space for the always-open sidebar.
    if (window.Omeka && Omeka.reserveSidebarSpace) {
        Omeka.reserveSidebarSpace();
    }

    $('.cron-tasks').on('click', '.cron-task-head', selectTask);

    // Reflect the "enabled" checkbox on its block, live.
    $('.cron-tasks').on('change', 'input[type="checkbox"][name$="[enabled]"]', function () {
        var $config = $(this).closest('.cron-task-config');
        $('.cron-task[data-task="' + $config.attr('data-task') + '"]').toggleClass('enabled', this.checked);
    });

});
