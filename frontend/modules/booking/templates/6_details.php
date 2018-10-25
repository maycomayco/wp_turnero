<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
use Bookly\Lib\Utils\Common;
use Bookly\Lib\Config;
use Bookly\Lib\Proxy;

/** @var \Bookly\Lib\UserBookingData $userData */
echo $progress_tracker;
?>

<div class="bookly-box"><?php echo $info_text ?></div>
<?php if ( $info_text_guest ) : ?>
    <div class="bookly-box bookly-js-guest"><?php echo $info_text_guest ?></div>
<?php endif ?>
<?php if ( get_option( 'bookly_app_show_login_button' ) && get_current_user_id() == 0 ) : ?>
<div class="bookly-box bookly-js-guest">
    <button class="bookly-btn bookly-inline-block bookly-js-login-show ladda-button"><?php echo Common::getTranslatedOption( 'bookly_l10n_step_details_button_login' ) ?></button>
</div>
<?php endif ?>

<div class="bookly-details-step">
    <?php if ( Config::showFirstLastName() ) : ?>
    <div class="bookly-box bookly-table">
        <div class="bookly-form-group">
            <label><?php echo Common::getTranslatedOption( 'bookly_l10n_label_first_name' ) ?></label>
            <div>
                <input class="bookly-js-first-name" type="text" value="<?php echo esc_attr( $userData->getFirstName() ) ?>"/>
            </div>
            <div class="bookly-js-first-name-error bookly-label-error"></div>
        </div>
        <div class="bookly-form-group">
            <label><?php echo Common::getTranslatedOption( 'bookly_l10n_label_last_name' ) ?></label>
            <div>
                <input class="bookly-js-last-name" type="text" value="<?php echo esc_attr( $userData->getLastName() ) ?>"/>
            </div>
            <div class="bookly-js-last-name-error bookly-label-error"></div>
        </div>
    </div>
    <?php endif ?>
    <div class="bookly-box bookly-table">
        <?php if ( ! get_option( 'bookly_cst_first_last_name' ) ) : ?>
        <div class="bookly-form-group">
            <label><?php echo Common::getTranslatedOption( 'bookly_l10n_label_name' ) ?></label>
            <div>
                <input class="bookly-js-full-name" type="text" value="<?php echo esc_attr( $userData->getFullName() ) ?>"/>
            </div>
            <div class="bookly-js-full-name-error bookly-label-error"></div>
        </div>
        <?php endif ?>
        <div class="bookly-form-group">
            <label><?php echo Common::getTranslatedOption( 'bookly_l10n_label_phone' ) ?></label>
            <div>
                <input class="bookly-js-user-phone-input<?php if ( get_option( 'bookly_cst_phone_default_country' ) != 'disabled' ) : ?> bookly-user-phone<?php endif ?>" value="<?php echo esc_attr( $userData->getPhone() ) ?>" type="text" />
            </div>
            <div class="bookly-js-user-phone-error bookly-label-error"></div>
        </div>
        <div class="bookly-form-group">
            <label><?php echo Common::getTranslatedOption( 'bookly_l10n_label_email' ) ?></label>
            <div>
                <input class="bookly-js-user-email" maxlength="255" type="text" value="<?php echo esc_attr( $userData->getEmail() ) ?>"/>
            </div>
            <div class="bookly-js-user-email-error bookly-label-error"></div>
        </div>
    </div>
    <?php Proxy\CustomerInformation::renderDetailsStep( $userData ) ?>
    <?php if ( Config::showNotes() ): ?>
        <div class="bookly-box">
            <div class="bookly-form-group">
                <label><?php echo Common::getTranslatedOption( 'bookly_l10n_label_notes' ) ?></label>
                <div>
                    <textarea class="bookly-js-user-notes" rows="3"><?php echo esc_html( $userData->getNotes() ) ?></textarea>
                </div>
            </div>
        </div>
    <?php endif ?>
    <?php Proxy\CustomFields::renderDetailsStep( $userData ) ?>
</div>

<?php Proxy\RecurringAppointments::renderInfoMessage( $userData ) ?>

<div class="bookly-box bookly-nav-steps">
    <button class="bookly-back-step bookly-js-back-step bookly-btn ladda-button" data-style="zoom-in" data-spinner-size="40">
        <span class="ladda-label"><?php echo Common::getTranslatedOption( 'bookly_l10n_button_back' ) ?></span>
    </button>
    <button class="bookly-next-step bookly-js-next-step bookly-btn ladda-button" data-style="zoom-in" data-spinner-size="40">
        <span class="ladda-label"><?php echo Common::getTranslatedOption( 'bookly_l10n_step_details_button_next' ) ?></span>
    </button>
</div>