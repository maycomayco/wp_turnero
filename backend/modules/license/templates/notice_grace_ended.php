<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>
<div id="bookly-tbs" class="wrap">
    <div class="alert alert-info bookly-tbs-body bookly-flexbox">
        <div class="bookly-flex-row">
            <div class="bookly-flex-cell" style="width:39px"><i class="alert-icon"></i></div>
            <div class="bookly-flex-cell">
                <button type="button" class="close" data-dismiss="alert"></button>
                <span class="h4"><?php _e( 'Bookly - License verification required', 'bookly' ) ?></span>
                <p></p>
                <p><?php _e( 'Access to your bookings has been disabled.', 'bookly' ) ?></p>
                <p><?php echo strtr( __( 'To enable access to your bookings, please verify your license by providing a valid purchase code. <a href="{url}">Details</a>', 'bookly' ), $replace_data ) ?></p>
            </div>
        </div>
    </div>
</div>