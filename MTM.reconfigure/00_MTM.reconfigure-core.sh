#!/bin/sh

#### SET STATIC VARIABLES ###
munki_log='/Library/Managed Installs/Logs/ManagedSoftwareUpdate.log'
munki_pref_file='/Library/Preferences/ManagedInstalls.plist'
onboardinfo_file='/Library/Managed Installs/onboardinfo'
onboardinfo2_file='/Library/Managed Installs/onboardinfo2'

#### DOWNLOAD LATEST VERSION OF ONBOARDINFO FILE ####
fingerprint=`openssl x509 -in /Library/Managed\ Installs/ssl/munki.pem -noout -fingerprint -sha1 | sed -e 's/://g' | awk -F= {'print $2'} | tr '[:upper:]' '[:lower:]' | sed -e 's/ *$//g'`
subject=`openssl x509 -in /Library/Managed\ Installs/ssl/munki.pem -noout -subject | sed -e 's/subject= //'`
base64subject=`/bin/echo -n "$subject" | base64 -b 0`
SoftwareRepoURL=`/usr/bin/defaults read "$munki_pref_file" 'SoftwareRepoURL'`
fullURL="$SoftwareRepoURL/MTM.reconfigure/get_info.php?fingerprint=$fingerprint&subject=$base64subject"
basicAuthHeader=`/usr/libexec/PlistBuddy -c 'Print :'AdditionalHttpHeaders:0'' "$munki_pref_file"`
/usr/bin/curl "$fullURL" -o "$onboardinfo_file" -H "$basicAuthHeader"

#### VERIFY ONBOARDINFO FILE EXISTS ####
exitcode=$?
if [ "$exitcode" != 0 ];then
	/bin/echo "$(date "+%b %d %Y %H:%M:%S %z") 'Unable to download onboard info. Exiting...'" >> $munki_log 2>&1
	exit 0
fi

#### PARSE ONBOARDINFO FILE ####
inclientidentifier=''
inrename=''
inname=''
inSoftwareRepoURL=''

while read A; do
	attr=`echo $A | cut -f1 -d:`
	val=`echo $A | cut -f2- -d: | sed -e 's/^\ //'`
	case $attr in
		clientidentifier*)
			inclientidentifier="$val"
			#echo clientidentifier
			;;
			
		rename*)
			inrename="$val"
			#echo rename
			;;

		name*)
			inname="$val"
			#echo name
			;;

		SoftwareRepoURL*)
			inSoftwareRepoURL="$val"
			#echo SoftwareRepoURL
			;;
			
		*)
			;;
	esac
done < "$onboardinfo_file"

#### UPDATE CLIENTIDENTIFIER PREFERENCE ####
ClientIdentifier=`/usr/bin/defaults read "$munki_pref_file" 'ClientIdentifier'`
if [ "$ClientIdentifier" != "$inclientidentifier" ];then
	`/usr/bin/defaults write '/Library/Preferences/ManagedInstalls.plist' 'ClientIdentifier' "$inclientidentifier"`
fi

#### UPDATE COMPUTER NAMES ####
if [ "$inrename" = '1' ];then
	compname=`scutil --get ComputerName`
	lhostname=`scutil --get LocalHostName`
	fullhostname=`scutil --get HostName`
	if [ "$compname" != "$inname" ];then
		scutil --set ComputerName "$inname"
	fi
	if [ "$lhostname" != "$inname" ];then
		scutil --set LocalHostName "$inname"
	fi
	if [ "$fullhostname" != "$inname" ];then
		scutil --set HostName "$inname"
	fi
fi

#### UPDATE SOFTWAREREPOURL PREFERENCE ####
SoftwareRepoURL=`/usr/bin/defaults read "$munki_pref_file" 'SoftwareRepoURL'`
exitcode=$?
# Defaults read doesn't return empty output
if [ ! -z "$SoftwareRepoURL" -a "$exitcode" = 0 ];then
	rememberURL="$SoftwareRepoURL"
	if [ "$SoftwareRepoURL" != "$inSoftwareRepoURL" ];then
		/bin/echo "$(date "+%b %d %Y %H:%M:%S %z") Cur SoftwareRepoURL is set to $rememberURL" >> $munki_log 2>&1
		/bin/echo "$(date "+%b %d %Y %H:%M:%S %z") New SoftwareRepoURL is set to $inSoftwareRepoURL" >> $munki_log 2>&1
		/bin/echo "$(date "+%b %d %Y %H:%M:%S %z") Updating SoftwareRepoURL and testing connection to repo" >> $munki_log 2>&1
		/usr/bin/defaults write '/Library/Preferences/ManagedInstalls.plist' 'SoftwareRepoURL' "$inSoftwareRepoURL"
		SoftwareRepoURL=`/usr/bin/defaults read $munki_pref_file 'SoftwareRepoURL'`
		newURL="$SoftwareRepoURL/MTM.reconfigure/get_info.php?fingerprint=$fingerprint&subject=$base64subject"
		/bin/echo "$(date "+%b %d %Y %H:%M:%S %z") Trying $SoftwareRepoURL" >> $munki_log 2>&1
		
		/usr/bin/curl "$newURL" -o "$onboardinfo2_file" -H "$basicAuthHeader"
		exitcode=$?
		if [ "$exitcode" = 0 ];then
			/bin/echo "$(date "+%b %d %Y %H:%M:%S %z") Connection successful. Repo URL Changed" >> $munki_log 2>&1
		else
			/usr/bin/defaults write '/Library/Preferences/ManagedInstalls.plist' 'SoftwareRepoURL' "$rememberURL"
			/bin/echo "$(date "+%b %d %Y %H:%M:%S %z") 'Repo URL change DID NOT WORK. Reverting to previous SoftwareRepoURL'" >> $munki_log 2>&1
		fi
	fi
fi
