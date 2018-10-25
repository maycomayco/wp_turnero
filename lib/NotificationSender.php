<?php
namespace Bookly\Lib;

use Bookly\Lib\DataHolders\Booking as DataHolders;
use Bookly\Lib\DataHolders\Notification\Settings;
use Bookly\Lib\Entities\CustomerAppointment;
use Bookly\Lib\Entities\Notification;

/**
 * Class NotificationSender
 * @package Bookly\Lib
 */
abstract class NotificationSender
{
    /** @var SMS */
    private static $sms = null;

    /**
     * Send notifications from cart.
     *
     * @param DataHolders\Order $order
     */
    public static function sendFromCart( DataHolders\Order $order )
    {
        if ( Config::combinedNotificationsEnabled() ) {
            self::_sendCombined( $order );
        } else {
            foreach ( $order->getItems() as $item ) {
                switch ( $item->getType() ) {
                    case DataHolders\Item::TYPE_SIMPLE:
                    case DataHolders\Item::TYPE_COMPOUND:
                        self::sendSingle( $item, $order );
                        break;
                    case DataHolders\Item::TYPE_SERIES:
                        Proxy\RecurringAppointments::sendRecurring( $item, $order );
                        break;
                }
            }
        }
    }

    /**
     * Send notifications for single appointment.
     *
     * @param DataHolders\Item $item
     * @param DataHolders\Order $order
     * @param array $codes_data
     * @param bool $to_staff
     * @param bool $to_customer
     */
    public static function sendSingle(
        DataHolders\Item $item,
        DataHolders\Order $order = null,
        array $codes_data = array(),
        $to_staff = true,
        $to_customer = true
    )
    {
        $order                     = $order ?: DataHolders\Order::createFromItem( $item );
        $status                    = $item->getCA()->getStatus();
        $staff_email_notification  = $to_staff ? self::_getEmailNotification( 'staff', $status ) : false;
        $staff_sms_notification    = $to_staff ? self::_getSmsNotification( 'staff', $status ) : false;
        $client_email_notification = $to_customer ? self::_getEmailNotification( 'client', $status ) : false;
        $client_sms_notification   = $to_customer ? self::_getSmsNotification( 'client', $status ) : false;

        if ( $staff_email_notification || $staff_sms_notification || $client_email_notification || $client_sms_notification ) {
            $wp_locale      = self::_getWpLocale();
            // Set wp locale for staff,
            // reason - it was changed on front-end.
            self::_switchLocale( $wp_locale );

            // Prepare codes.
            $codes = NotificationCodes::createForOrder( $order, $item );
            if ( isset ( $codes_data['cancellation_reason'] ) ) {
                $codes->cancellation_reason = $codes_data['cancellation_reason'];
            }

            // Notify staff by email.
            if ( $staff_email_notification ) {
                self::_sendEmailToStaff( $staff_email_notification, $codes, $item->getStaff()->getEmail() );
            }
            // Notify staff by SMS.
            if ( $staff_sms_notification ) {
                self::_sendSmsToStaff( $staff_sms_notification, $codes, $item->getStaff()->getPhone() );
            }

            // Send notifications to client.
            if ( $client_email_notification || $client_sms_notification ) {
                // Client locale.
                $client_locale = $item->getCA()->getLocale() ?: $wp_locale;
                self::_switchLocale( $client_locale );
                $codes->refresh();

                // Client time zone offset.
                if ( $item->getCA()->getTimeZoneOffset() !== null ) {
                    $codes->appointment_start = self::_applyTimeZone( $codes->appointment_start, $item->getCA() );
                    $codes->appointment_end   = self::_applyTimeZone( $codes->appointment_end, $item->getCA() );
                }
                // Notify client by email.
                if ( $client_email_notification ) {
                    self::_sendEmailToClient( $client_email_notification, $codes, $order->getCustomer()->getEmail() );
                }
                // Notify client by SMS.
                if ( $client_sms_notification ) {
                    self::_sendSmsToClient( $client_sms_notification, $codes, $order->getCustomer()->getPhone() );
                }

                // Restore locale.
                self::_switchLocale( $wp_locale );
            }
        }
    }

    /**
     * Send reminder (email or SMS) to client.
     *
     * @param Entities\Notification $notification
     * @param DataHolders\Item $item
     * @return bool
     */
    public static function sendFromCronToClient( Entities\Notification $notification, DataHolders\Item $item )
    {
        $wp_locale = self::_getWpLocale();

        $order = DataHolders\Order::createFromItem( $item );

        $client_locale = $item->getCA()->getLocale() ?: $wp_locale;
        self::_switchLocale( $client_locale );

        $codes = NotificationCodes::createForOrder( $order, $item );

        // Client time zone offset.
        if ( $item->getCA()->getTimeZoneOffset() !== null ) {
            $codes->appointment_start = self::_applyTimeZone( $codes->appointment_start, $item->getCA() );
            $codes->appointment_end   = self::_applyTimeZone( $codes->appointment_end, $item->getCA() );
        }

        // Send notification to client.
        $result = $notification->getGateway() == 'email'
            ? self::_sendEmailToClient( $notification, $codes, $order->getCustomer()->getEmail() )
            : self::_sendSmsToClient( $notification, $codes, $order->getCustomer()->getPhone() );

        // Restore locale.
        self::_switchLocale( $wp_locale );

        return $result;
    }

    /**
     * Send notification to Staff.
     *
     * @param Entities\Notification $notification
     * @param DataHolders\Item $item
     * @return bool
     */
    public static function sendFromCronToStaff( Entities\Notification $notification, DataHolders\Item $item )
    {
        $order = DataHolders\Order::createFromItem( $item );

        $codes = NotificationCodes::createForOrder( $order, $item );

        // Send notification to client.
        $result = $notification->getGateway() == 'email'
            ? self::_sendEmailToStaff( $notification, $codes, $item->getStaff()->getEmail() )
            : self::_sendSmsToStaff( $notification, $codes, $item->getStaff()->getPhone() );

        return $result;
    }

    /**
     * Send notification to administrators.
     *
     * @param Entities\Notification $notification
     * @param DataHolders\Item $item
     * @return bool
     */
    public static function sendFromCronToAdmin( Entities\Notification $notification, DataHolders\Item $item )
    {
        $order = DataHolders\Order::createFromItem( $item );

        $codes = NotificationCodes::createForOrder( $order, $item );

        // Send notification to client.
        $result = $notification->getGateway() == 'email'
            ? self::_sendEmailToAdmins( $notification, $codes )
            : self::_sendSmsToAdmin( $notification, $codes );

        return $result;
    }

    /**
     * Send reminder (email or SMS) to staff.
     *
     * @param Entities\Notification $notification
     * @param NotificationCodes $codes
     * @param string $email
     * @param string $phone
     * @return bool
     */
    public static function sendStaffAgendaFromCron( Entities\Notification $notification, NotificationCodes $codes, $email, $phone )
    {
        $result = false;
        if ( $notification->getToAdmin() ) {
            $result = $notification->getGateway() == 'email'
                ? self::_sendEmailToAdmins( $notification, $codes )
                : self::_sendSmsToAdmin( $notification, $codes );
        }

        $notification->setToAdmin( false );
        if ( $notification->getToStaff() ) {
            $result =  $notification->getGateway() == 'email'
                ? self::_sendEmailToStaff( $notification, $codes, $email, false )
                : self::_sendSmsToStaff( $notification, $codes, $phone );
        }

        return $result;
    }

    /**
     * Send birthday greeting to client.
     *
     * @param Entities\Notification $notification
     * @param Entities\Customer $customer
     * @return bool
     */
    public static function sendFromCronBirthdayGreeting( Entities\Notification $notification, Entities\Customer $customer )
    {
        $codes = new NotificationCodes();
        $codes->client_email      = $customer->getEmail();
        $codes->client_name       = $customer->getFullName();
        $codes->client_first_name = $customer->getFirstName();
        $codes->client_last_name  = $customer->getLastName();
        $codes->client_phone      = $customer->getPhone();

        $result = $notification->getGateway() == 'email'
            ? self::_sendEmailToClient( $notification, $codes, $customer->getEmail() )
            : self::_sendSmsToClient( $notification, $codes, $customer->getPhone() );

        if ( $notification->getToAdmin() ) {
            $notification->getGateway() == 'email'
                ? self::_sendEmailToAdmins( $notification, $codes )
                : self::_sendSmsToAdmin( $notification, $codes );
        }

        return $result;
    }

    /**
     * Send email/sms with username and password for newly created WP user.
     *
     * @param Entities\Customer $customer
     * @param $username
     * @param $password
     */
    public static function sendNewUserCredentials( Entities\Customer $customer, $username, $password )
    {
        $codes = new NotificationCodes();
        $codes->client_email       = $customer->getEmail();
        $codes->client_name        = $customer->getFullName();
        $codes->client_first_name  = $customer->getFirstName();
        $codes->client_last_name   = $customer->getLastName();
        $codes->client_phone       = $customer->getPhone();
        $codes->new_password       = $password;
        $codes->new_username       = $username;
        $codes->site_address       = site_url();

        $to_client = new Entities\Notification();
        if ( $to_client->loadBy( array( 'type' => 'client_new_wp_user', 'gateway' => 'email', 'active' => 1 ) ) ) {
            self::_sendEmailToClient( $to_client, $codes, $customer->getEmail() );
        }
        if ( $to_client->loadBy( array( 'type' => 'client_new_wp_user', 'gateway' => 'sms', 'active' => 1 ) ) ) {
            self::_sendSmsToClient( $to_client, $codes, $customer->getPhone() );
        }
    }

    /**
     * Send test notification emails.
     *
     * @param string $to_mail
     * @param array  $notification_types
     * @param string $send_as
     */
    public static function sendTestEmailNotifications( $to_mail, array $notification_types, $send_as )
    {
        $codes = NotificationCodes::createForTest();
        $notification = new Entities\Notification();

        /**
         * @see \Bookly\Backend\Modules\Notifications\Controller::executeTestEmailNotifications
         * overwrite this setting and headers
         * in filter bookly_email_headers
         */
        $reply_to_customer = false;

        foreach ( $notification_types as $type ) {
            $notification->loadBy( array( 'type' => $type, 'gateway' => 'email' ) );

            switch ( $type ) {
                case 'client_pending_appointment':
                case 'client_approved_appointment':
                case 'client_cancelled_appointment':
                case 'client_rejected_appointment':
                case 'client_waitlisted_appointment':
                case 'client_pending_appointment_cart':
                case 'client_approved_appointment_cart':
                case 'client_birthday_greeting':
                case 'client_follow_up':
                case 'client_new_wp_user':
                case 'client_reminder':
                case 'client_reminder_1st':
                case 'client_reminder_2nd':
                case 'client_reminder_3rd':
                case Entities\Notification::TYPE_CUSTOMER_BIRTHDAY:
                    self::_sendEmailToClient( $notification, $codes, $to_mail, $send_as );
                    break;
                case 'staff_pending_appointment':
                case 'staff_approved_appointment':
                case 'staff_cancelled_appointment':
                case 'staff_rejected_appointment':
                case 'staff_waitlisted_appointment':
                case 'staff_waiting_list':
                case 'staff_agenda':
                case Entities\Notification::TYPE_STAFF_DAY_AGENDA:
                    self::_sendEmailToStaff( $notification, $codes, $to_mail, $reply_to_customer, $send_as );
                    break;
                // Recurring Appointments email notifications.
                case 'client_pending_recurring_appointment':
                case 'client_approved_recurring_appointment':
                case 'client_cancelled_recurring_appointment':
                case 'client_rejected_recurring_appointment':
                case 'client_waitlisted_recurring_appointment':
                    self::_sendEmailToClient( $notification, $codes, $to_mail, $send_as );
                    break;
                case 'staff_pending_recurring_appointment':
                case 'staff_approved_recurring_appointment':
                case 'staff_cancelled_recurring_appointment':
                case 'staff_rejected_recurring_appointment':
                case 'staff_waitlisted_recurring_appointment':
                    self::_sendEmailToStaff( $notification, $codes, $to_mail, $reply_to_customer, $send_as );
                    break;
                // Packages email notifications.
                case 'client_package_purchased':
                case 'client_package_deleted':
                    self::_sendEmailToClient( $notification, $codes, $to_mail, $send_as );
                    break;
                case 'staff_package_purchased':
                case 'staff_package_deleted':
                    self::_sendEmailToStaff( $notification, $codes, $to_mail, $reply_to_customer, $send_as );
                    break;
                // Custom email notifications.
                case Entities\Notification::TYPE_APPOINTMENT_START_TIME:
                case Entities\Notification::TYPE_LAST_CUSTOMER_APPOINTMENT:
                case Entities\Notification::TYPE_CUSTOMER_APPOINTMENT_CREATED:
                case Entities\Notification::TYPE_CUSTOMER_APPOINTMENT_STATUS_CHANGED:
                    if ( $notification->getToStaff() ) {
                        self::_sendEmailToStaff( $notification, $codes, $to_mail, $reply_to_customer, $send_as );
                    }
                    if ( $notification->getToCustomer() ) {
                        self::_sendEmailToClient( $notification, $codes, $to_mail, $send_as );
                    }
                    if ( ! $notification->getToStaff() && $notification->getToAdmin() ) {
                        self::_sendEmailToAdmins( $notification, $codes );
                    }
                    break;
            }
        }
    }

    /**
     * Send notification on customer appointment created.
     *
     * @param CustomerAppointment $ca
     */
    public static function sendOnCACreated( CustomerAppointment $ca )
    {
        /** @var Notification[] $notifications */
        $notifications = Notification::query()->where( '`type`', Notification::TYPE_CUSTOMER_APPOINTMENT_CREATED )->where( 'active', '1' )->find();
        foreach ( $notifications as $notification ) {
            $settings = new Settings( $notification );
            if ( $settings->getInstant() &&
                in_array( $settings->getStatus(), array( 'any', $ca->getStatus() ) )
            ) {
                NotificationSender::_send( $notification, array( $ca ) );
            }
        }
    }

    /**
     * Send notification on customer appointment status changed.
     *
     * @param CustomerAppointment $ca
     */
    public static function sendOnCAStatusChanged( CustomerAppointment $ca )
    {
        /** @var Notification[] $notifications */
        $notifications = Notification::query()->where( '`type`', Notification::TYPE_CUSTOMER_APPOINTMENT_STATUS_CHANGED )->where( 'active', '1' )->find();
        foreach ( $notifications as $notification ) {
            $settings = new Settings( $notification );
            if ( $settings->getInstant() &&
                in_array( $settings->getStatus(), array( 'any', $ca->getStatus() ) )
            ) {
                NotificationSender::_send( $notification, array( $ca ) );
            }
        }
    }

    /**
     * @param Notification          $notification
     * @param CustomerAppointment[] $ca_list
     */
    public static function sendCustomNotification( Notification $notification, array $ca_list )
    {
        NotificationSender::_send( $notification, $ca_list );
    }

    /**
     * Mark sent notification.
     *
     * @param Entities\Notification $notification
     * @param int                   $ref_id
     */
    public static function wasSent( Entities\Notification $notification, $ref_id )
    {
        $sent_notification = new Entities\SentNotification();
        $sent_notification
            ->setRefId( $ref_id )
            ->setNotificationId( $notification->getId() )
            ->setCreated( current_time( 'mysql' ) )
            ->save();
    }

    /******************************************************************************************************************
     * Protected methods                                                                                                *
     ******************************************************************************************************************/

    /**
     * Send email notification to client.
     *
     * @param Entities\Notification $notification
     * @param NotificationCodes $codes
     * @param string $email
     * @param string|null $send_as
     * @return bool
     */
    protected static function _sendEmailToClient( Entities\Notification $notification, NotificationCodes $codes, $email, $send_as = null )
    {
        $subject = $codes->replace( $notification->getTranslatedSubject(), 'text' );

        $message = $notification->getTranslatedMessage();

        $send_as_html = $send_as === null ? Config::sendEmailAsHtml() : $send_as == 'html';
        if ( $send_as_html ) {
            $message = wpautop( $codes->replace( $message, 'html' ) );
        } else {
            $message = $codes->replace( $message, 'text' );
        }

        $attachments = array();

        // ICS.
        if ( $notification->getAttachIcs() ) {
            $file = static::_createIcs( $codes );
            if ( $file ) {
                $attachments[] = $file;
            }
        }

        $result = wp_mail( $email, $subject, $message, Utils\Common::getEmailHeaders(), $attachments );

        // Clean up attachments.
        foreach ( $attachments as $file ) {
            unlink( $file );
        }

        return $result;
    }

    /**
     * Send email notification to staff.
     *
     * @param Entities\Notification $notification
     * @param NotificationCodes $codes
     * @param string $email
     * @param bool $reply_to_customer
     * @param string|null $send_as
     * @return bool
     */
    protected static function _sendEmailToStaff(
        Entities\Notification $notification,
        NotificationCodes $codes,
        $email,
        $reply_to_customer = null,
        $send_as = null
    )
    {
        // Subject.
        $subject = $codes->replace( $notification->getSubject(), 'text' );

        // Message.
        $message = self::_getMessageForStaff( $notification, 'staff', $grace );
        $send_as_html = $send_as === null ? Config::sendEmailAsHtml() : $send_as == 'html';
        if ( $send_as_html ) {
            $message = wpautop( $codes->replace( $message, 'html' ) );
        } else {
            $message = $codes->replace( $message, 'text' );
        }

        // Headers.
        $extra_headers = array();
        if ( $reply_to_customer === null ? get_option( 'bookly_email_reply_to_customers' ) : $reply_to_customer ) {
            // Codes can be without order.
            if ( $codes->getOrder() !== null ) {
                $customer      = $codes->getOrder()->getCustomer();
                $extra_headers = array( 'reply-to' => array( 'email' => $customer->getEmail(), 'name' => $customer->getFullName() ) );
            }
        }

        $headers = Utils\Common::getEmailHeaders( $extra_headers );

        $attachments = array();

        // ICS.
        if ( $notification->getAttachIcs() ) {
            $file = static::_createIcs( $codes );
            if ( $file ) {
                $attachments[] = $file;
            }
        }

        // Send email to staff.
        $result = wp_mail( $email, $subject, $message, $headers, $attachments );

        // Clean up attachments.
        foreach ( $attachments as $file ) {
            unlink( $file );
        }

        // Send to administrators.
        if ( $notification->getToAdmin() ) {
            self::_sendEmailToAdmins( $notification, $codes );
        }

        return $result;
    }

    /**
     * Send email notification to admin.
     *
     * @param Entities\Notification $notification
     * @param NotificationCodes $codes
     *
     * @return bool
     */
    protected static function _sendEmailToAdmins(
        Entities\Notification $notification,
        NotificationCodes $codes
    )
    {
        $admin_emails = Utils\Common::getAdminEmails();
        if ( ! empty( $admin_emails ) ) {
            // Subject.
            $subject = $codes->replace( $notification->getSubject(), 'text' );

            // Message.
            $message      = self::_getMessageForStaff( $notification, 'staff', $grace );
            $send_as_html = Config::sendEmailAsHtml() == 'html';
            if ( $send_as_html ) {
                $message = wpautop( $codes->replace( $message, 'html' ) );
            } else {
                $message = $codes->replace( $message, 'text' );
            }

            $attachments = array();

            // ICS.
            if ( $notification->getAttachIcs() ) {
                $file = static::_createIcs( $codes );
                if ( $file ) {
                    $attachments[] = $file;
                }
            }

            $result = wp_mail( $admin_emails, $subject, $message, Utils\Common::getEmailHeaders(), $attachments );

            // Clean up attachments.
            foreach ( $attachments as $file ) {
                unlink( $file );
            }

            return $result;
        }

        return true;
    }

    /**
     * Create ICS attachment.
     *
     * @param NotificationCodes $codes
     * @return bool|string
     */
    protected static function _createIcs( NotificationCodes $codes )
    {
        $ics = new ICS( $codes );

        return $ics->create();
    }

    /**
     * Send SMS notification to client.
     *
     * @param Entities\Notification $notification
     * @param NotificationCodes $codes
     * @param string $phone
     * @return bool
     */
    protected static function _sendSmsToClient( Entities\Notification $notification, NotificationCodes $codes, $phone )
    {
        $message = $codes->replace( $notification->getTranslatedMessage(), 'text' );

        if ( self::$sms === null ) {
            self::$sms = new SMS();
        }

        return self::$sms->sendSms( $phone, $message, $notification->getTypeId() );
    }

    /**
     * Send SMS notification to staff.
     *
     * @param Entities\Notification $notification
     * @param NotificationCodes $codes
     * @param string $phone
     * @return bool
     */
    protected static function _sendSmsToStaff( Entities\Notification $notification, NotificationCodes $codes, $phone )
    {
        // Message.
        $message = $codes->replace( self::_getMessageForStaff( $notification, 'staff', $grace ), 'text' );

        // Send SMS to staff.
        if ( self::$sms === null ) {
            self::$sms = new SMS();
        }

        $result = self::$sms->sendSms( $phone, $message, $notification->getTypeId() );

        // Send to administrators.
        if ( $notification->getToAdmin() ) {
            if ( $grace ) {
                $message = $codes->replace( self::_getMessageForStaff( $notification, 'admin' ), 'text' );
            }

            self::$sms->sendSms( get_option( 'bookly_sms_administrator_phone', '' ), $message, $notification->getTypeId() );
        }

        return $result;
    }

    /**
     * Send SMS notification to admin.
     *
     * @param Entities\Notification $notification
     * @param NotificationCodes $codes
     * @return bool
     */
    protected static function _sendSmsToAdmin( Entities\Notification $notification, NotificationCodes $codes )
    {
        // Message.
        $message = $codes->replace( self::_getMessageForStaff( $notification, 'staff', $grace ), 'text' );

        // Send SMS to staff.
        if ( self::$sms === null ) {
            self::$sms = new SMS();
        }

        // Send to administrators.
        if ( $grace ) {
            $message = $codes->replace( self::_getMessageForStaff( $notification, 'admin' ), 'text' );
        }

        return self::$sms->sendSms( get_option( 'bookly_sms_administrator_phone', '' ), $message, $notification->getTypeId() );
    }

    /**
     * Get email notification for given recipient and status.
     *
     * @param string $recipient
     * @param string $status
     * @param bool $is_recurring
     * @return Entities\Notification|bool
     */
    protected static function _getEmailNotification( $recipient, $status, $is_recurring = false )
    {
        $postfix = $is_recurring ? '_recurring' : '';
        return self::_getNotification( "{$recipient}_{$status}{$postfix}_appointment", 'email' );
    }

    /**
     * Get SMS notification for given recipient and appointment status.
     *
     * @param string $recipient
     * @param string $status
     * @param bool $is_recurring
     * @return Entities\Notification|bool
     */
    protected static function _getSmsNotification( $recipient, $status, $is_recurring = false )
    {
        $postfix = $is_recurring ? '_recurring' : '';
        return self::_getNotification( "{$recipient}_{$status}{$postfix}_appointment", 'sms' );
    }

    /**
     * Get combined email notification for given appointment status.
     *
     * @param string $status
     * @return Entities\Notification|bool
     */
    protected static function _getCombinedEmailNotification( $status )
    {
        return self::_getNotification( "client_{$status}_appointment_cart", 'email' );
    }

    /**
     * Get combined SMS notification for given appointment status.
     *
     * @param string $status
     * @return Entities\Notification|bool
     */
    protected static function _getCombinedSmsNotification( $status )
    {
        return self::_getNotification( "client_{$status}_appointment_cart", 'sms' );
    }

    /**
     * Get notification object.
     *
     * @param string $type
     * @param string $gateway
     * @return Entities\Notification|bool
     */
    protected static function _getNotification( $type, $gateway )
    {
        $notification = new Entities\Notification();
        if ( $notification->loadBy( array(
            'type'    => $type,
            'gateway' => $gateway,
            'active'  => 1
        ) ) ) {
            return $notification;
        }

        return false;
    }

    /**
     * @param Entities\Notification $notification
     * @param string                $recipient
     * @param bool                  $grace
     * @return string
     */
    protected static function _getMessageForStaff( Entities\Notification $notification, $recipient, &$grace = null )
    {
        $states = Config::getPluginVerificationStates();

        $grace = true;

        if ( $states['bookly'] == 'expired' ) {
            if ( $recipient == 'staff' ) {
                return $notification->getGateway() == 'email'
                    ? __( 'A new appointment has been created. To view the details of this appointment, please contact your website administrator in order to verify Bookly license.', 'bookly' )
                    : __( 'You have a new appointment. To view it, contact your admin to verify Bookly license.', 'bookly' );
            } else {
                return $notification->getGateway() == 'email'
                    ? __( 'A new appointment has been created. To view the details of this appointment, please verify Bookly license in the administrative panel.', 'bookly' )
                    : __( 'You have a new appointment. To view it, please verify Bookly license.', 'bookly' );
            }
        } elseif ( ! empty ( $states['grace_remaining_days'] ) ) {
            $days_text = sprintf( _n( '%d day', '%d days', $states['grace_remaining_days'], 'bookly' ), $states['grace_remaining_days'] );
            $replace   = array( '{days}' => $days_text );
            if ( $states['bookly'] == 'in_grace' ) {
                if ( $recipient == 'staff' ) {
                    return $notification->getMessage() . PHP_EOL . ( $notification->getGateway() == 'email'
                        ? strtr( __( 'Please contact your website administrator to verify Bookly license. If you do not verify the license within {days}, access to your bookings will be disabled.', 'bookly' ), $replace )
                        : strtr( __( 'Contact your admin to verify Bookly license; {days} remaining.', 'bookly' ), $replace ) );
                } else {
                    return $notification->getMessage() . PHP_EOL . ( $notification->getGateway() == 'email'
                        ? strtr( __( 'Please verify Bookly license in the administrative panel. If you do not verify the license within {days}, access to your bookings will be disabled.', 'bookly' ), $replace )
                        : strtr( __( 'Please verify Bookly license; {days} remaining.', 'bookly' ), $replace ) );
                }
            } else {
                if ( $recipient == 'staff' ) {
                    return $notification->getMessage() . PHP_EOL . ( $notification->getGateway() == 'email'
                        ? strtr( __( 'Please contact your website administrator in order to verify the license for Bookly add-ons. If you do not verify the license within {days}, the respective add-ons will be disabled.', 'bookly' ), $replace )
                        : strtr( __( 'Contact your admin to verify Bookly add-ons license; {days} remaining.', 'bookly' ), $replace ) );
                } else {
                    return $notification->getMessage() . PHP_EOL . ( $notification->getGateway() == 'email'
                        ? strtr( __( 'Please verify the license for Bookly add-ons in the administrative panel. If you do not verify the license within {days}, the respective add-ons will be disabled.', 'bookly' ), $replace )
                        : strtr( __( 'Please verify Bookly add-ons license; {days} remaining.', 'bookly' ), $replace ) );
                }
            }
        }

        $grace = false;

        return $notification->getMessage();
    }

    /**
     * Switch WordPress and WPML locale
     *
     * @param $locale
     */
    protected static function _switchLocale( $locale )
    {
        global $sitepress;

        if ( $sitepress instanceof \SitePress ) {
            if ( $locale != $sitepress->get_current_language() ) {
                $sitepress->switch_lang( $locale );
                // WPML Multilingual CMS 3.9.2 // 2018-02
                // Does not overload the date translation
                $GLOBALS['wp_locale'] = new \WP_Locale();
            }
        }
    }

    /**
     * Get default locale.
     *
     * @return string
     */
    public static function _getWpLocale()
    {
        global $sitepress;

        return $sitepress instanceof \SitePress ? $sitepress->get_default_language() : null;
    }

    /**
     * Apply client time zone to given datetime string in WP time zone.
     *
     * @param string $datetime
     * @param Entities\CustomerAppointment $ca
     * @return false|string
     */
    protected static function _applyTimeZone( $datetime, Entities\CustomerAppointment $ca )
    {
        $time_zone        = $ca->getTimeZone();
        $time_zone_offset = $ca->getTimeZoneOffset();

        if ( $time_zone !== null ) {
            $datetime = date_create( $datetime . ' ' . Config::getWPTimeZone() );
            return date_format( date_timestamp_set( date_create( $time_zone ), $datetime->getTimestamp() ), 'Y-m-d H:i:s' );
        } elseif ( $time_zone_offset !== null ) {
            return Utils\DateTime::applyTimeZoneOffset( $datetime, $time_zone_offset );
        }

        return $datetime;
    }

    /**
     * @param Notification          $notification
     * @param CustomerAppointment[] $ca_list
     */
    private static function _send( Notification $notification, array $ca_list )
    {
        $compounds = array();
        foreach ( $ca_list as $ca ) {
            if ( $ca->getCompoundToken() ) {
                if ( ! isset ( $compounds[ $ca->getCompoundToken() ] ) ) {
                    $compounds[ $ca->getCompoundToken() ] = DataHolders\Compound::create(
                        Entities\Service::find( $ca->getCompoundToken() )
                    );
                }
                $compounds[ $ca->getCompoundToken() ]->addItem( DataHolders\Simple::create( $ca ) );
            } else {
                $marked_as_sent = false;

                $simple = DataHolders\Simple::create( $ca );
                if ( $notification->getToCustomer() && NotificationSender::sendFromCronToClient( $notification, $simple ) ) {
                    NotificationSender::wasSent( $notification, $ca->getId() );
                    $marked_as_sent = true;
                }

                if ( $notification->getToStaff() &&
                    ( $notification->getGateway() == 'email' && $simple->getStaff()->getEmail() != ''
                        || $notification->getGateway() == 'sms' && $simple->getStaff()->getPhone() != '' )
                ) {
                    if ( NotificationSender::sendFromCronToStaff( $notification, $simple ) && ! $marked_as_sent ) {
                        NotificationSender::wasSent( $notification, $ca->getId() );
                        $marked_as_sent = true;
                    }
                }
                if ( $notification->getToStaff() != 1 && $notification->getToAdmin() ) {
                    if ( NotificationSender::sendFromCronToAdmin( $notification, $simple ) && ! $marked_as_sent ) {
                        NotificationSender::wasSent( $notification, $ca->getId() );
                    }
                }
            }
        }

        foreach ( $compounds as $compound ) {
            if ( NotificationSender::sendFromCronToClient( $notification, $compound ) ) {
                /** @var DataHolders\Simple $item */
                foreach ( $compound->getItems() as $item ) {
                    NotificationSender::wasSent( $notification, $item->getCA()->getId() );
                }
            }
        }
    }

    /**
     * Send combined notifications.
     *
     * @param DataHolders\Order $order
     */
    protected static function _sendCombined( DataHolders\Order $order )
    {
        $wp_locale = self::_getWpLocale();
        $cart_info = array();
        $total     = 0.0;
        $items     = $order->getItems();
        $status    = get_option( 'bookly_gen_default_appointment_status' );

        $client_email_notification = self::_getCombinedEmailNotification( $status );
        $client_sms_notification   = self::_getCombinedSmsNotification( $status );

        $client_notify = ( $client_email_notification || $client_sms_notification );

        if ( $client_notify ) {
            // For recurring appointments,
            // key in array is unique_serial_id
            $first = reset( $items );
            if ( $first->isSeries() ) {
                /** @var $first DataHolders\Series */
                $sub_items  = $first->getItems();
                $first_item = $sub_items[0];
            } else {
                $first_item = $first;
            }

            $client_locale = $first_item->getCA()->getLocale() ?: $wp_locale;
        } else {
            $client_locale = $wp_locale;
        }

        foreach ( $items as $item ) {
            $sub_items = array();

            // Send notification to staff.
            switch ( $item->getType() ) {
                case DataHolders\Item::TYPE_SIMPLE:
                case DataHolders\Item::TYPE_COMPOUND:
                    self::sendSingle( $item, $order, array(), true, false );
                    $sub_items[] = $item;
                    break;
                case DataHolders\Item::TYPE_SERIES:
                    /** @var DataHolders\Series $item */
                    Proxy\RecurringAppointments::sendRecurring( $item, $order, array(), true, false );
                    $sub_items = $item->getItems();
                    if ( get_option( 'bookly_recurring_appointments_payment' ) == 'first' ) {
                        array_splice( $sub_items, 1 );
                    }
                    break;
            }
            if ( $client_notify ) {
                foreach ( $sub_items as $sub_item ) {
                    // Sub-item price.
                    $price = $sub_item->getTotalPrice();

                    // Prepare data for {cart_info} || {cart_info_c}.
                    $cart_info[] = array(
                        'appointment_price' => $price,
                        'appointment_start' => self::_applyTimeZone( $sub_item->getAppointment()->getStartDate(), $sub_item->getCA() ),
                        'cancel_url'        => admin_url( 'admin-ajax.php?action=bookly_cancel_appointment&token=' . $sub_item->getCA()->getToken() ),
                        'service_name'      => $sub_item->getService()->getTranslatedTitle( $client_locale ),
                        'staff_name'        => $sub_item->getStaff()->getTranslatedName( $client_locale ),
                        'extras'            => (array) Proxy\ServiceExtras::getInfo( json_decode( $sub_item->getCA()->getExtras(), true ), true, $client_locale ),
                        'appointment_start_info' => $sub_item->getService()->getDuration() < DAY_IN_SECONDS ? null : $sub_item->getService()->getStartTimeInfo(),
                    );

                    // Total price.
                    $total += $price;
                }
            }
        }

        // Send combined notifications to client.
        if ( $client_notify ) {
            self::_switchLocale( $client_locale );
            // Prepare codes.
            $codes = NotificationCodes::createForOrder( $order, $first_item );
            $codes->cart_info = $cart_info;
            if ( ! $order->hasPayment() ) {
                $codes->total_price = $total;
            }

            if ( $client_email_notification ) {
                self::_sendEmailToClient( $client_email_notification, $codes, $order->getCustomer()->getEmail() );
            }
            if ( $client_sms_notification ) {
                self::_sendSmsToClient( $client_sms_notification, $codes, $order->getCustomer()->getPhone() );
            }

            // Restore location.
            self::_switchLocale( $wp_locale );
        }
    }
}