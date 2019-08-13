<?php

class Aftership_Track_Model_Observer
{
    public function salesOrderShipmentTrackSaveAfter(Varien_Event_Observer $observer)
    {
        ob_start();
        $config = Mage::getStoreConfig('aftership_options/messages');

        /*if(array_key_exists("notification",$config)){
            $notifications = explode(",",$config["notification"]);
            if(array_search("1",$notifications)!==False){ // email
                $is_notify_email = true;
            }else{
                $is_notify_email = false;
            }
            if(array_search("2",$notifications)!==False){ // sms
                $is_notify_sms = true;
            }else{
                $is_notify_sms = false;
            }
        }else{
            $is_notify_email = false;
            $is_notify_sms = false;
        }*/

        $track = $observer->getEvent()->getTrack();
        $track_data = $track->getData();
        $order_data = $track->getShipment()->getOrder()->getData();
        $shipment_data = $track->getShipment()->getData();
        $shipping_address_data = $track->getShipment()->getOrder()->getShippingAddress()->getData();
        if (strlen(trim($track_data["track_number"])) > 0) {
            //1.6.2.0 or later
            $track_no = trim($track_data["track_number"]);
        } else {
            //1.5.1.0
            $track_no = trim($track_data["number"]);
        }

        $exist_track_data = Mage::getModel('track/track')
            ->getCollection()
            ->addFieldToFilter('tracking_number', array('eq' => $track_no))
            ->addFieldToFilter('order_id', array('eq' => $order_data["order_id"]))
            ->getData();


        if (!$exist_track_data) {
            $track = Mage::getModel('track/track');

            $track->setTrackingNumber($track_no);

            $track->setShipCompCode($track_data["carrier_code"]);
            //$track->setTitle($_SERVER['HTTP_HOST'] . " " . $order_data["increment_id"]);
            //$track->setTitle($_SERVER['HTTP_ORIGIN'] . " " . $order_data["increment_id"]);
            $track->setTitle($order_data["increment_id"]);

            $track->setOrderId($order_data["increment_id"]);

            if ($order_data["customer_email"] && $order_data["customer_email"] != "") {
                $track->setEmail($order_data["customer_email"]);
            }

            if ($shipping_address_data["telephone"] && $shipping_address_data["telephone"] != "") {
                $track->setTelephone($shipping_address_data["telephone"]);
            }

            /*
              if($is_notify_email){
                  $track->setEmail($order_data["customer_email"]);
              }

              if($is_notify_sms){
                  $track->setTelephone($shipping_address_data["telephone"]);
              }*/


            if (array_key_exists("status", $config) && $config["status"]) {
                $track->setPosted(0);
            } else {
                $track->setPosted(2);
            }

            $track->save();
        }


        if (array_key_exists("status", $config) && $config["status"])
        {
            $api_key = $config["api_key"];

            $post_tracks = Mage::getModel('track/track')
                ->getCollection()
                ->addFieldToFilter('posted', array('eq' => 0))
                ->getData();

            $url_params = array("api_key" => $api_key);

            foreach ($post_tracks as $track) {
                $url = "https://api.aftership.com/v1/trackings";
                $url_params["tracking_number"] = $track["tracking_number"];
                $url_params["smses"]        = array($track["telephone"]);
                $url_params["emails"]       = array($track["email"]);
                $url_params["title"]        = $track["title"];
                $url_params["order_id"]     = $track["order_id"];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);

                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $url_params);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
                curl_setopt($ch, CURLOPT_HEADER, 0);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); //the SSL is not correct
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); //the SSL is not correct

                $response = curl_exec($ch);
                $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);
                $response_obj = json_decode($response, true);

                if ($http_status == "201" || $http_status == "422") //422: repeated
                {
                    $track_obj = Mage::getModel('track/track');
                    $track_obj->load($track["track_id"]);
                    $track_obj->setPosted(1);
                    $track_obj->save();
                } else {

                }
            }
        }

        ob_end_clean();
    }

    public function adminSystemConfigChangedSectionAftership($obj)
    {
        $post_data = Mage::app()->getRequest()->getPost();
        $api_key = $post_data["groups"]["messages"]["fields"]["api_key"]["value"];
        if (!array_key_exists("notification", $post_data["groups"]["messages"]["fields"])) {
            Mage::getModel('core/config')->saveConfig('aftership_options/messages/notification', 0);
        }

        $url_params = array(
            "api_key" => $api_key
        );
        $url = "https://api.aftership.com/v1/users/authenticate";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($url_params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $response_obj = json_decode($response, true);

        if ($http_status != "200" && $http_status != "401") {
            Mage::getModel('core/config')->saveConfig('aftership_options/messages/status', 0);
            Mage::throwException(Mage::helper('adminhtml')->__("Connection error, please try again later."));
        } else {
            if (!$response_obj["success"]) //error
            {
                Mage::getModel('core/config')->saveConfig('aftership_options/messages/status', 0);
                Mage::throwException(Mage::helper('adminhtml')->__("Incorrect API Key"));
            }
        }
    }
}