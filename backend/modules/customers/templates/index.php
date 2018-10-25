<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
use Bookly\Lib;
?>
<div id="bookly-tbs" class="wrap">
    <div class="bookly-tbs-body">
        <div class="page-header text-right clearfix">
            <div class="bookly-page-title">
                <?php esc_html_e( 'Customers', 'bookly' ) ?>
            </div>
            <?php \Bookly\Backend\Modules\Support\Components::getInstance()->renderButtons( $this::page_slug ) ?>
        </div>
        <div class="panel panel-default bookly-main">
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <input class="form-control" type="text" id="bookly-filter" placeholder="<?php esc_attr_e( 'Quick search customer', 'bookly' ) ?>" />
                        </div>
                    </div>
                    <div class="col-md-8 form-inline bookly-margin-bottom-lg  text-right">
                        <div class="form-group">
                            <button type="button" class="btn btn-default bookly-btn-block-xs" data-toggle="modal" data-target="#bookly-export-customers-dialog"><i class="glyphicon glyphicon-export"></i> <?php esc_html_e( 'Export to CSV', 'bookly' ) ?></button>
                        </div>
                        <div class="form-group">
                            <button type="button" class="btn btn-default bookly-btn-block-xs" data-toggle="modal" data-target="#bookly-import-customers-dialog"><i class="glyphicon glyphicon-import"></i> <?php esc_html_e( 'Import', 'bookly' ) ?></button>
                        </div>
                        <div class="form-group">
                            <button type="button" class="btn btn-success bookly-btn-block-xs" id="bookly-add" data-toggle="modal" data-target="#bookly-customer-dialog"><i class="glyphicon glyphicon-plus"></i> <?php esc_html_e( 'New customer', 'bookly' ) ?></button>
                        </div>
                    </div>
                </div>

                <table id="bookly-customers-list" class="table table-striped" width="100%">
                    <thead>
                        <tr>
                            <th><?php echo esc_html( get_option( 'bookly_l10n_label_name' ) ) ?></th>
                            <th><?php echo esc_html( get_option( 'bookly_l10n_label_first_name' ) ) ?></th>
                            <th><?php echo esc_html( get_option( 'bookly_l10n_label_last_name' ) ) ?></th>
                            <th><?php esc_html_e( 'User', 'bookly' ) ?></th>
                            <?php Lib\Proxy\CustomerGroups::renderCustomerTableHeader() ?>
                            <th><?php echo esc_html( get_option( 'bookly_l10n_label_phone' ) ) ?></th>
                            <th><?php echo esc_html( get_option( 'bookly_l10n_label_email' ) ) ?></th>
                            <?php foreach ( $info_fields as $field ) : ?>
                                <th><?php echo $field->label ?></th>
                            <?php endforeach ?>
                            <th><?php esc_html_e( 'Notes', 'bookly' ) ?></th>
                            <th><?php esc_html_e( 'Last appointment', 'bookly' ) ?></th>
                            <th><?php esc_html_e( 'Total appointments', 'bookly' ) ?></th>
                            <th><?php esc_html_e( 'Payments', 'bookly' ) ?></th>
                            <th></th>
                            <th width="16"><input type="checkbox" id="bookly-check-all"></th>
                        </tr>
                    </thead>
                </table>

                <div class="text-right bookly-margin-top-lg">
                    <?php Lib\Utils\Common::deleteButton() ?>
                </div>
            </div>
        </div>

        <?php include '_import.php' ?>
        <?php include '_export.php' ?>

        <div id="bookly-delete-dialog" class="modal fade" tabindex=-1 role="dialog">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                        <div class="modal-title h2"><?php _e( 'Delete customers', 'bookly' ) ?></div>
                    </div>
                    <div class="modal-body">
                        <?php esc_html_e( 'You are about to delete customers which may have WordPress accounts associated to them. Do you want to delete those accounts too (if there are any)?', 'bookly' ) ?>
                        <div class="checkbox">
                            <label>
                                <input id="bookly-delete-remember-choice" type="checkbox" /><?php esc_html_e( 'Remember my choice', 'bookly' ) ?>
                            </label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-danger ladda-button" id="bookly-delete-no" data-spinner-size="40" data-style="zoom-in">
                            <span class="ladda-label"><i class="glyphicon glyphicon-trash"></i> <?php esc_html_e( 'No, delete just customers', 'bookly' ) ?></span>
                        </button>
                        <button type="button" class="btn btn-danger ladda-button" id="bookly-delete-yes" data-spinner-size="40" data-style="zoom-in">
                            <span class="ladda-label"><i class="glyphicon glyphicon-trash"></i> <?php esc_html_e( 'Yes', 'bookly' ) ?></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div ng-app="customer" ng-controller="customerCtrl">
            <div customer-dialog=saveCustomer(customer) customer="customer"></div>
            <?php \Bookly\Backend\Modules\Customers\Components::getInstance()->renderCustomerDialog() ?>
        </div>
    </div>
</div>