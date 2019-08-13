<?php
class Aftership_Track_Model_Mysql4_Track extends Mage_Core_Model_Mysql4_Abstract 
{
    protected function _construct()
    {
        $this->_init('track/track','track_id');
    }   
}
?>