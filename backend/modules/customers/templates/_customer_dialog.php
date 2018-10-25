<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
use Bookly\Lib\Utils\Common;
use Bookly\Lib\Config;
use Bookly\Lib\Proxy;
?>
<script type="text/ng-template" id="bookly-customer-dialog.tpl">
<div id="bookly-customer-dialog" class="modal fade" tabindex=-1 role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-hidden="true">&times;</button>
                <div class="modal-title h2"><?php esc_html_e( 'New Customer', 'bookly' ) ?></div>
            </div>
            <div ng-show=loading class="modal-body">
                <div class="bookly-loading"></div>
            </div>
            <div class="modal-body" ng-hide="loading">
                <div class="form-group">
                    <label for="wp_user"><?php esc_html_e( 'User', 'bookly' ) ?></label>
                    <select ng-model="form.wp_user_id" class="form-control" id="wp_user">
                        <option value=""></option>
                        <?php foreach ( get_users( array( 'fields' => array( 'ID', 'display_name' ), 'orderby' => 'display_name' ) ) as $wp_user ) : ?>
                            <option value="<?php echo $wp_user->ID ?>">
                                <?php echo $wp_user->display_name ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </div>

                <?php if ( Config::showFirstLastName() ) : ?>
                    <div class="form-group">
                        <div class="row">
                            <div class="col-sm-6">
                                <label for="first_name"><?php esc_html_e( 'First name', 'bookly' ) ?></label>
                                <input class="form-control" type="text" ng-model="form.first_name" id="first_name" />
                                <span style="font-size: 11px;color: red" ng-show="errors.first_name.required"><?php esc_html_e( 'Required', 'bookly' ) ?></span>
                            </div>
                            <div class="col-sm-6">
                                <label for="last_name"><?php esc_html_e( 'Last name', 'bookly' ) ?></label>
                                <input class="form-control" type="text" ng-model="form.last_name" id="last_name" />
                                <span style="font-size: 11px;color: red" ng-show="errors.last_name.required"><?php esc_html_e( 'Required', 'bookly' ) ?></span>
                            </div>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="form-group">
                        <label for="full_name"><?php esc_html_e( 'Name', 'bookly' ) ?></label>
                        <input class="form-control" type="text" ng-model="form.full_name" id="full_name" />
                        <span style="font-size: 11px;color: red" ng-show="errors.full_name.required"><?php esc_html_e( 'Required', 'bookly' ) ?></span>
                    </div>
                <?php endif ?>

                <div class="form-group">
                    <label for="phone"><?php esc_html_e( 'Phone', 'bookly' ) ?></label>
                    <input class="form-control" type="text" ng-model=form.phone id="phone" />
                </div>

                <div class="form-group">
                    <label for="email"><?php esc_html_e( 'Email', 'bookly' ) ?></label>
                    <input class="form-control" type="text" ng-model=form.email id="email" />
                </div>

                <?php Proxy\CustomerInformation::renderCustomerDialog() ?>
                <?php Proxy\CustomerGroups::renderCustomerDialog() ?>

                <div class="form-group">
                    <label for="notes"><?php esc_html_e( 'Notes', 'bookly' ) ?></label>
                    <textarea class="form-control" ng-model=form.notes id="notes"></textarea>
                </div>

                <div class="form-group">
                    <label for="birthday"><?php esc_html_e( 'Date of birth', 'bookly' ) ?></label>
                    <input class="form-control" type="text" ng-model=form.birthday id="birthday"
                           ui-date="dateOptions" ui-date-format="yy-mm-dd" autocomplete="off" />
                </div>
            </div>
            <div class="modal-footer">
                <div ng-hide=loading>
                    <?php Common::customButton( null, 'btn-success btn-lg', '', array( 'ng-click' => 'processForm()' ) ) ?>
                    <?php Common::customButton( null, 'btn-default btn-lg', __( 'Cancel', 'bookly' ), array( 'data-dismiss' => 'modal' ) ) ?>
                </div>
            </div>
        </div>
    </div>
</div>
</script>