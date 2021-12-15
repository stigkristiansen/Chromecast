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

		$this->RegisterMessage($this->InstanceID, IPS_KERNELMESSAGE);
	}

	public function MessageSink($TimeStamp, $SenderID, $Message, $Data) {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

		$this->SendDebug(__FUNCTION__, sprintf('Received a message: %d - %d - %d', $SenderID, $Message, $data[0]), 0);

		if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
            $this->SendDebug(__FUNCTION__, 'Detected "Kernel Ready"! Initialzing...', 0);
			$this->Init();
		}
    }

	private function Init() {
		$this->SetTimerInterval('PingPong', 5000);

		$requestId = 0;
		$transportId = "";
		$sessionId = "";

		$this->ConnectDevice();
		$this->GetDeviceStatus();
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
		
	}

	private function ConnectDevice() {
		$msg = new CastMessage();
		$msg->source_id = "sender-0";
		$msg->receiver_id = "receiver-0";
		$msg->urnnamespace = "urn:x-cast:com.google.cast.tp.connection";
		$msg->payloadtype = 0;
		$msg->payloadutf8 = '{"type":"CONNECT"}';

		$time = time();
		$this->UpdateBuffer('LastActiveTime', json_encode($time));
		$this->SendDebug(__FUNCTION__, sprintf('Updated "LastActiveTime". New value is %d', $time),0);
		
		$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($msg->encode())]));
		$this->SendDebug(__FUNCTION__, ' CONNECT was sent to the device', 0);

	}

	private function ConnectDeviceTransport() {
		$msg = new CastMessage();
		$msg->source_id = "sender-0";
		$msg->receiver_id = $this->transportId;
		$msg->urnnamespace = "urn:x-cast:com.google.cast.tp.connection";
		$msg->payloadtype = 0;
		$msg->payloadutf8 = '{"type":"CONNECT"}';

		$time = time();
		$this->UpdateBuffer('LastActiveTime', json_encode($time));
		$this->SendDebug(__FUNCTION__, sprintf('Updated "LastActiveTime". New value is %d', $time),0);
		
		$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($msg->encode())]));
		$this->SendDebug(__FUNCTION__, ' CONNECT with TransportId was sent to the device', 0);

	}

	private function GetDeviceStatus() {
		$msg = new CastMessage();
		$msg->source_id = "sender-0";
		$msg->receiver_id = "receiver-0";
		$msg->urnnamespace = "urn:x-cast:com.google.cast.receiver";
		$msg->payloadtype = 0;
		$msg->payloadutf8 = '{"type":"GET_STATUS","requestId":' . $this->requestId . '}';
		
		$time = time();
		$this->UpdateBuffer('LastActiveTime', json_encode($time));
		$this->SendDebug(__FUNCTION__, sprintf('Updated "LastActiveTime". New value is %d', $time),0);

		$this->requestId++;
						
		$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($msg->encode())]));
		$this->SendDebug(__FUNCTION__, sprintf('GET_STATUS was sent to the receiver with RequestId %d', $this->requestId), 0);

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

		$buffer = $this->FetchBuffer('Message');
		if(strlen($buffer) > 0) {
			$buffer .= utf8_decode($data->Buffer);
		} else {
			$buffer = utf8_decode($data->Buffer);
		}

		$handleData = false;
		if(preg_match("/urn:x-cast:com.google.cast.receiver/s", $buffer)) {
			$handleData = true;
		} else if(preg_match("/urn:x-cast:com.google.cast.media/s", $buffer)) {
			$handleData = true;
		} else if(preg_match("/urn:x-cast:com.google.cast.tp.heartbeat/s", $buffer)) {
			$handleData = true;
		}

		if(!$handleData) {
			$this->SendDebug(__FUNCTION__, 'Incoming namespace is not handled', 0);	
			$this->UpdateBuffer('Message', '');
			return;
		}

		$regex = '/(\{(?:(?>[^{}"\/]+)|(?>"(?:(?>[^\\"]+)|\\.)*")|<(?>\/\*.*?\*\/)|(?-1))*\})/';
		preg_match($regex, $buffer, $result);

		if(count($result)>0) {
			$data = json_decode($result[0]);

			if($data===null) {
				$this->SendDebug(__FUNCTION__, 'Incoming data is not complete. Saving the data for later usage...', 0);	
				$this->UpdateBuffer('Message', $buffer);
				return;
			} else {
				$this->UpdateBuffer('Message', '');
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

						if(time() - json_decode($this->GetBuffer('LastActiveTime')) > 10) {
							$this->Init();
						} else {
							$time = time();
							$this->UpdateBuffer('LastActiveTime', json_encode($time));
							$this->SendDebug(__FUNCTION__, sprintf('Updated "LastActiveTime". New value is %d', $time),0);
						}
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
						if(isset($data->status[0]->playerState)) {
							$this->SendDebug(__FUNCTION__, sprintf('PlayerState is "%s"', $data->status[0]->playerState), 0);
						}
						break;
				}
			} 
			
		} else {
			$this->SendDebug(__FUNCTION__, 'Incoming data is not complete. Saving the data for later usage...', 0);

			$this->UpdateBuffer('Message', $buffer);
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
		$regEx .= '\[(?:(?1)(?:,(?1))*)?\s*\]|'; //arrays
		$regEx .= '\{(?:\s*'.$regExString.
		'\s*:(?1)(?:,\s*'.$regExString.
		'\s*:(?1))*)?\s*\}'; //objects
		$regEx .= ')\Z/is';

		return $regEx;
	}

	private function UpdateBuffer(string $Name, string $Value) {
		if($this->Lock($Name)) {
			$this->SetBuffer($Name, $Value);
			$this->Unlock($Name);
		}
	}

	private function FetchBuffer(string $Name, string $Value) {
		if($this->Lock($Name)) {
			$value = $this->GetBuffer($Name);
			$this->Unlock($Name);
			return $value;
		}
	}

	private function Lock(string $Name) {
		$this->SendDebug(__FUNCTION__, sprintf('Locking %s...',$Name), 0);
        for ($i = 0; $i < 100; $i++){
            if (IPS_SemaphoreEnter(sprintf('%s%s',(string)$this->InstanceID,$Name), 1)){
				$this->SendDebug(__FUNCTION__, sprintf('Locked %s',$Name), 0);
                return true;
            } else {
                $this->SendDebug(__FUNCTION__, 'Waiting for lock...', 0);
				IPS_Sleep(mt_rand(1, 5));
            }
        }
        return false;
    }

    private function Unlock(string $Name) {
        IPS_SemaphoreLeave(sprintf('%s%s',(string)$this->InstanceID,$Name));
		$this->SendDebug(__FUNCTION__, sprintf('Unlocked %s', $Name), 0);
    }
}
