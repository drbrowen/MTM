#!/bin/sh

GLOBAL_PACKAGEPATH="$1"

VERSION="3.0.0"

if [ ! -d "$GLOBAL_PACKAGEPATH/Contents" -o ! -d "$GLOBAL_PACKAGEPATH/Scripts" ]; then
    exit 1
fi
mkdir "$GLOBAL_PACKAGEPATH"/munkiconfig-out/munkiconfig-$VERSION.pkg

# This makes the package linux-style.  Google tells you how to do this.
cd "$GLOBAL_PACKAGEPATH"/Contents
find . | cpio -o --format odc --owner 0:80 |gzip -c > "$GLOBAL_PACKAGEPATH"/munkiconfig-out/munkiconfig-$VERSION.pkg/Payload
mkbom -u 0 -g 80 . "$GLOBAL_PACKAGEPATH"/munkiconfig-out/munkiconfig-$VERSION.pkg/Bom

cat - > "$GLOBAL_PACKAGEPATH/munkiconfig-out/munkiconfig-$VERSION.pkg/Distribution" <<EOF
<?xml version="1.0" encoding="utf-8" standalone="yes"?>
<installer-script authoringTool="edu.illinois.PackageMaker" authoringToolVersion="3.0.4" authoringToolBuild="179" minSpecVersion="1">
    <title>MunkiConfig - Configure Munki for Illinois</title>
    <options customize="allow" allow-external-scripts="no"/>
    <domains enable_anywhere="true"/>
    <choices-outline>
        <line choice="config"/>
    </choices-outline>
    <choice id="config" title="Munki configuration"
	    description="Configures Munki to use the shared Munki Service">
        <pkg-ref id="edu.illinois.munkiconfig"/>
    </choice>
    <pkg-ref id="edu.illinois.munkiconfig" installKBytes="3" version="$VERSION" auth="Root">#munkiconfig-$VERSION.pkg</pkg-ref>
</installer-script>
EOF

cd "$GLOBAL_PACKAGEPATH"/munkiconfig-out
xar --compression none -cf "$GLOBAL_PACKAGEPATH"/munkiconfig.pkg *
xar --compression none -cf - *
