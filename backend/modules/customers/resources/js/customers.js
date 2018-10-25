jQuery(function($) {

    var
        $customers_list     = $('#bookly-customers-list'),
        $filter             = $('#bookly-filter'),
        $check_all_button   = $('#bookly-check-all'),
        $customer_dialog    = $('#bookly-customer-dialog'),
        $add_button         = $('#bookly-add'),
        $delete_button      = $('#bookly-delete'),
        $delete_dialog      = $('#bookly-delete-dialog'),
        $delete_button_no   = $('#bookly-delete-no'),
        $delete_button_yes  = $('#bookly-delete-yes'),
        $remember_choice    = $('#bookly-delete-remember-choice'),
        remembered_choice,
        row
        ;
    var columns = [
        {data: 'full_name', render: $.fn.dataTable.render.text(), responsivePriority: 2, visible: BooklyL10n.first_last_name == 0},
        {data: 'first_name', render: $.fn.dataTable.render.text(), responsivePriority: 2, visible: BooklyL10n.first_last_name == 1},
        {data: 'last_name', render: $.fn.dataTable.render.text(), responsivePriority: 2, visible: BooklyL10n.first_last_name == 1},
        {data: 'wp_user', render: $.fn.dataTable.render.text(), responsivePriority: 2}
    ];
    if (BooklyL10n.groupsActive == 1) {
        columns.push({data: 'group_name', render: $.fn.dataTable.render.text(), responsivePriority: 2});
    }
    columns = columns.concat([
        {data: 'phone', render: $.fn.dataTable.render.text(), responsivePriority: 2},
        {data: 'email', render: $.fn.dataTable.render.text(), responsivePriority: 2}
    ]);
    BooklyL10n.infoFields.forEach(function (field, i) {
        columns.push({
            data: 'info_fields.' + i + '.value' + (field.type === 'checkboxes' ? '[, ]' : ''),
            render: $.fn.dataTable.render.text(),
            responsivePriority: 3,
            orderable: false
        });
    });
    columns = columns.concat([
        {data: 'notes', render: $.fn.dataTable.render.text(), responsivePriority: 2},
        {data: 'last_appointment', responsivePriority: 2},
        {data: 'total_appointments', responsivePriority: 2},
        {data: 'payments', responsivePriority: 2}
    ]);

    /**
     * Init DataTables.
     */
    var dt = $customers_list.DataTable({
        order       : [[0, 'asc']],
        info        : false,
        searching   : false,
        lengthChange: false,
        pageLength  : 25,
        pagingType  : 'numbers',
        processing  : true,
        responsive  : true,
        serverSide  : true,
        ajax        : {
            url : ajaxurl,
            type: 'POST',
            data: function (d) {
                return $.extend({}, d, {
                    action    : 'bookly_get_customers',
                    csrf_token: BooklyL10n.csrfToken,
                    filter    : $filter.val()
                });
            }
        },
        columns: columns.concat([
            {
                responsivePriority: 1,
                orderable         : false,
                searchable        : false,
                render            : function (data, type, row, meta) {
                    return '<button type="button" class="btn btn-default" data-toggle="modal" data-target="#bookly-customer-dialog"><i class="glyphicon glyphicon-edit"></i> ' + BooklyL10n.edit + '</button>';
                }
            },
            {
                responsivePriority: 1,
                orderable         : false,
                searchable        : false,
                render            : function (data, type, row, meta) {
                    return '<input type="checkbox" value="' + row.id + '">';
                }
            }
        ]),
        dom: "<'row'<'col-sm-6'l><'col-sm-6'f>>" +
            "<'row'<'col-sm-12'tr>>" +
            "<'row pull-left'<'col-sm-12 bookly-margin-top-lg'p>>",
        language: {
            zeroRecords: BooklyL10n.zeroRecords,
            processing:  BooklyL10n.processing
        }
    });

    /**
     * Select all customers.
     */
    $check_all_button.on('change', function () {
        $customers_list.find('tbody input:checkbox').prop('checked', this.checked);
    });

    /**
     * On customer select.
     */
    $customers_list.on('change', 'tbody input:checkbox', function () {
        $check_all_button.prop('checked', $customers_list.find('tbody input:not(:checked)').length == 0);
    });

    /**
     * Edit customer.
     */
    $customers_list.on('click', 'button', function () {
        row = dt.row($(this).closest('td'));
    });

    /**
     * New customer.
     */
    $add_button.on('click', function () {
        row = null;
    });

    /**
     * On show modal.
     */
    $customer_dialog.on('show.bs.modal', function () {
        var $title = $customer_dialog.find('.modal-title');
        var $button = $customer_dialog.find('.modal-footer button:first');
        var customer;
        if (row) {
            customer = $.extend(true, {}, row.data());
            $title.text(BooklyL10n.edit_customer);
            $button.text(BooklyL10n.save);
        } else {
            customer = {
                id          : '',
                wp_user_id  : '',
                group_id    : '',
                full_name   : '',
                first_name  : '',
                last_name   : '',
                phone       : '',
                email       : '',
                info_fields : [],
                notes       : '',
                birthday    : ''
            };
            BooklyL10n.infoFields.forEach(function (field) {
                customer.info_fields.push({id: field.id, value: field.type === 'checkboxes' ? [] : ''});
            });
            $title.text(BooklyL10n.new_customer);
            $button.text(BooklyL10n.create_customer);
        }

        var $scope = angular.element(this).scope();
        $scope.$apply(function ($scope) {
            $scope.customer = customer;
            setTimeout(function() {
                if (BooklyL10nCustDialog.intlTelInput.enabled) {
                    $customer_dialog.find('#phone').intlTelInput('setNumber', customer.phone);
                } else {
                    $customer_dialog.find('#phone').val(customer.phone);
                }
            }, 0);
        });
    });

    /**
     * Delete customers.
     */
    $delete_button.on('click', function () {
        if (remembered_choice === undefined) {
            $delete_dialog.modal('show');
        } else {
            deleteCustomers(this, remembered_choice);
        }}
    );

    $delete_button_no.on('click', function () {
        if ($remember_choice.prop('checked')) {
            remembered_choice = false;
        }
        deleteCustomers(this, false);
    });

    $delete_button_yes.on('click', function () {
        if ($remember_choice.prop('checked')) {
            remembered_choice = true;
        }
        deleteCustomers(this, true);
    });

    function deleteCustomers(button, with_wp_user) {
        var ladda = Ladda.create(button);
        ladda.start();

        var data = [];
        var $checkboxes = $customers_list.find('tbody input:checked');
        $checkboxes.each(function () {
            data.push(this.value);
        });

        $.ajax({
            url  : ajaxurl,
            type : 'POST',
            data : {
                action       : 'bookly_delete_customers',
                csrf_token   : BooklyL10n.csrfToken,
                data         : data,
                with_wp_user : with_wp_user ? 1 : 0
            },
            dataType : 'json',
            success  : function(response) {
                ladda.stop();
                $delete_dialog.modal('hide');
                if (response.success) {
                    dt.ajax.reload(null, false);
                } else {
                    alert(response.data.message);
                }
            }
        });
    }

    /**
     * On filters change.
     */
    $filter.on('keyup', function () { dt.ajax.reload(); });
});

(function() {
    var module = angular.module('customer', ['customerDialog']);
    module.controller('customerCtrl', function($scope) {
        $scope.customer = {
            id          : '',
            wp_user_id  : '',
            group_id    : '',
            full_name   : '',
            first_name  : '',
            last_name   : '',
            phone       : '',
            email       : '',
            info_fields : [],
            notes       : '',
            birthday    : ''
        };
        $scope.saveCustomer = function(customer) {
            jQuery('#bookly-customers-list').DataTable().ajax.reload(null, false);
        };
    });
})();