<?php

declare(strict_types=1);

include __DIR__ . '/../libs/protobuf.php';

class ChromecastReceiver extends IPSModule {
	//private $requestId = 0;
	//private $transportId = "";
	//private $sessionId = "";
	//private $mediaSessionId = 0;
	//private $lastActiveTime;

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

		$this->RegisterMessage($this->InstanceID, IPS_KERNELMESSAGE);

		if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->Init();
        }
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

		$this->UpdateBuffer('RequestId', 0);
		$this->UpdateBuffer('TransportId', '');
		$this->UpdateBuffer('SessionId', '');
		$this->UpdateBuffer('MediaSessionId', 0);
		$this->UpdateBuffer('Message', '');
		$this->UpdateBuffer('LastActiveTime', time());

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

		//$time = time();
		//$this->UpdateBuffer('LastActiveTime', $time);
		//$this->SendDebug(__FUNCTION__, sprintf('Updated "LastActiveTime". New value is %d', $time),0);
		
		$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($msg->encode())]));
		$this->SendDebug(__FUNCTION__, ' CONNECT was sent to the device', 0);

	}

	private function ConnectDeviceTransport() {
		$transportId = $this->FetchBuffer('TransportId');
		$msg = new CastMessage();
		$msg->source_id = "sender-0";
		$msg->receiver_id = $transportId;
		$msg->urnnamespace = "urn:x-cast:com.google.cast.tp.connection";
		$msg->payloadtype = 0;
		$msg->payloadutf8 = '{"type":"CONNECT"}';

		//$time = time();
		//$this->UpdateBuffer('LastActiveTime', $time);
		//$this->SendDebug(__FUNCTION__, sprintf('Updated "LastActiveTime". New value is %d', $time),0);
		
		$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($msg->encode())]));
		$this->SendDebug(__FUNCTION__, ' CONNECT with TransportId was sent to the device', 0);

	}

	public function GetDeviceStatus() {
		$msg = new CastMessage();
		$msg->source_id = "sender-0";
		$msg->receiver_id = "receiver-0";
		$msg->urnnamespace = "urn:x-cast:com.google.cast.receiver";
		$msg->payloadtype = 0;
		$value = $this->FetchBuffer('RequestId');
		$requestId=$value!==false?$value:0;
		$msg->payloadutf8 = sprintf('{"type":"GET_STATUS","requestId":%d}', $requestId);
		
		//$time = time();
		//$this->UpdateBuffer('LastActiveTime', $time);
		//$this->SendDebug(__FUNCTION__, sprintf('Updated "LastActiveTime". New value is %d', $time),0);

		$requestId++;
		$this->UpdateBuffer('RequestId', $requestId);
						
		$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($msg->encode())]));
		$this->SendDebug(__FUNCTION__, sprintf('GET_STATUS was sent to the receiver with RequestId %d', $requestId), 0);
	}

	public function GetMediaStatus() {
		$msg = new CastMessage();
		$msg->source_id = 'sender-0';
		$msg->receiver_id = 'receiver-0';
		$msg->urnnamespace = 'urn:x-cast:com.google.cast.media';
		$msg->payloadtype = 0;
		$value = $this->FetchBuffer('RequestId');
		$requestId=$value!==false?$value:0;
		$msg->payloadutf8 = sprintf('{"type":"GET_STATUS","requestId":%d}', $requestId);
		
		//$time = time();
		//$this->UpdateBuffer('LastActiveTime', $time);
		//$this->SendDebug(__FUNCTION__, sprintf('Updated "LastActiveTime". New value is %d', $time),0);

		$requestId++;
		$this->UpdateBuffer('RequestId', $requestId);
						
		$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($msg->encode())]));
		$this->SendDebug(__FUNCTION__, sprintf('GET_STATUS (media) was sent to the receiver with RequestId %d', $requestId), 0);
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

		//$newLastActiveTime = time();
		//$oldLastActiveTime = $this->FetchBuffer('LastActiveTime');
		//$this->UpdateBuffer('LastActiveTime', $newLastActiveTime);
		//$this->SendDebug(__FUNCTION__, sprintf('Updated "LastActiveTime". New value is %d', $newLastActiveTime),0);

		$oldMessage = $this->FetchBuffer('Message');
		//$this->SendDebug(__FUNCTION__, $buffer, 0);
		if(strlen($oldMessage) > 0) {
			$buffer = $oldMessage . utf8_decode($data->Buffer);
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
			} else if(strlen($oldMessage) > 0) {
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
						
						$newLastActiveTime = time();
						$oldLastActiveTime = $this->FetchBuffer('LastActiveTime');

						if($newLastActiveTime - $oldLastActiveTime > 10) {
							$this->SendDebug(__FUNCTION__, sprintf('Old "LastActiveTime" (%d) is too old. Initiazing to reconnect to the device...', $oldLastActiveTime),0);
							$this->Init();
						} else {
							$this->UpdateBuffer('LastActiveTime', $newLastActiveTime);
							$this->SendDebug(__FUNCTION__, sprintf('Updated "LastActiveTime". New value is %d', $newLastActiveTime),0);
						}
						break;
					case 'receiver_status':
						$this->SendDebug(__FUNCTION__, 'Analyzing "RECEIVER_STATUS"...', 0);
						
						if(isset($data->status->applications[0]->sessionId)) {
							$sessionId = $data->status->applications[0]->sessionId;
							$this->UpdateBuffer('SessionId', $sessionId);
							$this->SendDebug(__FUNCTION__, sprintf('SessionId is "%s"', $sessionId), 0);
						}

						if(isset($data->status->applications[0]->transportId)) {
							$oldTransportId = $this->FetchBuffer('TransportId');
							$newTransportId = $data->status->applications[0]->transportId;
							
							$this->UpdateBuffer('TransportId', $newTransportId);
							$this->SendDebug(__FUNCTION__, sprintf('TransporId is "%s"', $newTransportId), 0);

							if($oldTransportId!=$newTransportId) {
								$this->SendDebug(__FUNCTION__, 'TransportId has changed. Connecting using new Id...', 0);
								$this->ConnectDeviceTransport();
							}
						}
						break;
					case 'media_status':
						$this->SendDebug(__FUNCTION__, 'Analyzing "MEDIA_STATUS"...', 0);
						if(isset($data->status[0]->mediaSessionId)) {
							$mediaSessionId = $data->status[0]->mediaSessionId;
							$this->UpdateBuffer('MediaSessionId', $mediaSessionId);
							$this->SendDebug(__FUNCTION__, sprintf('MediaSessionId is %d', $mediaSessionId), 0);
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

	private function UpdateBuffer(string $Name, $Value) {
		if($this->Lock($Name)) {
			$this->SetBuffer($Name, json_encode($Value));
			$this->SendDebug(__FUNCTION__, sprintf('Updated "%s"',$Name), 0);
			$this->Unlock($Name);
		} else {
			$msg = sprintf('Failed to Update "%s"',$Name);
			$this->LogMessage($msg, KL_ERROR);
			$this->SendDebug(__FUNCTION__, $msg, 0);
		}
	}

	private function FetchBuffer(string $Name) {
		if($this->Lock($Name)) {
			$value = $this->GetBuffer($Name);
			//$this->SendDebug(__FUNCTION__, sprintf('Fetched "%s"',$Name), 0);
			$this->Unlock($Name);
			return json_decode($value);
		} else {
			$msg = sprintf('Failed to Fetch "%s"',$Name);
			//$this->LogMessage($msg, KL_ERROR);
			$this->SendDebug(__FUNCTION__, $msg, 0);
			return false;
		}
	}

	private function Lock(string $Name) {
		//$this->SendDebug(__FUNCTION__, sprintf('Locking "%s"...',$Name), 0);
        for ($i = 0; $i < 100; $i++){
            if (IPS_SemaphoreEnter(sprintf('%s%s',(string)$this->InstanceID,$Name), 1)){
				//$this->SendDebug(__FUNCTION__, sprintf('"%s" is locked',$Name), 0);
                return true;
            } else {
                //$this->SendDebug(__FUNCTION__, 'Waiting for lock...', 0);
				IPS_Sleep(mt_rand(1, 5));
            }
        }
        return false;
    }

    private function Unlock(string $Name) {
        IPS_SemaphoreLeave(sprintf('%s%s',(string)$this->InstanceID,$Name));
		//$this->SendDebug(__FUNCTION__, sprintf('Unlocked "%s"', $Name), 0);
    }
}
