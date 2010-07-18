<?php

/**
 * 
 * Manages the batch mechanism
 *  
 * @package Core
 * @subpackage Batch
 */
class kBatchManager
{
	/**
	 * batch createFlavorAsset orgenize a convert job data 
	 * 
	 * @param flavorParamsOutputWrap $flavor
	 * @param int $partnerId
	 * @param int $entryId
	 * @param string $flavorAssetId
	 * @return flavorAsset
	 */
	public static function createFlavorAsset(flavorParamsOutputWrap $flavor, $partnerId, $entryId, $flavorAssetId = null)
	{
		$description = kBusinessConvertDL::parseFlavorDescription($flavor);
		
		$flavorAsset = null;
		if($flavorAssetId)
		{
			// creates the flavor asset 
			$flavorAsset = flavorAssetPeer::retrieveById($flavorAssetId);
			$description = $flavorAsset->getDescription() . "\n" . $description;
			$flavorAsset->setDescription($description);
			$flavorAsset->incrementVersion();
			$flavorAsset->save();	
		}
		else
		{
			$flavorAsset = flavorAssetPeer::retrieveByEntryIdAndFlavorParams($entryId, $flavor->getFlavorParamsId());
			
			if($flavorAsset)
			{ 
				$flavorAsset->incrementVersion();
			}
			else
			{
				// creates the flavor asset 
				$flavorAsset = new flavorAsset();
				$flavorAsset->setPartnerId($partnerId);
				$flavorAsset->setEntryId($entryId);
				$flavorAsset->setDescription($description);
			}
		}
		$flavorAsset->setTags($flavor->getTags());
		$flavorAsset->setStatus(flavorAsset::FLAVOR_ASSET_STATUS_QUEUED);
		$flavorAsset->setFlavorParamsId($flavor->getFlavorParamsId());
		$flavorAsset->setFileExt($flavor->getFileExt());
		
		// decided by the business logic layer
		if($flavor->_create_anyway)
		{
			kLog::log("Flavor [" . $flavor->getFlavorParamsId() . "] selected to be created anyway");
		}
		else
		{
			if(!$flavor->IsValid())
			{
				kLog::log("Flavor [" . $flavor->getFlavorParamsId() . "] is invalid");
				$flavorAsset->setStatus(flavorAsset::FLAVOR_ASSET_STATUS_ERROR);
				$flavorAsset->save();	
				return null;
			}
			
			if($flavor->_force)
			{
				kLog::log("Flavor [" . $flavor->getFlavorParamsId() . "] is forced");
			}
			else
			{
				if($flavor->_isNonComply)
				{
					kLog::log("Flavor [" . $flavor->getFlavorParamsId() . "] is none-comply");
					$flavorAsset->setStatus(flavorAsset::FLAVOR_ASSET_STATUS_NOT_APPLICABLE);
					$flavorAsset->save();	
					return null;
				}
				
				kLog::log("Flavor [" . $flavor->getFlavorParamsId() . "] is valid");
			}
		}
		$flavorAsset->save();
		
		// save flavor params
		$flavor->setPartnerId($partnerId);
		$flavor->setEntryId($entryId);
		$flavor->setFlavorAssetId($flavorAsset->getId());
		$flavor->setFlavorAssetVersion($flavorAsset->getVersion());
		$flavor->save();
			
		return $flavorAsset;
	}
	
	
	/**
	 * batch addMediaInfo adds a media info and updates the flavor asset 
	 * 
	 * @param mediaInfo $mediaInfoDb  
	 * @return mediaInfo 
	 */
	public static function addMediaInfo(mediaInfo $mediaInfoDb)
	{
		$mediaInfoDb->save();
		kLog::log("Added media info [" . $mediaInfoDb->getId() . "] for flavor asset [" . $mediaInfoDb->getFlavorAssetId() . "]");
		
		if(!$mediaInfoDb->getFlavorAssetId())
			return $mediaInfoDb;
			
		$flavorAsset = flavorAssetPeer::retrieveById($mediaInfoDb->getFlavorAssetId());
		if(!$flavorAsset)
			return $mediaInfoDb;

		if($flavorAsset->getIsOriginal())
		{
			kLog::log("Media info is for the original flavor asset");
			$profile = myPartnerUtils::getConversionProfile2ForEntry($flavorAsset->getEntryId());
			$tags = $profile->getInputTagsMap();
			kLog::log("Flavor asset tags from profile [$tags]");
			if(!is_null($tags))
			{
				$tagsArray = explode(',', $tags);
				
				// support for old migrated profiles
				if($profile->getCreationMode() == conversionProfile2::CONVERSION_PROFILE_2_CREATION_MODE_AUTOMATIC_BYPASS_FLV)
				{
					if(!KDLWrap::CDLIsFLV($mediaInfoDb))
					{
						$key = array_search(flavorParams::TAG_MBR, $tagsArray);
						unset($tagsArray[$key]);
					}
				}
				
				$finalTagsArray = KDLWrap::CDLMediaInfo2Tags($mediaInfoDb, $tagsArray);
				$finalTags = join(',', $finalTagsArray);
				kLog::log("Flavor asset tags from KDL [$finalTags]");
				$flavorAsset->setTags($finalTags);
			}
		}
				
		kLog::log("KDLWrap::ConvertMediainfoCdl2FlavorAsset(" . $mediaInfoDb->getId() . ", " . $flavorAsset->getId() . ");");
		KDLWrap::ConvertMediainfoCdl2FlavorAsset($mediaInfoDb, $flavorAsset);
		$flavorAsset->save();

		if(!$flavorAsset->hasTag(flavorParams::TAG_MBR))
			return $mediaInfoDb;
			
		$entry = entryPeer::retrieveByPK($flavorAsset->getEntryId());
		if(!$entry)
			return $mediaInfoDb;
		
		$entry->setDimensions($mediaInfoDb->getVideoWidth(), $mediaInfoDb->getVideoHeight());
		$entry->setLengthInMsecs($mediaInfoDb->getContainerDuration());
		$entry->save();
		
		return $mediaInfoDb;
	}
	
	// common to all the jobs using the BatchJob table 
	public static function freeExclusiveBatchJob($id, kExclusiveLockKey $lockKey, $resetExecutionAttempts = false)
	{
		return kBatchExclusiveLock::freeExclusive($id, $lockKey, $resetExecutionAttempts);
	}
	
	public static function getQueueSize($schedulerId, $workerId, $jobType, $filter)
	{
		$priority = self::getNextJobPriority($jobType);
		
		$c = new Criteria();
		$filter->attachToCriteria($c);
		return kBatchExclusiveLock::getQueueSize($c, $schedulerId, $workerId, $priority, $jobType);
		
		
//		// gets queues length
//		$c = new Criteria();
//		$filter->attachToCriteria($c);
//		
//		$crit = $c->getNewCriterion(BatchJobPeer::CHECK_AGAIN_TIMEOUT, time(), Criteria::LESS_THAN);
//		$crit->addOr($c->getNewCriterion(BatchJobPeer::CHECK_AGAIN_TIMEOUT, null, Criteria::ISNULL));
//		$c->addAnd($crit);
//		
//		$queueSize = BatchJobPeer::doCount($c, false, myDbHelper::getConnection(myDbHelper::DB_HELPER_CONN_PROPEL2));
//		
//		// gets queues length
//		$c = new Criteria();
//		$c->add(BatchJobPeer::SCHEDULER_ID, $schedulerId);
//		$c->add(BatchJobPeer::WORKER_ID, $workerId);
//		$c->add(BatchJobPeer::PROCESSOR_EXPIRATION, time(), Criteria::LESS_THAN);
//		$c->add(BatchJobPeer::EXECUTION_ATTEMPTS, BatchJobPeer::getMaxExecutionAttempts($jobType), Criteria::LESS_THAN);
//		$c->add(BatchJobPeer::JOB_TYPE, $jobType);
//		$queueSize += BatchJobPeer::doCount($c, false, myDbHelper::getConnection(myDbHelper::DB_HELPER_CONN_PROPEL2));
//		
//		return $queueSize;
	}
	
	public static function cleanExclusiveJobs()
	{
		$jobs = kBatchExclusiveLock::getExpiredJobs();
		foreach($jobs as $job)
		{
			kLog::log("Cleaning job id[" . $job->getId() . "]");
			kJobsManager::updateBatchJob($job, BatchJob::BATCHJOB_STATUS_FATAL);
		}
		
		$c = new Criteria();
		$c->add(BatchJobPeer::STATUS, BatchJobPeer::getClosedStatusList(), Criteria::IN);
		$c->add(BatchJobPeer::BATCH_INDEX, null, Criteria::ISNOTNULL);
		
		// MUST be the master DB
		$jobs = BatchJobPeer::doSelect($c, myDbHelper::getConnection(myDbHelper::DB_HELPER_CONN_PROPEL2));
		foreach($jobs as $job)
		{
			kLog::log("Cleaning job id[" . $job->getId() . "]");
			$job->setSchedulerId(null);
			$job->setWorkerId(null);
			$job->setBatchIndex(null);
			$job->setProcessorExpiration(null);
			$job->save();
		}
			
		return count($jobs);
	}
	
	// common to all the jobs using the BatchJob table 
	public static function updateExclusiveBatchJob($id, kExclusiveLockKey $lockKey, BatchJob $dbBatchJob, $entryStatus = null)
	{
		$dbBatchJob = kBatchExclusiveLock::updateExclusive($id, $lockKey, $dbBatchJob);
		return kFlowManager::updated($dbBatchJob, $entryStatus);
	}
	
	public static function getExclusiveAlmostDoneJobs(kExclusiveLockKey $lockKey, $maxExecutionTime, $numberOfJobs, $jobType, BatchJobFilter $filter)
	{
		$priority = self::getNextJobPriority($jobType);
		
		$c = new Criteria();
		$filter->attachToCriteria($c);
		return kBatchExclusiveLock::getExclusiveAlmostDoneJobs($c, $lockKey, $maxExecutionTime, $numberOfJobs, $priority, $jobType);
	}
	
	/*
	 * Find what is the priority that should be used for next task
	 */
	public static function getNextJobPriority($jobType)
	{
		//$priorities = array(1 => 33, 2 => 27, 3 => 20, 4 => 13, 5 => 7);
		$priorities = kConf::get('priority_percent');
		
		$createdAt = time() - kConf::get('priority_time_range');		
//		$createdAt = kConf::get('priority_time_range');
		

		// gets the current queues
		$c = new Criteria();
		$c->add(BatchJobPeer::CREATED_AT, $createdAt, Criteria::GREATER_THAN);
		$c->add(BatchJobPeer::JOB_TYPE, $jobType);
		$c->add(BatchJobPeer::STATUS, BatchJob::BATCHJOB_STATUS_PENDING, Criteria::GREATER_THAN);
		$c->addGroupByColumn(BatchJobPeer::PRIORITY);
		
		// To prevent stress on the master DB - use the slave for checking the queue sizes
		$queues = BatchJobPeer::doCountGroupBy($c, myDbHelper::getConnection(myDbHelper::DB_HELPER_CONN_PROPEL2));
		
		// copy the queues and calcs the total
		$total = 0;
		$queues_size = array();
		foreach($queues as $queue)
		{
			$queues_size[$queue['PRIORITY']] = $queue[BatchJobPeer::COUNT];
			$total += $queue[BatchJobPeer::COUNT];
		}
		
		// go over the priorities and see if its percent not used
		foreach($priorities as $priority => $top_percent)
		{
			if(! isset($queues_size[$priority]))
				return $priority;
			
			$percent = $queues_size[$priority] / ($total / 100);
			if($percent < $top_percent)
				return $priority;
		}
		
		return 1;
	}
	
	public static function updateEntry(BatchJob $dbBatchJob, $status)
	{
		$entry = $dbBatchJob->getEntry();
		if(!$entry)
			return null;
		
		// entry status didn't change - no need to send notification
		if($entry->getStatus() == $status)
			return $entry;
		
		// backward compatibility 
		// if entry has kshow, and this is the first entry in the mix, 
		// the thumbnail of the entry should be copied into the mix entry  
		if ($status == entry::ENTRY_STATUS_READY)
			myEntryUtils::createRoughcutThumbnailFromEntry($entry, false);
			
		// entry status is ready and above, not changing status through batch job
		if($entry->getStatus() >= entry::ENTRY_STATUS_READY)
			return $entry;
		
		$entry->setStatus($status);
		$entry->save();
		
		kFlowHelper::createEntryUpdateNotification($dbBatchJob);
		
		return $entry;
	}

}