#!/bin/bash
KALTURA_BIN=@BIN_DIR@
KALTURA_BIN_DIRS=$KALTURA_BIN
KALTURA_BIN_FFMPEG=$KALTURA_BIN_DIRS/ffmpeg-dir
LD_LIBRARY_PATH=$KALTURA_BIN_FFMPEG/lib $KALTURA_BIN_FFMPEG/ffmpeg $@
