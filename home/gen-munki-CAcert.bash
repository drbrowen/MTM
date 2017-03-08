#!/bin/bash

if [ -z "$1" -o -z "$2" ]; then
    echo "Usage: $0 <path to new munki CA> <root subject for certificate>" >&2
    echo
    echo "Example: $0 /etc/makemunki '/C=US/ST=Illinois/O=University of Illinois'" >&2
    echo
    echo Exiting.
    exit 1
fi

if [ ! -d "$1" ]; then
    echo "Path to '$1' does not exist.  Try with a different path." >&2
    echo Exiting.
    exit 1
fi

# Generate signing key pair
cd "$1"
openssl genrsa -out rootCA.key 2048
chmod 600 rootCA.key

openssl req -new -key rootCA.key -out rootCA.csr -subj "$2"

RES=$?

if [ $RES != 0 ]; then
    echo "openssl error.  Exiting." >&2
    exit 1
fi

echo "basicConstraints=critical,CA:true,pathlen:0" > extensions

# Generate the actual certificate.  This is set to 20 years.
openssl x509 -req -signkey rootCA.key -extfile extensions -in rootCA.csr -out rootCA.pem -days 7304

RES=$?
if [ $RES != 0 ]; then
    echo "openssl error.  Exiting." >&2
    exit 1
fi

