<?php
namespace Bookly\Lib;

/**
 * Class Google
 * @package Bookly\Lib
 */
class Google
{
    const EVENTS_PER_REQUEST = 250;

    /** @var \Google_Client */
    private $client;

    /** @var \Google_Service_Calendar */
    private $service;

    /** @var \Google_Service_Calendar_CalendarListEntry */
    private $calendar;

    /** @var \Google_Service_Calendar_Event */
    private $event;

    /** @var \Bookly\Lib\Entities\Staff */
    private $staff;

    private $errors = array();

    public function __construct()
    {
        include_once Plugin::getDirectory() . '/lib/google/autoload.php';

        $this->client = new \Google_Client();
        $this->client->setClientId( get_option( 'bookly_gc_client_id' ) );
        $this->client->setClientSecret( get_option( 'bookly_gc_client_secret' ) );
    }

    /**
     * Load Google and Calendar Service data by Staff
     *
     * @param Entities\Staff $staff
     * @return bool
     */
    public function loadByStaff( Entities\Staff $staff )
    {
        $this->staff = $staff;
        if ( ! Config::booklyExpired() && $staff->getGoogleData() ) {
            try {
                $this->client->setAccessToken( $staff->getGoogleData() );
                if ( $this->client->isAccessTokenExpired() ) {
                    $this->client->refreshToken( $this->client->getRefreshToken() );
                    $staff->setGoogleData( $this->client->getAccessToken() );
                    $staff->save();
                }

                $this->service = new \Google_Service_Calendar( $this->client );

                return true;
            } catch ( \Exception $e ) {
                $this->errors[] = 'Google Calendar: ' . $e->getMessage();
            }
        }

        return false;
    }

    /**
     * Load Google and Calendar Service data by Staff ID
     *
     * @param int $staff_id
     * @return bool
     */
    public function loadByStaffId( $staff_id )
    {
        $staff = Entities\Staff::find( $staff_id );

        return $this->loadByStaff( $staff );
    }

    /**
     * Create Event and return id
     *
     * @param Entities\Appointment $appointment
     * @return mixed
     */
    public function createEvent( Entities\Appointment $appointment )
    {
        try {
            if ( in_array( $this->getCalendarAccess(), array( 'writer', 'owner' ) ) ) {
                $this->event = new \Google_Service_Calendar_Event();

                $this->handleEventData( $appointment );

                /** @var \Google_Service_Calendar_Event $createdEvent */
                $createdEvent = $this->service->events->insert( $this->getCalendarID(), $this->event );

                return $createdEvent->getId();
            }
        } catch ( \Exception $e ) {
            $this->errors[] = $e->getMessage();
        }

        return false;
    }

    /**
     * Update event
     *
     * @param Entities\Appointment $appointment
     * @return bool
     */
    public function updateEvent( Entities\Appointment $appointment )
    {
        try {
            if ( in_array( $this->getCalendarAccess(), array( 'writer', 'owner' ) ) ) {
                $this->event = $this->service->events->get( $this->getCalendarID(), $appointment->getGoogleEventId() );

                $this->handleEventData( $appointment );

                $this->service->events->update( $this->getCalendarID(), $this->event->getId(), $this->event );

                return true;
            }
        } catch ( \Exception $e ) {
            $this->errors[] = $e->getMessage();
        }

        return false;
    }

    /**
     * Get list of Google Calendars.
     *
     * @return array
     */
    public function getCalendarList()
    {
        $result = array();
        try {
            $calendarList = $this->service->calendarList->listCalendarList();
            while ( true ) {
                /** @var \Google_Service_Calendar_CalendarListEntry $calendarListEntry */
                foreach ( $calendarList->getItems() as $calendarListEntry ) {
                    if ( in_array( $calendarListEntry->getAccessRole(), array( 'writer', 'owner' ) ) ) {
                        $result[ $calendarListEntry->getId() ] = array(
                            'primary' => $calendarListEntry->getPrimary(),
                            'summary' => $calendarListEntry->getSummary(),
                        );
                    }
                }
                $pageToken = $calendarList->getNextPageToken();
                if ( $pageToken ) {
                    $optParams    = array( 'pageToken' => $pageToken );
                    $calendarList = $this->service->calendarList->listCalendarList( $optParams );
                } else {
                    break;
                }
            }
        } catch ( \Exception $e ) {
            Session::set( 'staff_google_auth_error', json_encode( $e->getMessage() ) );
        }

        return $result;
    }

    /**
     * Returns a collection of Google calendar events
     *
     * @param \DateTime $start_date
     * @return array|false
     */
    public function getCalendarEvents( \DateTime $start_date )
    {
        // get all events from calendar, without timeMin filter (the end of the event can be later then the start of searched time period)
        $result = array();

        try {
            $calendar_access = $this->getCalendarAccess();
            $limit_events    = get_option( 'bookly_gc_limit_events' );

            $timeMin = $start_date->format( \DateTime::RFC3339 );

            $events = $this->service->events->listEvents( $this->getCalendarID(), array(
                'singleEvents' => true,
                'orderBy'      => 'startTime',
                'timeMin'      => $timeMin,
                'maxResults'   => $limit_events ?: self::EVENTS_PER_REQUEST,
            ) );

            while ( true ) {
                foreach ( $events->getItems() as $event ) {
                    /** @var \Google_Service_Calendar_Event $event */
                    // transparency = 'opaque'      - The event blocks time on the calendar.
                    //              = 'transparent' - The event does not block time on the calendar.
                    if ( $event->getStatus() !== 'cancelled' && ( $event->getTransparency() === null || $event->getTransparency() === 'opaque' ) ) {
                        // Skip events created by Bookly in non freeBusyReader calendar.
                        if ( $calendar_access != 'freeBusyReader' ) {
                            $ext_properties = $event->getExtendedProperties();
                            if ( $ext_properties !== null ) {
                                $private = $ext_properties->private;
                                if ( $private !== null && array_key_exists( 'service_id', $private ) ) {
                                    continue;
                                }
                            }
                        }

                        // Get start/end dates of event and transform them into WP timezone (Google doesn't transform whole day events into our timezone).
                        $event_start = $event->getStart();
                        $event_end   = $event->getEnd();

                        if ( $event_start->dateTime == null ) {
                            // All day event.
                            $event_start_date = new \DateTime( $event_start->date, new \DateTimeZone( $this->getCalendarTimezone() ) );
                            $event_end_date = new \DateTime( $event_end->date, new \DateTimeZone( $this->getCalendarTimezone() ) );
                        } else {
                            // Regular event.
                            $event_start_date = new \DateTime( $event_start->dateTime );
                            $event_end_date = new \DateTime( $event_end->dateTime );
                        }

                        // Convert to WP time zone.
                        $event_start_date = date_timestamp_set( date_create( Config::getWPTimeZone() ), $event_start_date->getTimestamp() );
                        $event_end_date   = date_timestamp_set( date_create( Config::getWPTimeZone() ), $event_end_date->getTimestamp() );

                        $result[] = new Slots\Booking(
                            0,
                            0,
                            1,
                            0,
                            $event_start_date->format( 'Y-m-d H:i:s' ),
                            $event_end_date->format( 'Y-m-d H:i:s' ),
                            0,
                            0,
                            0,
                            true
                        );
                    }
                }

                if ( ! $limit_events && $events->getNextPageToken() ) {
                    $events = $this->service->events->listEvents( $this->getCalendarID(), array(
                        'singleEvents' => true,
                        'orderBy'      => 'startTime',
                        'timeMin'      => $timeMin,
                        'pageToken'    => $events->getNextPageToken()
                    ) );
                } else {
                    break;
                }
            }

            return $result;
        } catch ( \Exception $e ) {
            $this->errors[] = $e->getMessage();
        }

        return false;
    }

    /**
     * @param $code
     * @return bool
     */
    public function authCodeHandler( $code )
    {
        $this->client->setRedirectUri( self::generateRedirectURI() );

        try {
            $this->client->authenticate( $code );

            return true;
        } catch ( \Exception $e ) {
            $this->errors[] = $e->getMessage();
        }

        return false;
    }

    /**
     * @return string
     */
    public function getAccessToken()
    {
        return $this->client->getAccessToken();
    }

    /**
     * Revoke Google Calendar token.
     */
    public function revokeToken()
    {
        try {
            $this->client->revokeToken( $this->staff->getGoogleData() );
        } catch ( \Exception $e ) {
            $this->errors[] = $e->getMessage();
        }
        $this->staff
            ->setGoogleData( null )
            ->setGoogleCalendarId( null )
            ->save();
    }

    /**
     * @param $staff_id
     * @return string
     */
    public function createAuthUrl( $staff_id )
    {
        $this->client->setRedirectUri( self::generateRedirectURI() );
        $this->client->addScope( 'https://www.googleapis.com/auth/calendar' );
        $this->client->setState( strtr( base64_encode( $staff_id ), '+/=', '-_,' ) );
        $this->client->setApprovalPrompt( 'force' );
        $this->client->setAccessType( 'offline' );

        return $this->client->createAuthUrl();
    }

    /**
     * Delete event by id
     *
     * @param $event_id
     * @return bool
     */
    public function delete( $event_id )
    {
        try {
            if ( in_array( $this->getCalendarAccess(), array( 'writer', 'owner' ) ) ) {
                $this->service->events->delete( $this->getCalendarID(), $event_id );

                return true;
            }
        } catch ( \Exception $e ) {
            $this->errors[] = $e->getMessage();
        }

        return false;
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param Entities\Appointment $appointment
     */
    private function handleEventData( Entities\Appointment $appointment )
    {
        $start_datetime = new \Google_Service_Calendar_EventDateTime();
        $start_datetime->setDateTime(
            Slots\DatePoint::fromStr( $appointment->getStartDate() )->format( \DateTime::RFC3339 )
        );

        $end_datetime = new \Google_Service_Calendar_EventDateTime();
        $end_datetime->setDateTime(
            Slots\DatePoint::fromStr( $appointment->getEndDate() )->modify( (int) $appointment->getExtrasDuration() )->format( \DateTime::RFC3339 )
        );

        if ( $appointment->getServiceId() ) {
            $service = Entities\Service::find( $appointment->getServiceId() );
        } else {
            // Custom service.
            $service = new Entities\Service();
            $service
                ->setTitle( $appointment->getCustomServiceName() )
                ->setPrice( $appointment->getCustomServicePrice() );
        }
        $description  = __( 'Service', 'bookly' ) . ': ' . $service->getTitle() . PHP_EOL;
        $client_names = array();
        foreach ( $appointment->getCustomerAppointments() as $ca ) {
            $description .= sprintf(
                "%s: %s\n%s: %s\n%s: %s\n",
                __( 'Name',  'bookly' ), $ca->customer->getFullName(),
                __( 'Email', 'bookly' ), $ca->customer->getEmail(),
                __( 'Phone', 'bookly' ), $ca->customer->getPhone()
            );
            if ( Config::customFieldsActive() ) {
                $description .= Proxy\CustomFields::getFormatted( $ca, 'text' ) . PHP_EOL;
            }
            if ( Config::serviceExtrasActive() ) {
                $appointment_extras = json_decode( $ca->getExtras(), true );
                $extras = implode( ', ', array_map( function ( $extra ) use ( $appointment_extras ) {
                    /** @var \BooklyServiceExtras\Lib\Entities\ServiceExtra $extra */
                    $count = $appointment_extras[ $extra->getId() ];

                    return ( $count > 1 ? $count . ' × ' : '' ) . $extra->getTitle();
                }, (array) Proxy\ServiceExtras::findByIds( array_keys( $appointment_extras ) ) ) );
                if ( $extras != '' ) {
                    $description .= __( 'Extras', 'bookly' ) . ': ' . $extras . PHP_EOL;
                }
            }
            $client_names[] = $ca->customer->getFullName();
        }

        $staff = Entities\Staff::find( $appointment->getStaffId() );

        $title = strtr( get_option( 'bookly_gc_event_title', '{service_name}' ), array(
            '{service_name}' => $service->getTitle(),
            '{client_names}' => implode( ', ', $client_names ),
            '{staff_name}'   => $staff->getFullName(),
            /** @deprecate [[CODE]] */
            '[[SERVICE_NAME]]' => $service->getTitle(),
            '[[CLIENT_NAMES]]' => implode( ', ', $client_names ),
            '[[STAFF_NAME]]'   => $staff->getFullName(),
        ) );

        $this->event->setStart( $start_datetime );
        $this->event->setEnd( $end_datetime );
        $this->event->setSummary( $title );
        $this->event->setDescription( $description );

        $extended_property = new \Google_Service_Calendar_EventExtendedProperties();
        $extended_property->setPrivate( array(
            'customers'      => json_encode( array_map( function( $ca ) { return $ca->customer->getId(); }, $appointment->getCustomerAppointments() ) ),
            'service_id'     => $service->getId(),
            'appointment_id' => $appointment->getId(),
        ) );
        $this->event->setExtendedProperties( $extended_property );
    }

    /**
     * @return string
     */
    private function getCalendarID()
    {
        return $this->staff->getGoogleCalendarId() ?: 'primary';
    }

    /**
     * @return string [freeBusyReader, reader, writer, owner]
     */
    private function getCalendarAccess()
    {
        if ( $this->calendar === null ) {
            $this->calendar = $this->service->calendarList->get( $this->getCalendarID() );
        }

        return $this->calendar->getAccessRole();
    }

    /**
     * @return mixed
     */
    private function getCalendarTimezone()
    {
        if ( $this->calendar === null ) {
            $this->calendar = $this->service->calendarList->get( $this->getCalendarID() );
        }

        return $this->calendar->getTimeZone();
    }

    /**
     * Validate calendar
     *
     * @param null $calendar_id (send this parameter on unsaved form)
     * @return bool
     */
    public function validateCalendar( $calendar_id = null )
    {
        if ( !$this->service ) {
            return false;
        }

        try {
            $this->service->calendarList->get( $calendar_id ?: $this->getCalendarID() );

            return true;
        } catch ( \Exception $e ) {
            $this->errors[] = $e->getMessage();
        }

        return false;
    }

    /**
     * @return string
     */
    public static function generateRedirectURI()
    {
        return admin_url( 'admin.php?page=' . \Bookly\Backend\Modules\Staff\Controller::page_slug );
    }

}