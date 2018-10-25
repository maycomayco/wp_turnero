<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
use Bookly\Lib\Utils\Common;
?>
<div id="bookly-print-dialog" class="modal fade" tabindex=-1 role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <h4 class="modal-title"><?php esc_html_e( 'Print', 'bookly' ) ?></h4>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <div class="checkbox"><label><input checked value="0" type="checkbox"/><?php echo Common::getTranslatedOption( 'bookly_l10n_label_employee' ) ?></label></div>
                    <div class="checkbox"><label><input checked value="1" type="checkbox"/><?php echo Common::getTranslatedOption( 'bookly_l10n_label_service' ) ?></label></div>
                    <div class="checkbox"><label><input checked value="2" type="checkbox"/><?php esc_html_e( 'Sessions', 'bookly' ) ?></label></div>
                    <div class="checkbox"><label><input checked value="3" type="checkbox"/><?php esc_html_e( 'Approved', 'bookly' ) ?></label></div>
                    <div class="checkbox"><label><input checked value="4" type="checkbox"/><?php esc_html_e( 'Pending', 'bookly' ) ?></label></div>
                    <div class="checkbox"><label><input checked value="5" type="checkbox"/><?php esc_html_e( 'Rejected', 'bookly' ) ?></label></div>
                    <div class="checkbox"><label><input checked value="6" type="checkbox"/><?php esc_html_e( 'Cancelled', 'bookly' ) ?></label></div>
                    <div class="checkbox"><label><input checked value="7" type="checkbox"/><?php esc_html_e( 'Customers', 'bookly' ) ?></label></div>
                    <div class="checkbox"><label><input checked value="8" type="checkbox"/><?php esc_html_e( 'New Customers', 'bookly' ) ?></label></div>
                    <div class="checkbox"><label><input checked value="9" type="checkbox"/><?php esc_html_e( 'Total', 'bookly' ) ?></label></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-lg btn-success" id="bookly-print" data-dismiss="modal">
                    <?php esc_html_e( 'Print', 'bookly' ) ?>
                </button>
            </div>
        </div>
    </div>
</div>