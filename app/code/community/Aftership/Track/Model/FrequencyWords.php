<?php
class Aftership_Track_Model_FrequencyWords
{
    public function toOptionArray()
    {
        return array(
            array('value'=>0, 'label'=>Mage::helper('aftership')->__('Every 30 minutes')),
            array('value'=>1, 'label'=>Mage::helper('aftership')->__('Every 60 minutes')),
            array('value'=>2, 'label'=>Mage::helper('aftership')->__('Every 6 hours')),
            array('value'=>3, 'label'=>Mage::helper('aftership')->__('Every 12 hours')),
            array('value'=>4, 'label'=>Mage::helper('aftership')->__('Every 24 hours')),
        );
    }

}
?>