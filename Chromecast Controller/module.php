<?php

declare(strict_types=1);

include __DIR__ . '/../libs/profile.php';
include __DIR__ . '/../libs/buffer.php';

class ChromecastController extends IPSModule {
	use Profiles;
	use Buffer;

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
		$this->RegisterProfileString('CCC.Information', 'Information', '', '');
		$this->RegisterProfileString('CCC.Time', 'Hourglass', '', '');
		$this->RegisterProfileString('CCC.Play', 'Script', '', '');
		$this->RegisterProfileInteger('CCC.Position', 'Distance', '', ' %', 0, 100, 1);

		$this->RegisterVariableInteger('Playback', 'Action', 'CCC.Playback', 0);
		$this->EnableAction('Playback');

		$this->RegisterVariableInteger('Volume', 'Volume', 'Intensity.100', 1);
		$this->EnableAction('Volume');

		$this->RegisterVariableBoolean('Mute', 'Mute', 'CCC.Mute', 2);
		$this->EnableAction('Mute');

		$this->RegisterVariableString('Status', 'Status', 'CCC.Information', 3);
		$this->RegisterVariableString('Source', 'Source', 'CCC.Information', 4);
		$this->RegisterVariableString('NowPlaying', 'Now Playing', 'CCC.Play', 5);
		$this->RegisterVariableString('Duration', 'Duration', 'CCC.Time', 6);
		$this->RegisterVariableString('CurrentTime', 'Current', 'CCC.Time', 7);
		$this->RegisterVariableString('TimeLeft', 'Time left', 'CCC.Time', 8);
		
		$this->RegisterVariableInteger('Position', 'Position', 'CCC.Position', 9);
		$this->EnableAction('Position');

		$this->ForceParent('{1AA6E1C3-E241-F658-AEC5-F8389B414A0C}');
		
		$this->RegisterTimer('ResetPlaybackState', 0, 'IPS_RequestAction(' . (string)$this->InstanceID . ', "ResetPlaybackState", 0);'); 
		$this->RegisterTimer('ResetVariables', 0, 'IPS_RequestAction(' . (string)$this->InstanceID . ', "ResetVariables", 0);'); 
	}

	public function Destroy() {
		$module = json_decode(file_get_contents(__DIR__ . '/module.json'));
		if(count(IPS_GetInstanceListByModuleID($module->id))==0) {
			$this->DeleteProfile('CCC.Playback');	
			$this->DeleteProfile('CCC.Mute');
			$this->DeleteProfile('CCC.Information');
			$this->DeleteProfile('CCC.Time');
			$this->DeleteProfile('CCC.Play');
			$this->DeleteProfile('CCC.Position');
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
			
			$request = [];
			switch (strtolower($Ident)) {
				case 'resetvariables':
					$this->Reset();
					$this->SetTimerInterval('ResetVariables', 0);
					break;
				case 'resetplaybackstate':
					$this->SetValue('Playback', 0);
					$this->SetTimerInterval('ResetPlaybackState', 0);
					break;
				case 'playback':
					$this->SendDebug( __FUNCTION__ , 'Changing Playback...', 0);
					$this->SetValue($Ident, $Value);
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
					$this->SetValue($Ident, $Value);
					if(is_numeric($Value)) {
						$request[] = ['Function'=>'Volume', 'Parameters'=>[$Value]];
					} else {
						throw new Exception('Invalid value for Volume. It should be a number between 0-100');	
					}
					break;
				case 'mute':
					$this->SendDebug( __FUNCTION__ , 'Changing Mute...', 0);
					$this->SetValue($Ident, $Value);
					if(is_bool($Value)) {
						$request[] = ['Function'=>'Mute', 'Parameters'=>[$Value]];
					} else {
						throw new Exception('Invalid value for Mute. It should be "True" or "False"');	
					}
					break;
				case 'position':
					$this->SendDebug( __FUNCTION__ , 'Changing Position...', 0);
					$this->SetValue($Ident, $Value);
					if(is_numeric($Value)) {
						$duration = $this->FetchBuffer('Duration');
						$newPosition = $duration/100*$Value;
						if($newPosition>0) {
							$request[] = ['Function'=>'Seek', 'Parameters'=>[$newPosition]];
						}
					} else {
						throw new Exception('Invalid value for Position. It should be a number between 0-100');	
					}
					break;
				default:
					throw new Exception('Invalid Ident. It should be "Playback", "Volume" or "Mute"');	
			}

			if(count($request)>0) {
				$this->SendDataToParent(json_encode(['DataID' => '{7F9B2C92-8242-882A-6C12-DA76767C9CA0}', 'Buffer' => $request]));
			}

		} catch(Exception $e) {
			$msg = sprintf('RequestAction failed. The error was "%s"',  $e->getMessage());
			$this->LogMessage($msg, KL_ERROR);
			$this->SendDebug( __FUNCTION__ , $msg, 0);
		}
	}

	public function ReceiveData($JSONString) {
		$data = json_decode($JSONString);
		$this->SendDebug( __FUNCTION__ , 'Received status: '. json_encode($data->Buffer), 0);

		if(isset($data->Buffer->Command)) {
			$command = $data->Buffer->Command;
			if(strtolower($command)=='reset') {
				$this->SendDebug( __FUNCTION__ , 'Resetting all variables...', 0);
				$this->Reset();
			}
		}
		
		if(isset($data->Buffer->Mute)) {
			$state = $data->Buffer->Mute;
			if(is_bool($state)) {
				$this->SetValueEx('Mute', $state);
			}
		}

		if(isset($data->Buffer->Volume)) {
			$level = $data->Buffer->Volume;
			if(is_numeric($level)) {
				$this->SetValueEx('Volume', (int)ceil($level*100));
			}
		}

		if(isset($data->Buffer->PlayerState)) {
			$playerState = $data->Buffer->PlayerState;
			if(is_string($playerState)) {
				if(strtolower($playerState)=='idle') {
					$source = $this->GetValue('Source');
					$this->Reset();
					$this->SetValue('Source', $source);
				} else {
					$this->SetValueEx('Status', $playerState);
					if(strtolower($playerState)=='playing') {
						$this->SendDebug( __FUNCTION__ , 'Setting/extending timer "ResetVariables" to two minutes', 0);
						$this->SetTimerInterval('ResetVariables', 120000);
					} else {
						$this->SendDebug( __FUNCTION__ , 'Stopping timer "ResetVariables"', 0);
						$this->SetTimerInterval('ResetVariables', 0);
					}
				}
			}
		}

		if(isset($data->Buffer->Title)) {
			$title = $data->Buffer->Title;
			if(is_string($title)) {
				$this->SetValueEx('NowPlaying', $title);
			}
		}

		if(isset($data->Buffer->DisplayName)) {
			$displayName = $data->Buffer->DisplayName;
			if(is_string($displayName) && strtolower($displayName)!='backdrop') {
				$oldSource = $this->GetValue('Source');
				if($oldSource!=$displayName) {
					$this->Reset();	
				}
				$this->SetValue('Source', $displayName);
			} else {
				$this->Reset();
			}
		}

		
		if(isset($data->Buffer->Duration)) {
			$duration = $data->Buffer->Duration;
			if(is_numeric($duration)) {
				$this->SetValueEx('Duration', $this->secondsToString($duration));
				$this->UpdateBuffer('Duration', $duration);
			}
		}

		$current = 0;
		if(isset($data->Buffer->CurrentTime)) {
			$current = $data->Buffer->CurrentTime;
			if(is_numeric($current)) {
				$this->SetValueEx('CurrentTime', $this->secondsToString($current));
			} else {
				$current = 0;
			}
		}

		$duration = $this->FetchBuffer('Duration');
		if($current>0 && $duration>0) {
			$timeLeft = $duration-$current;
			$this->SetValueEx('TimeLeft', $this->secondsToString($timeLeft));
			
			$position = (int)ceil($current/$duration*100);
			$this->SetValueEx('Position', $position);
		} else {
			$this->SetValueEx('TimeLeft', '');
			$this->SetValueEx('Position', 0);
		}
	}

	private function Reset() {
		$this->SetValueEx('Source', '');
		$this->SetValueEx('NowPlaying', '');
		$this->SetValueEx('Status', '');
		$this->SetValueEx('Playback', 0);
		$this->SetValueEx('CurrentTime', '');
		$this->SetValueEx('TimeLeft', '');
		$this->SetValueEx('Position', 0);
		$this->SetValueEx('Duration', '');
	
		$this->UpdateBuffer('Duration', 0);
	}

	private function secondsToString(float $Seconds, bool $ShowSeconds=false) {
		if($Seconds>=0) {
			$s = $Seconds%60;
			$m = floor(($Seconds%3600)/60);
			$h = floor(($Seconds%86400)/3600);
			
			if($ShowSeconds) {
				return sprintf('%02d:%02d:%02d', $h, $m, $s);
			} else {
				return sprintf('%02d:%02d', $h, $m);
			}
		} else {
			return 'N/A';
		}
	}

	private function SetValueEx(string $Ident, $Value) {
		$oldValue = $this->GetValue($Ident);
		if($oldValue!=$Value) {
			$this->SetValue($Ident, $Value);
		}
	}
}