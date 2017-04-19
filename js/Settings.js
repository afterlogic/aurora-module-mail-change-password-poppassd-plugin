'use strict';

var Types = require('%PathToCoreWebclientModule%/js/utils/Types.js');

module.exports = {
	ServerModuleName: 'MailChangePasswordPoppassdPlugin',
	HashModuleName: 'mail-poppassd-plugin',
	
	SupportedServers: '',
	Host: '',
	Port: 0,
	
	/**
	 * Initializes settings of the module.
	 * 
	 * @param {Object} oAppDataSection module section in AppData.
	 */
	init: function (oAppDataSection)
	{
		if (oAppDataSection)
		{
			this.SupportedServers = Types.pString(oAppDataSection.SupportedServers);
			this.Host = Types.pString(oAppDataSection.Host);
			this.Port = Types.pInt(oAppDataSection.Port);
		}
	},
	
	updateAdmin: function (aSupportedServers, sHost, iPort)
	{
		this.SupportedServers = Types.pString(aSupportedServers);
		this.Host = Types.pString(sHost);
		this.Port = Types.pInt(iPort);
	}
};
