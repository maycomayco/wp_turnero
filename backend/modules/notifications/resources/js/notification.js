jQuery(function($) {

    var $custom_notifications   = $('#bookly-js-custom-notifications'),
        $continue_confirm_modal = $('#bookly-js-continue-confirm');

    Ladda.bind( 'button[type=submit]' );

    // menu fix for WP 3.8.1
    $('#toplevel_page_ab-system > ul').css('margin-left', '0px');

    /* exclude checkboxes in form */
    var $checkboxes = $('.bookly-js-collapse .panel-title > input:checkbox');

    $checkboxes.change(function () {
        $(this).parents('.panel-heading').next().collapse(this.checked ? 'show' : 'hide');
    });

    $('[data-toggle="popover"]').popover({
        html: true,
        placement: 'top',
        trigger: 'hover',
        template: '<div class="popover bookly-font-xs" style="width: 220px" role="tooltip"><div class="popover-arrow"></div><h3 class="popover-title"></h3><div class="popover-content"></div></div>'
    });

    $custom_notifications
        .on('change', "select[name$='[type]']", function () {
            var $panel        = $(this).closest('.panel'),
                $settings     = $panel.find('.bookly-js-settings'),
                $attachIcs    = $panel.find('.bookly-js-attach-ics'),
                value         = $(this).find(':selected').val(),
                set           = $(this).find(':selected').data('set'),
                to            = $(this).find(':selected').data('to'),
                showAttachIcs = $(this).find(':selected').data('attach-ics') === 'show'
            ;

            $panel.find('table.bookly-codes').each(function () {
                $(this).toggle($(this).hasClass('bookly-js-codes-' + value));
            });

            $.each(['customer', 'staff', 'admin'], function (index, value) {
                $panel.find("[name$='[to_" + value + "]']").closest('.checkbox-inline').toggle(to.indexOf(value) != -1);
            });

            $settings.each(function () {
                $(this).toggle($(this).hasClass('bookly-js-' + set));
            });

            $attachIcs.toggle(showAttachIcs);

            switch (set) {
                case 'after_event':
                    var $set = $panel.find('.bookly-js-' + set);
                    $set.find('.bookly-js-to').toggle(value == 'ca_status_changed');
                    $set.find('.bookly-js-with').toggle(value != 'ca_status_changed');
                    break;
            }
        })
        .on('click', '.bookly-js-delete', function () {
            if (confirm(BooklyL10n.are_you_sure)) {
                var $button = $(this),
                    id = $button.data('notification_id');
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'bookly_delete_custom_notification',
                        id: id,
                        csrf_token: BooklyL10n.csrf_token
                    },
                    dataType: 'json',
                    success: function (response) {
                        if (response.success) {
                            $button.closest('.panel').remove();
                        }
                    }
                });
            }
        })
        .find("select[name$='[type]']").trigger('change');

    $('button[type=reset]').on('click', function () {
        setTimeout(function () {
            $("select[name$='[type]']", $custom_notifications).trigger('change');
        }, 0);
    });

    $('.bookly-js-save', $continue_confirm_modal).on('click', function () {
        var ladda = Ladda.create(this);
        ladda.start();
        $.ajax({
            url : ajaxurl,
            type: 'POST',
            data: {
                action    : 'bookly_create_custom_notification',
                render    : true,
                csrf_token: BooklyL10n.csrf_token
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    $custom_notifications.append(response.data.html);
                    var $subject = $custom_notifications.find('#notification_' + response.data.id + '_subject'),
                        $panel   = $subject.closest('.panel-collapse');
                    $panel.closest('.panel').hide();
                    $panel.find("select[name$='[type]']").trigger('change');
                    $panel.append('<input type="hidden" name="new_notification_id" value="' + response.data.id + '"/>');
                }
                $('button[type=submit]').trigger('click');
                $continue_confirm_modal.modal('hide');
            },
            complete: function () {

            }
        });
    });

    $('.bookly-js-continue', $continue_confirm_modal).on('click', function () {
        var ladda = Ladda.create(this);
        ladda.start();
        $.ajax({
            url : ajaxurl,
            type: 'POST',
            data: {
                action    : 'bookly_create_custom_notification',
                csrf_token: BooklyL10n.csrf_token
            },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    var url = window.location.href.match(/(^[^#]*)/);
                    window.location.href = url[0] + '&#notification_' + response.data.id;
                }
            },
            complete: function () {
                ladda.stop();
            }
        });
    });

    $("#bookly-js-new-notification").on('click', function () {
        $continue_confirm_modal.modal('show');
    });

    if (location.hash) {
        // example #notification_45
        var parts = location.hash.split('_');
        if (parts.length > 1) {
            expandNotification(parts[1]);
        }
    } else if (BooklyL10n.current_notification_id) {
        expandNotification(BooklyL10n.current_notification_id);
    }

    function expandNotification(id) {
        var $subject = $custom_notifications.find('#notification_' + id + '_subject'),
            $panel = $subject.closest('.panel-collapse');
        $panel.collapse('show');
        $panel.find("select[name$='[type]']").trigger('change');

        $subject.focus();
        $('html, body').animate({
            scrollTop: $subject.offset().top
        }, 1000);
    }

    booklyAlert(BooklyL10n.alert);
});