jQuery(function($) {

    var
        $table          = $('#bookly-analytics-table'),
        $dateFilter     = $('#bookly-filter-date'),
        $staffFilter    = $('#bookly-filter-staff'),
        $servicesFilter = $('#bookly-filter-services'),
        $printDialog    = $('#bookly-print-dialog'),
        $printButton    = $('#bookly-print'),
        $exportDialog   = $('#bookly-export-dialog'),
        $exportButton   = $('#bookly-export')
    ;

    /**
     * Init DataTables.
     */
    var dt = $table.DataTable({
        order:      [[ 0, 'desc' ]],
        info:       false,
        paging:     false,
        searching:  false,
        processing: true,
        responsive: true,
        ajax: {
            url : ajaxurl,
            type: 'POST',
            data: function () {
                var staff_ids   = [],
                    service_ids = [];
                $staffFilter.find('.bookly-js-check-entity:checked').each(function () {
                    staff_ids.push(this.value);
                });
                $servicesFilter.find('.bookly-js-check-entity:checked').each(function () {
                    service_ids.push(this.value);
                });

                return {
                    action      : 'bookly_get_analytics',
                    csrf_token  : BooklyL10n.csrfToken,
                    date        : $dateFilter.data('date'),
                    staff_ids   : staff_ids,
                    service_ids : service_ids
                };
            },
            dataSrc: function (json) {
                var $ths = $table.find('tfoot th');
                $ths.eq(1).html(json.total.visits.sessions);
                $ths.eq(2).html(json.total.visits.approved);
                $ths.eq(3).html(json.total.visits.pending);
                $ths.eq(4).html(json.total.visits.rejected);
                $ths.eq(5).html(json.total.visits.cancelled);
                $ths.eq(6).html(json.total.customers.customers);
                $ths.eq(7).html(json.total.customers.new_customers);
                $ths.eq(8).html(json.total.payments.total_formatted);

                return json.data;
            }
        },
        columns: [
            { data: 'staff', render: $.fn.dataTable.render.text() },
            { data: 'service', render: $.fn.dataTable.render.text() },
            { data: 'visits.sessions' },
            { data: 'visits.approved' },
            { data: 'visits.pending' },
            { data: 'visits.rejected' },
            { data: 'visits.cancelled' },
            { data: 'customers.customers' },
            { data: 'customers.new_customers' },
            { data: 'payments.total_formatted' }
        ],
        language: {
            zeroRecords: BooklyL10n.zeroRecords,
            processing:  BooklyL10n.processing
        }
    });

    /**
     * Init date range picker.
     */
    moment.locale('en', {
        months       : BooklyL10n.calendar.longMonths,
        monthsShort  : BooklyL10n.calendar.shortMonths,
        weekdays     : BooklyL10n.calendar.longDays,
        weekdaysShort: BooklyL10n.calendar.shortDays,
        weekdaysMin  : BooklyL10n.calendar.shortDays
    });

    var pickerRanges = {};
    pickerRanges[BooklyL10n.yesterday]  = [moment().subtract(1, 'days'), moment().subtract(1, 'days')];
    pickerRanges[BooklyL10n.today]      = [moment(), moment()];
    pickerRanges[BooklyL10n.tomorrow]   = [moment().add(1, 'days'), moment().add(1, 'days')];
    pickerRanges[BooklyL10n.last7]      = [moment().subtract(7, 'days'), moment()];
    pickerRanges[BooklyL10n.last30]     = [moment().subtract(30, 'days'), moment()];
    pickerRanges[BooklyL10n.thisMonth]  = [moment().startOf('month'), moment().endOf('month')];
    pickerRanges[BooklyL10n.nextMonth]  = [moment().add(1, 'month').startOf('month'), moment().add(1, 'month').endOf('month')];

    $dateFilter.daterangepicker(
        {
            parentEl: $dateFilter.parent(),
            startDate: moment().startOf('month'),
            endDate: moment().endOf('month'),
            ranges: pickerRanges,
            locale: {
                applyLabel : BooklyL10n.apply,
                cancelLabel: BooklyL10n.cancel,
                fromLabel  : BooklyL10n.from,
                toLabel    : BooklyL10n.to,
                customRangeLabel: BooklyL10n.customRange,
                daysOfWeek : BooklyL10n.calendar.shortDays,
                monthNames : BooklyL10n.calendar.longMonths,
                firstDay   : parseInt(BooklyL10n.startOfWeek),
                format     : BooklyL10n.mjsDateFormat
            }
        },
        function(start, end) {
            var format = 'YYYY-MM-DD';
            $dateFilter
                .data('date', start.format(format) + ' - ' + end.format(format))
                .find('span')
                .html(start.format(BooklyL10n.mjsDateFormat) + ' - ' + end.format(BooklyL10n.mjsDateFormat));
        }
    );

    /**
     * On select service.
     */
    $servicesFilter.on('change', 'input:checkbox', function() {
        updateEntitySelector($servicesFilter, BooklyL10n.services, $(this));
        dt.ajax.reload();
    });

    /**
     * On select staff.
     */
    $staffFilter.on('change', 'input:checkbox', function() {
        updateEntitySelector($staffFilter, BooklyL10n.staff, $(this));
        dt.ajax.reload();
    });

    /**
     * Update entity selectors.
     */
    function updateEntitySelector($container, data, $input) {
        var $checked, $check_all;
        if ($input && $input.hasClass('bookly-js-check-all-entities')) {
            $check_all = $input;
            $container.find('.bookly-js-check-entity').prop('checked', $input.prop('checked'));
        } else {
            $check_all = $container.find('.bookly-js-check-all-entities');
        }
        $checked = $container.find('.bookly-js-check-entity:checked');
        $check_all.prop('checked', false);
        if ($checked.length === 0) {
            $container.find('.bookly-js-entity-counter').text(data.nothingSelected);
        } else if ($checked.length === 1) {
            $container.find('.bookly-js-entity-counter').text(data.collection[$checked.val()].title);
        } else if ($checked.length === Object.keys(data.collection).length) {
            $container.find('.bookly-js-entity-counter').text(data.allSelected);
            $check_all.prop('checked', true);
        } else {
            $container.find('.bookly-js-entity-counter').text($checked.length + '/' + Object.keys(data.collection).length);
        }
    }

    $dateFilter.on('apply.daterangepicker', function () { dt.ajax.reload(); });

    /**
     * Export.
     */
    $exportButton.on('click', function () {
        var columns = [];
        $exportDialog.find('input:checked').each(function () {
            columns.push(this.value);
        });
        var config = {
            autoPrint: false,
            fieldSeparator: $('#bookly-csv-delimiter').val(),
            exportOptions: {
                columns: columns
            },
            filename: 'Analytics'
        };
        $.fn.dataTable.ext.buttons.csvHtml5.action(null, dt, null, $.extend({}, $.fn.dataTable.ext.buttons.csvHtml5, config));
    });

    /**
     * Print.
     */
    $printButton.on('click', function () {
        var columns = [];
        $printDialog.find('input:checked').each(function () {
            columns.push(this.value);
        });
        var config = {
            title: '',
            exportOptions: {
                columns: columns
            },
            customize: function (win) {
                win.document.firstChild.style.backgroundColor = '#fff';
                win.document.body.id = 'bookly-tbs';
                $(win.document.body).find('table').removeClass('collapsed');
            }
        };
        $.fn.dataTable.ext.buttons.print.action(null, dt, null, $.extend({}, $.fn.dataTable.ext.buttons.print, config));
    });
});