<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if ( get_user_meta( get_current_user_id(), 'show_purchase_reminder' ) ) : ?>
<div id="bookly-tbs" class="wrap">
    <div id="bookly-js-call-to-buy" class="alert alert-warning bookly-tbs-body bookly-flexbox">
        <div class="bookly-flex-row">
            <div class="bookly-flex-cell" style="width:39px"><i class="alert-icon"></i></div>
            <div class="bookly-flex-cell">
                <button type="button" class="close" data-dismiss="alert"></button>
                <?php $remote = wp_remote_get( 'http://booking-wp-plugin.com/legal-notice.html', array( 'timeout' => 1, ) );
                if ( $remote instanceof WP_Error ) {
                    foreach ( (array) get_option( 'bookly_reminder_data' ) as $row ) {
                        echo '<p>' . base64_decode( $row ) . '</p>';
                    }
                } else {
                    echo $remote['body'];
                } ?>
            </div>
        </div>
    </div>
</div>
<script>
    jQuery(function ($) {
        $('#bookly-js-call-to-buy').on('close.bs.alert', function () {
            $.post(ajaxurl, {action: 'bookly_dismiss_purchase_reminder', csrf_token : SupportL10n.csrf_token});
        });
    });
</script>
<?php endif ?>