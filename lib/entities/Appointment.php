<?php
namespace Bookly\Lib\Entities;

use Bookly\Lib;

/**
 * Class Appointment
 * @package Bookly\Lib\Entities
 */
class Appointment extends Lib\Base\Entity
{
    /** @var  int */
    protected $series_id;
    /** @var  int */
    protected $location_id;
    /** @var  int */
    protected $staff_id;
    /** @var  int */
    protected $staff_any = 0;
    /** @var  int */
    protected $service_id;
    /** @var  string */
    protected $custom_service_name;
    /** @var  float */
    protected $custom_service_price;
    /** @var  string */
    protected $start_date;
    /** @var  string */
    protected $end_date;
    /** @var  string */
    protected $google_event_id;
    /** @var  int */
    protected $extras_duration = 0;
    /** @var  string */
    protected $internal_note;

    protected static $table = 'ab_appointments';

    protected static $schema = array(
        'id'                   => array( 'format' => '%d' ),
        'series_id'            => array( 'format' => '%d', 'reference' => array( 'entity' => 'Series' ) ),
        'location_id'          => array( 'format' => '%d' ),
        'staff_id'             => array( 'format' => '%d', 'reference' => array( 'entity' => 'Staff' ) ),
        'staff_any'            => array( 'format' => '%d' ),
        'service_id'           => array( 'format' => '%d', 'reference' => array( 'entity' => 'Service' ) ),
        'custom_service_name'  => array( 'format' => '%s' ),
        'custom_service_price' => array( 'format' => '%f' ),
        'start_date'           => array( 'format' => '%s' ),
        'end_date'             => array( 'format' => '%s' ),
        'google_event_id'      => array( 'format' => '%s' ),
        'extras_duration'      => array( 'format' => '%d' ),
        'internal_note'        => array( 'format' => '%s' ),
    );

    /**
     * Get color of service
     *
     * @param string $default
     * @return string
     */
    public function getColor( $default = '#DDDDDD' )
    {
        if ( ! $this->isLoaded() ) {
            return $default;
        }

        $service = new Service();

        if ( $service->load( $this->getServiceId() ) ) {
            return $service->getColor();
        }

        return $default;
    }

    /**
     * Get CustomerAppointment entities associated with this appointment.
     *
     * @param bool $with_cancelled
     * @return CustomerAppointment[]   Array of entities
     */
    public function getCustomerAppointments( $with_cancelled = false )
    {
        $result = array();

        if ( $this->getId() ) {
            $appointments = CustomerAppointment::query( 'ca' )
                ->select( 'ca.*, c.full_name, c.first_name, c.last_name, c.phone, c.email' )
                ->leftJoin( 'Customer', 'c', 'c.id = ca.customer_id' )
                ->where( 'ca.appointment_id', $this->getId() );
            if ( ! $with_cancelled ) {
                $appointments->whereIn( 'ca.status', array( Lib\Entities\CustomerAppointment::STATUS_PENDING, Lib\Entities\CustomerAppointment::STATUS_APPROVED ) );
            }

            foreach ( $appointments->fetchArray() as $data ) {
                $ca = new CustomerAppointment( $data );

                // Inject Customer entity.
                $ca->customer = new Customer();
                $data['id']   = $data['customer_id'];
                $ca->customer->setFields( $data, true );

                $result[] = $ca;
            }
        }

        return $result;
    }

    /**
     * Set array of customers associated with this appointment.
     *
     * @param array  $cst_data  Array of customer IDs, custom_fields, number_of_persons, extras and status
     * @return CustomerAppointment[] Array of customer_appointment with changed status
     */
    public function saveCustomerAppointments( array $cst_data )
    {
        $ca_status_changed = array();
        $ca_data = array();
        foreach ( $cst_data as $item ) {
            if ( isset( $item['ca_id'] ) ) {
                $ca_id = $item['ca_id'];
            } else do {
                // New CustomerAppointment.
                $ca_id = 'new-' . mt_rand( 1, 999 );
            } while ( array_key_exists( $ca_id, $ca_data ) === true );
            $ca_data[ $ca_id ] = $item;
        }

        // Retrieve customer appointments IDs currently associated with this appointment.
        $current_ids   = array_map( function( CustomerAppointment $ca ) { return $ca->getId(); }, $this->getCustomerAppointments( true ) );
        $ids_to_delete = array_diff( $current_ids, array_keys( $ca_data ) );
        if ( ! empty ( $ids_to_delete ) ) {
            // Remove redundant customer appointments.
            CustomerAppointment::query()->delete()->whereIn( 'id', $ids_to_delete )->execute();
        }
        // Add new customer appointments.
        foreach ( array_diff( array_keys( $ca_data ), $current_ids ) as $id ) {
            $customer_appointment = new CustomerAppointment();
            $customer_appointment
                ->setAppointmentId( $this->getId() )
                ->setCustomerId( $ca_data[ $id ]['id'] )
                ->setCustomFields( json_encode( $ca_data[ $id ]['custom_fields'] ) )
                ->setExtras( json_encode( $ca_data[ $id ]['extras'] ) )
                ->setStatus( $ca_data[ $id ]['status'] )
                ->setNumberOfPersons( $ca_data[ $id ]['number_of_persons'] )
                ->setNotes( $ca_data[ $id ]['notes'] )
                ->setCreatedFrom( $ca_data[ $id ]['created_from'] )
                ->setPaymentId( $ca_data[ $id ]['payment_id'] )
                ->setCreated( current_time( 'mysql' ) )
                ->save();
            $ca_status_changed[] = $customer_appointment;
            Lib\Proxy\Files::attachFiles( $ca_data[ $id ]['custom_fields'], $customer_appointment );
        }

        // Update existing customer appointments.
        foreach ( array_intersect( $current_ids, array_keys( $ca_data ) ) as $id ) {
            $customer_appointment = new CustomerAppointment();
            $customer_appointment->load( $id );

            if ( $customer_appointment->getStatus() != $ca_data[ $id ]['status'] ) {
                $ca_status_changed[] = $customer_appointment;
                $customer_appointment->setStatus( $ca_data[ $id ]['status'] );
            }
            if ( $customer_appointment->getPaymentId() != $ca_data[ $id ]['payment_id'] ) {
                $customer_appointment->setPaymentId( $ca_data[ $id ]['payment_id'] );
            }
            Lib\Proxy\Files::attachFiles( $ca_data[ $id ]['custom_fields'], $customer_appointment );
            $customer_appointment
                ->setNumberOfPersons( $ca_data[ $id ]['number_of_persons'] )
                ->setNotes( $ca_data[ $id ]['notes'] )
                ->setCustomFields( json_encode( $ca_data[ $id ]['custom_fields'] ) )
                ->setExtras( json_encode( $ca_data[ $id ]['extras'] ) )
                ->save();
        }

        return $ca_status_changed;
    }

    /**
     * Create or update event in Google Calendar.
     *
     * @return bool
     */
    public function handleGoogleCalendar()
    {
        if ( $this->hasGoogleCalendarEvent() ) {
            return $this->updateGoogleCalendarEvent();
        } else {
            $google_event_id = $this->createGoogleCalendarEvent();
            if ( $google_event_id ) {
                $this->setGoogleEventId( $google_event_id );
                return (bool)$this->save();
            }
        }

        return false;
    }

    /**
     * Check whether this appointment has an associated event in Google Calendar.
     *
     * @return bool
     */
    public function hasGoogleCalendarEvent()
    {
        return ! empty( $this->google_event_id );
    }

    /**
     * Create a new event in Google Calendar and associate it to this appointment.
     *
     * @return string|false
     */
    public function createGoogleCalendarEvent()
    {
        $google = new Lib\Google();
        if ( $google->loadByStaffId( $this->getStaffId() ) ) {
            // Create new event in Google Calendar.
            return $google->createEvent( $this );
        }

        return false;
    }

    public function updateGoogleCalendarEvent()
    {
        $google = new Lib\Google();
        if ( $google->loadByStaffId( $this->getStaffId() ) ) {
            // Update existing event in Google Calendar.
            return $google->updateEvent( $this );
        }

        return false;
    }

    /**
     * Delete event from Google Calendar associated to this appointment.
     *
     * @return bool
     */
    public function deleteGoogleCalendarEvent()
    {
        $google = new Lib\Google();
        if ( $google->loadByStaffId( $this->getStaffId() ) ) {
            // Delete existing event in Google Calendar.
            return $google->delete( $this->getGoogleEventId() );
        }

        return false;
    }

    /**
     * Get max sum of extras duration of associated customer appointments.
     *
     * @return int
     */
    public function getMaxExtrasDuration()
    {
        $duration = 0;
        // Calculate extras duration for appointments with duration < 1 day.
        if ( strtotime( $this->getEndDate() ) - strtotime( $this->getStartDate() ) < DAY_IN_SECONDS ) {
            $customer_appointments = CustomerAppointment::query()
                ->select( 'extras' )
                ->where( 'appointment_id', $this->getId() )
                ->whereIn( 'status', array( CustomerAppointment::STATUS_PENDING, CustomerAppointment::STATUS_APPROVED ) )
                ->fetchArray();
            foreach ( $customer_appointments as $customer_appointment ) {
                if ( $customer_appointment['extras'] != '[]' ) {
                    $extras_duration = Lib\Proxy\ServiceExtras::getTotalDuration( (array) json_decode( $customer_appointment['extras'], true ) );
                    if ( $extras_duration > $duration ) {
                        $duration = $extras_duration;
                    }
                }
            }
        }

        return $duration;
    }

    /**
     * Get information about number of persons grouped by status.
     *
     * @return array
     */
    public function getNopInfo()
    {
        $res = self::query( 'a' )
           ->select( sprintf(
               'SUM(IF(ca.status = "%s", ca.number_of_persons, 0)) AS pending,
                SUM(IF(ca.status = "%s", ca.number_of_persons, 0)) AS approved,
                SUM(IF(ca.status = "%s", ca.number_of_persons, 0)) AS cancelled,
                SUM(IF(ca.status = "%s", ca.number_of_persons, 0)) AS rejected,
                SUM(IF(ca.status = "%s", ca.number_of_persons, 0)) AS waitlisted,
                ss.capacity_max',
                CustomerAppointment::STATUS_PENDING,
                CustomerAppointment::STATUS_APPROVED,
                CustomerAppointment::STATUS_CANCELLED,
                CustomerAppointment::STATUS_REJECTED,
                CustomerAppointment::STATUS_WAITLISTED
           ) )
           ->leftJoin( 'CustomerAppointment', 'ca', 'ca.appointment_id = a.id' )
           ->leftJoin( 'StaffService', 'ss', 'ss.staff_id = a.staff_id AND ss.service_id = a.service_id' )
           ->where( 'a.id', $this->getId() )
           ->groupBy( 'a.id' )
           ->fetchRow()
        ;

        $res['total_nop'] = $res['pending'] + $res['approved'];

        return $res;
    }

    /**************************************************************************
     * Entity Fields Getters & Setters                                        *
     **************************************************************************/

    /**
     * Gets series_id
     *
     * @return int
     */
    public function getSeriesId()
    {
        return $this->series_id;
    }

    /**
     * Sets series_id
     *
     * @param Series $series
     * @return $this
     */
    public function setSeries( Series $series )
    {
        return $this->setSeriesId( $series->getId() );
    }

    /**
     * Sets series_id
     *
     * @param int $series_id
     * @return $this
     */
    public function setSeriesId( $series_id )
    {
        $this->series_id = $series_id;

        return $this;
    }

    /**
     * Gets location_id
     *
     * @return int
     */
    public function getLocationId()
    {
        return $this->location_id;
    }

    /**
     * Sets location_id
     *
     * @param int $location_id
     * @return $this
     */
    public function setLocationId( $location_id )
    {
        $this->location_id = $location_id;

        return $this;
    }

    /**
     * Gets staff_id
     *
     * @return int
     */
    public function getStaffId()
    {
        return $this->staff_id;
    }

    /**
     * Sets staff
     *
     * @param Staff $staff
     * @return $this
     */
    public function setStaff( Staff $staff )
    {
        return $this->setStaffId( $staff->getId() );
    }
    /**
     * Sets staff_id
     *
     * @param int $staff_id
     * @return $this
     */
    public function setStaffId( $staff_id )
    {
        $this->staff_id = $staff_id;

        return $this;
    }

    /**
     * Gets staff_any
     *
     * @return int
     */
    public function getStaffAny()
    {
        return $this->staff_any;
    }

    /**
     * Sets staff_any
     *
     * @param int $staff_any
     * @return $this
     */
    public function setStaffAny( $staff_any )
    {
        $this->staff_any = $staff_any;

        return $this;
    }

    /**
     * Gets service_id
     *
     * @return int
     */
    public function getServiceId()
    {
        return $this->service_id;
    }

    /**
     * Sets service
     *
     * @param Service $service
     * @return $this
     */
    public function setService( Service $service )
    {
        return $this->setServiceId( $service->getId() );
    }

    /**
     * Sets service_id
     *
     * @param int $service_id
     * @return $this
     */
    public function setServiceId( $service_id )
    {
        $this->service_id = $service_id;

        return $this;
    }

    /**
     * Gets custom_service_name
     *
     * @return string
     */
    public function getCustomServiceName()
    {
        return $this->custom_service_name;
    }

    /**
     * Sets custom_service_name
     *
     * @param int $custom_service_name
     * @return $this
     */
    public function setCustomServiceName( $custom_service_name )
    {
        $this->custom_service_name = $custom_service_name;

        return $this;
    }

    /**
     * Gets custom_service_price
     *
     * @return string
     */
    public function getCustomServicePrice()
    {
        return $this->custom_service_price;
    }

    /**
     * Sets custom_service_price
     *
     * @param int $custom_service_price
     * @return $this
     */
    public function setCustomServicePrice( $custom_service_price )
    {
        $this->custom_service_price = $custom_service_price;

        return $this;
    }

    /**
     * Gets start_date
     *
     * @return string
     */
    public function getStartDate()
    {
        return $this->start_date;
    }

    /**
     * Sets start_date
     *
     * @param string $start_date
     * @return $this
     */
    public function setStartDate( $start_date )
    {
        $this->start_date = $start_date;

        return $this;
    }

    /**
     * Gets end_date
     *
     * @return string
     */
    public function getEndDate()
    {
        return $this->end_date;
    }

    /**
     * Sets end_date
     *
     * @param string $end_date
     * @return $this
     */
    public function setEndDate( $end_date )
    {
        $this->end_date = $end_date;

        return $this;
    }

    /**
     * Gets google_event_id
     *
     * @return string
     */
    public function getGoogleEventId()
    {
        return $this->google_event_id;
    }

    /**
     * Sets google_event_id
     *
     * @param string $google_event_id
     * @return $this
     */
    public function setGoogleEventId( $google_event_id )
    {
        $this->google_event_id = $google_event_id;

        return $this;
    }

    /**
     * Gets extras_duration
     *
     * @return int
     */
    public function getExtrasDuration()
    {
        return $this->extras_duration;
    }

    /**
     * Sets extras_duration
     *
     * @param int $extras_duration
     * @return $this
     */
    public function setExtrasDuration( $extras_duration )
    {
        $this->extras_duration = $extras_duration;

        return $this;
    }

    /**
     * Gets internal_note
     *
     * @return string
     */
    public function getInternalNote()
    {
        return $this->internal_note;
    }

    /**
     * Sets internal_note
     *
     * @param string $internal_note
     * @return $this
     */
    public function setInternalNote( $internal_note )
    {
        $this->internal_note = $internal_note;

        return $this;
    }

    /**************************************************************************
     * Overridden Methods                                                     *
     **************************************************************************/

    /**
     * Save appointment to database
     *(and delete event in Google Calendar if staff changes).
     *
     * @return false|int
     */
    public function save()
    {
        // Google Calendar.
        if ( $this->isLoaded() && $this->hasGoogleCalendarEvent() ) {
            $modified = $this->getModified();
            if ( array_key_exists( 'staff_id', $modified ) ) {
                // Delete event from the Google Calendar of the old staff if the staff was changed.
                $staff_id = $this->getStaffId();
                $this->setStaffId( $modified['staff_id'] );
                $this->deleteGoogleCalendarEvent();
                $this->setStaffId( $staff_id )
                     ->setGoogleEventId( null );
            }
        }

        return parent::save();
    }

    /**
     * Delete entity from database
     *(and delete event in Google Calendar if it exists).
     *
     * @return bool|false|int
     */
    public function delete()
    {
        // Delete all CustomerAppointments for current appointments
        $ca_list = Lib\Entities\CustomerAppointment::query()
            ->where( 'appointment_id', $this->getId() )
            ->find();
        /** @var Lib\Entities\CustomerAppointment $ca */
        foreach ( $ca_list as $ca ) {
            $ca->delete();
        }

        $result = parent::delete();
        if ( $result ) {
            if ( $this->hasGoogleCalendarEvent() ) {
                $this->deleteGoogleCalendarEvent();
            }
            if ( $this->getSeriesId() !== null ) {
                if ( Appointment::query()->where( 'series_id', $this->getSeriesId() )->count() === 0 ) {
                    Series::query()->delete()->where( 'id', $this->getSeriesId() )->execute();
                }
            }
        }

        return $result;
    }

}