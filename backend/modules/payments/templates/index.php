<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
use Bookly\Lib\Entities\Payment;
?>
<div id="bookly-tbs" class="wrap">
    <div class="bookly-tbs-body">
        <div class="page-header text-right clearfix">
            <div class="bookly-page-title">
                <?php _e( 'Payments', 'bookly' ) ?>
            </div>
            <?php \Bookly\Backend\Modules\Support\Components::getInstance()->renderButtons( $this::page_slug ) ?>
        </div>
        <div class="panel panel-default bookly-main">
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-4 col-lg-3">
                        <div class="bookly-margin-bottom-lg bookly-relative">
                            <button type="button" class="btn btn-block btn-default" id="bookly-filter-date" data-date="<?php echo date( 'Y-m-d', strtotime( '-30 day' ) ) ?> - <?php echo date( 'Y-m-d' ) ?>">
                                <i class="dashicons dashicons-calendar-alt"></i>
                                <span>
                                    <?php echo \Bookly\Lib\Utils\DateTime::formatDate( '-30 days' ) ?> - <?php echo \Bookly\Lib\Utils\DateTime::formatDate( 'today' ) ?>
                                </span>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-2 col-lg-2">
                        <div class="form-group">
                            <select id="bookly-filter-type" class="form-control bookly-js-select" data-placeholder="<?php esc_attr_e( 'Type', 'bookly' ) ?>">
                                <?php foreach ( $types as $type ) : ?>
                                    <option value="<?php echo esc_attr( $type ) ?>">
                                        <?php echo \Bookly\Lib\Entities\Payment::typeToString( $type ) ?>
                                    </option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2 col-lg-2">
                        <div class="form-group">
                            <select id="bookly-filter-staff" class="form-control bookly-js-select" data-placeholder="<?php esc_attr_e( 'Provider', 'bookly' ) ?>">
                                <?php foreach ( $providers as $provider ) : ?>
                                    <option value="<?php echo $provider['id'] ?>"><?php echo esc_html( $provider['full_name'] ) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2 col-lg-2">
                        <div class="form-group">
                            <select id="bookly-filter-service" class="form-control bookly-js-select" data-placeholder="<?php esc_attr_e( 'Service', 'bookly' ) ?>">
                                <?php foreach ( $services as $service ) : ?>
                                    <option value="<?php echo $service['id'] ?>"><?php echo esc_html( $service['title'] ) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2 col-lg-2">
                        <div class="form-group">
                            <select id="bookly-filter-status" class="form-control bookly-js-select" data-placeholder="<?php esc_attr_e( 'Status', 'bookly' ) ?>">
                                <option value="<?php echo Payment::STATUS_COMPLETED ?>"><?php echo Payment::statusToString( Payment::STATUS_COMPLETED ) ?></option>
                                <option value="<?php echo Payment::STATUS_PENDING ?>"><?php echo Payment::statusToString( Payment::STATUS_PENDING ) ?></option>
                                <option value="<?php echo Payment::STATUS_REJECTED ?>"><?php echo Payment::statusToString( Payment::STATUS_REJECTED ) ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <table id="bookly-payments-list" class="table table-striped" width="100%">
                    <thead>
                        <tr>
                            <th><?php _e( 'No.', 'bookly' ) ?></th>
                            <th><?php _e( 'Date', 'bookly' ) ?></th>
                            <th><?php _e( 'Type', 'bookly' ) ?></th>
                            <th><?php _e( 'Customer', 'bookly' ) ?></th>
                            <th><?php _e( 'Provider', 'bookly' ) ?></th>
                            <th><?php _e( 'Service', 'bookly' ) ?></th>
                            <th><?php _e( 'Appointment Date', 'bookly' ) ?></th>
                            <th><?php _e( 'Amount', 'bookly' ) ?></th>
                            <th><?php _e( 'Status', 'bookly' ) ?></th>
                            <th></th>
                            <th width="16"><input type="checkbox" id="bookly-check-all"></th>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <th colspan="7"><div class="pull-right"><?php _e( 'Total', 'bookly' ) ?>:</div></th>
                            <th colspan="4"><span id="bookly-payment-total"></span></th>
                        </tr>
                    </tfoot>
                </table>
                <div class="text-right bookly-margin-top-lg">
                    <?php \Bookly\Lib\Utils\Common::deleteButton() ?>
                </div>
            </div>
        </div>

        <div ng-app="paymentDetails" ng-controller="paymentDetailsCtrl">
            <div payment-details-dialog></div>
            <?php \Bookly\Backend\Modules\Payments\Components::getInstance()->renderPaymentDetailsDialog() ?>
        </div>
    </div>
</div>
