<?php

declare(strict_types=1);
	class ChromecastController extends IPSModule
	{
		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RegisterPropertyString('Name', '');
			$this->RegisterPropertyString('Id', '');

			$this->ForceParent('{1AA6E1C3-E241-F658-AEC5-F8389B414A0C}');
		}

		public function Destroy()
		{
			//Never delete this line!
			parent::Destroy();
		}

		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();

			

			$parentId = IPS_GetInstance($this->InstanceID)['ConnectionID'];
			IPS_SetProperty($parentId, 'Name', $this->ReadPropertyString('Name'));
			IPS_ApplyChanges($parentId);
		}

		public function Send()
		{
			$this->SendDataToParent(json_encode(['DataID' => '{7F9B2C92-8242-882A-6C12-DA76767C9CA0}']));
		}

		public function ReceiveData($JSONString)
		{
			$data = json_decode($JSONString);
			IPS_LogMessage('Device RECV', utf8_decode($data->Buffer));
		}
	}