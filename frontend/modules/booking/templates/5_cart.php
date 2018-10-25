<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
use Bookly\Lib\Utils\Common;
use Bookly\Lib\Utils\Price;
use Bookly\Lib\Proxy;

echo $progress_tracker;
?>
<div class="bookly-box"><?php echo $info_text ?></div>
<div class="bookly-box">
    <button class="bookly-add-item bookly-btn ladda-button" data-style="zoom-in" data-spinner-size="40">
        <span class="ladda-label"><?php echo Common::getTranslatedOption( 'bookly_l10n_button_book_more' ) ?></span>
    </button>
</div>
<div class="bookly-box bookly-label-error"></div>
<div class="bookly-cart-step">
    <div class="bookly-cart bookly-box">
        <table>
            <thead class="bookly-desktop-version">
                <tr>
                    <?php foreach ( $columns as $position => $column ) : ?>
                        <th <?php if ( isset( $positions['price'] ) && $position == $positions['price'] ) echo 'class="bookly-rtext"' ?>><?php echo $column ?></th>
                    <?php endforeach ?>
                    <th></th>
                </tr>
            </thead>
            <tbody class="bookly-desktop-version">
            <?php foreach ( $items_data as $key => $data ) : ?>
                <tr data-cart-key="<?php echo $key ?>" class="bookly-cart-primary">
                    <?php foreach ( $data as $position => $value ) : ?>
                    <td <?php if ( isset( $positions['price'] ) && $position == $positions['price'] ) echo 'class="bookly-rtext"' ?>><?php echo $value ?></td>
                    <?php endforeach ?>
                    <td class="bookly-rtext bookly-nowrap bookly-js-actions">
                        <button class="bookly-round" data-action="edit" title="<?php esc_attr_e( 'Edit', 'bookly' ) ?>" data-style="zoom-in" data-spinner-size="30"><span class="ladda-label"><i class="bookly-icon-sm bookly-icon-edit"></i></span></button>
                        <button class="bookly-round" data-action="drop" title="<?php esc_attr_e( 'Remove', 'bookly' ) ?>" data-style="zoom-in" data-spinner-size="30"><span class="ladda-label"><i class="bookly-icon-sm bookly-icon-drop"></i></span></button>
                    </td>
                </tr>
                <?php Proxy\Shared::renderCartItemInfo( $userData, $key, $positions, true ) ?>
            <?php endforeach ?>
            </tbody>
            <tbody class="bookly-mobile-version">
            <?php foreach ( $items_data as $key => $data ) : ?>
                <?php foreach ( $data as $position => $value ) : ?>
                    <tr data-cart-key="<?php echo $key ?>" class="bookly-cart-primary">
                        <th><?php echo $columns[ $position ] ?></th>
                        <td><?php echo $value ?></td>
                    </tr>
                <?php endforeach ?>
                <?php Proxy\Shared::renderCartItemInfo( $userData, $key, $positions, false ) ?>
                <tr data-cart-key="<?php echo $key ?>">
                    <th></th>
                    <td class="bookly-js-actions">
                        <button class="bookly-round" data-action="edit" title="<?php esc_attr_e( 'Edit', 'bookly' ) ?>" data-style="zoom-in" data-spinner-size="30"><span class="ladda-label"><i class="bookly-icon-sm bookly-icon-edit"></i></span></button>
                        <button class="bookly-round" data-action="drop" title="<?php esc_attr_e( 'Remove', 'bookly' ) ?>" data-style="zoom-in" data-spinner-size="30"><span class="ladda-label"><i class="bookly-icon-sm bookly-icon-drop"></i></span></button>
                    </td>
                </tr>
            <?php endforeach ?>
            </tbody>
            <?php if ( isset( $positions['price'] ) || ( $deposit['show'] && isset( $positions['deposit'] ) ) ) : ?>
                <tfoot class="bookly-mobile-version">
                <?php Proxy\CustomerGroups::renderCartDiscountRow( $columns, $positions, 'mobile' ) ?>
                <?php if ( isset ( $positions['price'] ) ) : ?>
                    <?php if ( $wl_total > 0 ): ?>
                        <tr class="bookly-cart-total">
                            <th><?php _e( 'Waiting list appointments', 'bookly' ) ?>:</th>
                            <td><strong class="bookly-js-total-waiting-list-price"><?php echo Price::format( - $wl_total ) ?></strong></td>
                        </tr>
                    <?php endif ?>
                    <tr class="bookly-cart-total">
                        <th><?php _e( 'Total', 'bookly' ) ?>:</th>
                        <td><strong class="bookly-js-total-price"><?php echo Price::format( $total ) ?></strong></td>
                    </tr>
                <?php endif ?>
                <?php if ( $deposit['show'] ) : ?>
                    <tr class="bookly-cart-total">
                        <th><?php _e( 'Deposit', 'bookly' ) ?>:</th>
                        <td><strong class="bookly-js-total-deposit-price"><?php echo Price::format( $deposit['to_pay'] ) ?></strong></td>
                    </tr>
                <?php endif ?>
                </tfoot>
                <tfoot class="bookly-desktop-version">
                <?php if ( $wl_total > 0 ): ?>
                    <tr class="bookly-cart-total">
                        <?php foreach ( $columns as $position => $column ): ?>
                            <td <?php if ( isset ( $positions['price'] ) && $position == $positions['price'] ) echo 'class="bookly-rtext"' ?>>
                                <?php if ( $position == 0 ) : ?>
                                    <strong><?php esc_html_e( 'Waiting list appointments', 'bookly' ) ?>:</strong>
                                <?php endif ?>
                                <?php if ( isset ( $positions['price'] ) && $position == $positions['price'] ): ?>
                                    <strong class="bookly-js-total-waiting-list-price"><?php echo Price::format( - $wl_total ) ?></strong>
                                <?php endif ?>
                                <?php if ( $deposit['show'] && $position == $positions['deposit'] ): ?>
                                    <strong class="bookly-js-total-deposit-price"><?php echo Price::format( - $wl_deposit ) ?></strong>
                                <?php endif ?>
                            </td>
                        <?php endforeach ?>
                        <td></td>
                    </tr>
                <?php endif ?>
                <?php Proxy\CustomerGroups::renderCartDiscountRow( $columns, $positions ) ?>
                <tr class="bookly-cart-total">
                    <?php foreach ( $columns as $position => $column ) : ?>
                    <td <?php if ( isset( $positions['price'] ) && $position == $positions['price'] ) echo 'class="bookly-rtext"' ?>>
                        <?php if ( $position == 0 ) : ?>
                        <strong><?php _e( 'Total', 'bookly' ) ?>:</strong>
                        <?php endif ?>
                        <?php if ( isset( $positions['price'] ) && $position == $positions['price'] ) : ?>
                        <strong class="bookly-js-total-price"><?php echo Price::format( $total ) ?></strong>
                        <?php endif ?>
                        <?php if ( $deposit['show'] && $position == $positions['deposit'] ) : ?>
                        <strong class="bookly-js-total-deposit-price"><?php echo Price::format( $deposit['to_pay'] ) ?></strong>
                        <?php endif ?>
                    </td>
                    <?php endforeach ?>
                    <td></td>
                </tr>
                </tfoot>
            <?php endif ?>
        </table>
    </div>
</div>

<?php Proxy\RecurringAppointments::renderInfoMessage( $userData ) ?>

<div class="bookly-box bookly-nav-steps">
    <button class="bookly-back-step bookly-js-back-step bookly-btn ladda-button" data-style="zoom-in" data-spinner-size="40">
        <span class="ladda-label"><?php echo Common::getTranslatedOption( 'bookly_l10n_button_back' ) ?></span>
    </button>
    <button class="bookly-next-step bookly-js-next-step bookly-btn ladda-button" data-style="zoom-in" data-spinner-size="40">
        <span class="ladda-label"><?php echo Common::getTranslatedOption( 'bookly_l10n_step_cart_button_next' ) ?></span>
    </button>
</div>