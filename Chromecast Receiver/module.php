<?php

declare(strict_types=1);

include __DIR__ . '/../libs/protobuf.php';

class ChromecastReceiver extends IPSModule {
	private $requestId = 0;
	private $transportId = "";
	private $sessionId = "";
	private $mediaSessionId = 0;
	private $lastActiveTime;

	public function Create() {
		//Never delete this line!
		parent::Create();

		$this->ForceParent('{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}');

		$this->RegisterTimer('PingPong', 0, 'IPS_RequestAction(' . (string)$this->InstanceID . ', "PingPong", 0);'); 

		$this->RegisterMessage(0, IPS_KERNELMESSAGE);
	}

	public function Destroy() {
		//Never delete this line!
		parent::Destroy();
	}

	public function ApplyChanges() {
		//Never delete this line!
		parent::ApplyChanges();

		if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->Init();
        }
	}

	public function MessageSink($TimeStamp, $SenderID, $Message, $Data) {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) 
            $this->Init();
    }

	private function Init() {
		$this->SetTimerInterval('PingPong', 5000);

		$this->ConnectDevice();
		$this->GetDeviceStatus();
		//$this->GetMediaStatus();
	}

	public function RequestAction($Ident, $Value) {
		switch (strtolower($Ident)) {
			case 'pingpong':
				$this->SendDebug(__FUNCTION__, 'Sending PING to device...', 0);
				$this->SendPingPong('PING');
				break;
		}
	}

	private function SendPingPong(string $Type) {
		$msg = new CastMessage();
		$msg->source_id = "sender-0";
		$msg->receiver_id = "receiver-0";
		$msg->urnnamespace = "urn:x-cast:com.google.cast.tp.heartbeat";
		$msg->payloadtype = 0;
		$msg->payloadutf8 = '{"type":"'.$Type.'"}';
	
		$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($msg->encode())]));
		$this->SendDebug(__FUNCTION__, $Type . ' was sent', 0);

		$this->lastActiveTime = time();		
	}

	private function ConnectDevice() {
		// CONNECT TO CHROMECAST
		// This connects to the chromecast in general.
		// Generally this is called by launch($appid) automatically upon launching an app
		// but if you want to connect to an existing running application then call this first,
		// then call getStatus() to make sure you get a transportid.
		
		$msg = new CastMessage();
		$msg->source_id = "sender-0";
		$msg->receiver_id = "receiver-0";
		$msg->urnnamespace = "urn:x-cast:com.google.cast.tp.connection";
		$msg->payloadtype = 0;
		$msg->payloadutf8 = '{"type":"CONNECT"}';
		
		$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($msg->encode())]));
		$this->SendDebug(__FUNCTION__, ' CONNECT was sent to the device', 0);

		$this->lastActiveTime = time();
				
	}

	private function ConnectDeviceTransport() {
		// This connects to the transport of the currently running app
		// (you need to have launched it yourself or connected and got the status)
		$msg = new CastMessage();
		$msg->source_id = "sender-0";
		$msg->receiver_id = $this->transportId;
		$msg->urnnamespace = "urn:x-cast:com.google.cast.tp.connection";
		$msg->payloadtype = 0;
		$msg->payloadutf8 = '{"type":"CONNECT"}';
		
		$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($msg->encode())]));
		$this->SendDebug(__FUNCTION__, ' CONNECT with TransportId was sent to the device', 0);

		$this->lastActiveTime = time();
		$this->requestId++;
	}

	private function GetDeviceStatus() {
		// Get the status of the chromecast in general and return it
		// also fills in the transportId of any currently running app
		
		$msg = new CastMessage();
		$msg->source_id = "sender-0";
		$msg->receiver_id = "receiver-0";
		$msg->urnnamespace = "urn:x-cast:com.google.cast.receiver";
		$msg->payloadtype = 0;
		$msg->payloadutf8 = '{"type":"GET_STATUS","requestId":' . $this->requestId . '}';
						
		$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($msg->encode())]));
		$this->SendDebug(__FUNCTION__, sprintf('GET_STATUS was sent to the receiver with RequestId %d', $this->requestId), 0);

		$this->lastActiveTime = time();
		$this->requestId++;
	}

	public function ForwardData($JSONString) {
		$data = json_decode($JSONString);
		$this->SendDebug(__FUNCTION__, 'Received for forwarding: ' . utf8_decode($data->Buffer), 0);
		
		$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => $data->Buffer]));

		return 'String data for device instance!';
	}

	public function ReceiveData($JSONString) {
		$data = json_decode($JSONString);
		$this->SendDebug(__FUNCTION__, 'Received from parent: ' . utf8_decode($data->Buffer), 0);

		$this->lastactivetime = time();

		$buffer = $this->GetBuffer('Message');
		if(strlen($buffer) > 0) {
			$buffer .= utf8_decode($data->Buffer);
		} else {
			$buffer = utf8_decode($data->Buffer);
		}

		$regex = $this->GetRegEx(); /*'/(\{(?:(?>[^{}"\/]+)|(?>"(?:(?>[^\\"]+)|\\.)*")|<(?>\/\*.*?\*\/)|(?-1))*\})/';*/
		preg_match($regex, $buffer, $result);

		if(count($result)>0) {
			$data = json_decode($result[0]);

			if($data===null) {
				$this->SendDebug(__FUNCTION__, 'Incoming data is not complete. Saving the data for later usage...', 0);	
				$this->SetBuffer('Message', $buffer);
			} else {
				$this->SetBuffer('Message', '');
			}

			$this->SendDebug(__FUNCTION__, 'Analyzing data...', 0);
			$this->SendDebug(__FUNCTION__, sprintf('The data is "%s"', $result[0]), 0);

			if(isset($data->type)) {
				switch(strtolower($data->type)) {
					case 'ping':
						$this->SendDebug(__FUNCTION__, 'Sending PONG to device', 0);
						$this->SendPingPong('PONG');
						break;
					case 'pong':
						$this->SendDebug(__FUNCTION__, 'Device responded to sent PING', 0);
						break;
					case 'receiver_status':
						$this->SendDebug(__FUNCTION__, 'Analyzing "RECEIVER_STATUS"...', 0);
						
						if(isset($data->status->applications[0]->sessionId)) {
							$this->sessionId = $data->status->applications[0]->sessionId;
							$this->SendDebug(__FUNCTION__, sprintf('SessionId is "%s"', $this->sessionId), 0);
						}

						if(isset($data->status->applications[0]->transportId)) {
							$oldTransportId = $this->transportId;
							$this->transportId = $data->status->applications[0]->transportId;
							$this->SendDebug(__FUNCTION__, sprintf('TransporId is "%s"', $this->transportId), 0);

							if($oldTransportId!=$this->transportId) {
								$this->SendDebug(__FUNCTION__, 'TransportId has changed. Conneting using new Id...', 0);
								$this->ConnectDeviceTransport();
							}
						}
						break;
					case 'media_status':
						$this->SendDebug(__FUNCTION__, 'Analyzing "MEDIA_STATUS"...', 0);
						if(isset($data->status[0]->mediaSessionId)) {
							$this->mediaSessionId = $data->status[0]->mediaSessionId;
							$this->SendDebug(__FUNCTION__, sprintf('MediaSessionId is "%s"', $this->mediaSessionId), 0);
						}
						break;
				}
			} 
			
		} else {
			$this->SendDebug(__FUNCTION__, 'Incoming data is not complete. Saving the data for later usage...', 0);

			$this->SetBuffer('Message', $buffer);
		}


		//$this->SendDataToChildren(json_encode(['DataID' => '{3FBC907B-E487-DC82-2730-11F8CBD494A8}', 'Buffer' => $data->Buffer]));
	}

	private function GetRegEx() {
		$regExString = '"([^"\\\\]*|\\\\["\\\\bfnrt\/]|\\\\u[0-9a-f]{4})*"';
		$regExNumber = '-?(?=[1-9]|0(?!\d))\d+(\.\d+)?([eE][+-]?\d+)?';
		$regExBoolean = 'true|false|null'; // these are actually copied from Mario's answer
		$regEx = '/\A('.$regExString.
		'|'.$regExNumber.
		'|'.$regExBoolean.
		'|'; //string, number, boolean
		$regEx. = '\[(?:(?1)(?:,(?1))*)?\s*\]|'; //arrays
		$regEx. = '\{(?:\s*'.$regExString.
		'\s*:(?1)(?:,\s*'.$regExString.
		'\s*:(?1))*)?\s*\}'; //objects
		$regEx. = ')\Z/is';

		return $regEx;
	}
}
