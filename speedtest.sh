#!/bin/sh
echo "Speed test start: $(/bin/date)"
RESULT=$(/usr/bin/speedtest)
echo "$RESULT"
echo ""
echo "Speed test end: $(/bin/date)"
echo "--------------------------"
