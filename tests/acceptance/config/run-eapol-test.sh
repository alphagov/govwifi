#!/bin/bash
TEST_DIR="tests/acceptance"
eapol_test \
  -r1 \
  -t5 \
  -c $TEST_DIR/config/currentconfig.conf \
  -a `cat /etc/hosts | grep -m 1 $FRONTEND_CONTAINER | awk '{ print $1 }'` \
  -s $RADIUS_KEY \
  > $TEST_DIR/testresults.txt
# TODO: maybe connect up a directory in the host machine with the container to retain
# datetime-named test result log file for further inspection - also add to gitignore

tac $TEST_DIR/testresults.txt 2>/dev/null | grep -m1 -E "(SUCCESS|FAILURE)"
