<?php
class Aftership_Track_Model_Words
{
    public function toOptionArray()
    {
        return array(
            array('value'=>0, 'label'=>Mage::helper('aftership')->__('Disable')),            
            array('value'=>1, 'label'=>Mage::helper('aftership')->__('Enable')),                       
        );
    }

}
?>