<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
use Bookly\Lib\Proxy;
use Bookly\Lib\Utils\Common;

echo $progress_tracker;
echo $coupon_html;
?>

<div class="bookly-payment-nav">
    <div class="bookly-box"><?php echo $info_text ?></div>
    <?php if ( $pay_local ) : ?>
        <div class="bookly-box bookly-list">
            <label>
                <input type="radio" class="bookly-payment" name="payment-method-<?php echo $form_id ?>" value="local"/>
                <span><?php echo Common::getTranslatedOption( 'bookly_l10n_label_pay_locally' ) ?></span>
            </label>
        </div>
    <?php endif ?>

    <?php if ( $pay_paypal ) : ?>
        <div class="bookly-box bookly-list">
            <label>
                <input type="radio" class="bookly-payment" name="payment-method-<?php echo $form_id ?>" value="paypal"/>
                <span><?php echo Common::getTranslatedOption( 'bookly_l10n_label_pay_paypal' ) ?>
                    <?php if ( $original_price !== null ) : ?>
                        <span class="bookly-js-pay"><?php echo \Bookly\Lib\Utils\Price::format( Bookly\Lib\Utils\Price::correction( $original_price, - get_option( 'bookly_paypal_increase' ), - get_option( 'bookly_paypal_addition' ) ) ) ?></span>
                    <?php endif ?>
                </span>
                <img src="<?php echo plugins_url( 'frontend/resources/images/paypal.png', \Bookly\Lib\Plugin::getMainFile() ) ?>" alt="PayPal" />
            </label>
            <?php if ( $payment['gateway'] == Bookly\Lib\Entities\Payment::TYPE_PAYPAL && $payment['status'] == 'error' ) : ?>
                <div class="bookly-label-error"><?php echo $payment['data'] ?></div>
            <?php endif ?>
        </div>
    <?php endif ?>

    <div class="bookly-box bookly-list" style="display: none">
        <input type="radio" class="bookly-js-coupon-free" name="payment-method-<?php echo $form_id ?>" value="coupon" />
    </div>

    <?php Proxy\Shared::renderPaymentGatewaySelector( $form_id, $payment, $original_price ) ?>
</div>

<?php Proxy\RecurringAppointments::renderInfoMessage( $userData ) ?>

<?php if ( $pay_local ) : ?>
    <div class="bookly-gateway-buttons pay-local bookly-box bookly-nav-steps">
        <button class="bookly-back-step bookly-js-back-step bookly-btn ladda-button" data-style="zoom-in"  data-spinner-size="40">
            <span class="ladda-label"><?php echo Common::getTranslatedOption( 'bookly_l10n_button_back' ) ?></span>
        </button>
        <button class="bookly-next-step bookly-js-next-step bookly-btn ladda-button" data-style="zoom-in" data-spinner-size="40">
            <span class="ladda-label"><?php echo Common::getTranslatedOption( 'bookly_l10n_step_payment_button_next' ) ?></span>
        </button>
    </div>
<?php endif ?>

<?php if ( $pay_paypal ) : ?>
    <div class="bookly-gateway-buttons pay-paypal bookly-box bookly-nav-steps" style="display:none">
        <?php if ( $pay_paypal === Bookly\Lib\Payment\PayPal::TYPE_EXPRESS_CHECKOUT ) :
            Bookly\Lib\Payment\PayPal::renderECForm( $form_id );
        elseif ( $pay_paypal === Bookly\Lib\Payment\PayPal::TYPE_PAYMENTS_STANDARD ) :
            Proxy\PaypalPaymentsStandard::renderPaymentForm( $form_id, $page_url );
        endif ?>
    </div>
<?php endif ?>

<div class="bookly-gateway-buttons pay-card bookly-box bookly-nav-steps" style="display:none">
    <button class="bookly-back-step bookly-js-back-step bookly-btn ladda-button" data-style="zoom-in" data-spinner-size="40">
        <span class="ladda-label"><?php echo Common::getTranslatedOption( 'bookly_l10n_button_back' ) ?></span>
    </button>
    <button class="bookly-next-step bookly-js-next-step bookly-btn ladda-button" data-style="zoom-in" data-spinner-size="40">
        <span class="ladda-label"><?php echo Common::getTranslatedOption( 'bookly_l10n_step_payment_button_next' ) ?></span>
    </button>
</div>

<?php Proxy\Shared::renderPaymentGatewayForm( $form_id, $page_url ) ?>

<div class="bookly-gateway-buttons pay-coupon bookly-box bookly-nav-steps" style="display: none">
    <button class="bookly-back-step bookly-js-back-step bookly-btn ladda-button" data-style="zoom-in" data-spinner-size="40">
        <span class="ladda-label"><?php echo Common::getTranslatedOption( 'bookly_l10n_button_back' ) ?></span>
    </button>
    <button class="bookly-next-step bookly-js-next-step bookly-js-coupon-payment bookly-btn ladda-button" data-style="zoom-in" data-spinner-size="40">
        <span class="ladda-label"><?php echo Common::getTranslatedOption( 'bookly_l10n_step_payment_button_next' ) ?></span>
    </button>
</div>
