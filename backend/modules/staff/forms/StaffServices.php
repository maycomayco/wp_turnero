<?php
namespace Bookly\Backend\Modules\Staff\Forms;

use Bookly\Lib;

/**
 * Class StaffServices
 * @package Bookly\Backend\Modules\Staff\Forms
 */
class StaffServices extends Lib\Base\Form
{
    protected static $entity_class = 'StaffService';

    /** @var Lib\Entities\Category[] */
    private $categories = array();

    /** @var array */
    private $services_data = array();

    /** @var array */
    private $uncategorized_services = array();

    public function configure()
    {
        $this->setFields( array( 'price', 'deposit', 'service', 'staff_id', 'capacity_min', 'capacity_max' ) );
    }

    public function load( $staff_id )
    {
        $data = Lib\Entities\Category::query( 'c' )
            ->select( 'c.name AS category_name, s.*' )
            ->innerJoin( 'Service', 's', 's.category_id = c.id' )
            ->sortBy( 'c.position, s.position' )
            ->whereIn( 's.type', array( Lib\Entities\Service::TYPE_SIMPLE, Lib\Entities\Service::TYPE_PACKAGE ) )
            ->fetchArray();
        if ( !$data ) {
            $data = array();
        }

        $this->uncategorized_services = Lib\Entities\Service::query( 's' )
            ->where( 's.category_id', null )
            ->whereIn( 's.type', array( Lib\Entities\Service::TYPE_SIMPLE, Lib\Entities\Service::TYPE_PACKAGE ) )
            ->find();

        $staff_services = Lib\Entities\StaffService::query( 'ss' )
            ->select( 'ss.service_id, ss.price, ss.deposit, ss.capacity_min, ss.capacity_max' )
            ->where( 'ss.staff_id', $staff_id )
            ->fetchArray();
        if ( $staff_services ) {
            foreach ( $staff_services as $staff_service ) {
                $this->services_data[ $staff_service['service_id'] ] = array( 'price' => $staff_service['price'], 'deposit' => $staff_service['deposit'], 'capacity_min' => $staff_service['capacity_min'], 'capacity_max' => $staff_service['capacity_max'] );
            }
        }

        foreach ( $data as $row ) {
            if ( ! isset( $this->categories[ $row['category_id'] ] ) ) {
                $category = new Lib\Entities\Category( array( 'id' => $row['category_id'], 'name' => $row['category_name'] ) );
                $this->categories[ $row['category_id'] ] = $category;
            }
            unset( $row['category_name'] );

            $service = new Lib\Entities\Service( $row );
            $this->categories[ $row['category_id'] ]->addService( $service );
        }

    }

    public function save()
    {
        $staff_id = $this->data['staff_id'];
        if ( $staff_id ) {
            Lib\Entities\StaffService::query()->delete()->where( 'staff_id', $staff_id )->execute();
            if ( isset ( $this->data['service'] ) ) {
                foreach ( $this->data['service'] as $service_id ) {
                    $staff_service = new Lib\Entities\StaffService();
                    $staff_service
                        ->setCapacityMin( $this->data['capacity_min'][ $service_id ] )
                        ->setCapacityMax( $this->data['capacity_max'][ $service_id ] )
                        ->setDeposit( isset ( $this->data['deposit'] ) ? $this->data['deposit'][ $service_id ] : '100%' )
                        ->setPrice( $this->data['price'][ $service_id ] )
                        ->setServiceId( $service_id )
                        ->setStaffId( $staff_id )
                        ->save();
                }
            }
        }
    }

    /**
     * @return Lib\Entities\Category[]
     */
    public function getCategories()
    {
        return $this->categories;
    }

    /**
     * @return array
     */
    public function getServicesData()
    {
        return $this->services_data;
    }

    /**
     * @return Lib\Entities\Service[]
     */
    public function getUncategorizedServices()
    {
        return $this->uncategorized_services;
    }

}