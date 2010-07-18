<?php
	class KDLSanityLimits {
		const MinDuration = 100;
		const MaxDuration = 360000000;	// 10h
		const MinBitrate = 1;
		const MaxBitrate = 400000;		// 40MBps
		const MinFileSize = 1;
		const MaxFileSize = 40000000000;		// 40GB
		const MinDimension = 10;
		const MaxDimension = 5000;		// 
	
		const MinFramerate = 2;
		const MaxFramerate = 90;		// 
		const MinDAR = 0.5;
		const MaxDAR = 2.5;		// 
	}

	class KDLConstants {
		const BitrateH263Factor = 1.0;
		const BitrateVP6Factor = 1.5;
		const BitrateH264Factor = 2.0;
		const BitrateOthersRatio = 1.3;
						
				/* FlavorBitrateRedundencyFactor - 
				 * The ratio between the current and prev flavors 
				 * should be at most of that value (curr/prev=ratio).
				 * Higher ratio means that the current flavor is redundant 
				 */
		const FlavorBitrateRedundencyFactor = 0.75;

		const FlavorBitrateCompliantFactor = 0.80;

		const ProductDurationFactor = 0.95;
		const ProductBitrateFactor = 0.7;

				/*
				 * TranscodersSourceBlackList
				 */
		static $TranscodersSourceBlackList = array(
			KDLTranscoders::ON2 => array(),
			KDLTranscoders::FFMPEG => array(
				KDLConstants::ContainerIndex=>array("ogg", "ogv"),
				KDLConstants::VideoIndex=>array("theora", "iv41","iv50"),
//				KDLConstants::AudioIndex=>array("vorbis"),
			),
			KDLTranscoders::FFMPEG_AUX => array(
				KDLConstants::ContainerIndex=>array("ogg", "ogv"),
				KDLConstants::VideoIndex=>array("theora", "iv41","iv50"),
//				KDLConstants::AudioIndex=>array("vorbis"),
			),
			KDLTranscoders::MENCODER => array(),
		);
				/*
				 * TranscodersTargetBlackList
				 */
		static $TranscodersTargetBlackList = array(
			KDLTranscoders::ON2 => array(
				KDLConstants::ContainerIndex=>array(KDLContainerTarget::WMV, KDLContainerTarget::ISMV),
				KDLConstants::VideoIndex=>array("wvc1", "wmv2","wmv3")),
			KDLTranscoders::EE3 => array(
				KDLConstants::ContainerIndex=>array("flv", "mp4")),
			KDLTranscoders::FFMPEG => array(
				KDLConstants::ContainerIndex=>array(KDLContainerTarget::ISMV)),
			KDLTranscoders::FFMPEG_AUX => array(
				KDLConstants::ContainerIndex=>array(KDLContainerTarget::ISMV)),
			KDLTranscoders::ENCODING_COM => array(
				KDLConstants::ContainerIndex=>array(KDLContainerTarget::ISMV)),
			KDLTranscoders::MENCODER => array(
				KDLConstants::ContainerIndex=>array(KDLContainerTarget::ISMV)),
		);
		
		const MaxFramerate = 30.0;
		const DefaultGOP = 60;
		
		const ContainerIndex = "container";
		const VideoIndex = "video";
		const AudioIndex = "audio";
		const ImageIndex = "image";
		
		const ForceCommandLineToken="forcecommandline";
		
	}

	class KDLTranscoders {
		const KALTURA 		= "kaltura.com";
		const FFMPEG 		= "ffmpeg";
		const MENCODER 		= "mencoder";
		const ON2 			= "cli_encode";
		const ENCODING_COM 	= "encoding.com";
		const FFMPEG_AUX 	= "ffmpeg-aux";
		const EE3			= "ee3";
		const FFMPEG_VP8	= "ffmpeg-vp8";
	};
	
	class KDLVideoTarget {
		const VP8 = "vp8";
		const COPY = "copy";
	}

	class KDLAudioTarget {
		const COPY = "copy";
	};
	
	class KDLCmdlinePlaceholders {
		const InFileName	= "__inFileName__";
		const OutFileName	= "__outFileName__";
		const OutDir		= "__outDir__";
		const ConfigFileName= "__configFileName__";
	};
	
	class KDLContainerTarget {
		const WMV = "wmv";
		const ISMV = "ismv";
		const MKV = "mkv";
	};
	
	class KDLErrors {
		const SanityInvalidFileSize = 1000;
		const SanityInvalidFrameDim = 1001;
		const NoValidTranscoders = 1102;
		const MissingMediaStream = 1103;
		const NoValidMediaStream = 1104;
		const Other = 1500;
		
		public static function ToString($err, $param1=null, $param2=null){
			$str = null;
			switch($err){
				case self::SanityInvalidFileSize:
					if($param1!=="")
						$str = $err.",".$param1."#Invalid file size(".$param1."kb).";
					else
						$str = $err."#Invalid file size.";
					break;
				case self::SanityInvalidFrameDim:
					if($param1!=="")
						$str = $err.",".$param1."#Invalid frame dimensions (".$param1."px)";
					else
						$str = $err."#Invalid frame dimension.";
					break;
					break;
				case self::NoValidTranscoders:
					$str = $err."#No valid transcoders.";
					break;
				case self::MissingMediaStream:
					$str = $err."#Missing media stream.";
					break;
				case self::NoValidMediaStream:
					$str = $err."#Invalid File - No media content.";
					break;
				case self::Other:
				default:
					$str = $err."#Unknown.";
					break;
			}
			return $str;
		}
	}

	class KDLWarnings {
		const SanityInvalidDuration = 2000;
		const SanityInvalidBitrate = 2001;
		const SanityInvalidFarmerate = 2002;
		const SanityInvalidDAR = 2003;
		const ProductShortDuration = 2104;
		const ProductLowBitrate = 2105;
		const RedundantBitrate = 2106;
		const TargetBitrateNotComply = 2107;
		const TruncatingFramerate = 2108;
		const TranscoderFormat = 2109;
		const TranscoderDAR_PAR = 2110;
		const SetDefaultBitrate = 2111;
		const TranscoderLimitation = 2112;
		const MissingMediaStream = 2113;
		const ForceCommandline=2114;
		const ZeroedFrameDim=2115;
		const Other = 2500;
		
		public static function ToString($err, $param1=null, $param2=null){
			$str = null;
			switch($err){
				case self::SanityInvalidDuration:
					if($param1!=="")
						$str = $err.",".$param1."#Invalid duration(".$param1."msec).";
					else
						$str = $err."#Invalid duration.";
					break;
				case self::SanityInvalidBitrate:
					if($param1!=="")
						$str = $err.",".$param1."#Invalid bitrate(".$param1."kbs).";
					else
						$str = $err."#Invalid bitrate.";
					break;
				case self::SanityInvalidFarmerate:
					if($param1!=="")
						$str = $err.",".$param1."#Invalid framerate(".$param1."fps).";
					else
						$str = $err."#Invalid framerate.";
					break;
				case self::SanityInvalidDAR:
					if($param1!=="")
						$str = $err.",".$param1."#Invalid DAR(".$param1.").";
					else
						$str = $err."#Invalid DAR.";
					break;
				case self::ProductShortDuration:
					$str = $err.",".$param1.",".$param2."#Product duration too short - ".($param1/1000)."sec, required - ".($param2/1000)."sec.";
					break;
				case self::ProductLowBitrate:
					$str = $err.",".$param1.",".$param2."#Product bitrate too low - ".($param1)."kbps, required - ".($param2)."kbps.";
					break;
				case self::RedundantBitrate:
					$str = $err.","."#Redundant bitrate.";
					break;
					case self::TargetBitrateNotComply:
// "The target flavor bitrate {".$target->_video->_bitRate."} does not comply with the requested bitrate (".$this->_video->_bitRate.").";
					$str = $err.",".$param1.",".$param2."#The target flavor bitrate {".$param1."} does not comply with the requested bitrate (".$param2.").";
					break;
				case self::TruncatingFramerate:
					$str = $err.",".$param1.",".$param2."#Truncating FPS to (".round($param1,2).") from evaluated (".round($param2,2).").";
					break;
				case self::TranscoderFormat:
//"The transcoder (".$param1.") can not process the (".$param2. ").";
					$str = $err.",".$param1.",".$param2."#The transcoder (".$param1.") can not process the (".$param2.").";
					break;
				case self::TranscoderDAR_PAR:
//"#The transcoder (".$param1.") does not handle properly DAR<>PAR.";
					$str = $err.",".$param1."#The transcoder (".$param1.") does not handle properly DAR<>PAR.";
					break;
				case self::SetDefaultBitrate:
//"#Invalid bitrate value. Set to defualt ".$param1;
					$str = $err.",".$param1."#Invalid bitrate value. Set to defualt ".$param1;
					break;
				case self::TranscoderLimitation:
					$str = $err.",".$param1."#The transcoder (".$param1.") can not handle this content.";
					break;
				case self::MissingMediaStream:
					$str = $err."#Missing media stream.";
					break;
				case self::ForceCommandline:
					$str = $err."#Force Commandline.";
					break;
				case self::ZeroedFrameDim:
					$str = $err.",".$param1."#Got zeroed frame dim. Changed to default (".$param1.").";
					break;
				case self::Other:
				default:
					$str = $err."#Unknown.";
					break;
			}
			return $str;
		}
				
	}
	
/*
 * MPEG-PS
 * avc1, wmv3,wmva,wvc1,h264,x264
 * BDAV - Blu ray
 * Format    : MPEG Video
 * Format    : Bitmap
 */

?>