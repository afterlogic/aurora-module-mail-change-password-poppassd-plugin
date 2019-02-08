<?php
/**
 * This code is licensed under AGPLv3 license or AfterLogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\MailChangePasswordPoppassdPlugin;

/**
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing AfterLogic Software License
 * @copyright Copyright (c) 2018, Afterlogic Corp.
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
	/**
	 * @var CApiPoppassdProtocol
	 */
	protected $oPopPassD;

	protected $oMailModule;

	/**
	 * @param CApiPluginManager $oPluginManager
	 */
	
	public function init() 
	{
		$this->oPopPassD = null;
		$this->oMailModule = null;
	
		$this->subscribeEvent('Mail::ChangePassword::before', array($this, 'onBeforeChangePassword'));
	}
	
	/**
	 * 
	 * @param array $aArguments
	 * @param mixed $mResult
	 */
	public function onBeforeChangePassword($aArguments, &$mResult)
	{
		$bResult = false;
		
		$oAccount = $this->getMailModule()->GetAccount($aArguments['AccountId']);

		if ($oAccount && $this->checkCanChangePassword($oAccount) && $oAccount->getPassword() === $aArguments['CurrentPassword'])
		{
			//inverting result for break subscriptions in case when password wasn't change
			$bResult = !$this->changePassword($oAccount, $aArguments['NewPassword']);
		}
		
		return $bResult;
	}

	/**
	 * @param \Aurora\Modules\StandardAuth\Classes\Account $oAccount
	 * @return bool
	 */
	protected function checkCanChangePassword($oAccount)
	{
		$bFound = in_array("*", $this->getConfig('SupportedServers', array()));
		
		if (!$bFound)
		{
			$oServer = $this->getMailModule()->GetServer($oAccount->ServerId);

			if ($oServer && in_array($oServer->Name, $this->getConfig('SupportedServers')))
			{
				$bFound = true;
			}
		}

		return $bFound;
	}
	/**
	 * @param \Aurora\Modules\StandardAuth\Classes\Account $oAccount
	 */
	protected function changePassword($oAccount, $sPassword)
	{
		$bResult = false;
		
		if (0 < strlen($oAccount->getPassword()) && $oAccount->getPassword() !== $sPassword)
		{
			if (null === $this->oPopPassD)
			{
				$this->oPopPassD = new Poppassd(
					$this->getConfig('Host', '127.0.0.1'),
					$this->getConfig('Port', 106)
				);
			}

			if ($this->oPopPassD && $this->oPopPassD->Connect())
			{
				try
				{
					if ($this->oPopPassD->Login($oAccount->IncomingLogin, $oAccount->getPassword()))
					{
						if (!$this->oPopPassD->NewPass($sPassword))
						{
							throw new \Aurora\System\Exceptions\ApiException(\Aurora\System\Exceptions\Errs::UserManager_AccountNewPasswordRejected);
						}
						else
						{
							$bResult = true;
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
		
		return $bResult;
	}
	
	public function GetSettings()
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::Anonymous);
		
		$sSupportedServers = implode("\n", $this->getConfig('SupportedServers', array()));
		
		$aAppData = array(
//			'Disabled' => $this->getConfig('Disabled', false),
			'SupportedServers' => $sSupportedServers,
			'Host' => $this->getConfig('Host', ''),
			'Port' => $this->getConfig('Port', 0),
		);

		return $aAppData;
	}
	
//	public function UpdateSettings($Disabled, $SupportedServers, $Host, $Port)
	public function UpdateSettings($SupportedServers, $Host, $Port)
	{
		\Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::TenantAdmin);
		
		$aSupportedServers = preg_split('/\r\n|[\r\n]/', $SupportedServers);
		
//		$this->setConfig('Disabled', $Disabled);
		$this->setConfig('SupportedServers', $aSupportedServers);
		$this->setConfig('Host', $Host);
		$this->setConfig('Port', $Port);
		$this->saveModuleConfig();
		return true;
	}

	protected function getMailModule()
	{
		if (!$this->oMailModule)
		{
			$this->oMailModule = \Aurora\System\Api::GetModule('Mail');
		}

		return $this->oMailModule;
	}
}