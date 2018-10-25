<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
use Bookly\Lib\Utils\Common;
use Bookly\Lib\Utils\Price;
use Bookly\Lib\Proxy;
?>
<form method="post" action="<?php echo esc_url( add_query_arg( 'tab', 'payments' ) ) ?>">
    <div class="row">
        <div class="col-lg-6">
            <div class="form-group">
                <label for="bookly_pmt_currency"><?php _e( 'Currency', 'bookly' ) ?></label>
                <select id="bookly_pmt_currency" class="form-control" name="bookly_pmt_currency">
                    <?php foreach ( Price::getCurrencies() as $code => $currency ) : ?>
                        <option value="<?php echo $code ?>" data-symbol="<?php esc_attr_e( $currency['symbol'] ) ?>" <?php selected( get_option( 'bookly_pmt_currency' ), $code ) ?> ><?php echo $code ?> (<?php esc_html_e( $currency['symbol'] ) ?>)</option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="form-group">
                <label for="bookly_pmt_price_format"><?php _e( 'Price format', 'bookly' ) ?></label>
                <select id="bookly_pmt_price_format" class="form-control" name="bookly_pmt_price_format">
                    <?php foreach ( Price::getFormats() as $format ) : ?>
                        <option value="<?php echo $format ?>" <?php selected( get_option( 'bookly_pmt_price_format' ), $format ) ?> ></option>
                    <?php endforeach ?>
                </select>
            </div>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading">
            <label for="bookly_pmt_local"><?php _e( 'Service paid locally', 'bookly' ) ?></label>
        </div>
        <div class="panel-body">
            <?php Common::optionToggle( 'bookly_pmt_local' ) ?>
        </div>
    </div>

    <div class="panel panel-default">
        <div class="panel-heading">
            <label for="bookly_paypal_enabled">PayPal</label>
            <img style="margin-left: 10px; float: right" src="<?php echo plugins_url( 'frontend/resources/images/paypal.png', \Bookly\Lib\Plugin::getMainFile() ) ?>" />
        </div>
        <div class="panel-body">
            <div class="form-group">
                <?php Common::optionToggle( 'bookly_paypal_enabled', null, null,
                        Proxy\PaypalPaymentsStandard::prepareToggleOptions( array(
                            array( '0', __( 'Disabled', 'bookly' ) ),
                            array( Bookly\Lib\Payment\PayPal::TYPE_EXPRESS_CHECKOUT, 'PayPal Express Checkout' ),
                        ) )
                ) ?>
            </div>
            <div class="bookly-paypal">
                <div class="bookly-paypal-ec">
                    <?php Common::optionText( 'bookly_paypal_api_username',  __( 'API Username', 'bookly' ) ) ?>
                    <?php Common::optionText( 'bookly_paypal_api_password',  __( 'API Password', 'bookly' ) ) ?>
                    <?php Common::optionText( 'bookly_paypal_api_signature', __( 'API Signature', 'bookly' ) ) ?>
                </div>
                <?php Proxy\PaypalPaymentsStandard::renderSetUpOptions() ?>
                <?php Common::optionToggle( 'bookly_paypal_sandbox', __( 'Sandbox Mode', 'bookly' ), null, array( array( 1, __( 'Yes', 'bookly' ) ), array( 0, __( 'No', 'bookly' ) ) ) ) ?>
                <?php Bookly\Backend\Modules\Settings\Components::getInstance()->renderPaymentCorrection( 'paypal' ) ?>
            </div>
        </div>
    </div>

    <?php Bookly\Lib\Proxy\Shared::renderPaymentGatewaySettings() ?>

    <div class="panel-footer">
        <?php Common::csrf() ?>
        <?php Common::submitButton() ?>
        <?php Common::resetButton( 'bookly-payments-reset' ) ?>
    </div>
</form>