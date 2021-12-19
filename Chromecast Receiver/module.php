<?php

declare(strict_types=1);

include __DIR__ . '/../libs/protobuf.php';
include __DIR__ . '/../libs/dnssd.php';
include __DIR__ . '/../libs/chromecast.php';
include __DIR__ . '/../libs/buffer.php';

class ChromecastReceiver extends IPSModule {
	use ServiceDiscovery; 
	use Chromecast;
	use Buffer;

	private $dnsSdId;

	public function __construct($InstanceID) {
		parent::__construct($InstanceID);

		$this->dnsSdId = $this->GetDnsSdId(); 
	}

	public function Create() {
		//Never delete this line!
		parent::Create();

		$this->ForceParent('{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}');

		$this->RegisterPropertyInteger('DiscoveryTimeout', 500);
		$this->RegisterPropertyString('Name', '');
				
		$this->RegisterTimer('PingPong', 0, 'IPS_RequestAction(' . (string)$this->InstanceID . ', "PingPong", 0);'); 
		$this->RegisterTimer('CheckIOConfig', 0, 'IPS_RequestAction(' . (string)$this->InstanceID . ', "CheckIOConfig", 0);'); 
		$this->RegisterTimer('DelayedInit', 0, 'IPS_RequestAction(' . (string)$this->InstanceID . ', "DelayedInit", 0);'); 
		$this->RegisterTimer('GetMediaStatus', 0, 'IPS_RequestAction(' . (string)$this->InstanceID . ', "GetMediaStatus", 0);'); 
	}

	public function Destroy() {
		//Never delete this line!
		parent::Destroy();
	}

	public function ApplyChanges() {
		//Never delete this line!
		parent::ApplyChanges();
		
		$this->RegisterMessage(0, IPS_KERNELMESSAGE);

		if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->Init();
        }
	}

	public function MessageSink($TimeStamp, $SenderID, $Message, $Data) {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

		$this->SendDebug(__FUNCTION__, sprintf('Received a message: %d - %d - %d', $SenderID, $Message, $data[0]), 0);

		if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) {
            $this->LogMessage('Detected "Kernel Ready"!', KL_NOTIFY);
			$this->Init();
		}
    }

	private function Init(bool $NewDiscover=true) {
		$msg = 'Initializing...';
		
		$this->LogMessage($msg, KL_NOTIFY);
		$this->SendDebug(__FUNCTION__, $msg, 0);
		
		$this->UpdateBuffer('RequestId', 0);
		$this->UpdateBuffer('TransportId', '');
		$this->UpdateBuffer('SessionId', '');
		$this->UpdateBuffer('MediaSessionId', 0);
		$this->UpdateBuffer('Message', '');
		$this->UpdateBuffer('LastActiveTime', time());

		$command['Command'] = 'Reset';
		$this->SendDataToChildren(json_encode(['DataID' => '{3FBC907B-E487-DC82-2730-11F8CBD494A8}', 'Buffer' => $command]));

		if($NewDiscover) {
			$this->SetTimerInterval('CheckIOConfig', 1000);
			$this->SetTimerInterval('DelayedInit', 10000);
		} else  {
			$this->ConnectDevice();
			$this->GetDeviceStatus();
		}
	}

	private function DelayedInit() {
		$this->SetTimerInterval('DelayedInit', 0);
		$this->SetTimerInterval('PingPong', 5000);
		$this->ConnectDevice();
		$this->GetDeviceStatus();
	}
	
	public function RequestAction($Ident, $Value) {
		switch (strtolower($Ident)) {
			case 'pingpong':
				$this->SendPingPong('PING');
				break;
			case 'checkioconfig':
				$this->CheckIOConfig();
				break;
			case 'delayedinit':
				$this->DelayedInit();
				break;
			case 'handleinstructions':
				$this->HandleInstructions(json_decode(urldecode($Value)));
				break;
			case 'getmediastatus':
				$this->SetTimerInterval('GetMediaStatus', 60000);
				$this->GetMediaStatus();
				break;
		}
	}

	private function CheckIOConfig() {
		$this->SendDebug(__FUNCTION__, 'Checking the configuration of the Chromecast device...', 0);

		$this->SetTimerInterval('CheckIOConfig', 60000*5); // Check config very 5 minutes
		
		$parentId = IPS_GetInstance($this->InstanceID)['ConnectionID'];
		$host = IPS_GetProperty($parentId, 'Host');
		$port = IPS_GetProperty($parentId, 'Port');

		$this->SendDebug(__FUNCTION__, sprintf('Current config of the device is "%s:%s"', $host, $port), 0);

		try {
			$type = '';
			$domain = '';
			$found = false;
			$name = $this->ReadPropertyString('Name');

			$this->SendDebug(__FUNCTION__, sprintf('Searching for "%s"...', $name), 0);

			$services = @ZC_QueryServiceTypeEx($this->dnsSdId, "_googlecast._tcp", "", $this->ReadPropertyInteger('DiscoveryTimeout'));
			if($services!==false) {
				foreach($services as $service) {
					if(strcasecmp($service['Name'], $name)==0) {
						$found = true;
						$domain = $service['Domain'];
						$type = $service['Type'];
						break;
					}
				}
			}

			if($found) {
				$this->SendDebug(__FUNCTION__, 'Found the device. Querying for more information...', 0);

				$device = @ZC_QueryServiceEx($this->dnsSdId , $name, $type , $domain, $this->ReadPropertyInteger('DiscoveryTimeout')); 

				if($device!==false && count($device)>0) {
					$this->SendDebug(__FUNCTION__, 'The query returned data', 0);
					$this->SendDebug(__FUNCTION__, sprintf('The data returned is: %s',json_encode($device[0])), 0);

					$newHost = $device[0]['IPv4'][0];
					$newPort = $device[0]['Port'];

					if($host!=$newHost || $port!=$newPort) {
						IPS_SetProperty($parentId, 'Host', $newHost);
						IPS_SetProperty($parentId, 'Port', $newPort);
						IPS_SetProperty($parentId, 'UseSSL', true);
						IPS_SetProperty($parentId, 'VerifyHost', false);
						IPS_SetProperty($parentId, 'VerifyPeer', false);
						IPS_SetProperty($parentId, "Open", true);
						IPS_ApplyChanges($parentId);

						$this->SendDebug(__FUNCTION__, 'Reconfigured the I/O instance to match the Chromecast device', 0);	
					} else {
						$this->SendDebug(__FUNCTION__, 'There is no change in the configuration', 0);	
					}
				} else {
					$this->SendDebug(__FUNCTION__, 'The query did not returned any information', 0);
				}
			} else {
				$this->SendDebug(__FUNCTION__, sprintf('The device "%s" was not found', $name), 0);
			}
		} catch(Exception $e) {
			$msg = sprintf('An unexpected error occurred: %s',  $e->getMessage());
			$this->SendDebug(__FUNCTION__, $msg, 0);
			$this->LogMessage($msg, KL_ERROR);
		} 
	}

	

	public function ForwardData($JSONString) {
		$data = json_decode($JSONString);
		if(isset($data->Buffer) && is_array($data->Buffer)) {
			$this->SendDebug(__FUNCTION__, sprintf('Received instruction(s) from child instance: %s', json_encode($data->Buffer)), 0);
			$script = 'IPS_RequestAction(' . (string)$this->InstanceID . ', "HandleInstructions","'.urlencode(json_encode($data->Buffer)).'");';
			$this->SendDebug(__FUNCTION__, 'Calling HandleInstructions in another thread...', 0);
			$this->RegisterOnceTimer('HandleInstructions', $script);
			
		} else {
			$msg = sprintf('Received invalid data: %s', json_encode($data));
			$this->SendDebug(__FUNCTION__, $msg, 0);
			$this->LogMessage($msg, KL_ERROR);
		}
	}

	private function HandleInstructions($Instructions) {
		$this->SendDebug(__FUNCTION__, 'Handling instructions...', 0);
		try {
			foreach($Instructions as $instruction) {
				if(isset($instruction->Function) && method_exists($this, $instruction->Function)) {
					$function = $instruction->Function;
					$this->SendDebug(__FUNCTION__, sprintf('Calling %s', $function), 0);
					if(isset($instruction->Parameters)) {
						$parameters = $instruction->Parameters;
						if(is_array($parameters)) {
							$result = call_user_func_array(array($this, $function), $parameters);
						} else {
							throw new Exception('Parameters must be in an array!');		
						}
					} else {
						$result = call_user_func(array($this, $function));
					}
				} else {
					throw new Exception('Invalid instruction or missing function!');
				}
			}
		} catch(Exception $e) {
			$msg = sprintf('An unexpected error occurred: %s',  $e->getMessage());
			$this->SendDebug(__FUNCTION__, $msg, 0);
			$this->LogMessage($msg, KL_ERROR);
		} 
	}

	public function ReceiveData($JSONString) {
		$data = json_decode($JSONString);
		$this->SendDebug(__FUNCTION__, 'Received from parent: ' . utf8_decode($data->Buffer), 0);

		$oldMessage = $this->FetchBuffer('Message');
		$this->SendDebug(__FUNCTION__, 'Old data is: ' . $oldMessage, 0);
		if($oldMessage!=NULL && strlen($oldMessage) > 0) {
			$this->SendDebug(__FUNCTION__, 'Merging incoming data...', 0);
			$buffer = $oldMessage . utf8_decode($data->Buffer);
			$this->SendDebug(__FUNCTION__, 'Merged data is: ' . $buffer, 0);
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
		} else if(preg_match("/urn:x-cast:com.google.cast.tp.connection/s", $buffer)) {
			$handleData = true;
		} else if(preg_match("/urn:x-cast:tv.viaplay.chromecast/s", $buffer)) {
			//$handleData = true;
		} 

		if(!$handleData) {
			$this->SendDebug(__FUNCTION__, 'Incoming data is not handled', 0);	
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
				$status = [];
				switch(strtolower($data->type)) {
					case 'posdur': 
						$this->SendDebug(__FUNCTION__, 'Analyzing "POSDUR"...', 0);
						if(isset($data->position)) {
							$position = $data->position;
							$status['CurrentTime'] = $position;
							$this->SendDebug(__FUNCTION__, sprintf('CurrentTime is %s', (string)$position), 0);
						}
						
						if(isset($data->duration)) {
							$duration = $data->duration;
							$status['Duration'] = $duration;
							$this->SendDebug(__FUNCTION__, sprintf('Duration is %s', (string)$duration), 0);
						}
						break;
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
							$this->Init(false);
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

						if(isset($data->status->volume->muted)) {
							$muted = $data->status->volume->muted;
							$status['Mute'] = $muted;
							$this->SendDebug(__FUNCTION__, sprintf('Muted State is "%s"', $muted?'TRUE':'FALSE'), 0);
						}
						
						if(isset($data->status->volume->level)) {
							$level = $data->status->volume->level;
							$status['Volume'] = $level;
							$this->SendDebug(__FUNCTION__, sprintf('Volume is %s', (string)$level), 0);
						}

						if(isset($data->status->applications[0]->displayName)) {
							$displayName = $data->status->applications[0]->displayName;
							$status['DisplayName'] = $displayName;
							$this->SendDebug(__FUNCTION__, sprintf('Display Name is %s', $displayName), 0);
						}

						if(isset($data->status->applications[0]->transportId)) {
							$oldTransportId = $this->FetchBuffer('TransportId');
							$newTransportId = $data->status->applications[0]->transportId;
							
							$this->UpdateBuffer('TransportId', $newTransportId);
							$this->SendDebug(__FUNCTION__, sprintf('TransporId is "%s"', $newTransportId), 0);

							if($oldTransportId!=$newTransportId) {
								$this->SendDebug(__FUNCTION__, 'TransportId has changed. Connecting using new Id...', 0);
								$this->UpdateBuffer('MediaStatusId', 0);
								$this->ConnectDeviceTransport();
								$this->GetMediaStatus();
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

						if(isset($data->status[0]->currentTime)) {
							$currentTime =$data->status[0]->currentTime;
							$status['CurrentTime'] = $currentTime;
							$this->SendDebug(__FUNCTION__, sprintf('CurrentTime is %d', $currentTime), 0);
						}

						if(isset($data->status[0]->media->duration)) {
							$duration =$data->status[0]->media->duration;
							$status['Duration'] = $duration;
							$this->SendDebug(__FUNCTION__, sprintf('Duration is %d', $duration), 0);
						}

						if(isset($data->status[0]->playerState)) {
							$playerState = $data->status[0]->playerState;
							$status['PlayerState'] = $playerState;
							
							if(strtolower($playerState)=='playing') {
								if($this->GetTimerInterval('GetMediaStatus')==0) {
									$this->SetTimerInterval('GetMediaStatus', 1);
								}
							} else {
								$this->SetTimerInterval('GetMediaStatus', 0);
							}
							$this->SendDebug(__FUNCTION__, sprintf('PlayerState is "%s"', $playerState), 0);

						}

						if(isset($data->status[0]->media->metadata->title)) {
							$title = $data->status[0]->media->metadata->title;
							$status['Title'] = $title;
							$this->SendDebug(__FUNCTION__, sprintf('Title is "%s"', $title), 0);
						}

						if(isset($data->status[0]->media->metadata->subtitle)) {
							$subTitle = $data->status[0]->media->metadata->subtitle;
							$status['SubTitle'] = $subTitle;
							$this->SendDebug(__FUNCTION__, sprintf('SubTitle is "%s"', $subTitle), 0);
						}
						break;
				}
				if(count($status)>0) {
					$this->SendDataToChildren(json_encode(['DataID' => '{3FBC907B-E487-DC82-2730-11F8CBD494A8}', 'Buffer' => $status]));
				}
			} else {
				$this->SendDebug(__FUNCTION__, 'Incoming data is not complete. Saving the data for later usage...', 0);
				$this->UpdateBuffer('Message', $buffer);	
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





}
