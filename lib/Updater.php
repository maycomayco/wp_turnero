<?php
namespace Bookly\Lib;

/**
 * Class Updater
 * @package Bookly
 */
class Updater extends Base\Updater
{
    function update_14_8()
    {
        global $wpdb;

        $this->alterTables( array(
            'ab_customers' => array(
                'ALTER TABLE `%s` ADD COLUMN `info_fields` TEXT DEFAULT NULL',
                'ALTER TABLE `%s` ADD COLUMN `created` DATETIME DEFAULT NULL',
            ),
        ) );
        $wpdb->query( sprintf( 'UPDATE `%s` SET `info_fields` = "[]", `created` = NOW()', $this->getTableName( 'ab_customers' ) ) );
        $this->alterTables( array(
            'ab_customers' => array(
                'ALTER TABLE `%s` CHANGE COLUMN `created` `created` DATETIME NOT NULL',
            ),
            'ab_customer_appointments' => array(
                'ALTER TABLE `%s` ADD COLUMN `rating_comment` TEXT DEFAULT NULL AFTER `time_zone_offset`',
                'ALTER TABLE `%s` ADD COLUMN `rating` INT DEFAULT NULL AFTER `time_zone_offset`',
            ),
        ) );

        update_option( 'bookly_pmt_price_format', str_replace( '{price', '{sign}{price', get_option( 'bookly_pmt_price_format' ) ) );
    }

    function update_14_6()
    {
        global $wpdb;

        $meta_id = (int) get_option( 'bookly_processing_wc_order_id' );
        if ( $meta_id == 0 ) {

            $this->alterTables( array(
                'ab_customers' => array(
                    'ALTER TABLE `%s` ADD COLUMN `group_id` INT UNSIGNED DEFAULT NULL AFTER `wp_user_id`',
                ),
                'ab_payments' => array(
                    'ALTER TABLE `%s` CHANGE COLUMN `status` `status` ENUM("pending","completed","rejected") NOT NULL DEFAULT "completed"',
                    'ALTER TABLE `%s` ADD COLUMN `coupon_id` INT UNSIGNED DEFAULT NULL AFTER `id`',
                    'ALTER TABLE `%s` ADD COLUMN `gateway_price_correction` DECIMAL(10,2) NULL DEFAULT 0.00 AFTER `paid_type`',
                ),
                'ab_services' => array(
                    'ALTER TABLE `%s` CHANGE COLUMN `visibility` `visibility` ENUM("public","private","group") NOT NULL DEFAULT "public"',
                    'ALTER TABLE `%s` ADD COLUMN `package_unassigned` TINYINT(1) NOT NULL DEFAULT 0 AFTER `package_size`',
                ),
            ) );

            if ( $this->tableExists( 'icl_strings' ) ) {
                $rows = $wpdb->get_results( sprintf(
                    'SELECT id, gateway, type FROM `%s`',
                    $this->getTableName( 'ab_notifications' )
                ) );

                $strings = array();
                foreach ( $rows as $row ) {
                    $name = sprintf( '%s_%s', $row->gateway, $row->type );
                    $strings[ $name ] = $name . '_' . $row->id;
                    if ( $row->gateway == 'email' ) {
                        $strings[ $name . '_subject' ] = $name . '_' . $row->id . '_subject';
                    }
                }
                $this->renameL10nStrings( $strings, false );
            }

            add_option( 'bookly_app_show_time_zone_switcher', '0' );
            add_option( 'bookly_paypal_increase', '0' );
            add_option( 'bookly_paypal_addition', '0' );

            $wc_exists = $this->tableExists( 'woocommerce_order_itemmeta' );
        } else {
            $wc_exists = true;
        }

        if ( $wc_exists ) {
            $wc_order_meta_table = $this->getTableName( 'woocommerce_order_itemmeta' );
            $rows = (array) $wpdb->get_results( $wpdb->prepare(
                'SELECT meta_id, meta_value FROM `' . $wc_order_meta_table . '` WHERE meta_key = \'bookly\' AND meta_id > %d',
                $meta_id
            ), ARRAY_A );
            foreach ( $rows as $row ) {
                $meta = @unserialize( $row['meta_value'] );
                if ( isset( $meta['items'] ) ) {
                    $update = false;
                    foreach ( $meta['items'] as &$data ) {
                        if ( is_numeric( $data['slots'][0][2] ) ) {
                            $data['slots'][0][2] = date( 'Y-m-d H:i:s', $data['slots'][0][2] );
                            $update = true;
                        }
                    }
                    if ( $update ) {
                        $wpdb->update( $wc_order_meta_table, array( 'meta_value' => serialize( $meta ) ), array( 'meta_id' => $row['meta_id'] ) );
                    }
                }
                update_option( 'bookly_processing_wc_order_id', $row['meta_id'] );
            }

            delete_option( 'bookly_processing_wc_order_id' );
        }
    }

    function update_14_5()
    {
        $bookly_custom_fields = get_option( 'bookly_custom_fields', 'missing' );
        if ( $bookly_custom_fields != 'missing' ) {
            update_option( 'bookly_custom_fields_data', $bookly_custom_fields );
            delete_option( 'bookly_custom_fields' );
        }
        $bookly_custom_fields_merge_repetitive = get_option( 'bookly_custom_fields_merge_repetitive', 'missing' );
        if ( $bookly_custom_fields_merge_repetitive != 'missing' ) {
            update_option( 'bookly_custom_fields_merge_repeating', $bookly_custom_fields_merge_repetitive );
            delete_option( 'bookly_custom_fields_merge_repetitive' );
        }
    }

    function update_14_4()
    {
        global $wpdb;

        if ( get_option( 'bookly_pmt_local' ) != 1 ) {
            update_option( 'bookly_pmt_local', '0' );
        }

        add_option( 'bookly_url_cancel_confirm_page_url', home_url() );
        add_option( 'bookly_ntf_processing_interval', '2' );
        add_option( 'bookly_app_show_notes', '0' );
        add_option( 'bookly_reminder_data', array( 'SW1wb3J0YW50ISBJdCBsb29rcyBsaWtlIHlvdSBhcmUgdXNpbmcgYW4gaWxsZWdhbCBjb3B5IG9mIEJvb2tseSDigJMgaXQgbWF5IGNvbnRhaW4gYSBtYWxpY2lvdXMgY29kZSwgYSB0cm9qYW4gb3IgYSBiYWNrZG9vci4=', 'VGhlIGxlZ2FsIGNvcHkgb2YgQm9va2x5IGluY2x1ZGVzIGFsbCBmZWF0dXJlcywgbGlmZXRpbWUgZnJlZSB1cGRhdGVzLCBhbmQgMjQvNyBzdXBwb3J0LiBCeSBidXlpbmcgYSBsZWdhbCBjb3B5IG9mIEJvb2tseSBhdCBhIHNwZWNpYWwgZGlzY291bnRlZCBwcmljZSwgeW91IG1heSBiZW5lZml0IGZyb20gb3VyIHBhcnRuZXLigJlzIGV4Y2x1c2l2ZSBkaXNjb3VudHMh', 'PGEgaHJlZj0iaHR0cHM6Ly93d3cuYm9va2luZy13cC1wbHVnaW4uY29tL2JlY29tZS1sZWdhbC8iIHRhcmdldD0iX2JsYW5rIj5DbGljayBoZXJlIHRvIGxlYXJuIG1vcmUgPj4+PC9hPg' ) );
        add_option( 'bookly_lic_repeat_time', time() + 7776000 );
        $this->addL10nOptions( array(
            'bookly_l10n_label_notes' => __( 'Notes', 'bookly' ),
        ) );

        $this->renameOptions( array(
            'bookly_pmt_paypal'               => 'bookly_paypal_enabled',
            'bookly_pmt_paypal_sandbox'       => 'bookly_paypal_sandbox',
            'bookly_pmt_paypal_api_password'  => 'bookly_paypal_api_password',
            'bookly_pmt_paypal_api_signature' => 'bookly_paypal_api_signature',
            'bookly_pmt_paypal_api_username'  => 'bookly_paypal_api_username',
            'bookly_pmt_paypal_id'            => 'bookly_paypal_id',
            'bookly_custom_fields'            => 'bookly_custom_fields_data',
            'bookly_custom_fields_merge_repetitive' => 'bookly_custom_fields_merge_repeating',
        ) );

        $this->alterTables( array(
            'ab_appointments' => array(
                'ALTER TABLE `%s` ADD COLUMN `custom_service_name` VARCHAR(255) DEFAULT NULL AFTER `service_id`',
                'ALTER TABLE `%s` ADD COLUMN `custom_service_price` DECIMAL(10,2) DEFAULT NULL AFTER `custom_service_name`',
                'ALTER TABLE `%s` CHANGE COLUMN `service_id` `service_id` INT UNSIGNED DEFAULT NULL'
            ),
            'ab_customer_appointments' => array(
                'ALTER TABLE `%s` ADD COLUMN `status_changed_at` DATETIME NULL AFTER `status`',
                'ALTER TABLE `%s` ADD COLUMN `notes` TEXT DEFAULT NULL AFTER `number_of_persons`'
            ),
            'ab_notifications' => array(
                'ALTER TABLE `%s` ADD COLUMN `attach_ics` TINYINT(1) NOT NULL DEFAULT 0 AFTER `to_admin`',
            ),
            'ab_services'     => array(
                'ALTER TABLE `%s` ADD COLUMN `recurrence_enabled` TINYINT(1) NOT NULL DEFAULT 1 AFTER `staff_preference`',
                'ALTER TABLE `%s` ADD COLUMN `recurrence_frequencies` SET("daily","weekly","biweekly","monthly") NOT NULL DEFAULT "daily,weekly,biweekly,monthly" AFTER `recurrence_enabled`',
            )
        ) );

        // Remove `unique_ids_idx` index from `ab_sub_services`.
        $ref = $wpdb->get_row( sprintf(
            'SELECT `constraint_name`, `referenced_table_name` FROM `information_schema`.`key_column_usage`
                WHERE `TABLE_SCHEMA` = SCHEMA() AND `TABLE_NAME` = "%s" AND `COLUMN_NAME` = "service_id" AND `REFERENCED_TABLE_NAME` IS NOT NULL',
            $this->getTableName( 'ab_sub_services' )
        ) );
        if ( $ref ) {
            $wpdb->query( sprintf( 'ALTER TABLE `%s` DROP FOREIGN KEY `%s`', $this->getTableName( 'ab_sub_services' ), $ref->constraint_name ) );
            $this->alterTables( array(
                'ab_sub_services' => array(
                    'ALTER TABLE `%s` DROP INDEX `unique_ids_idx`'
                ),
            ) );
            $wpdb->query( sprintf(
                'ALTER TABLE `%s` ADD CONSTRAINT FOREIGN KEY (service_id) REFERENCES %s(id) ON DELETE CASCADE ON UPDATE CASCADE',
                $this->getTableName( 'ab_sub_services' ),
                $ref->referenced_table_name
            ) );
        }

        foreach ( (array) json_decode( 'bookly_recurring_appointments_frequencies', true ) as $service_id => $frequencies ) {
            if ( $service = Entities\Service::find( $service_id ) ) {
                $service
                    ->setRecurrenceEnabled( $frequencies['enabled'] )
                    ->setRecurrenceFrequencies( implode( ',', $frequencies['frequencies'] ) )
                    ->save();
            }
        }
        delete_option( 'bookly_recurring_appointments_frequencies' );

        $notifications = (array) $wpdb->get_results( sprintf( 'SELECT id, settings FROM `%s` WHERE `type` IN (\'%s\',\'%s\') AND `active` = 1',
            $this->getTableName( 'ab_notifications' ),
            Entities\Notification::TYPE_APPOINTMENT_START_TIME,
            Entities\Notification::TYPE_LAST_CUSTOMER_APPOINTMENT
        ) );

        foreach ( $notifications as $notification ) {
            $settings = (array) json_decode( $notification->settings, true );
            if ( $settings[ DataHolders\Notification\Settings::SET_EXISTING_EVENT_WITH_DATE_AND_TIME ]['status'] == '' ) {
                $settings[ DataHolders\Notification\Settings::SET_EXISTING_EVENT_WITH_DATE_AND_TIME ]['status'] = 'any';
                $wpdb->update( $this->getTableName( 'ab_notifications' ), array( 'settings' => json_encode( $settings ) ), array( 'id' => $notification->id ) );
            }
        }
    }

    function update_14_3()
    {
        $this->renameOptions( array(
            'bookly_gen_approve_page_url'        => 'bookly_url_approve_page_url',
            'bookly_gen_approve_denied_page_url' => 'bookly_url_approve_denied_page_url',
            'bookly_gen_cancel_page_url'         => 'bookly_url_cancel_page_url',
            'bookly_gen_cancel_denied_page_url'  => 'bookly_url_cancel_denied_page_url',
            'bookly_gen_final_step_url'          => 'bookly_url_final_step_url',
        ) );

        add_option( 'bookly_url_reject_page_url', home_url() );
        add_option( 'bookly_url_reject_denied_page_url', home_url() );

        $this->alterTables( array(
            'ab_services' => array(
                'ALTER TABLE `%s` ADD COLUMN `end_time_info` VARCHAR(255) DEFAULT "" AFTER `info`',
                'ALTER TABLE `%s` ADD COLUMN `start_time_info` VARCHAR(255) DEFAULT "" AFTER `info`',
                'ALTER TABLE `%s` ADD COLUMN `appointments_limit` INT DEFAULT NULL AFTER `package_size`',
                'ALTER TABLE `%s` ADD COLUMN `limit_period` ENUM("off","day","week","month","year") NOT NULL DEFAULT "off" AFTER `appointments_limit`'
            ),
            'ab_customer_appointments' => array(
                'ALTER TABLE `%s` ADD COLUMN `package_id` INT UNSIGNED DEFAULT NULL AFTER `id`',
            ),
            'ab_notifications' => array(
                'ALTER TABLE `%s` ADD COLUMN `settings` TEXT NULL',
                'ALTER TABLE `%s` ADD COLUMN `to_staff` TINYINT(1) NOT NULL DEFAULT 0',
                'ALTER TABLE `%s` ADD COLUMN `to_customer` TINYINT(1) NOT NULL DEFAULT 0',
                'ALTER TABLE `%s` CHANGE COLUMN `copy` `to_admin` TINYINT(1) NOT NULL DEFAULT 0',
            ),
            'ab_sent_notifications' => array(
                'ALTER TABLE `%s` ADD COLUMN `notification_id` INT UNSIGNED',
                'UPDATE `%s` `sn` SET `sn`.`notification_id` = (SELECT `n`.`id` FROM `' . $this->getTableName( 'ab_notifications' ) . '` `n` WHERE `n`.`type` = `sn`.`type` LIMIT 1)',
                'ALTER TABLE `%s` CHANGE COLUMN `notification_id` INT UNSIGNED NOT NULL',
                'ALTER TABLE `%s` ADD CONSTRAINT FOREIGN KEY (`notification_id`) REFERENCES `' . $this->getTableName( 'ab_notifications' ) . '` (`id`) ON DELETE CASCADE ON UPDATE CASCADE'
            ),
            'ab_sub_services' => array(
                'ALTER TABLE `%s` ADD COLUMN `type` ENUM("service","spare_time") NOT NULL DEFAULT "service" AFTER `id`',
                'ALTER TABLE `%s` ADD COLUMN `duration` INT DEFAULT NULL AFTER `sub_service_id`',
                'ALTER TABLE `%s` CHANGE COLUMN `sub_service_id` `sub_service_id` INT UNSIGNED DEFAULT NULL',
            ),
        ) );

        $this->dropTableColumns( $this->getTableName( 'ab_services' ), array( 'sub_services' ) );
        $this->dropTableColumns( $this->getTableName( 'ab_sent_notifications' ), array( 'type', 'gateway' ) );

        add_option( 'bookly_cst_show_update_details_dialog', '1' );
        add_option( 'bookly_custom_fields_merge_repetitive', '0' );

        $options = array(
            'bookly_l10n_info_complete_step_limit_error' => __( 'You are trying to use the service too often. Please contact us to make a booking.', 'bookly' ),
            'bookly_l10n_info_complete_step_processing'  => __( 'Your payment has been accepted for processing.', 'bookly' ),
        );
        $this->addL10nOptions( $options );
    }

    function update_14_1()
    {
        global $wpdb;

        $wpdb->query( sprintf(
            'ALTER TABLE `%s` CHANGE `type` `type` ENUM("simple","compound","package") NOT NULL DEFAULT "simple"',
            $this->getTableName( 'ab_services' )
        ) );
        $wpdb->query( sprintf(
            'ALTER TABLE `%s` ADD `package_life_time` INT DEFAULT NULL AFTER `type`',
            $this->getTableName( 'ab_services' )
        ) );
        $wpdb->query( sprintf(
            'ALTER TABLE `%s` ADD `package_size` INT DEFAULT NULL AFTER `package_life_time`',
            $this->getTableName( 'ab_services' )
        ) );
        $wpdb->query( sprintf(
            'ALTER TABLE `%s` ADD COLUMN `staff_preference` ENUM("order","least_occupied","most_occupied","least_expensive","most_expensive") NOT NULL DEFAULT "most_expensive" AFTER `package_size`',
            $this->getTableName( 'ab_services' )
        ) );

        $wpdb->query(
            'CREATE TABLE IF NOT EXISTS `' . $this->getTableName( 'ab_sub_services' ) . '` (
                `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `service_id`        INT UNSIGNED NOT NULL,
                `sub_service_id`    INT UNSIGNED NOT NULL,
                `position`          INT NOT NULL DEFAULT 9999,
                UNIQUE KEY unique_ids_idx (service_id, sub_service_id),
                CONSTRAINT
                    FOREIGN KEY (service_id)
                    REFERENCES ' . $this->getTableName( 'ab_services' ) . '(id)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT
                    FOREIGN KEY (sub_service_id)
                    REFERENCES ' . $this->getTableName( 'ab_services' ) . '(id)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE = INNODB
            DEFAULT CHARACTER SET = utf8
            COLLATE = utf8_general_ci'
        );

        $wpdb->query(
            'CREATE TABLE IF NOT EXISTS `' . $this->getTableName( 'ab_messages' ) . '` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `message_id` INT UNSIGNED NOT NULL,
                `type`       VARCHAR(255) NOT NULL,
                `subject`    TEXT,
                `body`       TEXT,
                `seen`       TINYINT(1) NOT NULL DEFAULT 0,
                `created`    DATETIME NOT NULL
              ) ENGINE = INNODB
              DEFAULT CHARACTER SET = utf8
              COLLATE = utf8_general_ci'
        );

        $wpdb->query(
            'CREATE TABLE IF NOT EXISTS `' . $this->getTableName( 'ab_staff_preference_orders' ) . '` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `service_id`  INT UNSIGNED NOT NULL,
                `staff_id`    INT UNSIGNED NOT NULL,
                `position`    INT NOT NULL DEFAULT 9999,
                CONSTRAINT
                    FOREIGN KEY (service_id)
                    REFERENCES ' . $this->getTableName( 'ab_services' ) . '(id)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE,
                CONSTRAINT
                    FOREIGN KEY (staff_id)
                    REFERENCES ' . $this->getTableName( 'ab_staff' ) . '(id)
                    ON DELETE CASCADE
                    ON UPDATE CASCADE
            ) ENGINE = INNODB
            DEFAULT CHARACTER SET = utf8
            COLLATE = utf8_general_ci'
        );

        $wpdb->query( sprintf(
            'ALTER TABLE `%s` CHANGE COLUMN `name` `full_name` VARCHAR(255) NOT NULL DEFAULT ""',
            $this->getTableName( 'ab_customers' )
        ) );
        $wpdb->query( sprintf(
            'ALTER TABLE `%s`
                ADD COLUMN `first_name` VARCHAR(255) NOT NULL DEFAULT "" AFTER `full_name`,
                ADD COLUMN `last_name` VARCHAR(255) NOT NULL DEFAULT "" AFTER `first_name`',
            $this->getTableName( 'ab_customers' )
        ) );
        add_option( 'bookly_cst_first_last_name', '0' );

        $options = array(
            'bookly_l10n_label_first_name'    => __( 'First name', 'bookly' ),
            'bookly_l10n_label_last_name'     => __( 'Last name', 'bookly' ),
            'bookly_l10n_required_first_name' => __( 'Please tell us your first name', 'bookly' ),
            'bookly_l10n_required_last_name'  => __( 'Please tell us your last name', 'bookly' ),
        );
        $this->addL10nOptions( $options );

        // Update first and last name fields from full name.
        $wpdb->query( sprintf(
            'UPDATE `%s` SET `first_name` = SUBSTRING_INDEX(`full_name`, " ", 1), `last_name` = TRIM(SUBSTR(`full_name`, LOCATE(" ", `full_name`)))',
            $this->getTableName( 'ab_customers' )
        ) );

        $wpdb->query( sprintf(
            'ALTER TABLE `%s` ADD `staff_any` TINYINT(1) NOT NULL DEFAULT 0 AFTER `staff_id`',
            $this->getTableName( 'ab_appointments' )
        ) );

        // Move location from CustomerAppointment to Appointment.
        $wpdb->query( sprintf(
            'ALTER TABLE `%s` ADD `location_id` INT UNSIGNED DEFAULT NULL AFTER `series_id`',
            $this->getTableName( 'ab_appointments' )
        ) );
        $wpdb->query( sprintf(
            'UPDATE `%s` `a` SET `a`.`location_id` = (SELECT `ca`.`location_id` FROM `%s` `ca` WHERE `ca`.`appointment_id` = `a`.`id` AND `ca`.`location_id` IS NOT NULL LIMIT 1)',
            $this->getTableName( 'ab_appointments' ),
            $this->getTableName( 'ab_customer_appointments' )
        ) );
        $ref = $wpdb->get_row( sprintf(
            'SELECT `constraint_name`, `referenced_table_name` FROM `information_schema`.`key_column_usage`
                WHERE `TABLE_SCHEMA` = SCHEMA() AND `TABLE_NAME` = "%s" AND `COLUMN_NAME` = "location_id"',
            $this->getTableName( 'ab_customer_appointments' )
        ) );
        if ( $ref ) {
            $wpdb->query( sprintf( 'ALTER TABLE `%s` DROP FOREIGN KEY `%s`', $this->getTableName( 'ab_customer_appointments' ), $ref->constraint_name ) );
            $wpdb->query( sprintf(
                'ALTER TABLE `%s` ADD CONSTRAINT FOREIGN KEY (location_id) REFERENCES %s(id) ON DELETE SET NULL ON UPDATE CASCADE',
                $this->getTableName( 'ab_appointments' ),
                $ref->referenced_table_name
            ) );
        }
        $wpdb->query( sprintf( 'ALTER TABLE `%s` DROP COLUMN `location_id`', $this->getTableName( 'ab_customer_appointments' ) ) );

        // Add 'waitlisted' status.
        $wpdb->query( sprintf(
            'ALTER TABLE `%s` CHANGE `status` `status` ENUM("pending","approved","cancelled","rejected","waitlisted") NOT NULL DEFAULT "approved"',
            $this->getTableName( 'ab_customer_appointments' )
        ) );

        // Add new options.
        add_option( 'bookly_gen_approve_denied_page_url', get_option( 'bookly_gen_approve_page_url' ) );
    }

    function update_13_9()
    {
        global $wpdb;

        $wpdb->query(
            'CREATE TABLE IF NOT EXISTS `' . $this->getTableName( 'ab_stats' ) . '` (
                `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `name`      VARCHAR(255) NOT NULL,
                `value`     TEXT,
                `created`   DATETIME NOT NULL
              ) ENGINE = INNODB
              DEFAULT CHARACTER SET = utf8
              COLLATE = utf8_general_ci'
        );

        add_option( 'bookly_app_show_login_button', '0' );
        add_option( 'bookly_cst_remember_in_cookie', '0' );
        $this->addL10nOptions( array( 'bookly_l10n_step_details_button_login' => __( 'Log In' ) ) );

        $wpdb->query( sprintf(
            'ALTER TABLE `%s` ADD COLUMN `paid_type` ENUM("in_full","deposit") NOT NULL DEFAULT "in_full" AFTER `paid`',
            $this->getTableName( 'ab_payments' )
        ) );
        $wpdb->query( sprintf(
            'UPDATE `%s` SET `paid_type` = (CASE WHEN `paid` = `total` THEN "in_full" ELSE "deposit" END)',
            $this->getTableName( 'ab_payments' )
        ) );

        // Set price format.
        $currencies = Utils\Price::getCurrencies();
        $format     = $currencies[ get_option( 'bookly_pmt_currency' ) ]['format'];
        add_option( 'bookly_pmt_price_format', $format );

        // Time zone.
        $wpdb->query( sprintf(
            'ALTER TABLE `%s` ADD `time_zone` VARCHAR(255) AFTER `token`',
            $this->getTableName( 'ab_customer_appointments' )
        ) );
    }

    function update_13_4()
    {
        global $wpdb;

        $wpdb->query( sprintf(
            'ALTER TABLE `%s`
                ADD `created_from` ENUM("frontend","backend") NOT NULL DEFAULT "frontend" AFTER `compound_token`,
                ADD `created` DATETIME NOT NULL DEFAULT "0000-00-00 00:00:00" AFTER `created_from`',
            $this->getTableName( 'ab_customer_appointments' )
        ) );

        $wpdb->query( sprintf( 'ALTER TABLE `%s` CHANGE `capacity` `capacity_max` INT NOT NULL DEFAULT 1', $this->getTableName( 'ab_services' ) ) );
        $wpdb->query( sprintf( 'ALTER TABLE `%s` ADD `capacity_min` INT NOT NULL DEFAULT 1 AFTER `color`', $this->getTableName( 'ab_services' ) ) );
        $wpdb->query( sprintf( 'ALTER TABLE `%s` CHANGE `capacity` `capacity_max` INT NOT NULL DEFAULT 1', $this->getTableName( 'ab_staff_services' ) ) );
        $wpdb->query( sprintf( 'ALTER TABLE `%s` ADD `capacity_min` INT NOT NULL DEFAULT 1 AFTER `deposit`', $this->getTableName( 'ab_staff_services' ) ) );

        add_option( 'bookly_app_service_name_with_duration', '0' );

        $items = $wpdb->get_results( sprintf(
            'SELECT `subject`, `message`, `gateway` FROM `%s` WHERE `type` = "client_reminder"',
            $this->getTableName( 'ab_notifications' )
        ) );

        foreach ( $items as $item ) {
            $types = array( 'client_reminder_1st', 'client_reminder_2nd', 'client_reminder_3rd' );
            foreach ( $types as $type ) {
                $wpdb->insert( $this->getTableName( 'ab_notifications' ), array(
                    'gateway' => $item->gateway,
                    'type'    => $type,
                    'subject' => $item->subject,
                    'message' => $item->message,
                    'active'  => 0,
                ) );
            }
        }

        $times = get_option( 'bookly_cron_reminder_times' );
        $times['client_reminder_1st'] = 1;
        $times['client_reminder_2nd'] = 2;
        $times['client_reminder_3rd'] = 3;
        update_option( 'bookly_cron_reminder_times', $times );

        $bookly_cal_one_participant = '{service_name}' . "\n" . '{client_name}' . "\n" . '{client_phone}' . "\n" . '{client_email}' . "\n" . '{extras}' . '{location_name}' . '{custom_fields}' . "\n" . '{total_price} {payment_type} {payment_status}' . "\n" . __( 'Status', 'bookly' ) . ': {status}' . "\n" . __( 'Signed up', 'bookly' ) . ': {signed_up}' . "\n" . __( 'Capacity',  'bookly' ) . ': {service_capacity}';
        $bookly_cal_one_participant = str_replace( '{extras}', Config::serviceExtrasActive() ? '{extras}' . "\n" : '', $bookly_cal_one_participant );
        $bookly_cal_one_participant = str_replace( '{location_name}', Config::locationsActive() ? __( 'Location', 'bookly' ) . ': {location_name}' . "\n" : '', $bookly_cal_one_participant );

        add_option( 'bookly_cal_one_participant', $bookly_cal_one_participant );
        add_option( 'bookly_cal_many_participants', '{service_name}' . "\n" . __( 'Signed up', 'bookly' ) . ': {signed_up}' . "\n" . __( 'Capacity',  'bookly' ) . ': {service_capacity}' );
        $options = array(
            'bookly_l10n_step_time_slot_not_available' => __( 'The selected time is not available anymore. Please, choose another time slot.', 'bookly' ),
            'bookly_l10n_step_cart_slot_not_available' => __( 'The highlighted time is not available anymore. Please, choose another time slot.', 'bookly' ),
        );
        $this->addL10nOptions( $options );

        // Drop stats tables.
        $this->drop( array( $this->getTableName( 'ab_stats_forms' ), $this->getTableName( 'ab_stats_steps' ) ) );

        add_option( 'bookly_admin_preferred_language', '' );
    }

    function update_13_3()
    {
        add_option( 'bookly_app_custom_styles', '' );
        add_option( 'bookly_cst_required_phone', '1' );

        // Rename and add new appearance options.
        $info_coupon       = get_option( 'bookly_l10n_info_coupon' );
        $info_payment_step = get_option( 'bookly_l10n_info_payment_step' );
        $this->renameL10nStrings( array(
            'bookly_l10n_info_coupon'       => 'bookly_l10n_info_coupon_single_app',
            'bookly_l10n_info_payment_step' => 'bookly_l10n_info_payment_step_single_app',
        ) );
        $this->addL10nOptions( array(
            'bookly_l10n_info_coupon_several_apps'       => $info_coupon,
            'bookly_l10n_info_payment_step_several_apps' => $info_payment_step,
        ) );
    }

    function update_13_2()
    {
        $next = get_option( 'bookly_l10n_button_next' );
        $this->renameOptions( array( 'bookly_l10n_button_next' => 'bookly_l10n_step_service_button_next' ) );
        $options = array(
            'bookly_l10n_step_service_mobile_button_next' => $next,
            'bookly_l10n_step_cart_button_next'           => $next,
            'bookly_l10n_step_details_button_next'        => $next,
            'bookly_l10n_step_payment_button_next'        => $next,
        );
        $this->addL10nOptions( $options );
    }

    function update_13_1()
    {
        global $wpdb;

        // Statistics.
        add_option( 'bookly_gen_collect_stats', '1' );

        // Birthday greetings.
        $times = get_option( 'bookly_cron_reminder_times' );
        $times['client_birthday_greeting'] = 9;
        update_option( 'bookly_cron_reminder_times', $times );

        $wpdb->query( sprintf( 'ALTER TABLE `%s` ADD COLUMN `birthday` DATE AFTER `notes`', $this->getTableName( 'ab_customers' ) ) );
        $notifications = array(
            array(
                'gateway' => 'email',
                'type'    => 'client_birthday_greeting',
                'subject' => __( 'Happy Birthday!', 'bookly' ),
                'message' => wpautop( __( "Dear {client_name},\n\nHappy birthday!\nWe wish you all the best.\nMay you and your family be happy and healthy.\n\nThank you for choosing our company.\n\n{company_name}\n{company_phone}\n{company_website}", 'bookly' ) ),
                'active'  => 0,
            ),
            array(
                'gateway' => 'sms',
                'type'    => 'client_birthday_greeting',
                'subject' => '',
                'message' => __( "Dear {client_name},\nHappy birthday!\nWe wish you all the best.\nMay you and your family be happy and healthy.\nThank you for choosing our company.\n{company_name}\n{company_phone}\n{company_website}", 'bookly' ),
                'active'  => 0,
            ),
        );
        foreach ( $notifications as $data ) {
            $wpdb->insert( $this->getTableName( 'ab_notifications' ), $data );
        }

        $sn_table = $this->getTableName( 'ab_sent_notifications' );
        $wpdb->query( sprintf( 'ALTER TABLE `%s` ADD COLUMN `ref_id` INT UNSIGNED AFTER `id`, ADD INDEX `ref_id_idx` (`ref_id`)', $sn_table ) );
        $wpdb->query( sprintf( 'UPDATE `%s` SET `ref_id` = COALESCE(`customer_appointment_id`, `staff_id`)', $sn_table ) );
        $this->dropTableColumns( $sn_table, array( 'customer_appointment_id', 'staff_id' ) );
        $wpdb->query( sprintf(
            'ALTER TABLE `%s` CHANGE COLUMN `status` `status` ENUM("pending","approved","cancelled","rejected") NOT NULL DEFAULT "approved" AFTER `custom_fields`',
            $this->getTableName( 'ab_customer_appointments' )
        ) );
        $this->dropTableColumns( $this->getTableName( 'ab_services' ), array( 'start_time', 'end_time' ) );
    }

    function update_13_0()
    {
        global $wpdb;

        $dismiss_subscribe_notice = ! get_option( 'bookly_gen_show_subscribe_notice' );
        foreach ( get_users( array( 'role' => 'administrator' ) ) as $admin ) {
            delete_user_meta( $admin->ID, 'bookly_dismiss_admin_notice' );
            if ( $dismiss_subscribe_notice ) {
                update_user_meta( $admin->ID, 'bookly_dismiss_subscribe_notice', 1 );
            }
        }
        delete_option( 'bookly_gen_show_subscribe_notice' );

        add_option( 'bookly_api_server_error_time', '0' );
        add_option( 'bookly_grace_notifications', array ( 'bookly' => '0', 'add-ons' => '0', 'sent' => '0' ) );
        add_option( 'bookly_grace_hide_admin_notice_time', '0' );
        foreach ( apply_filters( 'bookly_plugins', array() ) as $plugin_class ) {
            add_option( $plugin_class::getPrefix() . 'grace_start', time() + 2 * WEEK_IN_SECONDS );
        }

        $options = array(
            'bookly_email_content_type'               => 'bookly_email_send_as',
            'bookly_pmt_authorizenet'                 => 'bookly_pmt_authorize_net',
            'bookly_pmt_authorizenet_api_login_id'    => 'bookly_pmt_authorize_net_api_login_id',
            'bookly_pmt_authorizenet_transaction_key' => 'bookly_pmt_authorize_net_transaction_key',
            'bookly_pmt_authorizenet_sandbox'         => 'bookly_pmt_authorize_net_sandbox',
            'bookly_pmt_pay_locally'                  => 'bookly_pmt_local',
        );
        $this->renameOptions( $options );

        if ( get_option( 'bookly_email_content_type' ) == 'plain' ) {
            update_option( 'bookly_email_content_type', 'text' );
        }

        // Authorize.Net => authorize_net.
        $wpdb->query( sprintf(
            'ALTER TABLE `%s` CHANGE COLUMN `type` `type` ENUM("local","coupon","paypal","authorizeNet","authorize_net","stripe","2checkout","payu_latam","payson","mollie","woocommerce") NOT NULL DEFAULT "local"',
            $this->getTableName( 'ab_payments' )
        ) );
        $wpdb->query( sprintf(
            'UPDATE `%s` SET `type` = "authorize_net" WHERE `type` = "authorizeNet"',
            $this->getTableName( 'ab_payments' )
        ) );
        $wpdb->query( sprintf(
            'ALTER TABLE `%s` CHANGE COLUMN `type` `type` ENUM("local","coupon","paypal","authorize_net","stripe","2checkout","payu_latam","payson","mollie","woocommerce") NOT NULL DEFAULT "local"',
            $this->getTableName( 'ab_payments' )
        ) );
        $this->dropTableColumns( $this->getTableName( 'ab_payments' ), array( 'transaction_id', 'token' ) );

        $notifications = array(
            array(
                'gateway' => 'email',
                'type'    => 'client_rejected_appointment',
                'subject' => __( 'Booking rejection', 'bookly' ),
                'message' => wpautop( __( "Dear {client_name}.\n\nYour booking of {service_name} on {appointment_date} at {appointment_time} has been rejected.\n\nReason: {cancellation_reason}\n\nThank you for choosing our company.\n\n{company_name}\n{company_phone}\n{company_website}", 'bookly' ) ),
                'active'  => 1,
            ),
            array(
                'gateway' => 'email',
                'type'    => 'staff_rejected_appointment',
                'subject' => __( 'Booking rejection', 'bookly' ),
                'message' => wpautop( __( "Hello.\n\nThe following booking has been rejected.\n\nReason: {cancellation_reason}\n\nService: {service_name}\nDate: {appointment_date}\nTime: {appointment_time}\nClient name: {client_name}\nClient phone: {client_phone}\nClient email: {client_email}", 'bookly' ) ),
                'active'  => 1,
            ),

            array(
                'gateway' => 'sms',
                'type'    => 'client_rejected_appointment',
                'subject' => '',
                'message' => __( "Dear {client_name}.\nYour booking of {service_name} on {appointment_date} at {appointment_time} has been rejected.\nReason: {cancellation_reason}\nThank you for choosing our company.\n{company_name}\n{company_phone}\n{company_website}", 'bookly' ),
                'active'  => 1,
            ),
            array(
                'gateway' => 'sms',
                'type'    => 'staff_rejected_appointment',
                'subject' => '',
                'message' => __( "Hello.\nThe following booking has been rejected.\nReason: {cancellation_reason}\nService: {service_name}\nDate: {appointment_date}\nTime: {appointment_time}\nClient name: {client_name}\nClient phone: {client_phone}\nClient email: {client_email}", 'bookly' ),
                'active'  => 1,
            ),
        );
        foreach ( $notifications as $data ) {
            $wpdb->insert( $this->getTableName( 'ab_notifications' ), $data );
        }

        $this->dropTableColumns( $this->getTableName( 'ab_customer_appointments' ), array( 'series' ) );
        $wpdb->query(
            'CREATE TABLE IF NOT EXISTS `' . $this->getTableName( 'ab_series' ) . '` (
                `id`     INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `repeat` VARCHAR(255),
                `token`  VARCHAR(255) NOT NULL
            ) ENGINE = INNODB
            DEFAULT CHARACTER SET = utf8
            COLLATE = utf8_general_ci'
        );
        $wpdb->query( sprintf( 'ALTER TABLE `%s` ADD COLUMN `series_id` INT UNSIGNED AFTER `id`', $this->getTableName( 'ab_appointments' ) ) );
        $wpdb->query( sprintf(
            'ALTER TABLE `%s` ADD CONSTRAINT FOREIGN KEY (series_id) REFERENCES %s(id) ON DELETE CASCADE ON UPDATE CASCADE',
            $this->getTableName( 'ab_appointments' ),
            $this->getTableName( 'ab_series' )
        ) );
    }

    function update_12_1()
    {
        global $wpdb;

        $options = array(
            'bookly_l10n_required_email'    => __( 'Please tell us your email', 'bookly' ),
            'bookly_l10n_required_employee' => __( 'Please select an employee', 'bookly' ),
            'bookly_l10n_required_name'     => __( 'Please tell us your name',  'bookly' ),
            'bookly_l10n_required_phone'    => __( 'Please tell us your phone', 'bookly' ),
            'bookly_l10n_required_service'  => __( 'Please select a service',   'bookly' ),
        );
        foreach ( $options as $option_name => $option_value ) {
            if ( get_option( $option_name ) == '' ) {
                $this->addL10nOptions( array( array( $option_name => $option_value ) ) );
            }
        }
        $wpdb->query( sprintf( 'ALTER TABLE `%s` ADD COLUMN `series` VARCHAR(255) NULL DEFAULT NULL', $this->getTableName( 'ab_customer_appointments' ) ) );
    }

    function update_12_0()
    {
        global $wpdb;

        $wpdb->query( sprintf( 'ALTER TABLE `%s` CHANGE COLUMN `google_data` `google_data` TEXT', $this->getTableName( 'ab_staff' ) ) );
    }

    function update_11_7()
    {
        global $wpdb;

        $wpdb->query( sprintf( 'ALTER TABLE `%s` ADD COLUMN `start_time` TIME NULL, ADD COLUMN `end_time` TIME NULL', $this->getTableName( 'ab_services' ) ) );

        $options = array(
            'ab_2checkout_api_secret_word'         => 'bookly_pmt_2checkout_api_secret_word',
            'ab_2checkout_api_seller_id'           => 'bookly_pmt_2checkout_api_seller_id',
            'ab_2checkout_sandbox'                 => 'bookly_pmt_2checkout_sandbox',
            'ab_appearance_color'                  => 'bookly_app_color',
            'ab_appearance_required_employee'      => 'bookly_app_required_employee',
            'ab_appearance_show_blocked_timeslots' => 'bookly_app_show_blocked_timeslots',
            'ab_appearance_show_calendar'          => 'bookly_app_show_calendar',
            'ab_appearance_show_day_one_column'    => 'bookly_app_show_day_one_column',
            'ab_appearance_show_progress_tracker'  => 'bookly_app_show_progress_tracker',
            'ab_appearance_staff_name_with_price'  => 'bookly_app_staff_name_with_price',
            'ab_authorizenet_api_login_id'         => 'bookly_pmt_authorizenet_api_login_id',
            'ab_authorizenet_sandbox'              => 'bookly_pmt_authorizenet_sandbox',
            'ab_authorizenet_transaction_key'      => 'bookly_pmt_authorizenet_transaction_key',
            'ab_cart_show_columns'                 => 'bookly_cart_show_columns',
            'ab_currency'                          => 'bookly_pmt_currency',
            'ab_custom_fields'                     => 'bookly_custom_fields',
            'ab_custom_fields_per_service'         => 'bookly_custom_fields_per_service',
            'ab_data_loaded'                       => 'bookly_data_loaded',
            'ab_db_version'                        => 'bookly_db_version',
            'ab_email_content_type'                => 'bookly_email_content_type',
            'ab_email_notification_reply_to_customers' => 'bookly_email_reply_to_customers',
            'ab_envato_purchase_code'              => 'bookly_envato_purchase_code',
            'ab_installation_time'                 => 'bookly_installation_time',
            'ab_mollie_api_key'                    => 'bookly_pmt_mollie_api_key',
            'ab_paypal_api_password'               => 'bookly_pmt_paypal_api_password',
            'ab_paypal_api_signature'              => 'bookly_pmt_paypal_api_signature',
            'ab_paypal_api_username'               => 'bookly_pmt_paypal_api_username',
            'ab_paypal_ec_mode'                    => 'bookly_pmt_paypal_sandbox',
            'ab_paypal_id'                         => 'bookly_pmt_paypal_id',
            'ab_payson_api_agent_id'               => 'bookly_pmt_payson_api_agent_id',
            'ab_payson_api_key'                    => 'bookly_pmt_payson_api_key',
            'ab_payson_api_receiver_email'         => 'bookly_pmt_payson_api_receiver_email',
            'ab_payson_fees_payer'                 => 'bookly_pmt_payson_fees_payer',
            'ab_payson_funding'                    => 'bookly_pmt_payson_funding',
            'ab_payson_sandbox'                    => 'bookly_pmt_payson_sandbox',
            'ab_payulatam_api_account_id'          => 'bookly_pmt_payu_latam_api_account_id',
            'ab_payulatam_api_key'                 => 'bookly_pmt_payu_latam_api_key',
            'ab_payulatam_api_merchant_id'         => 'bookly_pmt_payu_latam_api_merchant_id',
            'ab_payulatam_sandbox'                 => 'bookly_pmt_payu_latam_sandbox',
            'ab_settings_allow_staff_members_edit_profile' => 'bookly_gen_allow_staff_edit_profile',
            'ab_settings_approve_page_url'         => 'bookly_gen_approve_page_url',
            'ab_settings_cancel_denied_page_url'   => 'bookly_gen_cancel_denied_page_url',
            'ab_settings_cancel_page_url'          => 'bookly_gen_cancel_page_url',
            'ab_settings_cart_notifications_combined' => 'bookly_cst_combined_notifications',
            'ab_settings_client_cancel_appointment_action' => 'bookly_cst_client_cancel_action',
            'ab_settings_company_address'          => 'bookly_co_address',
            'ab_settings_company_logo_attachment_id'  => 'bookly_co_logo_attachment_id',
            'ab_settings_company_name'             => 'bookly_co_name',
            'ab_settings_company_phone'            => 'bookly_co_phone',
            'ab_settings_company_website'          => 'bookly_co_website',
            'ab_settings_coupons'                  => 'bookly_pmt_coupons',
            'ab_settings_create_account'           => 'bookly_cst_create_account',
            'ab_settings_cron_reminder'            => 'bookly_cron_reminder_times',
            'ab_settings_default_appointment_status' => 'bookly_gen_default_appointment_status',
            'ab_settings_final_step_url'           => 'bookly_gen_final_step_url',
            'ab_settings_friday_end'               => 'bookly_bh_friday_end',
            'ab_settings_friday_start'             => 'bookly_bh_friday_start',
            'ab_settings_google_client_id'         => 'bookly_gc_client_id',
            'ab_settings_google_client_secret'     => 'bookly_gc_client_secret',
            'ab_settings_google_event_title'       => 'bookly_gc_event_title',
            'ab_settings_google_limit_events'      => 'bookly_gc_limit_events',
            'ab_settings_google_two_way_sync'      => 'bookly_gc_two_way_sync',
            'ab_settings_link_assets_method'       => 'bookly_gen_link_assets_method',
            'ab_settings_maximum_available_days_for_booking' => 'bookly_gen_max_days_for_booking',
            'ab_settings_minimum_time_prior_booking' => 'bookly_gen_min_time_prior_booking',
            'ab_settings_minimum_time_prior_cancel'  => 'bookly_gen_min_time_prior_cancel',
            'ab_settings_monday_end'               => 'bookly_bh_monday_end',
            'ab_settings_monday_start'             => 'bookly_bh_monday_start',
            'ab_settings_new_account_role'         => 'bookly_cst_new_account_role',
            'ab_settings_pay_locally'              => 'bookly_pmt_pay_locally',
            'ab_settings_phone_default_country'    => 'bookly_cst_phone_default_country',
            'ab_settings_saturday_end'             => 'bookly_bh_saturday_end',
            'ab_settings_saturday_start'           => 'bookly_bh_saturday_start',
            'ab_settings_sender_email'             => 'bookly_email_sender',
            'ab_settings_sender_name'              => 'bookly_email_sender_name',
            'ab_settings_step_cart_enabled'        => 'bookly_cart_enabled',
            'ab_settings_sunday_end'               => 'bookly_bh_sunday_end',
            'ab_settings_sunday_start'             => 'bookly_bh_sunday_start',
            'ab_settings_thursday_end'             => 'bookly_bh_thursday_end',
            'ab_settings_thursday_start'           => 'bookly_bh_thursday_start',
            'ab_settings_time_slot_length'         => 'bookly_gen_time_slot_length',
            'ab_settings_tuesday_end'              => 'bookly_bh_tuesday_end',
            'ab_settings_tuesday_start'            => 'bookly_bh_tuesday_start',
            'ab_settings_use_client_time_zone'     => 'bookly_gen_use_client_time_zone',
            'ab_settings_wednesday_end'            => 'bookly_bh_wednesday_end',
            'ab_settings_wednesday_start'          => 'bookly_bh_wednesday_start',
            'ab_sms_administrator_phone'           => 'bookly_sms_administrator_phone',
            'ab_sms_default_country_code'          => 'bookly_cst_default_country_code',
            'ab_sms_notify_low_balance'            => 'bookly_sms_notify_low_balance',
            'ab_sms_notify_weekly_summary'         => 'bookly_sms_notify_weekly_summary',
            'ab_sms_notify_weekly_summary_sent'    => 'bookly_sms_notify_weekly_summary_sent',
            'ab_sms_token'                         => 'bookly_sms_token',
            'ab_stripe_publishable_key'            => 'bookly_pmt_stripe_publishable_key',
            'ab_stripe_secret_key'                 => 'bookly_pmt_stripe_secret_key',
            'ab_woocommerce_enabled'               => 'bookly_wc_enabled',
            'ab_woocommerce_product'               => 'bookly_wc_product',
            'bookly_payment_2checkout'             => 'bookly_pmt_2checkout',
            'bookly_payment_authorizenet'          => 'bookly_pmt_authorizenet',
            'bookly_payment_mollie'                => 'bookly_pmt_mollie',
            'bookly_payment_paypal'                => 'bookly_pmt_paypal',
            'bookly_payment_payson'                => 'bookly_pmt_payson',
            'bookly_payment_payulatam'             => 'bookly_pmt_payu_latam',
            'bookly_payment_stripe'                => 'bookly_pmt_stripe',
        );
        $this->renameOptions( $options );
        $appearance = array(
            'ab_appearance_text_button_apply'      => 'bookly_l10n_button_apply',
            'ab_appearance_text_button_back'       => 'bookly_l10n_button_back',
            'ab_appearance_text_button_book_more'  => 'bookly_l10n_button_book_more',
            'ab_appearance_text_button_next'       => 'bookly_l10n_button_next',
            'ab_appearance_text_info_cart_step'    => 'bookly_l10n_info_cart_step',
            'ab_appearance_text_info_complete_step' => 'bookly_l10n_info_complete_step',
            'ab_appearance_text_info_coupon'       => 'bookly_l10n_info_coupon',
            'ab_appearance_text_info_details_step' => 'bookly_l10n_info_details_step',
            'ab_appearance_text_info_details_step_guest' => 'bookly_l10n_info_details_step_guest',
            'ab_appearance_text_info_payment_step' => 'bookly_l10n_info_payment_step',
            'ab_appearance_text_info_service_step' => 'bookly_l10n_info_service_step',
            'ab_appearance_text_info_time_step'    => 'bookly_l10n_info_time_step',
            'ab_appearance_text_label_category'    => 'bookly_l10n_label_category',
            'ab_appearance_text_label_ccard_code'  => 'bookly_l10n_label_ccard_code',
            'ab_appearance_text_label_ccard_expire'=> 'bookly_l10n_label_ccard_expire',
            'ab_appearance_text_label_ccard_number'=> 'bookly_l10n_label_ccard_number',
            'ab_appearance_text_label_coupon'      => 'bookly_l10n_label_coupon',
            'ab_appearance_text_label_email'       => 'bookly_l10n_label_email',
            'ab_appearance_text_label_employee'    => 'bookly_l10n_label_employee',
            'ab_appearance_text_label_finish_by'   => 'bookly_l10n_label_finish_by',
            'ab_appearance_text_label_name'        => 'bookly_l10n_label_name',
            'ab_appearance_text_label_number_of_persons' => 'bookly_l10n_label_number_of_persons',
            'ab_appearance_text_label_pay_ccard'   => 'bookly_l10n_label_pay_ccard',
            'ab_appearance_text_label_pay_locally' => 'bookly_l10n_label_pay_locally',
            'ab_appearance_text_label_pay_mollie'  => 'bookly_l10n_label_pay_mollie',
            'ab_appearance_text_label_pay_paypal'  => 'bookly_l10n_label_pay_paypal',
            'ab_appearance_text_label_phone'       => 'bookly_l10n_label_phone',
            'ab_appearance_text_label_select_date' => 'bookly_l10n_label_select_date',
            'ab_appearance_text_label_service'     => 'bookly_l10n_label_service',
            'ab_appearance_text_label_start_from'  => 'bookly_l10n_label_start_from',
            'ab_appearance_text_option_category'   => 'bookly_l10n_option_category',
            'ab_appearance_text_option_employee'   => 'bookly_l10n_option_employee',
            'ab_appearance_text_option_service'    => 'bookly_l10n_option_service',
            'ab_appearance_text_required_email'    => 'bookly_l10n_required_email',
            'ab_appearance_text_required_employee' => 'bookly_l10n_required_employee',
            'ab_appearance_text_required_name'     => 'bookly_l10n_required_name',
            'ab_appearance_text_required_phone'    => 'bookly_l10n_required_phone',
            'ab_appearance_text_required_service'  => 'bookly_l10n_required_service',
            'ab_appearance_text_step_cart'         => 'bookly_l10n_step_cart',
            'ab_appearance_text_step_details'      => 'bookly_l10n_step_details',
            'ab_appearance_text_step_done'         => 'bookly_l10n_step_done',
            'ab_appearance_text_step_payment'      => 'bookly_l10n_step_payment',
            'ab_appearance_text_step_service'      => 'bookly_l10n_step_service',
            'ab_appearance_text_step_time'         => 'bookly_l10n_step_time',
            'ab_woocommerce_cart_info_name'        => 'bookly_l10n_wc_cart_info_name',
            'ab_woocommerce_cart_info_value'       => 'bookly_l10n_wc_cart_info_value',
        );
        $this->renameL10nStrings( $appearance );
        update_option( 'bookly_pmt_paypal_sandbox', ( get_option( 'bookly_pmt_paypal_sandbox' ) == '.sandbox' ) ? '1' : '0' );
        foreach ( get_users( array( 'role' => 'administrator' ) ) as $admin ) {
            add_user_meta( $admin->ID, 'bookly_dismiss_admin_notice', get_user_meta( $admin->ID, 'ab_dismiss_admin_notice' ) );
            delete_user_meta( $admin->ID, 'ab_dismiss_admin_notice' );
        }
        $wpdb->query( sprintf(
            'ALTER TABLE `%s` CHANGE COLUMN `type` `type` ENUM("local","coupon","paypal","authorizeNet","stripe","2checkout","payulatam","payson","mollie","woocommerce","payu_latam") NOT NULL DEFAULT "local"',
            $this->getTableName( 'ab_payments' )
        ) );
        $wpdb->query( sprintf(
            'UPDATE `%s` SET `type` = "payu_latam" WHERE `type` = "payulatam"',
            $this->getTableName( 'ab_payments' )
        ) );
        $wpdb->query( sprintf(
            'ALTER TABLE `%s` CHANGE COLUMN `type` `type` ENUM("local","coupon","paypal","authorizeNet","stripe","2checkout","payu_latam","payson","mollie","woocommerce") NOT NULL DEFAULT "local"',
            $this->getTableName( 'ab_payments' )
        ) );
        add_option( 'bookly_gen_service_duration_as_slot_length', '0' );
        add_option( 'bookly_gen_show_subscribe_notice', '1' );
    }

    function update_11_5()
    {
        add_option( 'ab_settings_new_account_role', 'subscriber' );
        update_option( 'ab_sms_notify_weekly_summary', '1' );
        $options = array(
            'ab_2checkout'         => 'bookly_payment_2checkout',
            'ab_authorizenet_type' => 'bookly_payment_authorizenet',
            'ab_mollie'            => 'bookly_payment_mollie',
            'ab_paypal_type'       => 'bookly_payment_paypal',
            'ab_payson'            => 'bookly_payment_payson',
            'ab_payulatam'         => 'bookly_payment_payulatam',
            'ab_stripe'            => 'bookly_payment_stripe',
        );
        $this->renameOptions( $options );
    }

    function update_11_4()
    {
        global $wpdb;

        $options = array(
            'ab_sms_notify_week_summary'      => 'ab_sms_notify_weekly_summary',
            'ab_sms_notify_week_summary_sent' => 'ab_sms_notify_weekly_summary_sent',
        );
        $this->renameOptions( $options );
        $wpdb->query( sprintf(
            'ALTER TABLE `%s` CHANGE COLUMN `type` `type` ENUM("local","coupon","paypal","authorizeNet","stripe","2checkout","payulatam","payson","mollie","woocommerce") NOT NULL DEFAULT "local"',
             $this->getTableName( 'ab_payments' )
        ) );
    }

    function update_11_1()
    {
        add_option( 'ab_sms_notify_week_summary', '0' );
        add_option( 'ab_sms_notify_week_summary_sent', date( 'W' ) );

        delete_option( 'ab_sms_username' );
        delete_option( 'ab_sms_auto_recharge_balance' );
        delete_option( 'ab_sms_auto_recharge_amount' );

        do_action( 'wpml_register_single_string', 'bookly', 'ab_woocommerce_cart_info_name',  get_option( 'ab_woocommerce_cart_info_name' ) );
        do_action( 'wpml_register_single_string', 'bookly', 'ab_woocommerce_cart_info_value', get_option( 'ab_woocommerce_cart_info_value' ) );
    }

    function update_11_0()
    {
        global $wpdb;

        $wpdb->query( sprintf(
            'ALTER TABLE `%s` ADD COLUMN `paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `total`',
            $this->getTableName( 'ab_payments' )
        ) );
        $wpdb->query( sprintf( 'UPDATE `%s` SET `paid` = `total`', $this->getTableName( 'ab_payments' ) ) );

        $option = get_option( 'ab_cart_show_columns' );
        $option['deposit'] = array( 'show' => 1 );
        update_option( 'ab_cart_show_columns', $option );
    }

    function update_10_9()
    {
        global $wpdb;

        add_option( 'ab_appearance_staff_name_with_price', (int) ! Config::paymentStepDisabled() );
        $wpdb->query( sprintf(
            'ALTER TABLE `%s` ADD COLUMN `location_id` INT UNSIGNED NULL DEFAULT NULL AFTER `appointment_id`',
            $this->getTableName( 'ab_customer_appointments' )
        ) );
        $wpdb->query(
            'CREATE TABLE IF NOT EXISTS `' . $this->getTableName( 'ab_coupon_services' ) . '` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `coupon_id`   INT UNSIGNED NOT NULL,
                `service_id`  INT UNSIGNED NOT NULL,
                CONSTRAINT
                    FOREIGN KEY (coupon_id)
                    REFERENCES  ' . $this->getTableName( 'ab_coupons' ) . '(id)
                    ON DELETE   CASCADE
                    ON UPDATE   CASCADE,
                CONSTRAINT
                    FOREIGN KEY (service_id)
                    REFERENCES  ' . $this->getTableName( 'ab_services' ) . '(id)
                    ON DELETE   CASCADE
                    ON UPDATE   CASCADE
            ) ENGINE = INNODB
            DEFAULT CHARACTER SET = utf8
            COLLATE = utf8_general_ci'
        );

        $coupons = $wpdb->get_results( sprintf( 'SELECT `id` FROM `%s`', $this->getTableName( 'ab_coupons' ) ) );
        if ( ! empty ( $coupons ) ) {
            $services = $wpdb->get_results( sprintf( 'SELECT `id` FROM `%s`', $this->getTableName( 'ab_services' ) ) );
            if ( ! empty ( $services ) ) {
                foreach ( $coupons as $coupon ) {
                    foreach ( $services as $service ) {
                        $wpdb->insert(
                            $this->getTableName( 'ab_coupon_services' ),
                            array( 'coupon_id' => $coupon->id, 'service_id' => $service->id ),
                            '%d'
                        );
                    }
                }
            }
        }
    }

    function update_10_0()
    {
        global $wpdb;
        global $wp_rewrite;

        $wpdb->query( sprintf(
            'ALTER TABLE `%s` ADD COLUMN `deposit` VARCHAR(100) NOT NULL DEFAULT "100%%" AFTER `price`',
            $this->getTableName( 'ab_staff_services' )
        ) );
        if ( get_option( 'bookly_service_extras_step_extras_enabled', 'missing' ) != 'missing' ) {
            $this->renameOptions( array( 'bookly_service_extras_step_extras_enabled' => 'bookly_service_extras_enabled' ) );
        }
        $wpdb->query( sprintf(
            'ALTER TABLE `%s` ADD COLUMN `attachment_id` INT UNSIGNED DEFAULT NULL AFTER `wp_user_id`',
            $this->getTableName( 'ab_staff' )
        ) );
        require_once  ABSPATH . 'wp-admin/includes/image.php';
        $support_types = array(
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'gif'  => 'image/gif',
        );
        $attachment_id = '';
        $media_path = get_option( 'ab_settings_company_logo_path' );
        if ( file_exists( $media_path ) ) {
            if ( ! isset( $wp_rewrite ) ) {
                require_once ABSPATH . WPINC . '/rewrite.php';
                $wp_rewrite = new \WP_Rewrite();
            }
            $ext = strtolower( pathinfo( $media_path, PATHINFO_EXTENSION ) );
            if ( isset( $support_types, $ext ) ) {
                $post_data = array(
                    'post_title'     => basename( $media_path ),
                    'guid'           => get_option( 'ab_settings_company_logo_url' ),
                    'post_status'    => 'publish',
                    'ping_status'    => 'closed',
                    'post_type'      => 'attachment',
                    'post_mime_type' => $support_types[ $ext ],
                );
                $attachment_id   = wp_insert_attachment( $post_data, $media_path );
                $attachment_data = wp_generate_attachment_metadata( $attachment_id, $media_path );
                wp_update_attachment_metadata( $attachment_id, $attachment_data );
            }
        }
        add_option( 'ab_settings_company_logo_attachment_id', $attachment_id );
        delete_option( 'ab_settings_company_logo_path' );
        delete_option( 'ab_settings_company_logo_url' );
        delete_option( 'ab_settings_company_logo' );

        $items = $wpdb->get_results( sprintf(
            'SELECT `id`, `avatar_url`, `avatar_path` FROM `%s` WHERE `attachment_id` IS NULL',
            $this->getTableName( 'ab_staff' )
        ) );
        foreach ( $items as $item ) {
            $media_path = $item->avatar_path;
            if ( file_exists( $media_path ) ) {
                $ext = strtolower( pathinfo( $media_path, PATHINFO_EXTENSION ) );
                if ( isset( $support_types, $ext ) ) {
                    $post_data = array(
                        'post_title'     => basename( $media_path ),
                        'guid'           => $item->avatar_url,
                        'post_status'    => 'publish',
                        'ping_status'    => 'closed',
                        'post_type'      => 'attachment',
                        'post_mime_type' => $support_types[ $ext ],
                    );
                    $attachment_id   = wp_insert_attachment( $post_data, $media_path );
                    $attachment_data = wp_generate_attachment_metadata( $attachment_id, $media_path );
                    wp_update_attachment_metadata( $attachment_id, $attachment_data );
                    $wpdb->query( sprintf(
                        'UPDATE `%s` SET `attachment_id` = %d WHERE `id` = %d',
                        $this->getTableName( 'ab_staff' ),
                        $attachment_id,
                        $item->id
                    ) );
                }
            }
        }
        $this->dropTableColumns( $this->getTableName( 'ab_staff' ), array( 'avatar_url', 'avatar_path' ) );

        $wpdb->query( sprintf( 'UPDATE `%s` SET `wp_user_id` = NULL WHERE `wp_user_id` = 0', $this->getTableName( 'ab_customers' ) ) );
    }

    function update_9_3()
    {
        global $wpdb;

        $exists = $wpdb->query( sprintf(
            'SELECT 1 FROM `%s` WHERE `type` = "client_pending_appointment_cart" LIMIT 1',
            $this->getTableName( 'ab_notifications' )
        ) );
        if ( ! $exists ) {
            $wpdb->query( sprintf(
                'ALTER TABLE `%s`
                    ADD COLUMN `payment_id` INT UNSIGNED DEFAULT NULL,
                    ADD COLUMN `compound_service_id` INT UNSIGNED DEFAULT NULL,
                    ADD COLUMN `compound_token` VARCHAR(255) DEFAULT NULL',
                $this->getTableName( 'ab_customer_appointments' )
            ) );
            $wpdb->query( sprintf(
                'ALTER TABLE `%s`
                    ADD COLUMN `type` ENUM("simple","compound") NOT NULL DEFAULT "simple",
                    ADD COLUMN `sub_services` TEXT NOT NULL',
                $this->getTableName( 'ab_services' )
            ) );
            $wpdb->query( sprintf( 'UPDATE `%s` SET `sub_services` = "[]"', $this->getTableName( 'ab_services' ) ) );
            $wpdb->query( sprintf( 'UPDATE `%s` SET `extras` = "[]" WHERE `extras` IS NULL', $this->getTableName( 'ab_customer_appointments' ) ) );
            $wpdb->query( sprintf( 'UPDATE `%s` `ca` JOIN `%s` `p` ON `p`.`customer_appointment_id` = `ca`.`id` SET `ca`.`payment_id` = `p`.`id`',
                $this->getTableName( 'ab_customer_appointments' ),
                $this->getTableName( 'ab_payments' )
            ) );
            $this->dropTableColumns( $this->getTableName( 'ab_payments' ), array( 'customer_appointment_id' ) );
            $wpdb->query( sprintf( 'ALTER TABLE `%s` ADD COLUMN `details` TEXT', $this->getTableName( 'ab_payments' ) ) );
            $wpdb->query( sprintf( 'ALTER TABLE `%s` ADD COLUMN `internal_note` TEXT', $this->getTableName( 'ab_appointments' ) ) );
            $wpdb->query( sprintf(
                'ALTER TABLE `%s` ADD CONSTRAINT FOREIGN KEY (payment_id) REFERENCES %s(id) ON DELETE SET NULL ON UPDATE CASCADE',
                $this->getTableName( 'ab_customer_appointments' ),
                $this->getTableName( 'ab_payments' )
            ) );
            $wpdb->query( sprintf( 'ALTER TABLE `%s` ADD COLUMN `visibility` ENUM("public","private") NOT NULL DEFAULT "public"', $this->getTableName( 'ab_services' )  ) );
            $wpdb->query( sprintf( 'ALTER TABLE `%s` ADD COLUMN `visibility` ENUM("public","private") NOT NULL DEFAULT "public"', $this->getTableName( 'ab_staff' ) ) );

            $notifications = array(
                array(
                    'gateway' => 'email',
                    'type'    => 'client_pending_appointment_cart',
                    'subject' => __( 'Your appointment information', 'bookly' ),
                    'message' => wpautop( __( "Dear {client_name}.\n\nThis is a confirmation that you have booked the following items:\n\n{cart_info}\n\nThank you for choosing our company.\n\n{company_name}\n{company_phone}\n{company_website}", 'bookly' ) ),
                    'active'  => 0,
                ),
                array(
                    'gateway' => 'email',
                    'type'    => 'client_approved_appointment_cart',
                    'subject' => __( 'Your appointment information', 'bookly' ),
                    'message' => wpautop( __( "Dear {client_name}.\n\nThis is a confirmation that you have booked the following items:\n\n{cart_info}\n\nThank you for choosing our company.\n\n{company_name}\n{company_phone}\n{company_website}", 'bookly' ) ),
                    'active'  => 1,
                ),

                array(
                    'gateway' => 'sms',
                    'type'    => 'client_pending_appointment_cart',
                    'subject' => '',
                    'message' => __( "Dear {client_name}.\nThis is a confirmation that you have booked the following items:\n{cart_info}\nThank you for choosing our company.\n{company_name}\n{company_phone}\n{company_website}", 'bookly' ),
                    'active'  => 0,
                ),
                array(
                    'gateway' => 'sms',
                    'type'    => 'client_approved_appointment_cart',
                    'subject' => '',
                    'message' => __( "Dear {client_name}.\nThis is a confirmation that you have booked the following items:\n{cart_info}\nThank you for choosing our company.\n{company_name}\n{company_phone}\n{company_website}", 'bookly' ),
                    'active'  => 1,
                ),
            );
            foreach ( $notifications as $data ) {
                $wpdb->insert( $this->getTableName( 'ab_notifications' ), $data );
            }
            add_option( 'ab_settings_approve_page_url', home_url() );
            add_option( 'ab_settings_cart_notifications_combined', '0' );
        }

        $details = json_encode( array( 'items' => array(), 'coupon' => null, 'customer' => null ) );
        $wpdb->query( $wpdb->prepare(
            sprintf( 'UPDATE `%s` SET `details` = %%s', $this->getTableName( 'ab_payments' )  ),
            $details
        ) );
        $this->dropTableColumns( $this->getTableName( 'ab_customer_appointments' ), array( 'coupon_code', 'coupon_discount', 'coupon_deduction' ) );
    }

    function update_9_2()
    {
        add_option( 'ab_appearance_required_employee', '0' );
        $this->addL10nOptions( array( 'ab_appearance_text_required_employee' => __( 'Please select an employee', 'bookly' ) ) );
    }

    function update_9_1()
    {
        add_option( 'ab_settings_client_cancel_appointment_action', 'delete' );
    }

    function update_9_0()
    {
        global $wpdb;

        add_option( 'ab_settings_default_appointment_status', 'approved' );
        $wpdb->query( sprintf(
            'ALTER TABLE `%s` ADD COLUMN `status` ENUM("pending","approved","cancelled") NOT NULL DEFAULT "approved"',
            $this->getTableName( 'ab_customer_appointments' )
        ) );

        $notifications = array(
            array(
                'gateway' => 'email',
                'type'    => 'client_pending_appointment',
                'subject' => __( 'Your appointment information', 'bookly' ),
                'message' => wpautop( __( "Dear {client_name}.\n\nThis is confirmation that you have booked {service_name}.\n\nWe are waiting you at {company_address} on {appointment_date} at {appointment_time}.\n\nThank you for choosing our company.\n\n{company_name}\n{company_phone}\n{company_website}", 'bookly' ) ),
                'active'  => 1,
            ),
            array(
                'gateway' => 'email',
                'type'    => 'staff_pending_appointment',
                'subject' => __( 'New booking information', 'bookly' ),
                'message' => wpautop( __( "Hello.\n\nYou have a new booking.\n\nService: {service_name}\nDate: {appointment_date}\nTime: {appointment_time}\nClient name: {client_name}\nClient phone: {client_phone}\nClient email: {client_email}", 'bookly' ) ),
                'active'  => 0,
            ),
            array(
                'gateway' => 'email',
                'type'    => 'client_cancelled_appointment',
                'subject' => __( 'Booking cancellation', 'bookly' ),
                'message' => wpautop( __( "Dear {client_name}.\n\nYou have cancelled your booking of {service_name} on {appointment_date} at {appointment_time}.\n\nThank you for choosing our company.\n\n{company_name}\n{company_phone}\n{company_website}", 'bookly' ) ),
                'active'  => 0,
            ),

            array(
                'gateway' => 'sms',
                'type'    => 'client_pending_appointment',
                'subject' => '',
                'message' => __( "Dear {client_name}.\nThis is confirmation that you have booked {service_name}.\nWe are waiting you at {company_address} on {appointment_date} at {appointment_time}.\nThank you for choosing our company.\n{company_name}\n{company_phone}\n{company_website}", 'bookly' ),
                'active'  => 1,
            ),
            array(
                'gateway' => 'sms',
                'type'    => 'staff_pending_appointment',
                'subject' => '',
                'message' => __( "Hello.\nYou have a new booking.\nService: {service_name}\nDate: {appointment_date}\nTime: {appointment_time}\nClient name: {client_name}\nClient phone: {client_phone}\nClient email: {client_email}", 'bookly' ),
                'active'  => 0,
            ),
            array(
                'gateway' => 'sms',
                'type'    => 'client_cancelled_appointment',
                'subject' => '',
                'message' => __( "Dear {client_name}.\nYou have cancelled your booking of {service_name} on {appointment_date} at {appointment_time}.\nThank you for choosing our company.\n{company_name}\n{company_phone}\n{company_website}", 'bookly' ),
                'active'  => 0,
            ),
        );
        foreach ( $notifications as $data ) {
            $wpdb->insert( $this->getTableName( 'ab_notifications' ), $data );
        }
        $notification_types = array(
            'client_new_appointment' => 'client_approved_appointment',
            'staff_new_appointment'  => 'staff_approved_appointment',
        );
        foreach ( $notification_types as $deprecated => $name ) {
            $wpdb->update( $this->getTableName( 'ab_notifications' ), array( 'type' => $name ), array( 'type' => $deprecated ) );
        }

        $l10n_strings = array(
            'email_client_new_appointment'         => 'email_client_approved_appointment',
            'email_client_new_appointment_subject' => 'email_client_approved_appointment_subject',
            'email_staff_new_appointment'          => 'email_staff_approved_appointment',
            'email_staff_new_appointment_subject'  => 'email_staff_approved_appointment_subject',
            'sms_client_new_appointment'           => 'sms_client_approved_appointment',
            'sms_staff_new_appointment'            => 'sms_staff_approved_appointment',
        );
        $this->renameL10nStrings( $l10n_strings, false );

        $ab_cart_show_columns = array(
            'service'  => array( 'show' => 0 ),
            'date'     => array( 'show' => 0 ),
            'time'     => array( 'show' => 0 ),
            'employee' => array( 'show' => 0 ),
            'price'    => array( 'show' => 0 ),
        );
        foreach ( (array) get_option( 'ab_cart_show_columns' ) as $column ) {
            $ab_cart_show_columns[ $column ]['show'] = 1;
        }
        update_option( 'ab_cart_show_columns', $ab_cart_show_columns );
    }

    function update_8_5()
    {
        global $wpdb;
        $wpdb->query( sprintf( 'ALTER TABLE `%s` ADD COLUMN `info` TEXT NULL', $this->getTableName( 'ab_services' ) ) );

        // Mollie - online payments system.
        add_option( 'ab_mollie', 'disabled' );
        add_option( 'ab_mollie_api_key', '' );
        $wpdb->query( sprintf(
            'ALTER TABLE `%s` CHANGE COLUMN `type` `type` ENUM("local","coupon","paypal","authorizeNet","stripe","2checkout","payulatam","payson","mollie") NOT NULL DEFAULT "local"',
            $this->getTableName( 'ab_payments' )
        ) );

        add_option( 'ab_settings_cron_reminder', array( 'client_follow_up' => 21, 'client_reminder' => 18, 'staff_agenda' => 18 ) );
        add_option( 'ab_cart_show_columns', array( 'service', 'date', 'time', 'employee', 'price' ) );
        $wpdb->query( sprintf( 'ALTER TABLE `%s` ADD COLUMN `extras` TEXT NULL', $this->getTableName( 'ab_customer_appointments' ) ) );
        $wpdb->query( sprintf( 'ALTER TABLE `%s` ADD COLUMN `extras_duration` INT NOT NULL DEFAULT 0', $this->getTableName( 'ab_appointments' ) ) );
        $wpdb->query( sprintf( 'ALTER TABLE `%s` DROP COLUMN `title`', $this->getTableName( 'ab_holidays' ) ) );
        $this->renameOptions( array( 'ab_settings_cart_enabled' => 'ab_settings_step_cart_enabled' ) );
        $this->addL10nOptions( array( 'ab_appearance_text_label_pay_mollie' => __( 'I will pay now with Mollie', 'bookly' ) ) );
    }

    function update_8_4()
    {
        global $wpdb;
        if ( get_option( 'ab_custom_fields_per_service', null ) === null ) {
            $ab_custom_fields = (array) json_decode( get_option( 'ab_custom_fields' ), true );
            foreach ( $ab_custom_fields as &$field ) {
                $field['services'] = array();
            }
            update_option( 'ab_custom_fields', json_encode( $ab_custom_fields ) );

            add_option( 'ab_custom_fields_per_service', '0' );
        }
        $options = array(
            'ab_appearance_text_required_service'  => __( 'Please select a service',   'bookly' ),
            'ab_appearance_text_required_name'     => __( 'Please tell us your name',  'bookly' ),
            'ab_appearance_text_required_phone'    => __( 'Please tell us your phone', 'bookly' ),
            'ab_appearance_text_required_email'    => __( 'Please tell us your email', 'bookly' ),
        );
        foreach ( $options as $option_name => $option_value ) {
            add_option( $option_name, $option_value );
            do_action( 'wpml_register_single_string', 'bookly', $option_name, $option_value );
        }

        $wpdb->query( sprintf( 'ALTER TABLE `%s` ADD COLUMN `info` TEXT NULL', $this->getTableName( 'ab_staff' ) ) );
    }

    function update_8_3()
    {
        $options = array(
            'ab_appearance_text_button_next'  => __( 'Next', 'bookly' ),
            'ab_appearance_text_button_back'  => __( 'Back', 'bookly' ),
            'ab_appearance_text_button_apply' => __( 'Apply', 'bookly' ),
            'ab_appearance_text_button_book_more' => __( 'Book More', 'bookly' ),
        );
        foreach ( $options as $option_name => $option_value ) {
            add_option( $option_name, $option_value );
            do_action( 'wpml_register_single_string', 'bookly', $option_name, $option_value );
        }
    }

    function update_8_1()
    {
        add_option( 'ab_payson_funding', array( 'CREDITCARD' ) );
        add_option( 'ab_settings_cart_enabled', '0' );
        add_option( 'ab_appearance_text_step_cart', __( 'Cart', 'bookly' ) );
        add_option( 'ab_appearance_text_info_cart_step', __( "Below you can find a list of services selected for booking.\nClick BOOK MORE if you want to add more services.", 'bookly' ) );
        do_action( 'wpml_register_single_string', 'bookly', 'ab_appearance_text_step_cart', get_option( 'ab_appearance_text_step_cart' ) );
        do_action( 'wpml_register_single_string', 'bookly', 'ab_appearance_text_info_cart_step', get_option( 'ab_appearance_text_info_cart_step' ) );
        $options = array(
            'ab_appearance_text_info_first_step'       => 'ab_appearance_text_info_service_step',
            'ab_appearance_text_info_second_step'      => 'ab_appearance_text_info_time_step',
            'ab_appearance_text_info_third_step'       => 'ab_appearance_text_info_details_step',
            'ab_appearance_text_info_third_step_guest' => 'ab_appearance_text_info_details_step_guest',
            'ab_appearance_text_info_fourth_step'      => 'ab_appearance_text_info_payment_step',
            'ab_appearance_text_info_fifth_step'       => 'ab_appearance_text_info_complete_step',
            'ab_woocommerce'                           => 'ab_woocommerce_enabled',
        );
        $this->renameOptions( $options );
        unset( $options['ab_woocommerce'] );
        $this->renameL10nStrings( $options, false );
    }

    function update_8_0()
    {
        global $wpdb;

        $wpdb->query( sprintf( 'ALTER TABLE `%s` ADD COLUMN `locale` VARCHAR(8) NULL', $this->getTableName( 'ab_customer_appointments' ) ) );

        add_option( 'ab_settings_minimum_time_prior_cancel', '0' );
        add_option( 'ab_settings_cancel_denied_page_url', home_url() );

        add_option( 'ab_sms_auto_recharge_balance', '0' );
        add_option( 'ab_sms_auto_recharge_amount', '0' );
        add_option( 'ab_sms_notify_low_balance', 1 );

        foreach ( json_decode( get_option( 'ab_custom_fields', array() ) ) as $custom_field ) {
            switch ( $custom_field->type ) {
                case 'textarea':
                case 'text-field':
                case 'captcha':
                    do_action( 'wpml_register_single_string', 'bookly', 'custom_field_' . $custom_field->id . '_' . sanitize_title( $custom_field->label ), $custom_field->label );
                    break;
                case 'checkboxes':
                case 'radio-buttons':
                case 'drop-down':
                    do_action( 'wpml_register_single_string', 'bookly', 'custom_field_' . $custom_field->id . '_' . sanitize_title( $custom_field->label ), $custom_field->label );
                    foreach ( $custom_field->items as $label ) {
                        do_action( 'wpml_register_single_string', 'bookly', 'custom_field_' . $custom_field->id . '_' . sanitize_title( $custom_field->label ) . '=' . sanitize_title( $label ), $label );
                    }
                    break;
            }
        }
    }

    function update_7_8_2()
    {
        global $wpdb;

        $wpdb->query( sprintf(
            'UPDATE `%s` SET `custom_fields` = "[]" WHERE `custom_fields` IS NULL OR `custom_fields` = ""',
            $this->getTableName( 'ab_customer_appointments' )
        ) );
    }

    function update_7_8()
    {
        global $wpdb;
        $wpdb->query( sprintf(
            'ALTER TABLE `%s` ADD COLUMN `status` ENUM("completed","pending") NOT NULL DEFAULT "completed"',
            $this->getTableName( 'ab_payments' )
        ) );
        $wpdb->query( sprintf(
            'ALTER TABLE `%s` CHANGE COLUMN `type` `type` ENUM("local","coupon","paypal","authorizeNet","stripe","2checkout","payulatam","payson") NOT NULL DEFAULT "local"',
            $this->getTableName( 'ab_payments' )
        ) );

        // PayU Latam - online payments system.
        add_option( 'ab_payulatam', 'disabled' );
        add_option( 'ab_payulatam_sandbox', '0' );
        add_option( 'ab_payulatam_api_account_id',  '' );
        add_option( 'ab_payulatam_api_key', '' );
        add_option( 'ab_payulatam_api_merchant_id', '' );

        // Payson - online payments system.
        add_option( 'ab_payson', 'disabled' );
        add_option( 'ab_payson_sandbox', '0' );
        add_option( 'ab_payson_fees_payer', 'PRIMARYRECEIVER' );
        add_option( 'ab_payson_api_agent_id', '' );
        add_option( 'ab_payson_api_key', '' );
        add_option( 'ab_payson_api_receiver_email', '' );
    }

    function update_7_7_2()
    {
        if ( get_option( 'ab_settings_pay_locally' ) == 0 ) {
            update_option( 'ab_settings_pay_locally', 'disabled' );
        }
    }

    function update_7_7()
    {
        global $wpdb;

        $wpdb->query( sprintf(
            'ALTER TABLE `%s` CHANGE COLUMN `type` `type` ENUM("local","coupon","paypal","authorizeNet","stripe","2checkout") NOT NULL DEFAULT "local"',
            $this->getTableName( 'ab_payments' )
        ) );
        $wpdb->query( sprintf(
            'ALTER TABLE `%s` CHANGE COLUMN `transaction` `transaction_id` VARCHAR(255) NOT NULL',
            $this->getTableName( 'ab_payments' )
        ) );

        add_option( 'ab_currency', get_option( 'ab_paypal_currency', 'USD' ) );
        add_option( 'ab_2checkout', 'disabled' );
        add_option( 'ab_2checkout_sandbox', '0' );
        add_option( 'ab_2checkout_api_seller_id', '' );
        add_option( 'ab_2checkout_api_secret_word', '' );
        add_option( 'ab_stripe_publishable_key', '' );
        if ( get_option( 'ab_stripe' ) == 0 ) {
            update_option( 'ab_stripe', 'disabled' );
        }
        delete_option( 'ab_paypal_currency' );

        add_option( 'ab_appearance_text_label_pay_paypal',   __( 'I will pay now with PayPal', 'bookly' ) );
        add_option( 'ab_appearance_text_label_pay_ccard',    __( 'I will pay now with Credit Card', 'bookly' ) );
        add_option( 'ab_appearance_text_label_ccard_number', __( 'Credit Card Number',  'bookly' ) );
        add_option( 'ab_appearance_text_label_ccard_expire', __( 'Expiration Date',     'bookly' ) );
        add_option( 'ab_appearance_text_label_ccard_code',   __( 'Card Security Code',  'bookly' ) );
        foreach ( array( 'ab_appearance_text_label_pay_paypal', 'ab_appearance_text_label_pay_ccard', 'ab_appearance_text_label_ccard_number', 'ab_appearance_text_label_ccard_expire', 'text_label_ccard_code' ) as $option_name ) {
            do_action( 'wpml_register_single_string', 'bookly', $option_name, get_option( $option_name ) );
        }
    }

    function update_7_6()
    {
        global $wpdb;

        $wpdb->query( sprintf(
            'ALTER TABLE `%s` ADD COLUMN `padding_left` INT NOT NULL DEFAULT 0, ADD COLUMN `padding_right` INT NOT NULL DEFAULT 0',
            $this->getTableName( 'ab_services' )
        ) );
    }

    function update_7_4()
    {
        add_option( 'ab_email_content_type', 'html' );
        add_option( 'ab_email_notification_reply_to_customers', '1' );
    }

    function update_7_3()
    {
        global $wpdb;

        add_option( 'ab_appearance_text_info_third_step_guest', '' );

        $staff_members = $wpdb->get_results( sprintf( 'SELECT `id`, `full_name` FROM `%s`', $this->getTableName( 'ab_staff' ) ) );
        foreach ( $staff_members as $staff ) {
            do_action( 'wpml_register_single_string', 'bookly', 'staff_' . $staff->id, $staff->full_name );
        }
        $categories = $wpdb->get_results( sprintf( 'SELECT `id`, `name` FROM `%s`', $this->getTableName( 'ab_categories' ) ) );
        foreach ( $categories as $category ) {
            do_action( 'wpml_register_single_string', 'bookly', 'category_' . $category->id, $category->name );
        }
        $services = $wpdb->get_results( sprintf( 'SELECT `id`, `title` FROM `%s`', $this->getTableName( 'ab_services' ) ) );
        foreach ( $services as $service ) {
            do_action( 'wpml_register_single_string', 'bookly', 'service_' . $service->id, $service->title );
        }
    }

    function update_7_1()
    {
        global $wpdb;

        // Register notifications for translate in WPML.
        $notifications = $wpdb->get_results( sprintf(
            'SELECT `gateway`, `type`, `subject`, `message` FROM `%s`',
            $this->getTableName( 'ab_notifications' )
        ) );
        foreach ( $notifications as $notification ) {
            do_action( 'wpml_register_single_string', 'bookly', $notification->gateway.'_'.$notification->type, $notification->message );
            if ( $notification->gateway == 'email' ) {
                do_action( 'wpml_register_single_string', 'bookly', $notification->gateway.'_'.$notification->type.'_subject', $notification->subject );
            }
        }
        $options = $wpdb->get_results( sprintf(
            'SELECT `option_value`, `option_name` FROM `%s` WHERE `option_name` LIKE "ab_appearance_text_%%"',
            $wpdb->options
        ) );
        foreach ( $options as $option ) {
            do_action( 'wpml_register_single_string', 'bookly', $option->option_name, $option->option_value );
        }

        add_option( 'ab_settings_phone_default_country', 'auto' );
    }

    function update_7_0()
    {
        global $wpdb;

        $wpdb->query( 'ALTER TABLE `ab_customer_appointment` ADD `coupon_deduction` DECIMAL(10,2) DEFAULT NULL AFTER `coupon_discount`' );
        $wpdb->query( 'ALTER TABLE `ab_coupons` CHANGE COLUMN `used` `used` INT UNSIGNED NOT NULL DEFAULT 0,
                       ADD COLUMN `deduction` DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `discount`,
                       ADD COLUMN `usage_limit` INT UNSIGNED NOT NULL DEFAULT 1' );

        $wpdb->query( 'ALTER TABLE `ab_notifications` CHANGE `slug` `type` VARCHAR(255) NOT NULL DEFAULT ""' );

        // SMS.
        $wpdb->query( 'ALTER TABLE `ab_notifications` ADD `gateway` ENUM("email","sms") NOT NULL DEFAULT "email"' );
        $wpdb->query( 'UPDATE `ab_notifications` SET `gateway` = "email"' );
        $sms_notifies = array(
            array(
                'type'    => 'client_new_appointment',
                'message' => __( "Dear {client_name}.\nThis is confirmation that you have booked {service_name}.\nWe are waiting you at {company_address} on {appointment_date} at {appointment_time}.\nThank you for choosing our company.\n{company_name}\n{company_phone}\n{company_website}", 'bookly' ),
                'active'  => 1,
            ),
            array(
                'type'    => 'staff_new_appointment',
                'message' => __( "Hello.\nYou have a new booking.\nService: {service_name}\nDate: {appointment_date}\nTime: {appointment_time}\nClient name: {client_name}\nClient phone: {client_phone}\nClient email: {client_email}", 'bookly' ),
                'active'  => 0,
            ),
            array(
                'type'    => 'client_reminder',
                'message' => __( "Dear {client_name}.\nWe would like to remind you that you have booked {service_name} tomorrow on {appointment_time}. We are waiting you at {company_address}.\nThank you for choosing our company.\n{company_name}\n{company_phone}\n{company_website}", 'bookly' ),
                'active'  => 0,
            ),
            array(
                'type'    => 'client_follow_up',
                'message' => __( "Dear {client_name}.\nThank you for choosing {company_name}. We hope you were satisfied with your {service_name}.\nThank you and we look forward to seeing you again soon.\n{company_name}\n{company_phone}\n{company_website}", 'bookly' ),
                'active'  => 0,
            ),
            array(
                'type'    => 'staff_agenda',
                'message' => __( "Hello.\nYour agenda for tomorrow is:\n{next_day_agenda}", 'bookly' ),
                'active'  => 0,
            ),
            array(
                'type'    => 'staff_cancelled_appointment',
                'message' => __( "Hello.\nThe following booking has been cancelled.\nService: {service_name}\nDate: {appointment_date}\nTime: {appointment_time}\nClient name: {client_name}\nClient phone: {client_phone}\nClient email: {client_email}", 'bookly' ),
                'active'  => 0,
            ),
            array(
                'type'    => 'client_new_wp_user',
                'message' => __( "Hello.\nAn account was created for you at {site_address}\nYour user details:\nuser: {new_username}\npassword: {new_password}\n\nThanks.", 'bookly' ),
                'active'  => 1,
            ),
        );
        // Insert notifications.
        foreach ( $sms_notifies as $data ) {
            $wpdb->insert( 'ab_notifications', array(
                'gateway' => 'sms',
                'type'    => $data['type'],
                'subject' => '',
                'message' => $data['message'],
                'active'  => $data['active'],
            ) );
        }

        // Rename notifications.
        $notifications = array(
            'client_info'        => 'client_new_appointment',
            'provider_info'      => 'staff_new_appointment',
            'evening_next_day'   => 'client_reminder',
            'evening_after'      => 'client_follow_up',
            'event_next_day'     => 'staff_agenda',
            'cancel_appointment' => 'staff_cancelled_appointment',
            'new_wp_user'        => 'client_new_wp_user',
        );
        foreach ( $notifications as $from => $to ) {
            $wpdb->query( "UPDATE `ab_notifications` SET `type` = '$to' WHERE `type` = '$from'" );
        }

        $this->drop( array( 'ab_email_notification' ) );

        // Rename tables.
        $ab_tables = array(
            'ab_appointment'          => $this->getTableName( 'ab_appointments' ),
            'ab_category'             => $this->getTableName( 'ab_categories' ),
            'ab_coupons'              => $this->getTableName( 'ab_coupons' ),
            'ab_customer'             => $this->getTableName( 'ab_customers' ),
            'ab_customer_appointment' => $this->getTableName( 'ab_customer_appointments' ),
            'ab_holiday'              => $this->getTableName( 'ab_holidays' ),
            'ab_notifications'        => $this->getTableName( 'ab_notifications' ),
            'ab_payment'              => $this->getTableName( 'ab_payments' ),
            'ab_schedule_item_break'  => $this->getTableName( 'ab_schedule_item_breaks' ),
            'ab_service'              => $this->getTableName( 'ab_services' ),
            'ab_staff'                => $this->getTableName( 'ab_staff' ),
            'ab_staff_schedule_item'  => $this->getTableName( 'ab_staff_schedule_items' ),
            'ab_staff_service'        => $this->getTableName( 'ab_staff_services' ),
        );
        foreach ( $ab_tables as $from => $to ) {
            $wpdb->query( "ALTER TABLE `{$from}` RENAME TO `{$to}`" );
        }

        $wpdb->query(
            'CREATE TABLE IF NOT EXISTS  `' . $this->getTableName( 'ab_sent_notifications' ) . '` (
                `id`                      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `customer_appointment_id` INT UNSIGNED,
                `staff_id`                INT UNSIGNED,
                `gateway`                 ENUM(\'email\',\'sms\') NOT NULL DEFAULT \'email\',
                `type`                    VARCHAR(60) NOT NULL,
                `created`                 DATETIME NOT NULL,
                CONSTRAINT
                    FOREIGN KEY (customer_appointment_id)
                    REFERENCES  ' . $this->getTableName( 'ab_customer_appointments' ) . '(id)
                    ON DELETE   CASCADE
                    ON UPDATE   CASCADE,
                CONSTRAINT
                    FOREIGN KEY (staff_id)
                    REFERENCES  ' . $this->getTableName( 'ab_staff' ) . '(id)
                    ON DELETE   CASCADE
                    ON UPDATE   CASCADE
              ) ENGINE = INNODB
              DEFAULT CHARACTER SET = utf8
              COLLATE = utf8_general_ci'
        );

        // Google Calendar.
        add_option( 'ab_settings_google_event_title', '{service_name}' );
        // Link assets.
        add_option( 'ab_settings_link_assets_method', 'enqueue' );
        // SMS.
        add_option( 'ab_sms_default_country_code', '' );
    }

    function update_6_2()
    {
        global $wpdb;

        $wpdb->query( 'ALTER TABLE `ab_holiday` CHANGE `holiday` `date` DATE NOT NULL' );
    }

    function update_6_0()
    {
        // WooCommerce.
        add_option( 'ab_woocommerce', '0' );
        add_option( 'ab_woocommerce_product', '' );
        add_option( 'ab_woocommerce_cart_info_name',  __( 'Appointment', 'bookly' ) );
        add_option( 'ab_woocommerce_cart_info_value', __( 'Date', 'bookly' ) . ": {appointment_date}\n" . __( 'Time', 'bookly' ) . ": {appointment_time}\n" . __( 'Service', 'bookly' ) . ": {service_name}" );
        // Staff Members Profile.
        add_option( 'ab_settings_allow_staff_members_edit_profile', 0 );
    }

    function update_5_0()
    {
        global $wpdb;

        // User profiles.
        add_option( 'ab_settings_create_account', 0, '', 'yes' );
        $wpdb->query( 'ALTER TABLE `ab_customer` ADD `wp_user_id` BIGINT(20) UNSIGNED' );
        // Move coupons from ab_payment to ab_customer_appointment.
        $wpdb->query( 'ALTER TABLE `ab_customer_appointment` ADD `coupon_code` VARCHAR(255) DEFAULT NULL' );
        $wpdb->query( 'ALTER TABLE `ab_customer_appointment` ADD `coupon_discount` DECIMAL(10,2) DEFAULT NULL' );
        $payments = $wpdb->get_results( 'SELECT * FROM `ab_payment`', ARRAY_A );
        foreach ( $payments as $payment ) {
            if ( $payment['coupon'] ) {
                $discount = $wpdb->get_var( $wpdb->prepare( 'SELECT `discount` FROM `ab_coupons` WHERE `code` = %s', $payment['coupon'] ) );
                $wpdb->update(
                    'ab_customer_appointment',
                    array(
                        'coupon_code' => $payment['coupon'],
                        'coupon_discount' => $discount ?: 0,
                    ),
                    array( 'id' => $payment['customer_appointment_id'] )
                );
            }
        }
        $wpdb->query('ALTER TABLE `ab_payment` DROP `coupon`');
        // New notifications.
        $wpdb->insert( 'ab_notifications', array(
            'slug'    => 'cancel_appointment',
            'subject' => __( 'Booking cancellation', 'bookly' ),
            'message' => __( "Hello.\n\nThe following booking has been cancelled.\n\nService: {service_name}\nDate: {appointment_date}\nTime: {appointment_time}\nClient name: {client_name}\nClient phone: {client_phone}\nClient email: {client_email}", 'bookly' ),
            'active'  => 0,
        ) );

        $wpdb->insert( 'ab_notifications', array(
            'slug'    => 'new_wp_user',
            'subject' => __( 'New customer', 'bookly' ),
            'message' => __( "Hello.\n\nAn account was created for you at {site_address}\n\nYour user details:\nuser: {new_username}\npassword: {new_password}\n\nThanks.", 'bookly' ),
            'active'  => 1,
        ) );
        // Link ab_email_notification to ab_customer_appointment.
        $wpdb->query( 'TRUNCATE TABLE `ab_email_notification`' );
        $wpdb->query( 'ALTER TABLE `ab_email_notification` ADD `customer_appointment_id` INT UNSIGNED' );
        $wpdb->query( 'ALTER TABLE `ab_email_notification`
            ADD CONSTRAINT fk_ab_email_notification_customer_appointment_id
              FOREIGN KEY (customer_appointment_id)
              REFERENCES  ab_customer_appointment(id)
              ON DELETE   CASCADE
              ON UPDATE   CASCADE' );
        $wpdb->query( 'ALTER TABLE `ab_email_notification` DROP FOREIGN KEY fk_ab_email_notification_customer_id, DROP INDEX ab_email_notification_customer_id_idx' );
        $wpdb->query( 'ALTER TABLE `ab_email_notification` DROP `customer_id`' );
    }

    function update_4_6()
    {
        global $wpdb;

        add_option( 'ab_appearance_text_label_number_of_persons', __( 'Number of persons', 'bookly' ), '', 'yes' );
        add_option( 'ab_settings_google_limit_events', 0, '', 'yes' );
        add_option( 'ab_appearance_show_calendar', 0, '', 'yes' );

        $wpdb->query( 'ALTER TABLE `ab_customer_appointment` ADD time_zone_offset INT' );
        $wpdb->query( 'ALTER TABLE `ab_customer_appointment` ADD number_of_persons INT UNSIGNED NOT NULL DEFAULT 1' );
    }

    function update_4_4()
    {
        add_option( 'ab_settings_maximum_available_days_for_booking', 365, '', 'yes' );
    }

    function update_4_3()
    {
        global $wpdb;

        // Positioning in lists.
        $wpdb->query( 'ALTER TABLE `ab_staff` ADD `position` INT NOT NULL DEFAULT 9999;' );
        $wpdb->query( 'ALTER TABLE `ab_category` ADD `position` INT NOT NULL DEFAULT 9999;' );
        $wpdb->query( 'ALTER TABLE `ab_service` ADD `position` INT NOT NULL DEFAULT 9999;' );

        add_option( 'ab_appearance_show_blocked_timeslots', 0, '', 'yes' );
        add_option( 'ab_appearance_show_day_one_column', 0, '', 'yes' );
    }

    function update_4_2()
    {
        global $wpdb;

        $wpdb->query( 'ALTER TABLE ab_payment ADD `customer_appointment_id` INT UNSIGNED DEFAULT NULL' );
        $payments = $wpdb->get_results( 'SELECT id, customer_id, appointment_id from `ab_payment`' );

        foreach ( $payments as $payment ) {
            $customer_appointment = $wpdb->get_row( $wpdb->prepare(
                'SELECT id from `ab_customer_appointment` WHERE `customer_id` = %d and `appointment_id` = %d LIMIT 1',
                $payment->customer_id,
                $payment->appointment_id
            ) );
            if ( $customer_appointment ) {
                $wpdb->update( 'ab_payment', array( 'customer_appointment_id' => $customer_appointment->id ), array( 'id' => $payment->id ) );
            }
        }

        $wpdb->query(
            'ALTER TABLE ab_payment
              DROP FOREIGN KEY fk_ab_payment_customer_id, DROP FOREIGN KEY fk_ab_payment_appointment_id, DROP customer_id, DROP appointment_id,
               ADD INDEX ab_payment_customer_appointment_id_idx (customer_appointment_id),
               ADD CONSTRAINT fk_ab_payment_customer_appointment_id
              FOREIGN KEY ab_payment_customer_appointment_id_idx (customer_appointment_id)
              REFERENCES  ab_customer_appointment(id)
              ON DELETE   CASCADE
              ON UPDATE   CASCADE;' );

        add_option( 'ab_appearance_text_label_pay_locally', __( 'I will pay locally', 'bookly' ), '', 'yes' );
        add_option( 'ab_settings_google_two_way_sync', 1, '', 'yes' );
    }

    function update_4_1()
    {
        add_option( 'ab_settings_final_step_url', '', '', 'yes' );
    }

    function update_4_0()
    {
        global $wpdb;

        add_option('ab_custom_fields', '[{"type":"textarea","label":"Notes","required":false,"id":1}]', '', 'yes');

        // Create relation between customer and appointment
        $ab_customer_appointments = $wpdb->get_results('SELECT * from `ab_customer_appointment` ');
        foreach ( $ab_customer_appointments as $ab_customer_appointment ) {
            $wpdb->update(
                'ab_customer_appointment',
                array( 'notes' => json_encode( array( array( 'id' => 1, 'value' => $ab_customer_appointment->notes ) ) ) ),
                array( 'id' => $ab_customer_appointment->id )
            );
        }

        $wpdb->query( 'ALTER TABLE ab_customer_appointment CHANGE `notes` `custom_fields` TEXT' );

        delete_option('ab_appearance_text_label_notes');

        $wpdb->query( 'ALTER TABLE ab_payment CHANGE `type` `type` ENUM("local","coupon","paypal","authorizeNet","stripe") NOT NULL DEFAULT "local";' );
    }

    function update_3_4()
    {
        global $wpdb;

        $wpdb->query( 'ALTER TABLE `ab_payment` DROP `status`;' );

        add_option( 'ab_settings_minimum_time_prior_booking', 0, '', 'yes' );

        delete_option( 'ab_settings_no_current_day_appointments' );
    }

    function update_3_2()
    {
        global $wpdb;

        // Google Calendar oAuth.
        $wpdb->query( 'ALTER TABLE `ab_staff` DROP `google_user`, DROP `google_PASS`;' );
        $wpdb->query( 'ALTER TABLE `ab_staff` ADD `google_data` VARCHAR(255) DEFAULT NULL, ADD `google_calendar_id` VARCHAR(255) DEFAULT NULL;' );
        $wpdb->query( 'ALTER TABLE `ab_appointment` ADD `google_event_id` VARCHAR(255) DEFAULT NULL;' );

        // Coupons
        $wpdb->query( '
            CREATE TABLE IF NOT EXISTS ab_coupons (
                id        INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                code      VARCHAR(255) NOT NULL DEFAULT "",
                discount  DECIMAL(3,0) NOT NULL DEFAULT "0",
                used      TINYINT(1) NOT NULL DEFAULT "0"
            ) ENGINE = INNODB
            DEFAULT CHARACTER SET = utf8
            COLLATE = utf8_general_ci;' );

        $wpdb->query( 'ALTER TABLE `ab_payment` ADD `coupon` VARCHAR(255) DEFAULT NULL;' );

        add_option( 'ab_appearance_text_label_coupon', __( 'Coupon', 'bookly' ), '', 'yes' );
        add_option( 'ab_appearance_text_info_coupon', __( 'The price for the service is {service_price}.', 'bookly' ), '', 'yes' );
        add_option( 'ab_settings_coupons', '0', '', 'yes' );
        add_option( 'ab_settings_google_client_id', '', '', 'yes' );
        add_option( 'ab_settings_google_client_secret', '', '', 'yes' );
    }

    function update_3_0()
    {
        global $wpdb;

        // Create new table with foreign keys
        $wpdb->query(
            'CREATE TABLE IF NOT EXISTS ab_customer_appointment (
                id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                customer_id     INT UNSIGNED NOT NULL,
                appointment_id  INT UNSIGNED NOT NULL,
                notes TEXT,
                token VARCHAR(255) DEFAULT NULL,
                INDEX ab_customer_appointment_customer_id_idx (customer_id),
                INDEX ab_customer_appointment_appointment_id_idx (appointment_id),
                CONSTRAINT fk_ab_customer_appointment_customer_id
                  FOREIGN KEY ab_customer_appointment_customer_id_idx (customer_id)
                  REFERENCES  ab_customer(id)
                  ON DELETE   CASCADE
                  ON UPDATE   CASCADE,
                CONSTRAINT fk_ab_customer_appointment_appointment_id
                  FOREIGN KEY ab_customer_appointment_appointment_id_idx (appointment_id)
                  REFERENCES  ab_appointment(id)
                  ON DELETE   CASCADE
                  ON UPDATE   CASCADE
            ) ENGINE = INNODB
            DEFAULT CHARACTER SET = utf8
            COLLATE = utf8_general_ci'
        );

        // Create relation between customer and appointment
        $appointments = $wpdb->get_results('SELECT * from `ab_appointment` ');
        foreach ($appointments as $appointment){
            $wpdb->insert('ab_customer_appointment', array(
                'customer_id'   => $appointment->customer_id,
                'appointment_id'=> $appointment->id,
                'notes'         => $appointment->notes,
                'token'         => $appointment->token,
            ));
        }

        // Refactor binding from customer to appointment (many - many)
        $wpdb->query( 'ALTER TABLE ab_appointment DROP FOREIGN KEY fk_ab_appointment_customer_id;' );
        $wpdb->query( 'ALTER TABLE ab_appointment DROP customer_id, DROP notes, DROP token;' );

        // Add Service and Staff capacity
        $wpdb->query( 'ALTER TABLE ab_service ADD capacity INT NOT NULL DEFAULT "1"' );
        $wpdb->query( 'ALTER TABLE ab_staff_service ADD capacity INT NOT NULL DEFAULT "1"' );

        // Delete table ab_payment_appointment
        $wpdb->query( 'ALTER TABLE ab_payment ADD appointment_id INT UNSIGNED DEFAULT NULL' );

        $payments_appointment = $wpdb->get_results( 'SELECT * from ab_payment_appointment' );
        foreach ( $payments_appointment as $payment_appointment ) {
            $wpdb->update( 'ab_payment', array( 'appointment_id' => $payment_appointment->appointment_id ), array( 'id' => $payment_appointment->payment_id ) );
        }

        $wpdb->query( 'DROP TABLE ab_payment_appointment' );

        $wpdb->query( '
            ALTER TABLE `ab_payment`
            ADD INDEX ab_payment_appointment_id_idx (`appointment_id`),
            ADD CONSTRAINT fk_ab_payment_appointment_id
            FOREIGN KEY ab_payment_appointment_id_idx (appointment_id)
            REFERENCES  ab_appointment(id)
            ON DELETE   SET NULL
            ON UPDATE   CASCADE;' );

        $wpdb->query( 'ALTER TABLE ab_staff_schedule_item DROP FOREIGN KEY fk_ab_staff_schedule_item_schedule_item_id' );
        $wpdb->query( 'ALTER TABLE ab_staff_schedule_item DROP INDEX ab_staff_schedule_item_unique_ids_idx' );
        $wpdb->query( 'ALTER TABLE ab_staff_schedule_item DROP INDEX ab_staff_schedule_item_schedule_item_id_idx' );
        $wpdb->query( 'DROP TABLE IF EXISTS ab_schedule_item' );

        $wpdb->query( 'ALTER TABLE ab_staff_schedule_item CHANGE COLUMN schedule_item_id day_index int(10) UNSIGNED NOT NULL AFTER staff_id' );
        $wpdb->query( 'ALTER TABLE ab_staff_schedule_item ADD UNIQUE KEY ab_staff_schedule_item_unique_ids_idx (staff_id, day_index)' );
    }

    function update_2_2_0()
    {
        global $wpdb;

        // stripe.com
        $wpdb->query( 'ALTER TABLE ab_payment CHANGE `type` `type` ENUM("local","paypal","authorizeNet","stripe") NOT NULL DEFAULT "local"' );
        add_option( 'ab_stripe', '0', '', 'yes' );
        add_option( 'ab_stripe_secret_key', '', '', 'yes' );

        // Remove old options.
        delete_option( 'ab_appearance_progress_tracker_type' );
    }

    function update_2_1_0()
    {
        global $wpdb;

        add_option( 'ab_installation_time', time() );

        // Rename some old options.
        add_option( 'ab_settings_pay_locally', get_option( 'ab_local_mode' ) );
        delete_option( 'ab_local_mode' );

        // Add Authorize.net option
        $wpdb->query( 'ALTER TABLE ab_payment CHANGE `type` `type` ENUM("local","paypal","authorizeNet") NOT NULL DEFAULT "local"' );
        add_option( 'ab_authorizenet_api_login_id',   '', '', 'yes' );
        add_option( 'ab_authorizenet_transaction_key',   '', '', 'yes' );
        add_option( 'ab_authorizenet_sandbox',  0, '', 'yes' );
        add_option( 'ab_authorizenet_type',  'disabled', '', 'yes' );
    }

    function update_2_0_1()
    {
        global $wpdb;

        // In previous migration there was a problem with adding these 2 fields. The problem has been resolved,
        // but we need to take care of users who have already run the previous migration script.
        $wpdb->query( 'ALTER TABLE `ab_staff` ADD `google_user` VARCHAR(255) DEFAULT NULL ;' );
        $wpdb->query( 'ALTER TABLE `ab_staff` ADD `google_pass` VARCHAR(255) DEFAULT NULL ;' );

        delete_option( 'ab_fixtures' );
        delete_option( 'ab_send_notifications_cron_sh_path' );
    }

    function update_2_0()
    {
        global $wpdb;

        add_option( 'ab_settings_time_slot_length', '15', '', 'yes' );
        add_option( 'ab_settings_no_current_day_appointments', '0', '', 'yes' );
        add_option( 'ab_settings_use_client_time_zone', '0', '', 'yes' );
        add_option( 'ab_settings_cancel_page_url', home_url(), '', 'yes' );

        // Add new appearance text options.
        add_option( 'ab_appearance_text_step_service', __( 'Service', 'bookly' ), '', 'yes' );
        add_option( 'ab_appearance_text_step_time', __( 'Time', 'bookly' ), '', 'yes' );
        add_option( 'ab_appearance_text_step_details', __( 'Details', 'bookly' ), '', 'yes' );
        add_option( 'ab_appearance_text_step_payment', __( 'Payment', 'bookly' ), '', 'yes' );
        add_option( 'ab_appearance_text_step_done', __( 'Done', 'bookly' ), '', 'yes' );
        add_option( 'ab_appearance_text_label_category', __( 'Category', 'bookly' ), '', 'yes' );
        add_option( 'ab_appearance_text_label_service', __( 'Service', 'bookly' ), '', 'yes' );
        add_option( 'ab_appearance_text_label_employee', __( 'Employee', 'bookly' ), '', 'yes' );
        add_option( 'ab_appearance_text_label_select_date', __( 'I\'m available on or after', 'bookly' ), '', 'yes' );
        add_option( 'ab_appearance_text_label_start_from', __( 'Start from', 'bookly' ), '', 'yes' );
        add_option( 'ab_appearance_text_label_finish_by', __( 'Finish by', 'bookly' ), '', 'yes' );
        add_option( 'ab_appearance_text_label_name', __( 'Name', 'bookly' ), '', 'yes' );
        add_option( 'ab_appearance_text_label_phone', __( 'Phone', 'bookly' ), '', 'yes' );
        add_option( 'ab_appearance_text_label_email', __( 'Email', 'bookly' ), '', 'yes' );
        add_option( 'ab_appearance_text_label_notes', __( 'Notes (optional)', 'bookly' ), '', 'yes' );
        add_option( 'ab_appearance_text_option_service', __( 'Select service', 'bookly' ), '', 'yes' );
        add_option( 'ab_appearance_text_option_category', __( 'Select category', 'bookly' ), '', 'yes' );
        add_option( 'ab_appearance_text_option_employee', __( 'Any', 'bookly' ), '', 'yes' );

        // Rename some old options.
        add_option( 'ab_appearance_color', get_option( 'ab_appearance_booking_form_color' ) );
        delete_option( 'ab_appearance_booking_form_color' );
        add_option( 'ab_appearance_text_info_first_step',  strip_tags( get_option( 'ab_appearance_first_step_booking_info' ) ) );
        delete_option( 'ab_appearance_first_step_booking_info' );
        add_option( 'ab_appearance_text_info_second_step', strip_tags( get_option( 'ab_appearance_second_step_booking_info' ) ) );
        delete_option( 'ab_appearance_second_step_booking_info' );
        add_option( 'ab_appearance_text_info_third_step',  strip_tags( get_option( 'ab_appearance_third_step_booking_info' ) ) );
        delete_option( 'ab_appearance_third_step_booking_info' );
        add_option( 'ab_appearance_text_info_fourth_step', strip_tags( get_option( 'ab_appearance_fourth_step_booking_info' ) ) );
        delete_option( 'ab_appearance_fourth_step_booking_info' );
        add_option( 'ab_appearance_text_info_fifth_step',  strip_tags( get_option( 'ab_appearance_fifth_step_booking_info' ) ) );
        delete_option( 'ab_appearance_fifth_step_booking_info' );

        $wpdb->query( 'ALTER TABLE `ab_staff` ADD `google_user` VARCHAR(255) DEFAULT NULL ;' );
        $wpdb->query( 'ALTER TABLE `ab_staff` ADD `google_pass` VARCHAR(255) DEFAULT NULL ;' );

        $wpdb->query( 'ALTER TABLE `ab_customer` ADD `notes` TEXT NOT NULL ;' );
        $wpdb->query( 'ALTER TABLE `ab_appointment` ADD `token` varchar(255) DEFAULT NULL ;' );
        $wpdb->query( 'ALTER TABLE `ab_notifications` DROP `name`;' );
    }
}