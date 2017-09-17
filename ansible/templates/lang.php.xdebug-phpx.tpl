#!/bin/bash

# README:
# - http://stackoverflow.com/questions/2288612/how-to-trigger-xdebug-profiler-for-a-command-line-php-script
# Use php with PHPStorm to debux
# in terminal:
# $ phpx script.php

php -dxdebug.remote_host=`echo $SSH_CLIENT | cut -d "=" -f 2 | awk '{print $1}'` $*