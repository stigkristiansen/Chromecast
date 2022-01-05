<?php
	declare(strict_types=1);

	require_once(__DIR__ . "/../libs/dnssd.php");

	class ChromecastDiscovery extends IPSModule {
		use ServiceDiscovery;

		private $dnsSdId;

		public function __construct($InstanceID) {
			parent::__construct($InstanceID);
	
			$this->dnsSdId = $this->GetDnsSdId(); // Defined in traits.php
		}

		public function Create()
		{
			//Never delete this line!
			parent::Create();

			$this->RegisterPropertyInteger('DiscoveryTimeout', 1000);

			$this->SetBuffer('Devices', json_encode([]));
            $this->SetBuffer('SearchInProgress', json_encode(false));
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
		}

		public function GetConfigurationForm() {
			$this->SendDebug(__FUNCTION__, 'Building form...', 0);
            $this->SendDebug(__FUNCTION__, sprintf('SearchInProgress is %s', json_decode($this->GetBuffer('SearchInProgress'))?'TRUE':'FALSE'), 0);
            			
			$devices = json_decode($this->GetBuffer('Devices'));
           
			if (!json_decode($this->GetBuffer('SearchInProgress'))) {
                $this->SendDebug(__FUNCTION__, 'Setting SearchInProgress to TRUE', 0);
				$this->SetBuffer('SearchInProgress', json_encode(true));
				
				$this->SendDebug(__FUNCTION__, 'Starting a timer to process the search in a new thread...', 0);
				$this->RegisterOnceTimer('LoadDevices', 'IPS_RequestAction(' . (string)$this->InstanceID . ', "Discover", 0);');
            }

			$form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
			$form['actions'][0]['visible'] = count($devices)==0;
			
			$form['actions'][1]['values'] = $devices;
			$this->SendDebug(__FUNCTION__, 'Added cached devices', 0);

			$this->SendDebug(__FUNCTION__, 'Building form is done', 0);

            return json_encode($form);
		}

		public function RequestAction($Ident, $Value) {
			$this->SendDebug( __FUNCTION__ , sprintf('ReqestAction called for Ident "%s" with Value %s, $Ident', $Ident, (string)$Value), 0);

			switch (strtolower($Ident)) {
				case 'discover':
					$this->SendDebug(__FUNCTION__, 'Calling LoadDevices()...', 0);
					$this->LoadDevices();
					break;
			}
		}

		private function LoadDevices() {
			$this->SendDebug(__FUNCTION__, 'Updating discovery form...', 0);

			$ccDevices = $this->DiscoverCCDevices();
			$ccInstances = $this->GetCCInstances();

			$this->SendDebug(__FUNCTION__, 'Setting SearchInProgress to FALSE', 0);
			$this->SetBuffer('SearchInProgress', json_encode(false));
	
			$values = [];

			// Add devices that are discovered
			if(count($ccDevices)>0)
				$this->SendDebug(__FUNCTION__, 'Adding found devices...', 0);
			else
				$this->SendDebug(__FUNCTION__, 'No devices found', 0);

			
			foreach ($ccDevices as $id => $device) {
				$value = [
					'DisplayName'	=> $device['DisplayName'],
					'instanceID' 			=> 0,
				];

				$this->SendDebug(__FUNCTION__, sprintf('Added the device %s to the form', $device['DisplayName']), 0);
				
				// Check if discovered device has an instance that is created earlier. If found, set InstanceID and DisplayName
				$instanceId = array_search($id, $ccInstances);
				if ($instanceId !== false) {
					$this->SendDebug(__FUNCTION__, sprintf('The device %s exists with instance id %d', $device['DisplayName'], $instanceId), 0);
					unset($ccInstances[$instanceId]); // Remove from list to avoid duplicates
					$value['DisplayName'] = IPS_GetName($instanceId);
					$value['instanceID'] = $instanceId;
				} 
				
				$value['create'] = [
					[
						'moduleID'      => '{C8CE073C-D0D9-A7F9-C37E-A0C4978886E3}',
						'name'			=> $device['DisplayName'],
						'configuration' => [
							'Name' => $device['Name'],
							'Id'   => $id
						]
					],
					[
						'moduleID'      => '{1AA6E1C3-E241-F658-AEC5-F8389B414A0C}',
						'configuration' => [
							'Name' => $device['Name']
						]
					],
					[
						'moduleID'      => '{3CFF0FD9-E306-41DB-9B5A-9D06D38576C3}',  
						'configuration'	=> [
							'Open' 		=> true,
							'Host'		=> $device['Host'],
							'Port'		=> $device['Port'],
							'UseSSL'	=> true,
							'VerifyHost' => false,
							'VerifyPeer' => false
						]
					]
				];
			
				$values[] = $value;
			}

			// Add devices that are not discovered, but created earlier
			if(count($ccInstances)>0) {
				$this->SendDebug(__FUNCTION__, 'Adding existing instances that are not discovered', 0);
			}
			
			foreach ($ccInstances as $instanceId => $id) {
				$values[] = [
					'DisplayName' => IPS_GetName($instanceId), 
					'instanceID'  => $instanceId
				];

				$this->SendDebug(__FUNCTION__, sprintf('Added existing instance "%s" with InstanceId %d', IPS_GetName($instanceId), $instanceId), 0);
			}

			$newDevices = json_encode($values);
			$this->SetBuffer('Devices', $newDevices);

			$this->UpdateFormField('Discovery', 'values', $newDevices);
            $this->UpdateFormField('SearchingInfo', 'visible', false);

			$this->SendDebug(__FUNCTION__, 'Updating Discovery Form completed', 0);
		}
	
		private function DiscoverCCDevices() : array {
			$this->LogMessage('Discovering Chromecast devices...', KL_NOTIFY);

			$this->SendDebug(__FUNCTION__, 'Starting discovery of Chromecast devices', 0);
			
			$devices = [];

			$services = @ZC_QueryServiceTypeEx($this->dnsSdId, "_googlecast._tcp", "", $this->ReadPropertyInteger('DiscoveryTimeout'));

			if($services!==false) {
				if(count($services)>0) {
					foreach($services as $service) {
						$this->SendDebug(__FUNCTION__, sprintf('Quering device %s for details', $service['Name']), 0);
						
						$device = @ZC_QueryServiceEx ($this->dnsSdId , $service['Name'], $service['Type'] ,  $service['Domain'], $this->ReadPropertyInteger('DiscoveryTimeout')); 
						if($device===false || count($device)==0) {
							$this->SendDebug(__FUNCTION__, sprintf('%s dit not respond', $service['Name']), 0);
							continue;
						}
						
						$displayName = $this->GetServiceTXTRecord($device[0]['TXTRecords'], 'fn');
						$id = $this->GetServiceTXTRecord($device[0]['TXTRecords'], 'id');
						if($displayName!==false && $id!==false) {
							$this->SendDebug(__FUNCTION__, sprintf('Retrieved details for %s', $displayName), 0);
						
							$devices[$id] = [	// Id is used as index
								'Name' 		  => $service['Name'],
								'DisplayName' => $displayName,
								'Host'		  => $device[0]['IPv4'][0],
								'Port'		  => $device[0]['Port']	
							];
						} else {
							$msg = 
							$this->SendDebug(__FUNCTION__, sprintf('Invalid query response from "%s". The response was: %s', $service['Name'], json_encode($device[0])), 0);
							$this->LogMessage('Returned TXT-records are invalid', KL_ERROR);
						}
					}
				} else
					$this->SendDebug(__FUNCTION__, 'No Chromecast devices where found', 0);	
			} else {
				$msg = 'Discovering Chromecast devices failed';
				$this->SendDebug(__FUNCTION__, $msg, 0);
				$this->LogMessage($msg, KL_ERROR);
			}

			$this->SendDebug(__FUNCTION__, 'Discovering Chromecast devices completed', 0);	
			
			return $devices;
		}

		private function GetCCInstances () : array {
			$devices = [];

			$this->SendDebug(__FUNCTION__, sprintf('Building list of all created Chromecast instances (module id: %s)', '{C8CE073C-D0D9-A7F9-C37E-A0C4978886E3}'), 0);

			$instanceIds = IPS_GetInstanceListByModuleID('{C8CE073C-D0D9-A7F9-C37E-A0C4978886E3}'); 
        	
        	foreach ($instanceIds as $instanceId) {
				$devices[$instanceId] = IPS_GetProperty($instanceId, 'Id');
			}

			$this->SendDebug(__FUNCTION__, sprintf('Found %d instances', count($devices)), 0);
			$this->SendDebug(__FUNCTION__, 'Building list of instances completed', 0);	

			return $devices;
		}

	}