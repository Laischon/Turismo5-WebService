<?php
	/**
	 * 2020-07-31
	 * TEST PAGE: https://q-rer.turitweb.it/   >   dataentry3:dataentry
	 * REGION: Emilia-Romagna > https://statistica.regione.emilia-romagna.it/documentazione/rilevazioni/turismo
	 */

	class Turismo5{

		private $username;
		private $password;
		private $wsdl = "https://datiturismo.regione.emilia-romagna.it/ws/checkinV2";
	//	private $wsdl = "https://q-rer.turitweb.it/ws/checkinV2"; //use it for dev enviroment

		function __construct($username, $password){
			$this->username = $username;
			$this->password = $password;
		}


		private function success($data){
			return array("success"=>true, "data"=>$data);
		}

		private function error($message){
			return array("success"=>false, "error"=>$message);
		}

		private function getAuthorizationKey(){
			return base64_encode($this->username.':'.$this->password);
		}

		public function inviaMovimentazione($xml){
			$authorization_key = $this->getAuthorizationKey();
			$xml = explode("\n", $xml);
			array_shift($xml);
			$xml = implode("\n", $xml);

			$xml = str_replace('movimenti','movimentazione', $xml);
			$xml = '<S:Envelope xmlns:S="http://schemas.xmlsoap.org/soap/envelope/">
							<S:Body>
							<ns2:inviaMovimentazione
							xmlns:ns2="http://checkin.ws.service.turismo5.gies.it/">
								'.$xml.'
							</ns2:inviaMovimentazione>
							</S:Body>
							</S:Envelope> ';

			$curl = curl_init();

			curl_setopt_array($curl, array(
				CURLOPT_URL => $this->wsdl,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_ENCODING => "",
				CURLOPT_MAXREDIRS => 10,
				CURLOPT_TIMEOUT => 0,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST => "POST",
				CURLOPT_POSTFIELDS => $xml,
				CURLOPT_HTTPHEADER => array(
					"Authorization: Basic ".$authorization_key,
					"Content-Type: text/xml; charset=utf-8"
				),
			));

			$response = curl_exec($curl);
			/*
			echo "<pre>";
			print_r($response);
			echo "</pre>";
			*/
			curl_close($curl);
			$response = preg_replace("/(<\/?)(\w+):([^>]*>)/", "$1$2$3", $response);
			$xml = simplexml_load_string($response);
			$json = json_encode($xml);
			$responseArray = json_decode($json,true);
			return $this->parseResponse($responseArray);
		}


		public function parseResponse($response = array()){
			$errors = array();

			if(!empty($response['soapBody'])){
				if(!empty($response['soapBody']['ns2inviaMovimentazioneResponse'])){
					if(!empty($response['soapBody']['ns2inviaMovimentazioneResponse']['return'])){
						if(!empty($response['soapBody']['ns2inviaMovimentazioneResponse']['return']['risultatiGiorno'])){
							foreach ($response['soapBody']['ns2inviaMovimentazioneResponse']['return']['risultatiGiorno'] as $day => $dailyReport) {
								if(!empty($dailyReport['arrivi'])){
									foreach ($dailyReport['arrivi']['arrivo'] as $arrival) {
										if(!empty($arrival['successo'])){
											$errors[] = array(
												'type' => 'ARRIVAL',
												'day' => $day,
												'idswh' => !empty($arrival['idswh'])?$arrival['idswh']:'',
												'error' => !empty($arrival['errore'])?$arrival['errore']:'',
											);
										}
									}
								}
								if(!empty($dailyReport['partenze'])){
									foreach ($dailyReport['partenze']['partenza'] as $departure) {
									//	sendTelegramSystemNotification(json_encode($departure,true));die();
										if(!empty($departure['errore'])){
											$errors[] = array(
												'type' => 'DEPARTURE',
												'day' => $day,
												'idswh' => !empty($departure['idswh'])?$departure['idswh']:'',
												'error' => !empty($departure['errore'])?$departure['errore']:'',
											);
										}
									}
								}
							}
						}
					}
				}
			}

			if(empty($errors)) return $this->success($response);
			return $this->error($errors);
		}





	}

?>
