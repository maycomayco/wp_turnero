<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
use Bookly\Lib\Utils\Common;
?><div id="bookly-payment-attach-modal" class="modal fade" tabindex=-1 role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <div class="modal-title h2"><?php _e( 'Attach payment', 'bookly' ) ?></div>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label><?php _e( 'Enter payment ID', 'bookly' ) ?></label>
                    <input class="form-control bookly-js-attach-payment-id" type="text" ng-model="attach.payment_id"/>
                </div>
            </div>
            <div class="modal-footer">
                <div ng-hide=loading>
                    <?php Common::customButton( 'bookly-attach-payment-apply', 'btn-lg btn-success', __( 'Apply', 'bookly' ), array( 'data-dismiss' => 'modal', 'data-toggle' => 'modal', 'href' => '#bookly-payment-details-modal', 'data-payment_id' => '{{attach.payment_id}}', 'data-payment_bind' => true ), 'submit' ) ?>
                    <?php Common::customButton( null, 'btn-lg btn-default', __( 'Cancel', 'bookly' ), array( 'data-dismiss' => 'modal' ) ) ?>
                </div>
            </div>
        </div>
    </div>
</div>