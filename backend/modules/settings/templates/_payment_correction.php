<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
use Bookly\Lib\Utils\Common;
?>
<label for="bookly_<?php echo $system ?>_discount"><?php _e( 'Price correction', 'bookly' ) ?></label>
<div class="form-group">
    <div class="row">
        <div class="col-md-6">
            <?php Common::optionNumeric( 'bookly_' . $system . '_increase', __( 'Increase/Discount (%)', 'bookly' ), null, -100, 'any', 100 ) ?>
        </div>
        <div class="col-md-6">
            <?php Common::optionNumeric( 'bookly_' . $system . '_addition', __( 'Addition/Deduction', 'bookly' ), null, null, 'any' ) ?>
        </div>
    </div>
</div>