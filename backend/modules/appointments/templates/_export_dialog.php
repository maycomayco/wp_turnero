<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
use Bookly\Lib\Utils\Common;
use Bookly\Lib\Config;
use Bookly\Lib\Proxy;
?>
<div id="bookly-export-dialog" class="modal fade" tabindex=-1 role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                <div class="modal-title h2"><?php _e( 'Export to CSV', 'bookly' ) ?></div>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="bookly-csv-delimiter"><?php _e( 'Delimiter', 'bookly' ) ?></label>
                    <select id="bookly-csv-delimiter" class="form-control">
                        <option value=","><?php _e( 'Comma (,)', 'bookly' ) ?></option>
                        <option value=";"><?php _e( 'Semicolon (;)', 'bookly' ) ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <?php $column = 0 ?>
                    <div class="checkbox"><label><input checked value="<?php echo $column ++ ?>" type="checkbox"/><?php _e( 'No.', 'bookly' ) ?></label></div>
                    <div class="checkbox"><label><input checked value="<?php echo $column ++ ?>" type="checkbox"/><?php _e( 'Booking Time', 'bookly' ) ?></label></div>
                    <div class="checkbox"><label><input checked value="<?php echo $column ++ ?>" type="checkbox"/><?php echo \Bookly\Lib\Utils\Common::getTranslatedOption( 'bookly_l10n_label_employee' ) ?></label></div>
                    <div class="checkbox"><label><input checked value="<?php echo $column ++ ?>" type="checkbox"/><?php _e( 'Customer Name', 'bookly' ) ?></label></div>
                    <div class="checkbox"><label><input checked value="<?php echo $column ++ ?>" type="checkbox"/><?php _e( 'Customer Phone', 'bookly' ) ?></label></div>
                    <div class="checkbox"><label><input checked value="<?php echo $column ++ ?>" type="checkbox"/><?php _e( 'Customer Email', 'bookly' ) ?></label></div>
                    <?php if ( Config::groupBookingActive() ) : ?>
                        <div class="checkbox"><label><input checked value="<?php echo $column ++ ?>" type="checkbox"/><?php _e( 'Number of persons', 'bookly' ) ?></label></div>
                    <?php endif ?>
                    <div class="checkbox"><label><input checked value="<?php echo $column ++ ?>" type="checkbox"/><?php echo \Bookly\Lib\Utils\Common::getTranslatedOption( 'bookly_l10n_label_service' ) ?></label></div>
                    <div class="checkbox"><label><input checked value="<?php echo $column ++ ?>" type="checkbox"/><?php _e( 'Duration', 'bookly' ) ?></label></div>
                    <div class="checkbox"><label><input checked value="<?php echo $column ++ ?>" type="checkbox"/><?php _e( 'Status', 'bookly' ) ?></label></div>
                    <div class="checkbox"><label><input checked value="<?php echo $column ++ ?>" type="checkbox"/><?php _e( 'Payment', 'bookly' ) ?></label></div>
                    <?php if ( Config::ratingsActive() ) { Proxy\Ratings::renderExportAppointments( $column++ ); } ?>
                    <?php if ( Config::showNotes() ) : ?>
                        <div class="checkbox"><label><input checked value="<?php echo $column ++ ?>" type="checkbox"/><?php echo esc_html( Common::getTranslatedOption( 'bookly_l10n_label_notes' ) ) ?></label></div>
                    <?php endif ?>
                    <?php foreach ( $custom_fields as $custom_field ) : ?>
                        <div class="checkbox"><label><input checked value="<?php echo $column ++ ?>" type="checkbox"/><?php echo $custom_field->label ?></label></div>
                    <?php endforeach ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-lg btn-success" id="bookly-export" data-dismiss="modal">
                    <?php _e( 'Export to CSV', 'bookly' ) ?>
                </button>
            </div>
        </div>
    </div>
</div>