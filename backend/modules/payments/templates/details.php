<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
use Bookly\Lib\Utils\Common;
use Bookly\Lib\Utils\Price;
use Bookly\Lib\Utils\DateTime;
use Bookly\Lib\Entities;
use Bookly\Lib\Proxy;

$subtotal = 0;
$subtotal_deposit = 0;
?>
<?php if ( $payment ) : ?>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th width="50%"><?php _e( 'Customer', 'bookly' ) ?></th>
                    <th width="50%"><?php _e( 'Payment', 'bookly' ) ?></th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo esc_html( $payment['customer'] ) ?></td>
                    <td>
                        <div><?php _e( 'Date', 'bookly' ) ?>: <?php echo DateTime::formatDateTime( $payment['created'] ) ?></div>
                        <div><?php _e( 'Type', 'bookly' ) ?>: <?php echo Entities\Payment::typeToString( $payment['type'] ) ?></div>
                        <div><?php _e( 'Status', 'bookly' ) ?>: <?php echo Entities\Payment::statusToString( $payment['status'] ) ?></div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th><?php _e( 'Service', 'bookly' ) ?></th>
                    <th><?php _e( 'Date', 'bookly' ) ?></th>
                    <th><?php _e( 'Provider', 'bookly' ) ?></th>
                    <?php if ( $deposit_enabled ): ?>
                        <th class="text-right"><?php _e( 'Deposit', 'bookly' ) ?></th>
                    <?php endif ?>
                    <th class="text-right"><?php _e( 'Price', 'bookly' ) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $items as $item ) :
                    $extras_price = 0; ?>
                    <tr>
                        <td>
                            <?php if ( $item['number_of_persons'] > 1 ) echo $item['number_of_persons'] . '&nbsp;&times;&nbsp;'  ?><?php echo esc_html( $item['service_name'] ) ?>
                            <?php if ( ! empty ( $item['extras'] ) ) : ?>
                                <ul class="bookly-list list-dots">
                                    <?php foreach ( $item['extras'] as $extra ) : ?>
                                        <li><?php if ( $extra['quantity'] > 1 ) echo $extra['quantity'] . '&nbsp;&times;&nbsp;' ?><?php echo esc_html( $extra['title'] ) ?></li>
                                        <?php $extras_price += $extra['price'] * $extra['quantity'] ?>
                                    <?php endforeach ?>
                                </ul>
                            <?php endif ?>
                        </td>
                        <td><?php echo DateTime::formatDateTime( $item['appointment_date'] ) ?></td>
                        <td><?php echo esc_html( $item['staff_name'] ) ?></td>
                        <?php $deposit = Proxy\DepositPayments::prepareAmount( $payment['extras_multiply_nop'] ? $item['number_of_persons'] * ( $item['service_price'] + $extras_price ) : $item['number_of_persons'] * $item['service_price'] + $extras_price, $item['deposit'], $item['number_of_persons'] ) ?>
                        <?php if ( $deposit_enabled ) : ?>
                            <td class="text-right"><?php echo Proxy\DepositPayments::formatDeposit( $deposit, $item['deposit'] ) ?></td>
                        <?php endif ?>
                        <td class="text-right">
                            <?php $service_price = Price::format( $item['service_price'] ) ?>
                            <?php if ( $item['number_of_persons'] > 1 ) $service_price = $item['number_of_persons'] . '&nbsp;&times;&nbsp' . $service_price ?>
                            <?php echo $service_price ?>
                            <ul class="bookly-list">
                            <?php foreach ( $item['extras'] as $extra ) : ?>
                                <li>
                                    <?php printf( '%s%s%s',
                                        ( $item['number_of_persons'] > 1 && $payment['extras_multiply_nop'] ) ? $item['number_of_persons'] . '&nbsp;&times;&nbsp;' : '',
                                        ( $extra['quantity'] > 1 ) ? $extra['quantity'] . '&nbsp;&times;&nbsp;' : '',
                                        Price::format( $extra['price'] )
                                    ) ?>
                                </li>
                                <?php $subtotal += $payment['extras_multiply_nop'] ? $item['number_of_persons'] * $extra['price'] * $extra['quantity'] : $extra['price'] * $extra['quantity'] ?>
                            <?php endforeach ?>
                            </ul>
                        </td>
                    </tr>
                    <?php $subtotal += $item['number_of_persons'] * $item['service_price'] ?>
                    <?php $subtotal_deposit += $deposit ?>
                <?php endforeach ?>
            </tbody>
            <tfoot>
                <tr>
                    <th rowspan="2" style="border-left-color: white; border-bottom-color: white;"></th>
                    <th colspan="2"><?php _e( 'Subtotal', 'bookly' ) ?></th>
                    <?php if ( $deposit_enabled ) : ?>
                        <th class="text-right"><?php echo Price::format( $subtotal_deposit ) ?></th>
                    <?php endif ?>
                    <th class="text-right"><?php echo Price::format( $subtotal ) ?></th>
                </tr>
                <tr>
                    <th colspan="<?php echo 2 + (int) $deposit_enabled ?>">
                        <?php _e( 'Discount', 'bookly' ) ?>
                        <?php if ( $payment['coupon'] ) : ?><div><small>(<?php echo $payment['coupon']['code'] ?>)</small></div><?php endif ?>
                    </th>
                    <th class="text-right">
                        <?php if ( $payment['coupon'] ) : ?>
                            <?php if ( $payment['coupon']['discount'] ) : ?>
                                <div><?php echo $payment['coupon']['discount'] ?>%</div>
                            <?php endif ?>
                            <?php if ( $payment['coupon']['deduction'] ) : ?>
                                <div><?php echo Price::format( $payment['coupon']['deduction'] ) ?></div>
                            <?php endif ?>
                        <?php else : ?>
                            <?php echo Price::format( 0 ) ?>
                        <?php endif ?>
                    </th>
                </tr>
                <?php Proxy\CustomerGroups::renderPaymentsDialogRow( $payment['customer_group'] ) ?>
                <?php foreach ( $adjustments as $adjustment ) : ?>
                <tr>
                    <th style="border-left-color:#fff;border-bottom-color:#fff;"></th>
                    <th colspan="<?php echo 2 + (int) $deposit_enabled ?>">
                        <?php echo esc_html( $adjustment['reason'] ) ?>
                    </th>
                    <th class="text-right"><?php echo Price::format( $adjustment['amount'] ) ?></th>
                </tr>
                <?php endforeach ?>
                <tr id="bookly-js-adjustment-field" class="collapse">
                    <th style="border-left-color:#fff;border-bottom-color:#fff;"></th>
                    <th colspan="<?php echo 3 + (int) $deposit_enabled ?>" style="font-weight: normal;">
                        <div class="form-group">
                            <label for="bookly-js-adjustment-reason"><?php _e( 'Reason', 'bookly' ) ?></label>
                            <textarea class="form-control" id="bookly-js-adjustment-reason"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="bookly-js-adjustment-amount"><?php _e( 'Amount', 'bookly' ) ?></label>
                            <input class="form-control" type="number" step="1" id="bookly-js-adjustment-amount">
                        </div>
                        <div class="text-right">
                            <?php Common::customButton( 'bookly-js-adjustment-cancel', 'btn btn-default', __( 'Cancel', 'bookly' ) ) ?>
                            <?php Common::customButton( 'bookly-js-adjustment-apply', 'btn btn-success', __( 'Apply', 'bookly' ) ) ?>
                        </div>
                    </th>
                </tr>
                <?php if ( $payment['price_correction'] ) : ?>
                    <tr>
                        <th style="border-left-color:#fff;border-bottom-color:#fff;"></th>
                        <th colspan="<?php echo 2 + (int) $deposit_enabled ?>">
                            <?php echo Entities\Payment::typeToString( $payment['type'] ) ?>
                        </th>
                        <th class="text-right">
                            <?php echo Price::format( $payment['price_correction'] ) ?>
                        </th>
                    </tr>
                <?php endif ?>
                <tr>
                    <th style="border-left-color:#fff;border-bottom-color:#fff;"></th>
                    <th colspan="<?php echo 2 + (int) $deposit_enabled ?>"><?php _e( 'Total', 'bookly' ) ?></th>
                    <th class="text-right"><?php echo Price::format( $payment['total'] ) ?></th>
                </tr>
                <?php if ( $payment['total'] != $payment['paid'] ) : ?>
                    <tr>
                        <th rowspan="2" style="border-left-color:#fff;border-bottom-color:#fff;"></th>
                        <th colspan="<?php echo 2 + (int) $deposit_enabled ?>"><i><?php _e( 'Paid', 'bookly' ) ?></i></th>
                        <th class="text-right"><i><?php echo Price::format( $payment['paid'] ) ?></i></th>
                    </tr>
                    <tr>
                        <th colspan="<?php echo 2 + (int) $deposit_enabled ?>"><i><?php _e( 'Due', 'bookly' ) ?></i></th>
                        <th class="text-right"><i><?php echo Price::format( $payment['total'] - $payment['paid'] ) ?></i></th>
                    </tr>
                <?php endif ?>
                    <tr>
                        <th style="border-left-color:#fff;border-bottom-color:#fff;"></th>
                        <th colspan="<?php echo 3 + (int) $deposit_enabled ?>" class="text-right">
                            <div class="bookly-js-details-main-controls">
                                <?php Common::customButton( 'bookly-js-adjustment-button', 'btn btn-default', __( 'Manual adjustment', 'bookly' ) ) ?>
                                <?php if ( $payment['total'] != $payment['paid'] ) : ?>
                                <button type="button" class="btn btn-success ladda-button" id="bookly-complete-payment" data-spinner-size="40" data-style="zoom-in"><i><?php _e( 'Complete payment', 'bookly' ) ?></i></button>
                                <?php endif ?>
                            </div>
                            <div class="bookly-js-details-bind-controls collapse">
                                <?php Common::customButton( 'bookly-js-attach-payment', 'btn btn-success', __( 'Bind payment', 'bookly' ) ) ?>
                            </div>
                        </th>
                    </tr>
            </tfoot>
        </table>
    </div>
<?php endif ?>