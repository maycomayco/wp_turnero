jQuery(function ($) {
    var $form                = $('#business-hours'),
        $final_step_url      = $('input[name=bookly_url_final_step_url]'),
        $final_step_url_mode = $('#bookly_settings_final_step_url_mode'),
        $help_btn            = $('#bookly-help-btn'),
        $participants        = $('#bookly_appointment_participants')
        ;

    booklyAlert(BooklyL10n.alert);

    Ladda.bind('button[type=submit]', {timeout: 2000});

    $('.select_start', $form).on('change', function () {
        var $flexbox = $(this).closest('.bookly-flexbox'),
            $end_select = $('.select_end', $flexbox),
            start_time = this.value;

        if (start_time) {
            $flexbox.find('.bookly-hide-on-off').show();

            // Hides end time options with value less than in the start time.
            var frag      = document.createDocumentFragment();
            var old_value = $end_select.val();
            var new_value = null;
            $('option', $end_select).each(function () {
                if (this.value <= start_time) {
                    var span = document.createElement('span');
                    span.style.display = 'none';
                    span.appendChild(this.cloneNode(true));
                    frag.appendChild(span);
                } else {
                    frag.appendChild(this.cloneNode(true));
                    if (new_value === null || old_value == this.value) {
                        new_value = this.value;
                    }
                }
            });
            $end_select.empty().append(frag).val(new_value);
        } else { // OFF
            $flexbox.find('.bookly-hide-on-off').hide();
        }
    }).each(function () {
        $(this).data('default_value', this.value);
    }).trigger('change');

    // Reset.
    $('#bookly-hours-reset', $form).on('click', function () {
        $('.select_start', $form).each(function () {
            $(this).val($(this).data('default_value')).trigger('change');
        });
    });

    // Customers Tab
    var $default_country      = $('#bookly_cst_phone_default_country'),
        $default_country_code = $('#bookly_cst_default_country_code');

    $.each($.fn.intlTelInput.getCountryData(), function (index, value) {
        $default_country.append('<option value="' + value.iso2 + '" data-code="' + value.dialCode + '">' + value.name + ' +' + value.dialCode + '</option>');
    });
    $default_country.val(BooklyL10n.default_country);

    $default_country.on('change', function () {
        $default_country_code.val($default_country.find('option:selected').data('code'));
    });

    // Calendar tab
    $participants.on('change', function () {

        $('#bookly_cal_one_participant').hide();
        $('#bookly_cal_many_participants').hide();
        $('#' + $(this).val() ).show();
    }).trigger('change');

    $("#bookly_settings_calendar button[type=reset]").on( 'click', function () {
        setTimeout(function () {
            $participants.trigger('change');
        }, 50 );
    });

    // Company Tab
    $('#bookly-company-reset').on('click', function () {
        var $div = $('#bookly-js-logo .bookly-js-image'),
            $input = $('[name=bookly_co_logo_attachment_id]');
        $div.attr('style', $div.data('style'));
        $input.val($input.data('default'));
    });

    // Cart Tab
    $('#bookly_cart_show_columns').sortable({
        axis : 'y',
        handle : '.bookly-js-handle'
    });

    // Payment Tab
    var $currency = $('#bookly_pmt_currency'),
        $formats  = $('#bookly_pmt_price_format')
    ;
    $currency.on('change', function () {
        $formats.find('option').each(function () {
            var decimals = this.value.match(/{price\|(\d)}/)[1],
                price    = BooklyL10n.sample_price
            ;
            if (decimals < 3) {
                price = price.slice(0, -(decimals == 0 ? 4 : 3 - decimals));
            }
            var html = this.value
                .replace('{sign}', '')
                .replace('{symbol}', $currency.find('option:selected').data('symbol'))
                .replace(/{price\|\d}/, price)
            ;
            html += ' (' + this.value
                .replace('{sign}', '-')
                .replace('{symbol}', $currency.find('option:selected').data('symbol'))
                .replace(/{price\|\d}/, price) + ')'
            ;
            this.innerHTML = html;
        });
    }).trigger('change');

    $('#bookly_paypal_enabled').change(function () {
        $('.bookly-paypal-ec').toggle(this.value == 'ec');
        $('.bookly-paypal-ps').toggle(this.value == 'ps');
        $('.bookly-paypal').toggle(this.value != '0');
    }).change();

    $('#bookly_authorize_net_enabled').change(function () {
        $('.bookly-authorize-net').toggle(this.value != '0');
    }).change();

    $('#bookly_stripe_enabled').change(function () {
        $('.bookly-stripe').toggle(this.value == 1);
    }).change();

    $('#bookly_2checkout_enabled').change(function () {
        $('.bookly-2checkout').toggle(this.value != '0');
    }).change();

    $('#bookly_payu_latam_enabled').change(function () {
        $('.bookly-payu_latam').toggle(this.value != '0');
    }).change();

    $('#bookly_payson_enabled').change(function () {
        $('.bookly-payson').toggle(this.value != '0');
    }).change();

    $('#bookly_mollie_enabled').change(function () {
        $('.bookly-mollie').toggle(this.value != '0');
    }).change();

    $('#bookly_payu_latam_sandbox').change(function () {
        var live = this.value != 1;
        $('.bookly-payu_latam > .form-group:eq(0)').toggle(live);
        $('.bookly-payu_latam > .form-group:eq(1)').toggle(live);
        $('.bookly-payu_latam > .form-group:eq(2)').toggle(live);
    }).change();

    $('#bookly-payments-reset').on('click', function (event) {
        setTimeout(function () {
            $('#bookly_pmt_currency,#bookly_paypal_enabled,#bookly_authorize_net_enabled,#bookly_stripe_enabled,#bookly_2checkout_enabled,#bookly_payu_latam_enabled,#bookly_payson_enabled,#bookly_mollie_enabled,#bookly_payu_latam_sandbox').change();
        }, 0);
    });

    $('#bookly-customer-reset').on('click', function (event) {
        $default_country.val($default_country.data('country'));
    });

    if ($final_step_url.val()) { $final_step_url_mode.val(1); }
    $final_step_url_mode.change(function () {
        if (this.value == 0) {
            $final_step_url.hide().val('');
        } else {
            $final_step_url.show();
        }
    });

    // Change link to Help page according to activated tab.
    var help_link = $help_btn.attr('href');
    $('.bookly-nav li[data-toggle="tab"]').on('shown.bs.tab', function(e) {
        $help_btn.attr('href', help_link + e.target.getAttribute('data-target').substring(1).replace(/_/g, '-'));
    });

    // Holidays Tab
    var d = new Date();
    $('.bookly-js-annual-calendar').jCal({
        day: new Date(d.getFullYear(), 0, 1),
        days:       1,
        showMonths: 12,
        scrollSpeed: 350,
        events:     BooklyL10n.holidays,
        action:     'bookly_settings_holiday',
        csrf_token: BooklyL10n.csrf_token,
        dayOffset:  parseInt(BooklyL10n.start_of_week),
        loadingImg: BooklyL10n.loading_img,
        dow:        BooklyL10n.days,
        ml:         BooklyL10n.months,
        we_are_not_working: BooklyL10n.we_are_not_working,
        repeat:     BooklyL10n.repeat,
        close:      BooklyL10n.close
    });

    $('.bookly-js-jCalBtn').on('click', function (e) {
        e.preventDefault();
        var trigger = $(this).data('trigger');
        $('.bookly-js-annual-calendar').find($(trigger)).trigger('click');
    });

    // Purchase Code Tab.
    $('.bookly-js-detach-pc').on('click', function (e) {
        e.preventDefault();
        if (confirm(BooklyL10n.confirm_detach)) {
            var $this  = $(this),
                $input = $this.closest('.form-group').find('input'),
                name   = $input.prop('id')
            ;
            $input.prop('disabled', true);
            $.post(ajaxurl, {
                action: 'bookly_detach_purchase_code',
                csrf_token: BooklyL10n.csrf_token,
                name: name
            }, function (response) {
                $input.prop('disabled', false);
                if (response.success) {
                    $input.val('');
                    $this.closest('p').remove();
                }
                booklyAlert(response.data.alert);
            });
        }
    });

    // Activate tab.
    $('li[data-target="#bookly_settings_' + BooklyL10n.current_tab + '"]').tab('show');

    $('#bookly-js-logo .bookly-pretty-indicator').on('click', function(){
        var frame = wp.media({
            library: {type: 'image'},
            multiple: false
        });
        frame.on('select', function () {
            var selection = frame.state().get('selection').toJSON(),
                img_src
                ;
            if (selection.length) {
                if (selection[0].sizes['thumbnail'] !== undefined) {
                    img_src = selection[0].sizes['thumbnail'].url;
                } else {
                    img_src = selection[0].url;
                }
                $('[name=bookly_co_logo_attachment_id]').val(selection[0].id);
                $('#bookly-js-logo .bookly-js-image').css({'background-image': 'url(' + img_src + ')', 'background-size': 'cover'});
                $('#bookly-js-logo .bookly-thumb-delete').show();
                $(this).hide();
            }
        });

        frame.open();
    });

    $('#bookly-js-logo .bookly-thumb-delete').on('click', function () {
        var $thumb = $(this).parents('.bookly-js-image');
        $thumb.attr('style', '');
        $('[name=bookly_co_logo_attachment_id]').val('');
    });
});