<?php

declare(strict_types=1);

include __DIR__ . '/../libs/profile.php';

class ChromecastController extends IPSModule {
	public function Create() {
		//Never delete this line!
		parent::Create();

		$this->RegisterPropertyString('Name', '');
		$this->RegisterPropertyString('Id', '');

		$this->RegisterProfileIntegerEx('CCC.Playback', 'Execute', '', '', [
			[0, ' ', '', -1],
			[1, 'Play', '', -1],
			[2, 'Pause', '', -1],
			[3, 'Stop', '', -1]
		]);

		$this->RegisterVariableInteger('Playback', 'Action', 'CCC.Playback', 0);
		$this->EnableAction('Action');

		$this->RegisterVariableInteger('Volume', 'Volume', 'Intensity.100', 1);
		$this->EnableAction('Action');

		$this->RegisterVariableString('Status', 'Information', '', 2);
		$this->RegisterVariableString('Source', 'Information', '', 3);
		$this->RegisterVariableString('NowPlaying', 'Information', '', 4);

		$this->ForceParent('{1AA6E1C3-E241-F658-AEC5-F8389B414A0C}');
	}

	public function Destroy() {
		$module = json_decode(file_get_contents(__DIR__ . '/module.json'));
		if(count(IPS_GetInstanceListByModuleID($module->id))==0) {
			$this->DeleteProfile('CCC.Playback');	
		}
		
		//Never delete this line!
		parent::Destroy();
	}

	public function ApplyChanges() {
		//Never delete this line!
		parent::ApplyChanges();

		$parentId = IPS_GetInstance($this->InstanceID)['ConnectionID'];
		IPS_SetProperty($parentId, 'Name', $this->ReadPropertyString('Name'));
		IPS_ApplyChanges($parentId);
	}

	public function RequestAction($Ident, $Value) {
		try {
			$this->SendDebug( __FUNCTION__ , sprintf('ReqestAction called for Ident "%s" with Value %s', $Ident, (string)$Value), 0);
			
			$this->SetValue($Ident, $Value);

			$command = '';
			$parameter = '';
			switch (strtolower($Ident)) {
				case 'playback':
					$this->SendDebug( __FUNCTION__ , 'Changing Playback...', 0);
					switch($Value) {
						case 0: 
							return;
						case 1:
							$command = 'Play';
							break;
						case 2:
							$commnad = 'Pause';
							break;
						case 3:
							$command = 'Stop';
							break;
						default:
							throw new Exception('Invalid value for Playback. Accepted values are 0,1,2,3');
							
					}
					break;
				case 'volume':
					$this->SendDebug( __FUNCTION__ , 'Changing Volume...', 0);
					if(is_numeric($Value)) {
						$command = 'Volume';
						$parameter = $Value;
					} else {
						throw new Exception('Invalid value for Volume. It should be a number between 0-100');	
					}
					break;
			}
			if(strlen($parameter)>0) {
				$request = array('command' => $command, 'parameter' => $parameter);
			} else {
				$request = array('command' => $command);
			}

			$this->SendDataToParent(json_encode(['DataID' => '{047CD9E9-0492-37DF-0955-3DF2F006F0A2}', 'Buffer' => $request]));

		} catch(Exception $e) {
			$msg = sprintf('RequestAction failed. The error was "%s"',  $e->getMessage());
			$this->LogMessage($msg, KL_ERROR);
			$this->SendDebug( __FUNCTION__ , $msg, 0);
		}
	}

	public function ReceiveData($JSONString) {
		$data = json_decode($JSONString);
		IPS_LogMessage('Device RECV', utf8_decode($data->Buffer));
	}
}