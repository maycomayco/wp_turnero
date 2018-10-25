<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<div id="bookly-tbs" class="wrap">
    <div class="alert alert-info bookly-tbs-body bookly-flexbox">
        <div class="bookly-flex-row">
            <div class="bookly-flex-cell" style="width:39px"><i class="alert-icon"></i></div>
            <div class="bookly-flex-cell">
                <button type="button" class="close" data-dismiss="alert"></button>
                <span class="h4"><?php _e( 'Bookly - License verification required', 'bookly' ) ?></span>
                <p></p>
                <p><?php _e( 'Please verify your license by providing a valid purchase code. Upon providing the purchase code you will get access to software updates, including feature improvements and important security fixes.', 'bookly' ) ?></p>
                <p><?php echo strtr( __( 'If you do not provide a valid purchase code within {days}, access to your bookings will be disabled. <a href="{url}">Details</a>', 'bookly' ), $replace_data ) ?></p>
            </div>
        </div>
    </div>
</div>