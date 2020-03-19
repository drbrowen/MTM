#!/bin/bash

cd /home/makemunki/MTM/softwarelist
./softwarelist.php > softwarelisttmp.json 2>/dev/null
mv softwarelisttmp.json ../portal/packages.json


