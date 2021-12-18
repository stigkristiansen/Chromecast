<?php

declare(strict_types=1);

include __DIR__ . '/../libs/profile.php';

class ChromecastController extends IPSModule {
	use Profiles;

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

		$this->RegisterProfileBooleanEx('CCC.Mute', 'Speaker', '', '', [
			[false, 'Off', '', -1],
			[true, 'On', '', -1]
		]);

		$this->RegisterVariableInteger('Playback', 'Action', 'CCC.Playback', 0);
		$this->EnableAction('Playback');

		$this->RegisterVariableInteger('Volume', 'Volume', 'Intensity.100', 1);
		$this->EnableAction('Volume');

		$this->RegisterVariableBoolean('Mute', 'Mute', 'CCC.Mute', 2);
		$this->EnableAction('Mute');

		$this->RegisterVariableString('Status', 'Status', '', 3);
		$this->RegisterVariableString('Source', 'Source', '', 4);
		$this->RegisterVariableString('NowPlaying', 'Now Playing', '', 5);

		$this->ForceParent('{1AA6E1C3-E241-F658-AEC5-F8389B414A0C}');
		
		$this->RegisterTimer('ResetPlaybackState', 0, 'IPS_RequestAction(' . (string)$this->InstanceID . ', "ResetPlaybackState", 0);'); 
	}

	public function Destroy() {
		$module = json_decode(file_get_contents(__DIR__ . '/module.json'));
		if(count(IPS_GetInstanceListByModuleID($module->id))==0) {
			$this->DeleteProfile('CCC.Playback');	
			$this->DeleteProfile('CCC.Mute');
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

			switch (strtolower($Ident)) {
				case 'resetplaybackstate':
					$this->SetValue('Playback', 0);
					$this->SetTimerInterval('ResetPlaybackState', 0);
					break;
				case 'playback':
					$this->SendDebug( __FUNCTION__ , 'Changing Playback...', 0);
					switch($Value) {
						case 0: 
							return;
						case 1:
							$request[] = ['Function'=>'Play'];
							break;
						case 2:
							$request[] = ['Function'=>'Pause'];
							break;
						case 3:
							$request[] = ['Function'=>'Stop'];
							break;
						default:
							throw new Exception('Invalid value for Playback. Accepted values are 0,1,2,3');
					}
					
					if($Value!=0) {
						$this->SetTimerInterval('ResetPlaybackState', 2000);
					}

					break;
				case 'volume':
					$this->SendDebug( __FUNCTION__ , 'Changing Volume...', 0);
					if(is_numeric($Value)) {
						$request[] = ['Function'=>'Volume', 'Parameters'=>[$Value]];
					} else {
						throw new Exception('Invalid value for Volume. It should be a number between 0-100');	
					}
					break;
				case 'mute':
					$this->SendDebug( __FUNCTION__ , 'Changing Mute...', 0);
					if(is_bool($Value)) {
						$request[] = ['Function'=>'Mute', 'Parameters'=>[$Value]];
					} else {
						throw new Exception('Invalid value for Mute. It should be "True" or "False"');	
					}
					break;
				default:
					throw new Exception('Invalid Ident. It should be "Playback", "Volume" or "Mute"');	
			}

			$this->SendDataToParent(json_encode(['DataID' => '{7F9B2C92-8242-882A-6C12-DA76767C9CA0}', 'Buffer' => $request]));

		} catch(Exception $e) {
			$msg = sprintf('RequestAction failed. The error was "%s"',  $e->getMessage());
			$this->LogMessage($msg, KL_ERROR);
			$this->SendDebug( __FUNCTION__ , $msg, 0);
		}
	}

	public function ReceiveData($JSONString) {
		$data = json_decode($JSONString);
		$this->SendDebug( __FUNCTION__ , 'Received status: '. json_encode($data->Buffer), 0);
		
		if(isset($data->Buffer->Mute)) {
			$state = $data->Buffer->Mute;
			if(is_bool($state)) {
				$this->SetValue('Mute', $state);
			}
		}

		if(isset($data->Buffer->Volume)) {
			$level = $data->Buffer->Volume;
			if(is_numeric($level)) {
				$this->SetValue('Volume', (int)ceil($level*100));
			}
		}

		if(isset($data->Buffer->PlayerState)) {
			$playerState = $data->Buffer->PlayerState;
			if(is_string($playerState)) {
				$this->SetValue('Status', $playerState);
				
				/*switch(strtolower($playerState)) {
					case 'playing':
					case 'buffering':
						$state = 1;
						break;
					case 'paused':
						$state = 2;
						break;
					default:
						$state = 0;
				}
				$this->SetValue('Playback', $state); */
			}
		}

		if(isset($data->Buffer->Title)) {
			$title = $data->Buffer->Title;
			if(is_string($title)) {
				$this->SetValue('NowPlaying', $title);
			}
		}

		if(isset($data->Buffer->DisplayName)) {
			$displayName = $data->Buffer->DisplayName;
			if(is_string($displayName) && strcasecmp($displayName, 'Backdrop')!=0) {
				$this->SetValue('Source', $displayName);
			} else {
				$this->SetValue('Source', '');
				$this->SetValue('NowPlaying', '');
				$this->SetValue('Status', '');
				$this->SetValue('Playback', 0);
			}
		}

	}
}