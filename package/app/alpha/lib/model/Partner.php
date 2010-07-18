<?php

/**
 * Subclass for representing a row from the 'partner' table.
 *
 * 
 *
 * @package lib.model
 */ 
class Partner extends BasePartner
{
	const PARTNER_GROUP_TYPE_PUBLISHER = 1;
	const PARTNER_GROUP_TYPE_VAR = 2;
	const PARTNER_GROUP_TYPE_GROUP = 3;
	
	const BATCH_PARTNER_ID = -1;
	const ADMIN_CONSOLE_PARTNER_ID = -2;
	
	const PARTNER_THAT_DOWS_NOT_EXIST = -1;
	
	const VALIDATE_WRONG_LOGIN = -1;
	const VALIDATE_WRONG_PASSWORD = -2;
	const VALIDATE_TOO_MANY_INVALID_LOGINS = -3;
	const VALIDATE_LKS_DISABLED = -10;
	
	const PARTNER_STATUS_ACTIVE = 1;
	const PARTNER_STATUS_CONTENT_BLOCK = 2;
	const PARTNER_STATUS_FULL_BLOCK = 3;
	
	const CONTENT_BLOCK_SERVICE_CONFIG_ID = 'services_limited_partner.ct';
	const FULL_BLOCK_SERVICE_CONFIG_ID = 'services_block.ct';
	
	const MAX_ALLOWD_INVALID_LOGIN_COUNT = 10;
	
	const MAX_ACCESS_CONTROLS = 24;
	
	const MAX_NUMBER_OF_CATEGORIES = 100;
	
	const CATEGORIES_LOCK_TIMEOUT = 300; // in seconds
	
	// added by Tan-Tan, 06/10/09
	const PARTNER_TYPE_KMC = 1;
	const PARTNER_TYPE_OTHER = 2;
	const PARTNER_TYPE_BATCH = 3;
	
	const PARTNER_TYPE_WIKI = 100;
	const PARTNER_TYPE_WORDPRESS = 101;
	const PARTNER_TYPE_DRUPAL = 102;
	const PARTNER_TYPE_DEKIWIKI = 103;
	const PARTNER_TYPE_MOODLE = 104;
	const PARTNER_TYPE_COMMUNITY_EDITION = 105;
	
	public static $s_content_root ;
	
	public function save ( $con = null )
	{
		PartnerPeer::removePartnerFromCache( $this->getId() );
		// if the custom_data obj has change - serialize it
		$this->setCustomDataObj();
		
		return parent::save ( $con ) ;		
	}
	
	public function validateSecret ( $partner_secret , $partner_key , &$ks_max_expiry_in_seconds , $admin = false )
	{
		if ( $this->getInvalidLoginCount() > self::MAX_ALLOWD_INVALID_LOGIN_COUNT )
		{
//			return self::VALIDATE_TOO_MANY_INVALID_LOGINS;
		}
		
		$secret_to_match = $admin ? $this->getAdminSecret() : $this->getSecret() ;
		if ( $partner_secret == $secret_to_match )
		{
			$ks_max_expiry_in_seconds = $this->getKsMaxExpiryInSeconds();
			if ( $this->getInvalidLoginCount() > 0 )
			{
				$this->setInvalidLoginCount( 0 ); // reset the invalid login count 
				$this->save();
			}
			return true;
		}
		else
		{
			// same invalid count is done both for secret and for admin_secret - 
			// TODO - split counts ?
			$this->setInvalidLoginCount( $this->getInvalidLoginCount() + 1 );
			$this->save();
			
			return self::VALIDATE_WRONG_PASSWORD;
		}
	}
	
	
	// TODO - this should be part of the data on a partner in the DB
	public static function allowMultipleRoughcuts ( $partner_id )
	{
		return false;
		//if ( in_array ( $partner_id , array ( 1, 2, 8, 18 ) ) ) return false; // only for wikia 
		//return true;
	}
	
	public function getExtraData ( $lang = null )
	{
		if ( empty ( $lang ) ) $lang = "en";
		$path = self::getPartnerContentPath( );
		$path .= "/" . $this->getId() . "/Config{$lang}.txt";
		if ( !file_exists ( $path ))
			return null;
		return file_get_contents( $path );
	}
	
	// TODO - this will be called many times - cache with memcache in the best format we find 
	public function getExtraDataParsed ( $lang = null )
	{
		$extra_data_str = $this->getExtraData( $lang );
		if ( empty (  $extra_data_str ) )
			return null;
		
		$lines = explode ( "\n" , $extra_data_str );
		
		$name_value = array();
		foreach ( $lines as $line )
		{
			list ( $name , $value ) = explode ( "=" , $line , 2); // stop after the second '=" - the value side might have it in it's content 
			$name_value[$name] = $value; 
		}
		return $name_value;
	}	
	
	public static function getPartnerContentPath ( )
	{
		if ( empty ( $lang ) ) $lang = "en";
		
		if ( ! self::$s_content_root )
		{
			self::$s_content_root = myContentStorage::getFSContentRootPath(); 
		}
		
		return self::$s_content_root ;
	}
	
	public function getWidgetImagePath()
	{
		return myContentStorage::getGeneralEntityPath("partner/widget", $this->getId(), $this->getId(), ".gif" );
	}
	
	public function getName ()
	{
		return $this->getPartnerName();
	}
	
	public function setName ( $v)
	{
		return $this->setPartnerName( $v );
	}
	
	public function getSubp ()
	{
		return 100 * $this->getId();
	}
	
	public function getSubpid ()
	{
		return $this->getSubp();
	}
	
	public function getDefaultWidgetId()
	{
		return "_" . $this->getId(); 	
	}
	
	private $m_partner_stats;
	public function getPartnerStats()
	{
		return $this->m_partner_stats;
	}
	
	public function setPartnerStats( $v)
	{
		$this->m_partner_stats = $v;
	}
	
	private static $s_config_params = array ( );

	public function getUseDefaultKshow()	{		return $this->getFromCustomData( "useDefaultKshow" , null , true );	}
	public function setUseDefaultKshow( $v )	{		return $this->putInCustomData( "useDefaultKshow", $v );	}
		
	public function getShouldForceUniqueKshow()
	{
		return $this->getFromCustomData( "forceUniqueKshow" , null , false );
	}
	
	public function setShouldForceUniqueKshow( $v )
	{
		return $this->putInCustomData( "forceUniqueKshow", $v );	
	}
	
	public function getReturnDuplicateKshow()
	{
		return $this->getFromCustomData( "returnDuplicateKshow" , null , true );
	}
	
	public function setReturnDuplicateKshow( $v )
	{
		return $this->putInCustomData( "returnDuplicateKshow", $v );
	}

	public function getAllowQuickEdit()
	{
		return $this->getFromCustomData( "allowQuickEdit" , null , true );
	}
	
	public function setAllowQuickEdit( $v )
	{
		return $this->putInCustomData( "allowQuickEdit", $v );
	}

	
	public function getConversionString()
	{
		return $this->getFromCustomData( "conversionString" , null  );
	}
	
	public function setConversionString( $v )
	{
		return $this->putInCustomData( "conversionString", $v );
	}	

	public function getFlvConversionString()
	{
		return $this->getFromCustomData( "flvConversionString" , null  );
	}
	
	public function setFlvConversionString( $v )
	{
		return $this->putInCustomData( "flvConversionString", $v );
	}	
	
	/**
	 * @deprecated getDefaultConversionProfileId should be used and is used by the new conversion profiles
	 * @deprecated once the old conversion mechanism is completely obsolete - have this changed to the DEFAULT_COVERSION_PROFILE_TYPE 
	 * @return string
	 */
	public function getDefConversionProfileType()
	{
		$res = $this->getFromCustomData( "defConversionProfileType" , null , ConversionProfile::DEFAULT_COVERSION_PROFILE_TYPE  );
		if ( $res ) return  $res;
		return ConversionProfile::DEFAULT_COVERSION_PROFILE_TYPE;
		//return $this->getFromCustomData( "defConversionProfileType" , null , null  );
	}
	
	/**
	 * @param string $v
	 * @deprecated setDefaultConversionProfileId should be used and is used by the new conversion profiles 
	 */
	public function setDefConversionProfileType( $v )
	{
		return $this->putInCustomData( "defConversionProfileType", $v );
	}	

	
	/**
	 * @deprecated getDefaultConversionProfileId should be used and is used by the new conversion profiles 
	 * @return string
	 */
	public function getCurrentConversionProfileType()
	{
		$res = $this->getFromCustomData( "curConvProfType" , null );
		return $res;
	}
	
	/**
	 * @param string $v
	 * @deprecated setDefaultConversionProfileId should be used and is used by the new conversion profiles 
	 */
	public function setCurrentConversionProfileType( $v )
	{
		return $this->putInCustomData( "curConvProfType", $v );
	}	
	
	/**
	 * Get the default conversion profile id for the partner
	 * 
	 * @return int 
	 */
	public function getDefaultConversionProfileId()
	{
		return $this->getFromCustomData("defaultConversionProfileId");
	}
	
	/**
	 * Set the default access control profile id for the partner
	 *  
	 * @param int $v
	 * @return int
	 */
	public function setDefaultAccessControlId($v)
	{
		$this->putInCustomData("defaultAccessControlId", $v);
	}
	
	/**
	 * Get the default access control profile id for the partner
	 * 
	 * @return int 
	 */
	public function getDefaultAccessControlId()
	{
		return $this->getFromCustomData("defaultAccessControlId");
	}
	
	/**
	 * Set the default conversion profile id for the partner
	 *  
	 * @param int $v
	 * @return int
	 */
	public function setDefaultConversionProfileId($v)
	{
		$this->putInCustomData("defaultConversionProfileId", $v);
	}
	
	public function getNotificationsConfig()
	{
		return $this->getFromCustomData( "notificationsConfig" , null  );
	}
	
	public function setNotificationsConfig( $v )
	{
		return $this->putInCustomData( "notificationsConfig", $v );
	}	
	
	public function getAllowMultiNotification()
	{
		return $this->getFromCustomData( "allowMultiNotification" , null  );
	}
	
	public function setAllowMultiNotification( $v )
	{
		return $this->putInCustomData( "allowMultiNotification", $v );
	}

	public function getAllowLks()
	{
		return $this->getFromCustomData( "allowLks" , false  );
	}
	
	public function setAllowLks( $v )
	{
		return $this->putInCustomData( "allowLks", $v );
	}		
	
	public function getMaxUploadSize()
	{
		return $this->getFromCustomData( "maxUploadSize" , null, "150"  );
	}
	
	public function setMaxUploadSize( $v )
	{
		return $this->putInCustomData( "maxUploadSize", $v );
	}

	public function getMergeEntryLists()
	{
		return $this->getFromCustomData( "mergeEntryLists" , false  );
	}
	
	public function setMergeEntryLists( $v )
	{
		return $this->putInCustomData( "mergeEntryLists", $v );
	}

	public function getPartnerSpecificServices()
	{
		return $this->getFromCustomData( "partnerSpecificServices" , false  );
	}
	
	public function setPartnerSpecificServices( $v )
	{
		return $this->putInCustomData( "partnerSpecificServices", $v );
	}

	
	public function getEnabledServices()	{		return $this->getFromCustomData( "enabledServices" , null, false  );	}
	public function setEnabledServices( $v )	{		return $this->putInCustomData( "enabledServices", $v );	}	
	
	public function getAllowAnonymousRanking()	{		return $this->getFromCustomData( "allowAnonymousRanking" , null, false  );	}
	public function setAllowAnonymousRanking( $v )	{		return $this->putInCustomData( "allowAnonymousRanking", $v );	}
	
	public function getMatchIp()	{		return $this->getFromCustomData( "matchIp" , null, false  );	}
	public function setMatchIp( $v )	{		return $this->putInCustomData( "matchIp", $v );	}

	public function getDefThumbOffset()	{		return $this->getFromCustomData( "defThumbOffset" , false  );	}
	public function setDefThumbOffset( $v )	{		return $this->putInCustomData( "defThumbOffset", $v );	}
	
	public function getHost()	{		return $this->getFromCustomData( "host" , null, false  );	}
	public function setHost( $v )	{		return $this->putInCustomData( "host", $v );	}
		
	public function getCdnHost()	{		return $this->getFromCustomData( "cdnHost" , null, false  );	}
	public function setCdnHost( $v )	{		return $this->putInCustomData( "cdnHost", $v );	}	
		
	public function getRtmpUrl()	{		return $this->getFromCustomData( "rtmpUrl" , null, false  );	}
	public function setRtmpUrl( $v )	{		return $this->putInCustomData( "rtmpUrl", $v );	}	
		
	public function getIisHost()	{		return $this->getFromCustomData( "iisHost" , null, false  );	}
	public function setIisHost( $v )	{		return $this->putInCustomData( "iisHost", $v );	}	
	
	public function getLandingPage()	{		return $this->getFromCustomData( "landingPage" , null, null  );	}
	public function setLandingPage( $v )	{		return $this->putInCustomData( "landingPage", $v );	}	

	public function getUserLandingPage()	{		return $this->getFromCustomData( "userLandingPage" , null, null  );	}
	public function setUserLandingPage( $v )	{		return $this->putInCustomData( "userLandingPage", $v );	}	
	
	public function getMaxConccurentImports()	{		return $this->getFromCustomData( "maxConccurentImports" , null, null  );	}
	public function setMaxConccurentImports( $v )	{		return $this->putInCustomData( "maxConccurentImports", $v );	}

	public function getIsFirstLogin() { return (bool)$this->getFromCustomData("isFirstLogin", null, false); } // if not set to true explicitly, default will be false
	public function setIsFirstLogin( $v ) { $this->putInCustomData("isFirstLogin", (bool)$v); } 
	
	public function getTemplatePartnerId() { return $this->getFromCustomData("templatePartnerId", null, 0); }
	public function setTemplatePartnerId( $v ) { $this->putInCustomData("templatePartnerId", (int)$v); } 
	

	public function getLicensedJWPlayer() { return $this->getFromCustomData("licensedJWPlayer", null, 0); }
	public function setLicensedJWPlayer( $v ) { $this->putInCustomData("licensedJWPlayer", (int)$v); } 

	public function getAddEntryMaxFiles() { return $this->getFromCustomData("addEntryMaxFiles", null, myFileUploadService::MAX_FILES); }
	public function setAddEntryMaxFiles( $v ) { $this->putInCustomData("addEntryMaxFiles", (int)$v); }

	private function getCategoriesLockTime() { return $this->getFromCustomData("categoriesLockTime", null, 0); }
	private function setCategoriesLockTime( $v ) { $this->putInCustomData("categoriesLockTime", (int)$v); }

	public function getAdSupported() { return $this->getFromCustomData("adSupported", null, 0); }
	public function setAdSupported( $v ) { $this->putInCustomData("adSupported", (int)$v); } 

	public function getMaxBulkSize() { return $this->getFromCustomData("maxBulk", null, null); }
	public function setMaxBulkSize( $v ) { $this->putInCustomData("maxBulk", (int)$v); } 

	public function getLiveStreamEnabled() { return $this->getFromCustomData("liveEnabled", null, 0); }
	public function setLiveStreamEnabled( $v ) { $this->putInCustomData("liveEnabled", (int)$v); } 

	public function getStorageServePriority() { return $this->getFromCustomData("storageServePriority", null, 0); }
	public function setStorageServePriority( $v ) { $this->putInCustomData("storageServePriority", (int)$v); } 
	
	public function getStorageDeleteFromKaltura() { return $this->getFromCustomData("storageDeleteFromKaltura", null, 0); }
	public function setStorageDeleteFromKaltura( $v ) { $this->putInCustomData("storageDeleteFromKaltura", (int)$v); } 
	
	public function getFlowManagerClass() { return $this->getFromCustomData("flowManagerClass", null, 0); }
	public function setFlowManagerClass( $v ) { $this->putInCustomData("flowManagerClass", $v); } 
 
	public function getEnableAnalyticsTab() { return $this->getFromCustomData("enableAnalyticsTab", null, 0); }
	public function setEnableAnalyticsTab( $v ) { $this->putInCustomData("enableAnalyticsTab", $v); } 

	public function getEnableSilverLight() { return $this->getFromCustomData("enableSilverLight", null, 0); }
	public function setEnableSilverLight( $v ) { $this->putInCustomData("enableSilverLight", $v); } 
	
	public function lockCategories()
	{
		$this->setCategoriesLockTime(time());
		$this->save();
	}
	
	public function unlockCategories()
	{
		$this->setCategoriesLockTime(0);
		$this->save();
	}
	
	public function isCategoriesLocked()
	{
		if ($this->getCategoriesLockTime() + self::CATEGORIES_LOCK_TIMEOUT > time())
		{
			return true;
		}
		else
		{
			$this->unlockCategories();
			return false;
		}
	}

	public function getOpenId ()
	{
		return "http://www.kaltura.com/openid/pid/" . $this->getId();
	}
	
	public function getServiceConfig ()
	{
		$service_config_id = $this->getServiceConfigId() ;
		return  myServiceConfig::getInstance ( $service_config_id );	
	}
	
//include ( "_customData.php" );
/**
 * This is a partial file - it does not stand alone !
 * It can be included as-is in a lass that has 
 */
/* ---------------------- CustomData functions ------------------------- */
	private $m_custom_data = null;
	
	public function getPriority($isBulk)
	{
		$priorityGroup = PriorityGroupPeer::retrieveByPK($this->getPriorityGroupId());
		
		if(!$priorityGroup)
		{
			if($isBulk)
				return PriorityGroup::DEFAULT_BULK_PRIORITY;
				
			return PriorityGroup::DEFAULT_PRIORITY;
		}
		
		if($isBulk)
			return $priorityGroup->getBulkPriority();
			
		return $priorityGroup->getPriority();
	}
	
	public function putInCustomData ( $name , $value , $namespace = null )
	{
//		sfLogger::getInstance()->warning ( __METHOD__ . " " . ( $namespace ? $namespace. ":" : "" ) . "[$name]=[$value]");
		$custom_data = $this->getCustomDataObj( );
		$custom_data->put ( $name , $value , $namespace );
	}

	public function getFromCustomData ( $name , $namespace = null , $def_value = null )
	{
		$custom_data = $this->getCustomDataObj( );
		$res = $custom_data->get ( $name , $namespace );
		if ( $res === null ) return $def_value;
		return $res;
	}

	public function removeFromCustomData ( $name , $namespace = null)
	{

		$custom_data = $this->getCustomDataObj( );
		return $custom_data->remove ( $name , $namespace );
	}

	public function incInCustomData ( $name , $delta = 1, $namespace = null)
	{
		$custom_data = $this->getCustomDataObj( );
		return $custom_data->inc ( $name , $delta , $namespace  );
	}

	public function decInCustomData ( $name , $delta = 1, $namespace = null)
	{
		$custom_data = $this->getCustomDataObj(  );
		return $custom_data->dec ( $name , $delta , $namespace );
	}

	private function getCustomDataObj( )
	{
		if ( ! $this->m_custom_data )
		{
			$this->m_custom_data = myCustomData::fromString ( $this->getCustomData() );
		}
		return $this->m_custom_data;
	}
	
	private function setCustomDataObj()
	{
		if ( $this->m_custom_data != null )
		{
			$this->setCustomData( $this->m_custom_data->toString() );
		}
	}
/* ---------------------- CustomData functions ------------------------- */
	
}
