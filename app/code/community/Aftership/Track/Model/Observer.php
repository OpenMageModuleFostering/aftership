<?php

class Aftership_Track_Model_Observer {
	const ENDPOINT_TRACKING = 'https://api.aftership.com/v1/trackings';
	const ENDPOINT_CONFIG = 'https://api.aftership.com/v1/users/authenticate';


	public function salesOrderShipmentTrackSaveAfter(Varien_Event_Observer $observer) {
		ob_start();

		$track = $observer->getEvent()->getTrack();
		$track_data = $track->getData();
		$order_data = $track->getShipment()->getOrder()->getData();

		$website_id = Mage::getModel('core/store')->load($order_data['store_id'])->getWebsiteId();

		//if only 1 store, get the default
		if (count(Mage::app()->getWebsites()) == 1) {
			$config = $this->getDefaultConfig();
		} else {
			$config = $this->getConfigByWebsiteId($website_id);
		}

		$shipping_address_data = $track->getShipment()->getOrder()->getShippingAddress()->getData();
		if (strlen(trim($track_data["track_number"])) > 0) {
			//1.6.2.0 or later
			$track_no = trim($track_data["track_number"]);
		} else {
			//1.5.1.0
			$track_no = trim($track_data["number"]);
		}

		$order_id = '';

		if (isset($order_data["order_id"])) {
			$order_id = $order_data["order_id"];
		} else if (isset($order_data["increment_id"])) {
			$order_id = $order_data["increment_id"];
		}

		$exist_track_data = Mage::getModel('track/track')
			->getCollection()
			->addFieldToFilter('tracking_number', array('eq' => $track_no))
			->addFieldToFilter('order_id', array('eq' => $order_id))
			->getData();

		if (!$exist_track_data) {
			$track = Mage::getModel('track/track');

			$track->setTrackingNumber($track_no);

			$track->setShipCompCode($track_data["carrier_code"]);
			$track->setTitle($order_data["increment_id"]);

			$track->setOrderId($order_data["increment_id"]);

			if ($order_data["customer_email"] && $order_data["customer_email"] != "") {
				$track->setEmail($order_data["customer_email"]);
			}

			if ($shipping_address_data["telephone"] && $shipping_address_data["telephone"] != "") {
				$track->setTelephone($shipping_address_data["telephone"]);
			}

			if (array_key_exists("status", $config) && $config["status"]) {
				$track->setPosted(0);
			} else {
				$track->setPosted(2);
			}

			$track->save();
		}

		if (array_key_exists("status", $config) && $config["status"]) {

			$api_key = $config["api_key"];

			$post_tracks = Mage::getModel('track/track')
				->getCollection()
				->addFieldToFilter('posted', array('eq' => 0))
				->getData();

			$url_params = array("api_key" => $api_key . "");

			foreach ($post_tracks as $track) {

				$url = self::ENDPOINT_TRACKING;
				$url_params["tracking_number"] = $track["tracking_number"];
				$url_params["smses[]"] = $track["telephone"];
				$url_params["emails[]"] = $track["email"];
				$url_params["title"] = $track["title"];
				$url_params["order_id"] = $track["order_id"];
				$url_params["customer_name"] = $shipping_address_data["firstname"] . " " . $shipping_address_data['lastname'];
				$url_params["source"] = "magento";

				$url_params = http_build_query($url_params);

				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $url_params);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
				//curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

				//handle SSL certificate problem: unable to get local issuer certificate issue
				curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); //the SSL is not correct
				curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); //the SSL is not correct
				curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Length: ' . strlen($url_params)));

				$response = curl_exec($ch);

				$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				$error = curl_error($ch);
				curl_close($ch);

				$response_obj = json_decode($response, true);

				//422: repeated
				if ($http_status == "201" || $http_status == "422") {
					$track_obj = Mage::getModel('track/track');
					$track_obj->load($track["track_id"]);
					$track_obj->setPosted(1);
					$track_obj->save();
				}

				//simulate track success post
				/*$track_obj = Mage::getModel('track/track');
				$track_obj->load($track["track_id"]);
				$track_obj->setPosted(1);
				$track_obj->save();*/

			}
		}

		ob_end_clean();
	}

	public function adminSystemConfigChangedSectionAftership(Varien_Event_Observer $observer)
	{
		$post_data = Mage::app()->getRequest()->getPost();

		//$website 	= Mage::app()->getRequest()->getParam('website');
		//$scope		= "websites";
		//$score_id	= (int)Mage::getConfig()->getNode('websites/'.$website.'/system/website/id');

		if (!isset($post_data["groups"]["messages"]["fields"]["api_key"]['inherit']) || $post_data["groups"]["messages"]["fields"]["api_key"]['inherit'] != 1) {
			$api_key = $post_data["groups"]["messages"]["fields"]["api_key"]["value"];

			$url_params = array(
				"api_key" => $api_key
			);
			$url = self::ENDPOINT_CONFIG;

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($url_params));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			//handle SSL certificate problem: unable to get local issuer certificate issue
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); //the SSL is not correct
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); //the SSL is not correct

			$response = curl_exec($ch);
			$error = curl_error($ch);
			$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			curl_close($ch);
			$response_obj = json_decode($response, true);

			if ($http_status != "200" && $http_status != "401") {
				Mage::throwException(Mage::helper('adminhtml')->__("Connection error, please try again later."));
			} else {
				if (!$response_obj["success"]) {
					Mage::throwException(Mage::helper('adminhtml')->__("Incorrect API Key"));
				}
			}
		}
	}

	/*Mage::getModel('core/config')->saveConfig('aftership_options/messages/status', 0);
	Mage::getModel('core/config_data')
		->setScope($scope)
		->setScopeId($score_id)
		->setPath('aftership_options/messages/status')
		->setValue(0)
		->save();*/

	/*
	 *
	 * Cron
	 *
	 */

	public function sendTracking($api_key, $tracking_number, $carrier_code, $telephone, $email, $title, $order_id, $customer_name) {
		//echo $tracking_number.', '.$telephone.', '.$email.', '.$title.', '.$order_id.', '.$customer_name;

		//save to table
		$exist_track_data = Mage::getModel('track/track')
			->getCollection()
			->addFieldToFilter('tracking_number', array('eq' => $tracking_number))
			->addFieldToFilter('order_id', array('eq' => $order_id))
			->getData();

		if (!$exist_track_data) {
			$track = Mage::getModel('track/track')
				->setTrackingNumber($tracking_number)
				->setShipCompCode($carrier_code)
				->setTitle($title)
				->setOrderId($order_id)
				->setEmail($email)
				->setTelephone($telephone)
				->setPosted(0)
				->save();

			$track_id = $track->getTrackId();
		} else {
			$track = $exist_track_data[0];
			$track_id = $track['track_id'];
		}

		//send
		$url = self::ENDPOINT_TRACKING;

		$url_params = array();

		$url_params["api_key"] = (string)$api_key;
		$url_params["tracking_number"] = $tracking_number;
		$url_params["smses[]"] = $telephone;
		$url_params["emails[]"] = $email;
		$url_params["title"] = $title;
		$url_params["order_id"] = $order_id;
		$url_params["customer_name"] = $customer_name;
		$url_params["source"] = "magento";

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($url_params));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0); //the SSL is not correct
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); //the SSL is not correct
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Length: ' . strlen($url_params)));

		$response = curl_exec($ch);
		$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);

		$response_obj = json_decode($response, true);

		//save, 422: repeated
		if ($http_status == "201" || $http_status == "422") {
			Mage::getModel('track/track')
				->load($track_id)
				->setPosted(1)
				->save();
		}
	}

	public function cron() {

		set_time_limit(0);

		$global_config = Mage::getStoreConfig('aftership_options/messages');

		$last_update = $global_config['last_update'];

		//load website config
		$website_config = array();

		//if there if only 1 store
		if (count(Mage::app()->getWebsites()) == 1) {
			$website_config[1] = $this->getDefaultConfig();
		} else {
			foreach (Mage::app()->getWebsites() as $website) {
				$website_config[$website->getId()] = $this->getConfigByWebsiteId($website->getId());
			}
		}

		$from = '';
		$to = '';

		$debug_range = 1;

		if ($last_update == '0' || !$last_update) {
			$from = gmdate('Y-m-d H:i:s', time() - 3 * 60 * 60 * $debug_range); //past 3 hours
			$to = gmdate('Y-m-d H:i:s');
		} else {
			$from = gmdate('Y-m-d H:i:s', $last_update);
			$to = gmdate('Y-m-d H:i:s');
		}

		//echo "from: ".$from.", to: ".$to."\n";

		$track_collection = Mage::getResourceModel('sales/order_shipment_track_collection')
			->addAttributeToFilter('created_at', array(
				'from' => $from,
				'to' => $to,
			))
			->addAttributeToSort('created_at', 'asc');

		foreach ($track_collection as $track) {
			//echo "order id: ".$track->getOrderId().", tracking number: ".$track->getTrackNumber().", created at: ".$track->getCreatedAt()."\n";

			$order_shipment = $track->getShipment();

			$store_id = $order_shipment->getStoreId();

			//if website enabled
			if (isset($website_config[$store_id]['status']) && $website_config[$store_id]['status'] == 1 &&
				isset($website_config[$store_id]['cron_job_enable']) && $website_config[$store_id]['cron_job_enable'] == 1) {
				//check with existing as_track table and see if sent
				$post_tracks = Mage::getModel('track/track')
					->getCollection()
					->addFieldToFilter('posted', array('eq' => 1))
					->addFieldToFilter('tracking_number', array('eq' => $track->getTrackNumber()))
					->getData();

				//if not sent
				if (count($post_tracks) == 0) {
					$order = $order_shipment->getOrder();
					$shipping_address = $order->getShippingAddress();

					$api_key = $website_config[$store_id]['api_key'];
					$telephone = $shipping_address->getTelephone();
					$carrier_code = $track->getCarrierCode();
					$email = $order->getCustomerEmail();
					$title = $order->getIncrementId();
					$order_id = $order->getIncrementId();
					$customer_name = $shipping_address->getFirstname() . " " . $shipping_address->getLastname();

					$this->sendTracking($api_key, $track->getTrackNumber(), $carrier_code, $telephone, $email, $title, $order_id, $customer_name);
				}
			}
		}

		//update time ONLY if success
		Mage::getModel('core/config')->saveConfig('aftership_options/messages/last_update', time());

		foreach (Mage::app()->getWebsites() as $website) {
			$website_id = $website->getId();
			$scope = "websites";
			$scope_id = (int)Mage::getConfig()->getNode('websites/' . Mage::app()->getWebsite($website_id)->getCode() . '/system/website/id');

			Mage::getModel('core/config_data')
				->setScope($scope)
				->setScopeId($scope_id)
				->setPath('aftership_options/messages/last_update')
				->setValue(time())
				->save();
		}
	}

	private function getDefaultConfig() {
		return Mage::getStoreConfig('aftership_options/messages');
	}

	private function getConfigByWebsiteId($website_id) {
		$config_obj = Mage::app()->getWebsite($website_id)->getConfig('aftership_options');
		$config = array();
		$config['api_key']			= $config_obj['messages']->api_key.'';
		$config['status']			= $config_obj['messages']->status.'';
		$config['cron_job_enable']	= $config_obj['messages']->cron_job_enable.'';
		return $config;
	}
}