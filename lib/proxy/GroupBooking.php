<?php
namespace Bookly\Lib\Proxy;

use Bookly\Lib\Base;

/**
 * Class Bookly Groups
 * Invoke local methods from Book lyGroups add-on.
 *
 * @package Bookly\Lib\Proxy
 *
 * @method static void renderServiceCapacity( array $service ) Render service capacity
 * @see \BooklyGroupBooking\Lib\ProxyProviders\Local::renderServiceCapacity()
 *
 * @method static void renderStaffServiceLabel() Render column header for capacity
 * @see \BooklyGroupBooking\Lib\ProxyProviders\Local::renderStaffServiceLabel()
 *
 * @method static void renderAppearance() Render number of persons in Appearance
 * @see \BooklyGroupBooking\Lib\ProxyProviders\Local::renderAppearance()
 *
 * @method static void renderBooklyFormSettings() Render number of persons in PopUp for short_code settings
 * @see \BooklyGroupBooking\Lib\ProxyProviders\Local::renderBooklyFormSettings()
 *
 * @method static void renderStaffCabinetSettings() Render number of persons in PopUp for short_code settings
 * @see \BooklyGroupBooking\Lib\ProxyProviders\Local::renderStaffCabinetSettings()
 */
abstract class GroupBooking extends Base\ProxyInvoker
{

}