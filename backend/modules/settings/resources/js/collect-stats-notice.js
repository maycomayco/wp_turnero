jQuery(function ($) {
    var $alert = $('#bookly-collect-stats-notice');
    $alert.on('close.bs.alert', function () {
        $.post(ajaxurl, {action: 'bookly_dismiss_collect_stats_notice', csrf_token : SupportL10n.csrf_token});
    });
});
