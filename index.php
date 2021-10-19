<?php
/*
	TODO:
	Transaction id?
	
	Multiple orders?
		
	Change format in enventa
	Check payment methods in enventa
*/
require_once('config.php');

//Only do something, if the csv not actually exists yet
if(!file_exists($csvPath)){
	$currentOrder = getCurrentOrderNumber($currentOrderPath);

	$access_token =  getAccessToken($shopUrl, $clientId, $clientSecret);

	$csv = generateCsvInformation($shopUrl, $access_token, $currentOrder);
	
	if($csv !== null){
		writeCsvToFile($csvPath, $csv);
	
		countOrderNumberUpwards($currentOrderPath, $currentOrder);

		echo $csv;
	}
}
else{
	echo "CSV already exists!";
}

	
	

/*
*
*/
function getAccessToken($shopUrl, $clientId, $clientSecret){
	$curl = curl_init();
	
	curl_setopt_array($curl, [
	  CURLOPT_URL => $shopUrl . "/oauth/token",
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => "",
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => 30,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => "POST",
	  CURLOPT_POSTFIELDS => 
	  '{ 
		"grant_type": "client_credentials",
		"client_id":"' . $clientId . '",
		"client_secret":"' . $clientSecret . '"	
	  }',
	  CURLOPT_HTTPHEADER => [
		"Content-Type: application/json"
	  ],
	]);
	
	$response = curl_exec($curl);
	$err = curl_error($curl);

	curl_close($curl);

	if ($err) {
		echo "cURL Error #:" . $err;
	} else {
		return json_decode($response, true)["access_token"];
	}
}



/*
*
*/
function generateCsvInformation($shopUrl, $access_token, $currentOrder){	
	//General order info
	$order = getEntityFromAPI($shopUrl, $access_token, "orderNumber", $currentOrder, "/search/order");
	
	//If the order exists, start generating the csv content
	if(sizeof($order["data"]) > 0){
		$csv = "";
		
		$orderId = $order["included"][1]["attributes"]["orderId"];
		$billingAddressId = $order["data"][0]["attributes"]["billingAddressId"];
		
		//Shipping address
		$shippingAddress = getEntityFromAPI($shopUrl, $access_token, "orderId", $orderId, "/search/order-address");
		
		//Billing address
		$billingAddress = getEntityFromAPI($shopUrl, $access_token, "id", $billingAddressId, "/search/order-address");
		
		//Order items
		$orderLineItem = getEntityFromAPI($shopUrl, $access_token, "orderId", $orderId, "/search/order-line-item");
		
		//Order transaction
		$orderTransaction = getEntityFromAPI($shopUrl, $access_token, "orderId", $orderId, "/search/order-transaction");
		$paymentMethodId = $orderTransaction["data"][0]["attributes"]["paymentMethodId"];
		
		$paymentMethod = getEntityFromAPI($shopUrl, $access_token, "id", $paymentMethodId, "/search/payment-method");

		$billingCountry = getEntityFromAPI($shopUrl, $access_token, "id", $billingAddress["data"][0]["attributes"]["countryId"], "/search/country");
		$shippingCountry = getEntityFromAPI($shopUrl, $access_token, "id", $shippingAddress["data"][0]["attributes"]["countryId"], "/search/country");
		
		//Add headline to list
		$csv .= generateHeadline();
		
		//Generate a csv line for each item bought
		foreach($orderLineItem["data"] as $item){
			/* OrderId */ 				$csv .= $order["data"][0]["attributes"]["orderNumber"] . ";"; 
			/* Net price total */		$csv .= $order["data"][0]["attributes"]["price"]["totalPrice"] . ";";
			/* Article number */		$csv .= $item["attributes"]["payload"]["productNumber"] . ";";
			/* Article price */			$csv .= $item["attributes"]["unitPrice"] . ";";
			/* Article quantity */		$csv .= $item["attributes"]["quantity"] . ";";
			/* Payment type */			$csv .= $paymentMethod["data"][0]["attributes"]["name"] . ";";
			/* Shipping costs */		$csv .= $order["data"][0]["attributes"]["shippingCosts"]["totalPrice"] . ";";
			/* Billing firm/user */		
			if($billingAddress["data"][0]["attributes"]["company"] !== null){ 
				$csv .= $billingAddress["data"][0]["attributes"]["company"] . " "; 
			}
			$csv .= $billingAddress["data"][0]["attributes"]["firstName"] . " ";
			$csv .= $billingAddress["data"][0]["attributes"]["lastName"] . ";";
			/* Billing street */		$csv .= $billingAddress["data"][0]["attributes"]["additionalAddressLine1"] . ";";
			/* Billing street */		$csv .= $billingAddress["data"][0]["attributes"]["additionalAddressLine2"] . ";";
			/* Billing street */		$csv .= $billingAddress["data"][0]["attributes"]["street"] . ";";
			/* Billing zip code */		$csv .= $billingAddress["data"][0]["attributes"]["zipcode"] . ";";
			/* Billing city */			$csv .= $billingAddress["data"][0]["attributes"]["city"] . ";";
			/* Billing country */		$csv .= $billingCountry["data"][0]["attributes"]["name"] . ";";
			/* Billing country code */	$csv .= $billingCountry["data"][0]["attributes"]["iso"] . ";";
			if($shippingAddress["data"][0]["attributes"]["company"] !== null){ 
				$csv .= $shippingAddress["data"][0]["attributes"]["company"] . " "; 
			}
			$csv .= $shippingAddress["data"][0]["attributes"]["firstName"] . " ";
			$csv .= $shippingAddress["data"][0]["attributes"]["lastName"] . ";";
			/* Shipping street */		$csv .= $shippingAddress["data"][0]["attributes"]["additionalAddressLine1"] . ";";
			/* Shipping street */		$csv .= $shippingAddress["data"][0]["attributes"]["additionalAddressLine2"] . ";";
			/* Shipping street */		$csv .= $shippingAddress["data"][0]["attributes"]["street"] . ";";
			/* Shipping zip code */		$csv .= $shippingAddress["data"][0]["attributes"]["zipcode"] . ";";
			/* Shipping city */			$csv .= $shippingAddress["data"][0]["attributes"]["city"] . ";";
			/* Shipping country */		$csv .= $shippingCountry["data"][0]["attributes"]["name"] . ";";
			/* Shipping country code */	$csv .= $shippingCountry["data"][0]["attributes"]["iso"] . ";";
			/* Customer e-mail */		$csv .= $order["included"][1]["attributes"]["email"] . ";";
			/* Payment TransactionId */	$csv .= ";"; //TODO! - Find out, where this information is hidden
			/* Customer phone number */	$csv .= $shippingAddress["data"][0]["attributes"]["phoneNumber"];
			/* New line */				$csv .= "\r\n";
		}
		
		return $csv;
	}
	else{
		echo "No new orders!";
		return null;
	}
}



/*
*
*/
function writeCsvToFile($csvPath, $csv){
	$fp = fopen($csvPath, 'w');
	fwrite($fp, $csv);
	fclose($fp);
}



/*
*
*/
function generateHeadline(){
	$headline = 'Bestellung; Nettowert;';
	$headline .= 'Artikelnr; Preis; Anzahl;';
	$headline .= 'BillingFirma; BillingAdd1; BillingAdd2; BillingStrasse; BillingPLZ; BillingOrt; BillingLand; BillingLKZ;';
	$headline .= 'ShippingFirma; ShippingAdd1; ShippingAdd2; ShippingStrasse; ShippingPLZ; ShippingOrt; ShippingLand; ShippingLKZ;';
	$headline .= 'Mail; TransactionId; Phone';
	$headline .= "\r\n";
	
	return $headline;
}




/*
*
*/
function getCurrentOrderNumber($currentOrderPath){
	return file_get_contents($currentOrderPath);
}



/*
*
*/
function countOrderNumberUpwards($currentOrderPath, $currentOrder){
	$fp = fopen($currentOrderPath, 'w');
	fwrite($fp, $currentOrder+1);
	fclose($fp);
}


/*
*
*/
function getEntityFromAPI($shopUrl, $access_token, $filterName, $filterValue, $endPoint){	
	$curl = curl_init();

	curl_setopt_array($curl, [
	  CURLOPT_URL => $shopUrl . $endPoint,
	  CURLOPT_RETURNTRANSFER => true,
	  CURLOPT_ENCODING => "",
	  CURLOPT_MAXREDIRS => 10,
	  CURLOPT_TIMEOUT => 30,
	  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	  CURLOPT_CUSTOMREQUEST => "POST",
	  CURLOPT_POSTFIELDS => 
	  '{ 
		"page": "1",
		"limit": "999",
		"filter": {
			"' . $filterName . '":"' . $filterValue . '"
		}
	  }',
	  CURLOPT_HTTPHEADER => [
		"Authorization: Bearer " . $access_token,
		"Content-Type: application/json"
	  ],
	]);

	$response = curl_exec($curl);
	$err = curl_error($curl);

	curl_close($curl);

	if ($err) {
		echo "cURL Error #:" . $err;
	} else {
		return json_decode($response, true);
	}
}