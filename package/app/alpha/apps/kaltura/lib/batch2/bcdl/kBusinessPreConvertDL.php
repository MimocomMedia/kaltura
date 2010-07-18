<?php

class kBusinessPreConvertDL
{

	/**
	 * batch redecideFlavorConvert is the decision layer for a single flavor conversion 
	 * 
	 * @param string $srcFileSyncLocalPath
	 * @param int $flavorAssetId
	 * @param int $flavorParamsOutputId
	 * @param int $mediaInfoId
	 * @param BatchJob $parentJob
	 * @param BatchJob $remoteConvertJob
	 * @param int $lastEngineType
	 * @return BatchJob 
	 */
	public static function redecideFlavorConvert($flavorAssetId, $flavorParamsOutputId, $mediaInfoId, BatchJob $parentJob, $lastEngineType)
	{
		$originalFlavorAsset = flavorAssetPeer::retrieveOriginalByEntryId($parentJob->getEntryId());
		if (is_null($originalFlavorAsset))
		{
			kLog::log('Original flavor asset not found');
			return null;
		}
		$srcSyncKey = $originalFlavorAsset->getSyncKey(flavorAsset::FILE_SYNC_FLAVOR_ASSET_SUB_TYPE_ASSET);
		
		$flavor = flavorParamsOutputPeer::retrieveByPK($flavorParamsOutputId);
		if (is_null($flavor))
		{
			kLog::log("Flavor params output not found [$flavorParamsOutputId]");
			return null;
		}
		
		return kJobsManager::addFlavorConvertJob($srcSyncKey, $flavor, $flavorAssetId, $mediaInfoId, $parentJob, $lastEngineType);
	}
	
	/**
	 * batch decideFlavorConvert is the decision layer for a single flavor conversion 
	 * 
	 * @param FileSyncKey $srcSyncKey
	 * @param int $flavorParamsId
	 * @param string $errDescription
	 * @param int $mediaInfoId
	 * @param BatchJob $parentJob
	 * @param BatchJob $remoteConvertJob
	 * @param int $lastEngineType
	 * @return BatchJob 
	 */
	public static function decideFlavorConvert(FileSyncKey $srcSyncKey, $flavorParamsId, &$errDescription, $mediaInfoId = null, BatchJob $parentJob = null, $lastEngineType = null)
	{
		$flavorParams = flavorParamsPeer::retrieveByPK($flavorParamsId);
		$mediaInfo = mediaInfoPeer::retrieveByPK($mediaInfoId);
		
		$flavor = self::validateFlavorAndMediaInfo($flavorParams, $mediaInfo, $errDescription);
		if(is_null($flavor))
			return null;
		
		$flavorAsset = kBatchManager::createFlavorAsset($flavor, $parentJob->getPartnerId(), $parentJob->getEntryId());
		if(is_null($flavorAsset))
			return null;
		
		return kJobsManager::addFlavorConvertJob($srcSyncKey, $flavor, $flavorAsset->getId(), $mediaInfo->getId(), $parentJob, $lastEngineType);
	}
	
	/**
	 * batch decideAddEntryFlavor is the decision layer for adding a single flavor conversion to an entry 
	 *
	 * @param BatchJob $parentJob
	 * @param int $entryId 
	 * @param int $flavorParamsId
	 * @param string $errDescription
	 * @param string $flavorAssetId
	 * @return BatchJob 
	 */
	public static function decideAddEntryFlavor(BatchJob $parentJob = null, $entryId, $flavorParamsId, &$errDescription)
	{
		//KalturaLog::debug(__METHOD__." - (parentJob === null) [".($parentJob === null)."] entryId [$entryId], flavorParamsId [$flavorParamsId]");
		kLog::log(__METHOD__." - (parentJob === null) [".($parentJob === null)."] entryId [$entryId], flavorParamsId [$flavorParamsId]");
		
		$originalFlavorAsset = flavorAssetPeer::retrieveOriginalByEntryId($entryId);
		if (is_null($originalFlavorAsset))
		{
			$errDescription = 'Original flavor asset not found';
			kLog::log(__METHOD__." - ".$errDescription);
			return null;
		}
		
		$mediaInfoId = null;
		$mediaInfo = mediaInfoPeer::retrieveByFlavorAssetId($originalFlavorAsset->getId());
		if($mediaInfo)
			$mediaInfoId = $mediaInfo->getId();
		
		$flavorParams = flavorParamsPeer::retrieveByPK($flavorParamsId);
		$flavor = self::validateFlavorAndMediaInfo($flavorParams, $mediaInfo, $errDescription);
		
		if (is_null($flavor))
		{
			kLog::log(__METHOD__." - Failed to validate media info [$errDescription]");
			return null;
		}
			
		if ($parentJob) // prefer the partner id from the parent job, although it should be the same
			$partnerId = $parentJob->getPartnerId();
		else
			$partnerId = $originalFlavorAsset->getPartnerId();
			
		
		$flavorAssetId = null;
		$flavorAsset = flavorAssetPeer::retrieveByEntryIdAndFlavorParams($entryId, $flavorParamsId);
		if($flavorAsset)
			$flavorAssetId = $flavorAsset->getId();
		
		$srcSyncKey = $originalFlavorAsset->getSyncKey(flavorAsset::FILE_SYNC_FLAVOR_ASSET_SUB_TYPE_ASSET);
		$flavor->_force = true; // force to convert the flavor, even if none complied
		
		$flavorAsset = kBatchManager::createFlavorAsset($flavor, $partnerId, $entryId, $flavorAssetId);
		if (!$flavorAsset)
		{
			KalturaLog::err(__METHOD__." - Failed to create flavor asset");
			return null;
		}
		$flavorAssetId = $flavorAsset->getId();
	
		$collectionTag = $flavor->getCollectionTag();
		if($collectionTag)
		{
			$entry = entryPeer::retrieveByPK($entryId);
			if(!$entry)
				throw new APIException(APIErrors::INVALID_ENTRY, $parentJob, $entryId);
		
			$dbConvertCollectionJob = null;
			if ($parentJob)
			{
				$dbConvertCollectionJob = $parentJob->createChild(false);
				$dbConvertCollectionJob->setEntryId($entryId);
				$dbConvertCollectionJob->save();
			}
			
			$flavorAssets = flavorAssetPeer::retrieveByEntryId($entryId);
			$flavorAssets = flavorAssetPeer::filterByTag($flavorAssets, $collectionTag);
			$flavors = array();
			foreach($flavorAssets as $tagedFlavorAsset)
			{
				if($tagedFlavorAsset->getStatus() == flavorAsset::FLAVOR_ASSET_STATUS_NOT_APPLICABLE || $tagedFlavorAsset->getStatus() == flavorAsset::FLAVOR_ASSET_STATUS_DELETED)
					continue;

				$flavorParamsOutput = flavorParamsOutputPeer::retrieveByFlavorAssetId($tagedFlavorAsset->getId());
				if(is_null($flavorParamsOutput))
				{
					kLog::log("Creating flavor params output for asset [" . $tagedFlavorAsset->getId() . "]");
				
					$flavorParams = flavorParamsPeer::retrieveByPK($tagedFlavorAsset->getId());
					$flavorParamsOutput = self::validateFlavorAndMediaInfo($flavorParams, $mediaInfo, $errDescription);
					
					if (is_null($flavorParamsOutput))
					{
						kLog::log(__METHOD__." - Failed to validate media info [$errDescription]");
						continue;
					}
				}
				
				if($flavorParamsOutput)
				{
					kLog::log("Adding Collection flavor [" . $flavorParamsOutput->getId() . "] for asset [" . $tagedFlavorAsset->getId() . "]");
					$flavors[$tagedFlavorAsset->getId()] = flavorParamsOutputPeer::retrieveByFlavorAssetId($tagedFlavorAsset->getId());
				}
			}
			if($flavorAssetId)
			{
				kLog::log("Updating Collection flavor [" . $flavor->getId() . "] for asset [" . $tagedFlavorAsset->getId() . "]");
				$flavors[$flavorAssetId] = $flavor;
			}
		
			switch($collectionTag)
			{
				case flavorParams::TAG_ISM:
					kLog::log("Calling addConvertIsmCollectionJob with [" . count($flavors) . "] flavor params");
					return kJobsManager::addConvertIsmCollectionJob($collectionTag, $srcSyncKey, $entry, $parentJob, $flavors, $dbConvertCollectionJob);
					
				default:
					kLog::log("Error: Invalid collection tag [$collectionTag]");
					return null;
			}
		}
		
		$dbConvertFlavorJob = null;
		if ($parentJob)
		{
			$dbConvertFlavorJob = $parentJob->createChild(false);
			$dbConvertFlavorJob->setEntryId($entryId);
			$dbConvertFlavorJob->save();
		}
		
		return kJobsManager::addFlavorConvertJob($srcSyncKey, $flavor, $flavorAsset->getId(), $mediaInfoId, $parentJob, null, $dbConvertFlavorJob);
	}
	
	/**
	 * batch validateConversionProfile validates profile completion rules 
	 * 
	 * @param mediaInfo $mediaInfo
	 * @param array $flavors is array of flavorParams
	 * @param string $errDescription
	 * @return array of flavorParamsOutput
	 */
	protected static function validateConversionProfile(mediaInfo $mediaInfo = null, array $flavors, array $flavorParamsConversionProfiles, &$errDescription)
	{
		// if there is no media info, the entire profile returned as is, decision layer ignored
		if(!$mediaInfo)
		{
			kLog::log("Validate Conversion Profile, no media info supplied");
//			$ret = array();
//			foreach($flavors as $flavor)
//			{
//				$outFlavor = new flavorParamsOutputWrap();
//				$ret[] = flavorParamsOutputPeer::doCopy($flavor, $outFlavor);
//			}
//			return $ret; 
		}
		else
		{
			kLog::log("Validate Conversion Profile, media info [" . $mediaInfo->getId() . "]");
		}
		
		// call the decision layer
		kLog::log("Generate Target " . count($flavors) . " Flavors supplied");
		$cdl = KDLWrap::CDLGenerateTargetFlavors($mediaInfo, $flavors);
		kLog::log("Generate Target " . count($cdl->_targetList) . " Flavors returned");
		
		// check for errors
		$errDescription = '';
		if(count($cdl->_errors))
		{
			$errDesc = '';
			foreach($cdl->_errors as $section => $errors)
				$errDesc .= "$section errors: " . join(";", $errors) . "\n";
				
			kLog::log("Decision layer input errors: $errDesc");
			$errDescription .= "\nMedia err: $errDesc";
		}
		
		// check for warnings
		if(count($cdl->_warnings))
		{
			$errDesc = '';
			foreach($cdl->_warnings as $section => $errors)
				$errDesc .= "$section warnings: " . join(";", $errors) . "\n";
				
			kLog::log("Decision layer input warnings: $errDesc");
			$errDescription .= "\nMedia warn: $errDesc";
		}
			
		// rv - returned value from the decision layer
		if(!$cdl->_rv)
		{
			kLog::log("Decision layer returned false");
			return null;
		}
	
		// orgenizing the flavors by the tags
		$tagedFlavors = array();
		foreach($cdl->_targetList as $flavor)
		{
			// overwrite ready behavior from the conversion profile
			$flavorParamsConversionProfile = $flavorParamsConversionProfiles[$flavor->getFlavorParamsId()];
			$flavor->_force = $flavorParamsConversionProfile->getForceNoneComplied();
			
			if($flavorParamsConversionProfile->getReadyBehavior() != flavorParamsConversionProfile::READY_BEHAVIOR_INHERIT_FLAVOR_PARAMS)
				$flavor->setReadyBehavior($flavorParamsConversionProfile->getReadyBehavior());	

			if(!$flavor->IsValid())
			{
				kLog::log("Flavor [" . $flavor->getFlavorParamsId() . "] is invalid");
				
				// if required - failing the profile
				if($flavor->getReadyBehavior() == flavorParamsConversionProfile::READY_BEHAVIOR_REQUIRED)
				{
					$errDescription = "Business decision layer, required flavor not valid: " . $flavor->getId();
					$errDescription .= kBusinessConvertDL::parseFlavorDescription($flavor);
					kLog::log($errDescription);
					return null;
				}
			}
			
			// if required - failing the profile
			if($flavor->_isNonComply)
			{
				kLog::log("Flavor [" . $flavor->getFlavorParamsId() . "] is none complied");
				
				if($flavor->getReadyBehavior() == flavorParamsConversionProfile::READY_BEHAVIOR_REQUIRED)
				{
					$errDescription = "Business decision layer, required flavor none complied: id[" . $flavor->getId() . "] flavor params id [" . $flavor->getFlavorParamsId() . "]";
					$errDescription .= kBusinessConvertDL::parseFlavorDescription($flavor);
					kLog::log($errDescription);
					return null;
				}
			}
			
			foreach($flavor->getTagsArray() as $tag)
			{
				kLog::log("Taged [$tag] flavor added [" . $flavor->getFlavorParamsId() . "]");
				$tagedFlavors[$tag][$flavor->getFlavorParamsId()] = $flavor;
			}
		}
		
		// filter out all not forced, none complied, and invalid flavors
		$finalTagedFlavors = array();
		foreach($tagedFlavors as $tag => $tagedFlavorsArray)
		{
			kLog::log("Filtering flavors by tag [$tag]");
			$finalTagedFlavors[$tag] = kBusinessConvertDL::filterTagFlavors($tagedFlavorsArray);
		}
			
		$finalFlavors = array();
		foreach($finalTagedFlavors as $tag => $tagedFlavorsArray)
		{
			foreach($tagedFlavorsArray as $flavorParamsId => $tagedFlavor)
				$finalFlavors[$flavorParamsId] = $tagedFlavor;
		}
		
		// sort the flavors to decide which one will be performed first
		usort($finalFlavors, array('kBusinessConvertDL', 'compareFlavors'));
		kLog::log(count($finalFlavors) . " flavors sorted for execution");
	
		return $finalFlavors;
	}
	
	/**
	 * batch validateFlavorAndMediaInfo validate and manipulate a flavor according to the given media info
	 * 
	 * @param flavorParams $flavor
	 * @param mediaInfo $mediaInfo
	 * @param string $errDescription
	 * @return flavorParamsOutputWrap or null for fail
	 */
	protected static function validateFlavorAndMediaInfo(flavorParams $flavor, mediaInfo $mediaInfo = null, &$errDescription)
	{
		$cdl = KDLWrap::CDLGenerateTargetFlavors($mediaInfo, array($flavor));
		
		$errDescription = '';
		if(count($cdl->_errors))
		{
			$errDesc = '';
			foreach($cdl->_errors as $section => $errors)
				$errDesc .= "$section errors: " . join(";", $errors) . "\n";
				
			kLog::log("Decision layer input error: $errDesc");
			$errDescription .= "\nMedia err: $errDesc";
		}
		
		if(count($cdl->_warnings))
		{
			$errDesc = '';
			foreach($cdl->_warnings as $section => $errors)
				$errDesc .= "$section warnings: " . join(";", $errors) . "\n";
				
			kLog::log("Decision layer input warning: $errDesc");
			$errDescription .= "\nMedia warn: $errDesc";
		}
			
		if(!$cdl->_rv)
			return null;

		return reset($cdl->_targetList);
	}
	
	public static function bypassConversion(flavorAsset $originalFlavorAsset, entry $entry, BatchJob $convertProfileJob = null)
	{
		if(!$originalFlavorAsset->hasTag(flavorParams::TAG_MBR))
		{
			$mediaInfo = mediaInfoPeer::retrieveByFlavorAssetId($originalFlavorAsset->getId());
			if($mediaInfo)
			{
				$tagsArray = $originalFlavorAsset->getTagsArray();
				$finalTagsArray = KDLWrap::CDLMediaInfo2Tags($mediaInfo, $tagsArray);
				$originalFlavorAsset->setTagsArray($finalTagsArray);
			}
		}
		
		$partner = $entry->getPartner();
		$offset = $entry->getThumbOffset($partner->getDefThumbOffset());
		
		$srcSyncKey = $originalFlavorAsset->getSyncKey(flavorAsset::FILE_SYNC_FLAVOR_ASSET_SUB_TYPE_ASSET);
		
		$postConvertData = new kPostConvertJobData();
		$postConvertData->setSrcFileSyncLocalPath(kFileSyncUtils::getLocalFilePathForKey($srcSyncKey));
		$postConvertData->setFlavorAssetId($originalFlavorAsset->getId());
		$postConvertData->setThumbOffset($offset);
		$postConvertData->setCreateThumb(true);
		$postConvertData->setThumbHeight($originalFlavorAsset->getHeight());
		$postConvertData->setThumbBitrate($originalFlavorAsset->getBitrate());
		
		kLog::log("Post Convert created with file: " . $postConvertData->getSrcFileSyncLocalPath());
		
		$job = null;
		if($convertProfileJob)
		{
			$job = $convertProfileJob->createChild(false);
		}
		else
		{
			$job = new BatchJob();
			
			$job->setStatus(BatchJob::BATCHJOB_STATUS_PENDING);
			$job->setEntryId($entry->getId());
			$job->setPartnerId($entry->getPartnerId());
		}
		
		kJobsManager::addJob($job, $postConvertData, BatchJob::BATCHJOB_TYPE_POSTCONVERT);
		
//		kJobsManager::updateBatchJob($convertProfileJob, BatchJob::BATCHJOB_STATUS_FINISHED);
//		kBatchManager::updateEntry($convertProfileJob, entry::ENTRY_STATUS_READY);
	}
	
	/**
	 * batch decideProfileConvert is the decision layer for a conversion profile
	 * 
	 * @param BatchJob $parentJob
	 * @param BatchJob $convertProfileJob
	 * @param int $mediaInfoId  
	 * @return bool true if created all required conversions
	 */
	public static function decideProfileConvert(BatchJob $parentJob, BatchJob $convertProfileJob, $mediaInfoId)
	{
		kLog::log("Conversion decision layer used for entry [" . $parentJob->getEntryId() . "]");
		$convertProfileData = $convertProfileJob->getData();
		
		$entryId = $convertProfileJob->getEntryId();
		$profile = myPartnerUtils::getConversionProfile2ForEntry($entryId);
		if(! $profile)
		{
			$errDescription = "Conversion profile for entryId [$entryId] not found";
			$convertProfileJob = kJobsManager::failBatchJob($convertProfileJob, $errDescription, BatchJob::BATCHJOB_TYPE_CONVERT_PROFILE);
			kBatchManager::updateEntry($convertProfileJob, entry::ENTRY_STATUS_ERROR_CONVERTING);
			kLog::log($errDescription);
			kLog::log("No flavors created");
			return false;
		}
	
		$originalFlavorAsset = flavorAssetPeer::retrieveOriginalByEntryId($entryId);
		if (is_null($originalFlavorAsset))
		{
			$errDescription = 'Original flavor asset not found';
			kLog::log(__METHOD__." - ".$errDescription);
			$convertProfileJob = kJobsManager::failBatchJob($convertProfileJob, $errDescription, BatchJob::BATCHJOB_TYPE_CONVERT_PROFILE);
			kBatchManager::updateEntry($convertProfileJob, entry::ENTRY_STATUS_ERROR_CONVERTING);
			return false;
		}
		
		$shouldConvert = true;
		
		// gets the list of flavor params of the conversion profile - except for the source
		$c = new Criteria();
		$c->add(flavorParamsConversionProfilePeer::CONVERSION_PROFILE_ID, $profile->getId());
		$c->add(flavorParamsConversionProfilePeer::FLAVOR_PARAMS_ID, flavorParams::SOURCE_PARAMS_ID , Criteria::NOT_EQUAL);
		$list = flavorParamsConversionProfilePeer::doSelect($c);
		if(! count($list))
		{
			kLog::log("No flavors match the profile id [{$profile->getId()}] profile is bypass");
			$shouldConvert = false;
		}
			
		$mediaInfo = mediaInfoPeer::retrieveByPK($mediaInfoId);
		
		if($profile->getCreationMode() == ConversionProfile2::CONVERSION_PROFILE_2_CREATION_MODE_AUTOMATIC_BYPASS_FLV)
		{
			kLog::log("The profile created from old conversion profile with bypass flv");
			$isFlv = false;
			if($mediaInfo)
				$isFlv = KDLWrap::CDLIsFLV($mediaInfo);
			
			if($isFlv && $originalFlavorAsset->hasTag(flavorParams::TAG_MBR))
			{
				kLog::log("The source is mbr and flv, conversion will be bypassed");
				$shouldConvert = false;
			}
			else
			{
				kLog::log("The source is NOT mbr or flv, conversion will NOT be bypassed");
			}
		}
		
		$entry = $convertProfileJob->getEntry();
		if(!$shouldConvert)
		{
			if(!$entry)
				throw new APIException(APIErrors::INVALID_ENTRY, $convertProfileJob, $convertProfileJob->getEntryId());
				
			self::bypassConversion($originalFlavorAsset, $entry, $convertProfileJob);
			return true;
		}
		
		// gets the ids of the flavor params 
		$flavorsIds = array(); 
		$flavorParamsConversionProfiles = array();
		foreach($list as $flavorParamsConversionProfile)
		{
			$flavorsId = $flavorParamsConversionProfile->getFlavorParamsId();
			$flavorsIds[] = $flavorsId;
			$flavorParamsConversionProfiles[$flavorsId] = $flavorParamsConversionProfile;
		}
			
		// gets the flavor params by the id
		$flavors = flavorParamsPeer::retrieveByPKs($flavorsIds);
		kLog::log(count($flavorsIds) . " flavors found for this profile[" . $profile->getId() . "]");
		
		$errDescription = null;
		$finalFlavors = self::validateConversionProfile($mediaInfo, $flavors, $flavorParamsConversionProfiles, $errDescription);
			
		kLog::log(count($finalFlavors) . " flavors returned from the decision layer");
		if(is_null($finalFlavors))
		{
			$convertProfileJob = kJobsManager::failBatchJob($convertProfileJob, $errDescription);
			kLog::log("No flavors created");
			return false;
		}
		elseif(strlen($errDescription))
		{
			$err = $convertProfileJob->getDescription() . $errDescription;
			$convertProfileJob->setDescription($err);
			$convertProfileJob->save();
		}
			
		$srcSyncKey = $originalFlavorAsset->getSyncKey(flavorAsset::FILE_SYNC_FLAVOR_ASSET_SUB_TYPE_ASSET);
		
		$conversionsCreated = 0;
		
		$flavorsCollections = array();
		// create a convert job per each flavor
		foreach($finalFlavors as $flavor)
		{
			$flavorAsset = kBatchManager::createFlavorAsset($flavor, $entry->getPartnerId(), $entry->getId());
			if(!$flavorAsset)
			{
				kLog::log("Flavor asset could not be created, flavor conversion won't be created");
				continue;
			}
			
			$collectionTag = $flavor->getCollectionTag();
			if($collectionTag)
			{
				$flavorsCollections[$collectionTag][] = $flavor;
				continue;
			}
				
			kLog::log("Adding flavor conversion with flavor params output id [" . $flavor->getId() . "] and flavor params asset id [" . $flavorAsset->getId() . "]");
			$createdJob = kJobsManager::addFlavorConvertJob($srcSyncKey, $flavor, $flavorAsset->getId(), $mediaInfoId, $parentJob);
			
			if($createdJob)
				$conversionsCreated++;
		}
		
		foreach($flavorsCollections as $tag => $flavors)
		{
			switch($tag)
			{
				case flavorParams::TAG_ISM:
					$createdJob = kJobsManager::addConvertIsmCollectionJob($tag, $srcSyncKey, $entry, $parentJob, $flavors);
					if($createdJob)
						$conversionsCreated++;
					break;
					
				default:
					kLog::log("Error: Invalid collection tag [$tag]");
					break;
			}
		}
			
		if(!$conversionsCreated)
		{
			$convertProfileJob = kJobsManager::failBatchJob($convertProfileJob, $errDescription);
			kLog::log("No flavors created");
			return false;
		}
		
		return true;
	}
}