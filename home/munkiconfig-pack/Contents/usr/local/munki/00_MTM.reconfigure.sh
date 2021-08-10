#!/bin/sh

#### SET STATIC VARIABLES ####
munki_log='/Library/Managed Installs/Logs/ManagedSoftwareUpdate.log'
munki_pref_file='/Library/Preferences/ManagedInstalls.plist'
mtm00ReconfigureCoreBase='00_MTM.reconfigure-core.sh'
mtm00ReconfigureCore="/usr/local/munki/$mtm00ReconfigureCoreBase"

#### DOWNLOAD LATEST VERSION OF 00_MTM.RECONFIGURE-CORE SCRIPT ####
####       Uses Munki preference file to generate URL          ####
/bin/echo "$(date "+%b %d %Y %H:%M:%S %z") Fetching latest update code" >> $munki_log 2>&1

SoftwareRepoURL=`/usr/bin/defaults read "$munki_pref_file" 'SoftwareRepoURL'`
fullURL="$SoftwareRepoURL/MTM.reconfigure/$mtm00ReconfigureCoreBase"

basicAuthHeader=`/usr/libexec/PlistBuddy -c 'Print :'AdditionalHttpHeaders:0'' $munki_pref_file`

# Need to handle if preference doesn't exist/improperly formatted??
/usr/bin/curl "$fullURL" -o "$mtm00ReconfigureCore" -H "$basicAuthHeader"
exitcode=$?
if [ "$exitcode" != 0 ];then
	/bin/echo "$(date "+%b %d %Y %H:%M:%S %z") 'Unable to download 00_MTM.reconfigure-core. Exiting...'" >> $munki_log 2>&1
	exit 0
fi

#### SET EXECUTABLE BIT FOR 00_MTM.RECONFIGURE-CORE ####
/bin/chmod +x "$mtm00ReconfigureCore"

#### RUN 00_MTM.RECONFIGURE-CORE SCRIPT ####
/bin/echo "$(date "+%b %d %Y %H:%M:%S %z") Running $mtm00ReconfigureCoreBase" >> $munki_log 2>&1
mtm00ReconfigureCoreOutput=`$mtm00ReconfigureCore 2>&1`

#### WRITE OUTPUT TO MUNKI LOG ####
/bin/echo "$(date "+%b %d %Y %H:%M:%S %z") $mtm00ReconfigureCoreOutput" >> $munki_log 2>&1
