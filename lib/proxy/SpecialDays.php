<?php
namespace Bookly\Lib\Proxy;

use Bookly\Lib\Base;

/**
 * Class SpecialDays
 * Invoke local methods from Special Days add-on.
 *
 * @package Bookly\Lib\Proxy
 *
 * @method static array getSchedule( array $staff_ids, \DateTime $start_date, \DateTime $end_date )
 * @see \BooklySpecialDays\Lib\ProxyProviders\Local::getSchedule()
 *
 *  @method static array getServiceSchedule( int $service_id, \DateTime $start_date, \DateTime $end_date )
 * @see \BooklySpecialDays\Lib\ProxyProviders\Local::getServiceSchedule()
 *
 * @method static array getDaysAndTimes()
 * @see \BooklySpecialDays\Lib\ProxyProviders\Local::getDaysAndTimes()
 *
 * @method static void renderStaffCabinetSettings() Render special days in PopUp for short_code settings
 * @see \BooklySpecialDays\Lib\ProxyProviders\Local::renderStaffCabinetSettings()
 *
 */
abstract class SpecialDays extends Base\ProxyInvoker
{

}