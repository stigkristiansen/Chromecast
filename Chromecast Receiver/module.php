<?php

declare(strict_types=1);

include __DIR__ . '/../libs/protobuf.php';

class ChromecastReceiver extends IPSModule {
	private $requestId = 0;
	private $lastactivetime;

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
            $this->SetTimer();
        }
	}

	public function MessageSink($TimeStamp, $SenderID, $Message, $Data) {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($Message == IPS_KERNELMESSAGE && $Data[0] == KR_READY) 
            $this->SetTimer();
    }

	private function SetTimer() {
		$this->SetTimerInterval('PingPong', 5000);
	}

	public function RequestAction($Ident, $Value) {
		switch (strtolower($Ident)) {
			case 'pingpong':
				$this->SendPingPong('PING');
		}
	}

	private function SendPingPong(string $Type) {
		$c = new CastMessage();
		$c->source_id = "sender-0";
		$c->receiver_id = "receiver-0";
		$c->urnnamespace = "urn:x-cast:com.google.cast.tp.heartbeat";
		$c->payloadtype = 0;
		$c->payloadutf8 = '{"type":"'.$Type.'"}';
		//fwrite($this->socket, $c->encode());
		//fflush($this->socket);
		$this->lastactivetime = time();
		$this->requestId++;

		$this->SendDebug(__FUNCTION__, 'Sending ' . $Type . '...', 0);
		$this->SendDataToParent(json_encode(['DataID' => '{79827379-F36E-4ADA-8A95-5F8D1DC92FA9}', 'Buffer' => utf8_encode($c->encode())]));
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

		$regex = '/(\{(?:(?>[^{}"\/]+)|(?>"(?:(?>[^\\"]+)|\\.)*")|<(?>\/\*.*?\*\/)|(?-1))*\})/';
		preg_match($regex, utf8_decode($data->Buffer), $result);

		if(count($result)>0) {
			$data = json_decode($result[0]);

			if(isset($data->type)) {
				switch(strtolower($data->type)) {
					case 'ping':
						$this->SendPingPong('PONG');
				}
			}
			
		}


		//$this->SendDataToChildren(json_encode(['DataID' => '{3FBC907B-E487-DC82-2730-11F8CBD494A8}', 'Buffer' => $data->Buffer]));
	}
}