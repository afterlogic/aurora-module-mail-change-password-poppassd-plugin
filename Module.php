<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\MailChangePasswordPoppassdPlugin;

use Aurora\Modules\Mail\Models\MailAccount;
use Aurora\System\Notifications;

/**
 * Allows users to change passwords on their email accounts using POPPASSD protocol.
 *
 * @license https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @license https://afterlogic.com/products/common-licensing Afterlogic Software License
 * @copyright Copyright (c) 2023, Afterlogic Corp.
 *
 * @property Settings $oModuleSettings
 *
 * @package Modules
 */
class Module extends \Aurora\System\Module\AbstractModule
{
    /**
     * @var Poppassd
     */
    protected $oPopPassD;

    public function init()
    {
        $this->oPopPassD = null;

        $this->subscribeEvent('Mail::Account::ToResponseArray', array($this, 'onMailAccountToResponseArray'));
        $this->subscribeEvent('ChangeAccountPassword', array($this, 'onChangeAccountPassword'));
    }

    /**
     * @return Module
     */
    public static function getInstance()
    {
        return parent::getInstance();
    }

    /**
     * @return Module
     */
    public static function Decorator()
    {
        return parent::Decorator();
    }

    /**
     * @return Settings
     */
    public function getModuleSettings()
    {
        return $this->oModuleSettings;
    }

    /**
     * Adds to account response array information about if allowed to change the password for this account.
     * @param array $aArguments
     * @param mixed $mResult
     */
    public function onMailAccountToResponseArray($aArguments, &$mResult)
    {
        $oAccount = $aArguments['Account'];

        if ($oAccount && $this->checkCanChangePassword($oAccount)) {
            if (!isset($mResult['Extend']) || !is_array($mResult['Extend'])) {
                $mResult['Extend'] = [];
            }
            $mResult['Extend']['AllowChangePasswordOnMailServer'] = true;
        }
    }

    /**
     * Tries to change password for account if allowed.
     * @param array $aArguments
     * @param mixed $mResult
     */
    public function onChangeAccountPassword($aArguments, &$mResult)
    {
        $bPasswordChanged = false;
        $bBreakSubscriptions = false;

        $oAccount = $aArguments['Account'] instanceof MailAccount ? $aArguments['Account'] : false;
        if ($oAccount && $this->checkCanChangePassword($oAccount) && $oAccount->getPassword() === $aArguments['CurrentPassword']) {
            $bPasswordChanged = $this->changePassword($oAccount, $aArguments['NewPassword']);
            $bBreakSubscriptions = true; // break if Poppassd plugin tries to change password in this account.
        }

        if (is_array($mResult)) {
            $mResult['AccountPasswordChanged'] = $mResult['AccountPasswordChanged'] || $bPasswordChanged;
        }

        return $bBreakSubscriptions;
    }

    /**
     * Checks if allowed to change password for account.
     * @param \Aurora\Modules\Mail\Models\MailAccount $oAccount
     * @return bool
     */
    protected function checkCanChangePassword($oAccount)
    {
        $bFound = in_array('*', $this->oModuleSettings->SupportedServers);

        if (!$bFound) {
            $oServer = $oAccount->getServer();

            if ($oServer && in_array($oServer->IncomingServer, $this->oModuleSettings->SupportedServers)) {
                $bFound = true;
            }
        }

        return $bFound;
    }

    /**
     * Tries to change password for account.
     * @param \Aurora\Modules\Mail\Models\MailAccount $oAccount
     * @param string $sPassword
     * @return boolean
     * @throws \Aurora\System\Exceptions\ApiException
     */
    protected function changePassword($oAccount, $sPassword)
    {
        $bResult = false;

        if (0 < strlen($oAccount->getPassword()) && $oAccount->getPassword() !== $sPassword) {
            if (null === $this->oPopPassD) {
                $this->oPopPassD = new Poppassd(
                    $this->oModuleSettings->Host,
                    $this->oModuleSettings->Port
                );
            }

            if ($this->oPopPassD && $this->oPopPassD->Connect()) {
                try {
                    if ($this->oPopPassD->Login($oAccount->IncomingLogin, $oAccount->getPassword())) {
                        $aNewPasswordResult = $this->oPopPassD->NewPass($sPassword);
                        if (!$aNewPasswordResult[0]) {
                            throw new \Aurora\System\Exceptions\ApiException(Notifications::CanNotChangePassword, null, $aNewPasswordResult[1]);
                        } else {
                            $bResult = true;
                        }
                    } else {
                        throw new \Aurora\System\Exceptions\ApiException(Notifications::AccountOldPasswordNotCorrect);
                    }
                } catch (\Exception $oException) {
                    $this->oPopPassD->Disconnect();
                    throw $oException;
                }
            } else {
                throw new \Aurora\System\Exceptions\ApiException(Notifications::CanNotChangePassword);
            }
        }

        return $bResult;
    }

    /**
     * Obtains list of module settings for super admin.
     * @return array
     */
    public function GetSettings()
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);

        $sSupportedServers = implode("\n", $this->oModuleSettings->SupportedServers);

        $aAppData = array(
            'SupportedServers' => $sSupportedServers,
            'Host' => $this->oModuleSettings->Host,
            'Port' => $this->oModuleSettings->Port,
        );

        return $aAppData;
    }

    /**
     * Updates module's super admin settings.
     * @param string $SupportedServers
     * @param string $Host
     * @param int $Port
     * @return boolean
     */
    public function UpdateSettings($SupportedServers, $Host, $Port)
    {
        \Aurora\System\Api::checkUserRoleIsAtLeast(\Aurora\System\Enums\UserRole::SuperAdmin);

        $aSupportedServers = preg_split('/\r\n|[\r\n]/', $SupportedServers);

        $this->setConfig('SupportedServers', $aSupportedServers);
        $this->setConfig('Host', $Host);
        $this->setConfig('Port', $Port);

        return $this->saveModuleConfig();
    }
}
