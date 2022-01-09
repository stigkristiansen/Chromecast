<?php

declare(strict_types=1);

trait Chromecast {
    private function SendPingPong(string $Type) {
		$msg = new CastMessage();
		$msg->source_id = "sender-0";
		$msg->receiver_id = "receiver-0";
		$msg->urnnamespace = "urn:x-cast:com.google.cast.tp.heartbeat";
		$msg->payloadtype = 0;
		$msg->payloadutf8 = '{"type":"'.$Type.'"}';
		
		$result = @$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($msg->encode())]));

		$this->SendDebug(__FUNCTION__, $Type . ' was sent with result ' . $result, 0);
	}

	private function ConnectDevice() {
		$msg = new CastMessage();
		$msg->source_id = "sender-0";
		$msg->receiver_id = "receiver-0";
		$msg->urnnamespace = "urn:x-cast:com.google.cast.tp.connection";
		$msg->payloadtype = 0;
		$msg->payloadutf8 = '{"type":"CONNECT"}';

		$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($msg->encode())]));
		$this->SendDebug(__FUNCTION__, ' CONNECT was sent to the device', 0);
	}

	private function ConnectDeviceTransport() {
		$value = $this->FetchBuffer('TransportId');
		$transportId=$value!==false?$value:'';

		$msg = new CastMessage();
		$msg->source_id = "sender-0";
		$msg->receiver_id = $transportId;
		$msg->urnnamespace = "urn:x-cast:com.google.cast.tp.connection";
		$msg->payloadtype = 0;
		$msg->payloadutf8 = '{"type":"CONNECT"}';

		$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($msg->encode())]));
		$this->SendDebug(__FUNCTION__, 'CONNECT with TransportId was sent to the device', 0);
	}

    private function Launch(string $AppId) {
        $value = $this->FetchBuffer('RequestId');
        $requestId=$value!==false?$value:0;

	    $msg = new CastMessage();
		$msg->source_id = "sender-0";
		$msg->receiver_id = "receiver-0";
		$msg->urnnamespace = "urn:x-cast:com.google.cast.receiver";
		$msg->payloadtype = 0;
		$msg->payloadutf8 = sprintf('{"type":"LAUNCH", "appId":"%s", "requestId":%d}', $AppId, $requestId);

        $requestId++;
		$this->UpdateBuffer('RequestId', $requestId);

        $this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($msg->encode())]));
		$this->SendDebug(__FUNCTION__, sprintf('LAUNCH was sent to the device with AppId %s', $AppId), 0);
	}

	private function GetDeviceStatus() {
		$value = $this->FetchBuffer('RequestId');
		$requestId=$value!==false?$value:0;
		
		$msg = new CastMessage();
		$msg->source_id = "sender-0";
		$msg->receiver_id = "receiver-0";
		$msg->urnnamespace = "urn:x-cast:com.google.cast.receiver";
		$msg->payloadtype = 0;
		$msg->payloadutf8 = sprintf('{"type":"GET_STATUS","requestId":%d}', $requestId);
		
		$requestId++;
		$this->UpdateBuffer('RequestId', $requestId);
						
		$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($msg->encode())]));
		$this->SendDebug(__FUNCTION__, sprintf('GET_STATUS was sent to the receiver with RequestId %d', $requestId), 0);
	}

	private function GetMediaStatus() {
		$value = $this->FetchBuffer('TransportId');
		$transportId=$value!==false?$value:'';

		$value = $this->FetchBuffer('RequestId');
		$requestId=$value!==false?$value:0;

		$msg = new CastMessage();
		$msg->source_id = 'sender-0';
		$msg->receiver_id = $transportId;
		$msg->urnnamespace = 'urn:x-cast:com.google.cast.media';
		$msg->payloadtype = 0;
		$msg->payloadutf8 = sprintf('{"type":"GET_STATUS","requestId":%d}', $requestId);
		
		$requestId++;
		$this->UpdateBuffer('RequestId', $requestId);
						
		$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($msg->encode())]));
		$this->SendDebug(__FUNCTION__, sprintf('GET_STATUS (media) was sent to the receiver with RequestId %d', $requestId), 0);
	}

	private function Stop() {
		$value = $this->FetchBuffer('RequestId');
		$requestId=$value!==false?$value:0;

		$value = $this->FetchBuffer('SessionId');
		$sessionId=$value!==false?$value:0;

		$msg = new CastMessage();
		$json = '{"type":"STOP", "sessionId":"'. $sessionId . '", "requestId":' .$requestId . '}';
		$message = $msg->FormatMessage("urn:x-cast:com.google.cast.receiver", $json);

        $requestId++;
		$this->UpdateBuffer('RequestId', $requestId);

		$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($message)]));
		$this->SendDebug(__FUNCTION__, 'STOP was sent', 0);
	}

	private function Pause() {
		$value = $this->FetchBuffer('RequestId');
		$requestId=$value!==false?$value:0;

		$value = $this->FetchBuffer('MediaSessionId');
		$mediaSessionId=$value!==false?$value:0;

		$value = $this->FetchBuffer('TransportId');
		$transportId=$value!==false?$value:'';

		$msg = new CastMessage();
		$json = '{"type":"PAUSE", "mediaSessionId":' . $mediaSessionId . ', "requestId":'.$requestId.'}';
		$urn = 'urn:x-cast:com.google.cast.media';
		$message = $msg->FormatMessage($urn, $json, $transportId);

        $requestId++;
		$this->UpdateBuffer('RequestId', $requestId);

		$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($message)]));
		$this->SendDebug(__FUNCTION__, 'PAUSE was sent', 0);
	}

	private function Play() {
		$value = $this->FetchBuffer('RequestId');
		$requestId=$value!==false?$value:0;

		$value = $this->FetchBuffer('MediaSessionId');
		$mediaSessionId=$value!==false?$value:0;

		$value = $this->FetchBuffer('TransportId');
		$transportId=$value!==false?$value:'';

		$requestId++;
		$this->UpdateBuffer('RequestId', $requestId);

		$msg = new CastMessage();
		$json = '{"type":"PLAY", "mediaSessionId":' . $mediaSessionId . ', "requestId":'.$requestId.'}';
		$urn = 'urn:x-cast:com.google.cast.media';
		$message = $msg->FormatMessage($urn, $json, $transportId);

		$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($message)]));
		$this->SendDebug(__FUNCTION__, 'PLAY was sent', 0);
	}

	private function Seek(float $NewCurrentTime) {
		$value = $this->FetchBuffer('RequestId');
		$requestId=$value!==false?$value:0;

		$value = $this->FetchBuffer('MediaSessionId');
		$mediaSessionId=$value!==false?$value:0;

		$value = $this->FetchBuffer('TransportId');
		$transportId=$value!==false?$value:'';

		$requestId++;
		$this->UpdateBuffer('RequestId', $requestId);

		$msg = new CastMessage();
		$json = sprintf('{"type":"SEEK", "mediaSessionId":%s, "requestId":%d, "currentTime":%F}',$mediaSessionId, $requestId, $NewCurrentTime);
		$urn = 'urn:x-cast:com.google.cast.media';
		$message = $msg->FormatMessage($urn, $json, $transportId);

		$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($message)]));
		$this->SendDebug(__FUNCTION__, 'SEEK was sent', 0);
	}

    private function Mute(bool $State) {
		$value = $this->FetchBuffer('RequestId');
		$requestId=$value!==false?$value:0;

        $muteState = $State?'true':'false';
		
		$msg = new CastMessage();
		$json = sprintf('{"type":"SET_VOLUME", "volume":{"muted":%s}, "requestId":%d}',$muteState, $requestId);
		$urn = 'urn:x-cast:com.google.cast.receiver';
		$message = $msg->FormatMessage($urn, $json);

        $requestId++;
		$this->UpdateBuffer('RequestId', $requestId);

		$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($message)]));
		$this->SendDebug(__FUNCTION__, sprintf('MUTE was sent with value %s', $muteState), 0);
	}

    private function Volume(int $Level) {
        if($Level>=0 && $Level<=100) {
            $value = $this->FetchBuffer('RequestId');
            $requestId=$value!==false?$value:0;

            $volumeLevel = (float)$Level/100;

            $msg = new CastMessage();
            $json = sprintf('{"type":"SET_VOLUME", "volume":{"level":%F}, "requestId":%d }',$volumeLevel, $requestId);
            $urn = 'urn:x-cast:com.google.cast.receiver';
            $message = $msg->FormatMessage($urn, $json);

            $requestId++;
            $this->UpdateBuffer('RequestId', $requestId);

            $this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($message)]));
            $this->SendDebug(__FUNCTION__, sprintf('VOLUME LEVEL was sent with value %f', $volumeLevel), 0);
        } else  {
            $this->SendDebug(__FUNCTION__, sprintf('Invalid VOLUME LEVEL %d!', $Level), 0);
        }
	}
}
