<?php
/**
 * @copyright Copyright (c) 2017, Afterlogic Corp.
 * @license AGPL-3.0 or AfterLogic Software License
 *
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\MailChangePasswordPoppassdPlugin;

/**
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	/**
	 * @var CApiPoppassdProtocol
	 */
	protected $oPopPassD;

	/**
	 * @param CApiPluginManager $oPluginManager
	 */
	
	public function init() 
	{
		$this->oPopPassD = null;
		
		$this->oMailModule = \Aurora\System\Api::GetModule('Mail');
	
		$this->subscribeEvent('Mail::ChangePassword::before', array($this, 'onBeforeChangePassword'));
	}
	
	/**
	 * 
	 * @param array $aArguments
	 * @param mixed $mResult
	 */
	public function onBeforeChangePassword($aArguments, &$mResult)
	{
		$mResult = true;
		
		$oAccount = $this->oMailModule->GetAccount($aArguments['AccountId']);

		if ($oAccount && $this->checkCanChangePassword($oAccount))
		{
			$mResult = $this->сhangePassword($oAccount, $aArguments['NewPassword']);
		}
		
		return true;
	}

	/**
	 * @param CAccount $oAccount
	 * @return bool
	 */
	protected function checkCanChangePassword($oAccount)
	{
		$bFound = in_array("*", $this->getConfig('SupportedServers', array()));
		
		if (!$bFound)
		{
			$oServer = $this->oMailModule->GetServer($oAccount->ServerId);

			if ($oServer && in_array($oServer->Name, $this->getConfig('SupportedServers')))
			{
				$bFound = true;
			}
		}

		return $bFound;
	}
	/**
	 * @param CAccount $oAccount
	 */
	protected function сhangePassword($oAccount, $sPassword)
	{
		if (0 < strlen($oAccount->IncomingPassword) && $oAccount->IncomingPassword !== $sPassword)
		{
			if (null === $this->oPopPassD)
			{
				$this->oPopPassD = new \Aurora\System\Net\Protocols\Poppassd(
					$this->getConfig('Host', '127.0.0.1'),
					$this->getConfig('Port', 106)
				);
			}

			if ($this->oPopPassD && $this->oPopPassD->Connect())
			{
				try
				{
					if ($this->oPopPassD->Login($oAccount->IncomingLogin, $oAccount->IncomingPassword))
					{
						if (!$this->oPopPassD->NewPass($sPassword))
						{
							throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Exceptions\Errs::UserManager_AccountNewPasswordRejected);
						}
					}
					else
					{
						throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Exceptions\Errs::UserManager_AccountOldPasswordNotCorrect);
					}
				}
				catch (Exception $oException)
				{
					$this->oPopPassD->Disconnect();
					throw $oException;
				}
			}
			else
			{
				throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Exceptions\Errs::UserManager_AccountNewPasswordUpdateError);
			}
		}
	}
}