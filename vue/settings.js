import _ from 'lodash'

class LicenseSettings {
  constructor (appData) {
    const mailChangePasswordPoppassdPlugin = appData.MailChangePasswordPoppassdPlugin
    if (!_.isEmpty(mailChangePasswordPoppassdPlugin)) {
      this.host = mailChangePasswordPoppassdPlugin.Host
      this.port = mailChangePasswordPoppassdPlugin.Port
      this.supportedServers = mailChangePasswordPoppassdPlugin.SupportedServers
    }
  }

  savePoppassdSettings ({ host, port, supportedServers }) {
    this.host = host
    this.port = port
    this.supportedServers = supportedServers
  }
}

let settings = null

export default {
  init (appData) {
    settings = new LicenseSettings(appData)
  },
  savePoppassdSettings (data) {
    settings.savePoppassdSettings(data)
  },
  getPoppassdSettings () {
    return {
      host: settings.host,
      port: settings.port,
      supportedServers: settings.supportedServers
    }
  },
}
