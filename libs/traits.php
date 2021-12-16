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
			$instanceIds = IPS_GetInstanceListByModuleID(Modules::DNSSD);
			if(count($instanceIds)==0) {
				$this->LogMessage(Errors::MISSINGDNSSD, KL_ERROR);
				return false;
			}
			
			return $instanceIds[0];
		}
	}
