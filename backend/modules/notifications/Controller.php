<?php
namespace Bookly\Backend\Modules\Notifications;

use Bookly\Lib;

/**
 * Class Controller
 * @package Bookly\Backend\Modules\Notifications
 */
class Controller extends Lib\Base\Controller
{
    const page_slug = 'bookly-notifications';

    public function index()
    {
        $this->enqueueStyles( array(
            'frontend' => array( 'css/ladda.min.css' ),
            'backend'  => array( 'bootstrap/css/bootstrap-theme.min.css', ),
        ) );

        $this->enqueueScripts( array(
            'backend'  => array(
                'bootstrap/js/bootstrap.min.js' => array( 'jquery' ),
                'js/angular.min.js',
                'js/help.js'  => array( 'jquery' ),
                'js/alert.js' => array( 'jquery' ),
            ),
            'module'   => array(
                'js/notification.js' => array( 'jquery' ),
                'js/ng-app.js' => array( 'jquery', 'bookly-angular.min.js' ),
            ),
            'frontend' => array(
                'js/spin.min.js'  => array( 'jquery' ),
                'js/ladda.min.js' => array( 'jquery' ),
            )
        ) );
        $cron_reminder = (array) get_option( 'bookly_cron_reminder_times' );
        $form  = new Forms\Notifications( 'email' );
        $alert = array( 'success' => array() );
        $current_notification_id = null;
        // Save action.
        if ( ! empty ( $_POST ) ) {
            if ( $this->csrfTokenValid() ) {
                $form->bind( $this->getPostParameters() );
                $form->save();
                $alert['success'][] = __( 'Settings saved.', 'bookly' );
                update_option( 'bookly_email_send_as', $this->getParameter( 'bookly_email_send_as' ) );
                update_option( 'bookly_email_reply_to_customers', $this->getParameter( 'bookly_email_reply_to_customers' ) );
                update_option( 'bookly_email_sender', $this->getParameter( 'bookly_email_sender' ) );
                update_option( 'bookly_email_sender_name', $this->getParameter( 'bookly_email_sender_name' ) );
                update_option( 'bookly_ntf_processing_interval', (int) $this->getParameter( 'bookly_ntf_processing_interval' ) );
                foreach ( array( 'staff_agenda', 'client_follow_up', 'client_reminder', 'client_birthday_greeting' ) as $type ) {
                    $cron_reminder[ $type ] = $this->getParameter( $type . '_cron_hour' );
                }
                foreach ( array( 'client_reminder_1st', 'client_reminder_2nd', 'client_reminder_3rd', ) as $type ) {
                    $cron_reminder[ $type ] = $this->getParameter( $type . '_cron_before_hour' );
                }
                update_option( 'bookly_cron_reminder_times', $cron_reminder );
                $current_notification_id = $this->getParameter( 'new_notification_id' );
            }
        }
        $cron_uri = plugins_url( 'lib/utils/send_notifications_cron.php', Lib\Plugin::getMainFile() );
        wp_localize_script( 'bookly-alert.js', 'BooklyL10n',  array(
            'csrf_token'   => Lib\Utils\Common::getCsrfToken(),
            'are_you_sure' => __( 'Are you sure?', 'bookly' ),
            'alert'        => $alert,
            'current_notification_id' => $current_notification_id,
            'sent_successfully'       => __( 'Sent successfully.', 'bookly' ),
        ) );
        $statuses = Lib\Entities\CustomerAppointment::getStatuses();
        foreach ( range( 1, 23 ) as $hours ) {
            $bookly_ntf_processing_interval_values[] = array( $hours, Lib\Utils\DateTime::secondsToInterval( $hours * HOUR_IN_SECONDS ) );
        }
        $this->render( 'index', compact( 'form', 'cron_uri', 'cron_reminder', 'statuses', 'bookly_ntf_processing_interval_values' ) );
    }

    public function executeGetEmailNotificationsData()
    {
        $form = new Forms\Notifications( 'email' );

        $bookly_email_sender_name  = get_option( 'bookly_email_sender_name' ) == '' ?
            get_option( 'blogname' )    : get_option( 'bookly_email_sender_name' );

        $bookly_email_sender = get_option( 'bookly_email_sender' ) == '' ?
            get_option( 'admin_email' ) : get_option( 'bookly_email_sender' );

        $notifications = array();
        foreach ( $form->getData() as $notification ) {
            $name = Lib\Entities\Notification::getName( $notification['type'] );
            if ( in_array( $notification['type'], Lib\Entities\Notification::getCustomNotificationTypes() ) && $notification['subject'] != '' ) {
                // In window Test Email Notification
                // for custom notification, subject is name.
                $name = $notification['subject'];
            }
            $notifications[] = array(
                'type'   => $notification['type'],
                'name'   => $name,
                'active' => $notification['active'],
            );
        }

        $result = array(
            'notifications' => $notifications,
            'sender_email'  => $bookly_email_sender,
            'sender_name'   => $bookly_email_sender_name,
            'send_as'       => get_option( 'bookly_email_send_as' ),
            'reply_to_customers' => get_option( 'bookly_email_reply_to_customers' ),
        );

        wp_send_json_success( $result );
    }

    public function executeTestEmailNotifications()
    {
        $to_email      = $this->getParameter( 'to_email' );
        $sender_name   = $this->getParameter( 'sender_name' );
        $sender_email  = $this->getParameter( 'sender_email' );
        $send_as       = $this->getParameter( 'send_as' );
        $notifications = $this->getParameter( 'notifications' );
        $reply_to_customers = $this->getParameter( 'reply_to_customers' );

        // Change 'Content-Type' and 'Reply-To' for test email notification.
        add_filter( 'bookly_email_headers', function ( $headers ) use ( $sender_name, $sender_email, $send_as, $reply_to_customers ) {
            $headers = array();
            if ( $send_as == 'html' ) {
                $headers[] = 'Content-Type: text/html; charset=utf-8';
            } else {
                $headers[] = 'Content-Type: text/plain; charset=utf-8';
            }
            $headers[] = 'From: ' . $sender_name . ' <' . $sender_email . '>';
            if ( $reply_to_customers ) {
                $headers[] = 'Reply-To: ' . $sender_name . ' <' . $sender_email . '>';
            }

            return $headers;
        }, 10, 1 );

        Lib\NotificationSender::sendTestEmailNotifications( $to_email, $notifications, $send_as );

        wp_send_json_success();
    }

    /**
     * Create new custom notification
     */
    public function executeCreateCustomNotification()
    {
        $notification = new Lib\Entities\Notification();
        $notification
            ->setType( Lib\Entities\Notification::TYPE_APPOINTMENT_START_TIME )
            ->setToCustomer( 1 )
            ->setToStaff( 1 )
            ->setSettings( json_encode( Lib\DataHolders\Notification\Settings::getDefault() ) )
            ->setGateway( 'email' )
            ->save();

        $notification = $notification->getFields();
        $id   = $notification['id'];
        $html = '';
        if ( $this->getParameter( 'render' ) ) {
            $form     = new Forms\Notifications( 'email' );
            $statuses = Lib\Entities\CustomerAppointment::getStatuses();

            $html = $this->render( '_custom_notification', compact( 'form', 'notification', 'statuses' ), false );
        }
        wp_send_json_success( compact( 'html', 'id' ) );
    }

    /**
     * Delete custom notification
     */
    public function executeDeleteCustomNotification()
    {
        $id = $this->getParameter( 'id' );
        Lib\Entities\Notification::query()
            ->delete()
            ->where( 'id', $id )
            ->whereIn( 'type', Lib\Entities\Notification::getCustomNotificationTypes() )
            ->execute();

        wp_send_json_success();
    }

}