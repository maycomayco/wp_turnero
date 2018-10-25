<?php
namespace Bookly\Backend\Modules\Staff;

use Bookly\Lib;

/**
 * Class Components
 * @package Bookly\Backend\Modules\Calendar
 */
class Components extends Lib\Base\Components
{
    /**
     * Render appointment dialog.
     * @throws \Exception
     */
    public function renderDeleteCascadeDialog()
    {
        $this->enqueueStyles( array(
            'frontend' => array( 'css/ladda.min.css', ),
        ) );

        $this->enqueueScripts( array(
            'frontend' => array(
                'js/spin.min.js'  => array( 'jquery' ),
                'js/ladda.min.js' => array( 'bookly-spin.min.js', 'jquery' ),
            )
        ) );

        $this->render( 'dialog_delete_cascade' );
    }

}