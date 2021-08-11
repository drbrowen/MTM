#!/bin/sh

GLOBAL_PACKAGEPATH="$1"

if [ -z "$1" ]; then
    echo "Usage: $0 <full path to munkionboard-pack directory>" >&2
    exit 1
fi

VERSION="2.9.9"

. ./bash-ini-parser
cfg_parser '/etc/makemunki/config'
cfg_section_main

ONBOARDURL="$baseurl"

if [ ! -d "$GLOBAL_PACKAGEPATH/Contents" -o ! -d "$GLOBAL_PACKAGEPATH/Scripts" ]; then
    exit 1
fi
if [ -d "$GLOBAL_PACKAGEPATH"/munkionboard-out/munkionboard-$VERSION.pkg ]; then
    rm -rf "$GLOBAL_PACKAGEPATH"/munkionboard-out/munkionboard-$VERSION.pkg
fi
mkdir "$GLOBAL_PACKAGEPATH"/munkionboard-out/munkionboard-$VERSION.pkg

cat - > "$GLOBAL_PACKAGEPATH/Contents/Library/Managed Installs/initial-config/ManagedInstalls.plist" <<EOF
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
        <key>SuccessfulInstall</key>
        <true/>
        <key>ClientIdentifier</key>
        <string></string>
        <key>InstallAppleSoftwareUpdates</key>
        <false/>
        <key>LogFile</key>
        <string>/Library/Managed Installs/Logs/ManagedSoftwareUpdate.log</string>
        <key>LogToSyslog</key>
        <false/>
        <key>LoggingLevel</key>
        <integer>1</integer>
        <key>ManagedInstallDir</key>
        <string>/Library/Managed Installs</string>
        <key>PendingUpdateCount</key>
        <integer>0</integer>
        <key>SoftwareRepoURL</key>
        <string>$ONBOARDURL/ONBOARD</string>
</dict>
</plist>

EOF

# This makes the package linux-style.  Google tells you how to do this.
cd "$GLOBAL_PACKAGEPATH"/Contents
find . | cpio -o --format odc --owner 0:80 |gzip -c > "$GLOBAL_PACKAGEPATH"/munkionboard-out/munkionboard-$VERSION.pkg/Payload
cd "$GLOBAL_PACKAGEPATH"/Scripts
find . | cpio -o --format odc --owner 0:80 |gzip -c > "$GLOBAL_PACKAGEPATH"/munkionboard-out/munkionboard-$VERSION.pkg/Scripts
mkbom -u 0 -g 80 . "$GLOBAL_PACKAGEPATH"/munkionboard-out/munkionboard-$VERSION.pkg/Bom


cat - > "$GLOBAL_PACKAGEPATH/munkionboard-out/Distribution" <<EOF
<?xml version="1.0" encoding="utf-8" standalone="yes"?>
<installer-script authoringTool="edu.illinois.PackageMaker" authoringToolVersion="3.0.4" authoringToolBuild="179" minSpecVersion="1">
    <title>Munkionboard - Configure Munki for Illinois</title>
    <options customize="allow" allow-external-scripts="no" hostArchitectures="x86_64,arm64"/>
    <domains enable_anywhere="true"/>
    <choices-outline>
        <line choice="config"/>
    </choices-outline>
    <choice id="config" title="Munki configuration"
            description="Configures Munki to use the shared Munki Service">
        <pkg-ref id="edu.illinois.munkiconfig"/>
    </choice>
    <pkg-ref id="edu.illinois.munkiconfig" installKBytes="3" version="$VERSION" auth="Root">#munkionboard-$VERSION.pkg</pkg-ref>
</installer-script>
EOF

cat - > "$GLOBAL_PACKAGEPATH/munkionboard-out/munkionboard-$VERSION.pkg/PackageInfo" <<EOF
<?xml version="1.0" standalone="no"?>
<pkg-info format-version="2" install-location="/" identifier="edu.illinois.munkionboard" version="$VERSION" generator-version="InstallCmds-502 (14E46)" auth="root">
    <payload numberOfFiles="9" installKBytes="4"/>
    <bundle-version/>
    <upgrade-bundle/>
    <update-bundle/>
    <atomic-update-bundle/>
    <strict-identifier/>
    <relocate/>
    <scripts>
      <postinstall file="./postflight"/>
    </scripts>
</pkg-info>
EOF


cd "$GLOBAL_PACKAGEPATH"/munkionboard-out
xar --compression none -cf "$GLOBAL_PACKAGEPATH"/munkionboard.pkg * > "$GLOBAL_PACKAGEPATH/MTM.onboard.pkg"
xar --compression none -cf - * >> "$GLOBAL_PACKAGEPATH/MTM.onboard.pkg"

