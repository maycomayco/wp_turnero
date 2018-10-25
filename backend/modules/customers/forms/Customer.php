<?php
namespace Bookly\Backend\Modules\Customers\Forms;

use Bookly\Lib;

/**
 * Class Customer
 * @package Bookly\Backend\Modules\Customers\Forms
 */
class Customer extends Lib\Base\Form
{
    protected static $entity_class = 'Customer';

    public function configure()
    {
        $this->setFields( array(
            'wp_user_id',
            'group_id',
            'full_name',
            'first_name',
            'last_name',
            'phone',
            'email',
            'notes',
            'birthday',
            'info_fields',
        ) );
    }

}
