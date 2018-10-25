<?php
namespace Bookly\Lib\Proxy;

use Bookly\Lib\Base;

/**
 * Class Ratings
 * Invoke local methods from Ratings add-on.
 *
 * @package Bookly\Lib\Proxy
 *
 * @method static void renderAppointmentsTableHeader() Render 'Ratings' in appointments table
 * @see \BooklyRatings\Lib\ProxyProviders\Local::renderAppointmentsTableHeader()
 *
 * @method static void renderExportAppointments( int $column ) Render 'Ratings' in appointments export popup
 * @see \BooklyRatings\Lib\ProxyProviders\Local::renderExportAppointments()
 *
 * @method static void renderStaffServiceRating( int $staff_id, int $service_id, string $type ) getStaffRating
 * @see \BooklyRatings\Lib\ProxyProviders\Local::renderStaffServiceRating()
 */
abstract class Ratings extends Base\ProxyInvoker
{

}