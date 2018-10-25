<?php
namespace Bookly\Backend\Modules\Appearance;

use Bookly\Lib;

/**
 * Class Components
 * @package Bookly\Backend\Modules\Appearance
 */
class Components extends Lib\Base\Components
{
    /**
     * @param array $variables
     * @param bool  $echo
     * @return string|void
     */
    public function renderCodes( $variables = array(), $echo = true )
    {
        return $this->render( '_codes', $variables, $echo );
    }
}