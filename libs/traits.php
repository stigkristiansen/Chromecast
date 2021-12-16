<?php

	declare(strict_types=1);

	trait ServiceDiscovery {
		private function GetServiceTXTRecord($Records, $Key) {
			foreach($Records as $record) {
				if(stristr($record, $Key.'=')!==false)
					return substr($record, 3);
			}

			return false;
		}

		private function GetDnsSdId() {
			$instanceIds = IPS_GetInstanceListByModuleID('{780B2D48-916C-4D59-AD35-5A429B2355A5}');
			if(count($instanceIds)==0) {
                $msg = 'The core module DNS-SD is missing';
				$this->SendDebug(__FUNCTION__, $msg, 0);
                $this->LogMessage($msg, KL_ERROR);
				return false;
			}
			
			return $instanceIds[0];
		}
	}
