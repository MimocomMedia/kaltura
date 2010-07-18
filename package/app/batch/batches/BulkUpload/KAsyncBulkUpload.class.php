<?php
require_once ("bootstrap.php");
/**
 * Will initiate a single bulk upload.
 * The state machine of the job is as follows:
 * 	 	get the csv, parse it and validate it
 * 		creates the entries
 *
 * @package Scheduler
 * @subpackage Bulk-Upload
 */
class KAsyncBulkUpload extends KBatchBase
{
	const VALUES_COUNT_V1 = 5;
	const VALUES_COUNT_V2 = 12;
	const BULK_UPLOAD_DATE_FORMAT = '%Y-%m-%dT%H:%i:%s';
	
	/**
	 * @return number
	 */
	public static function getType()
	{
		return KalturaBatchJobType::BULKUPLOAD;
	}
	
	protected function init()
	{
		$this->saveQueueFilter(self::getType());
	}
	
	public function run()
	{
		KalturaLog::info("Bulk upload batch is running");
		
		if($this->taskConfig->isInitOnly())
			return $this->init();
		
		$jobs = $this->kClient->batch->getExclusiveBulkUploadJobs($this->getExclusiveLockKey(), $this->taskConfig->maximumExecutionTime, 1, $this->getFilter());
		
		KalturaLog::info(count($jobs) . " bulk upload jobs to perform");
		
		if(! count($jobs))
		{
			KalturaLog::info("Queue size: 0 sent to scheduler");
			$this->saveSchedulerQueue(self::getType());
			return;
		}
		
		foreach($jobs as $job)
			$this->startBulkUpload($job, $job->data);
	}
	
	/**
	 * @param array $fields
	 * @return string
	 */
	private function getDateFormatRegex(&$fields = null)
	{
		$replace = array(
			'%Y' => '([1-2][0-9]{3})',
			'%m' => '([0-1][0-9])',
			'%d' => '([0-3][0-9])',
			'%H' => '([0-2][0-9])',
			'%i' => '([0-5][0-9])',
			'%s' => '([0-5][0-9])',
//			'%T' => '([A-Z]{3})',
		);
	
		$fields = array();
		$arr = null;
//		if(!preg_match_all('/%([YmdTHis])/', self::BULK_UPLOAD_DATE_FORMAT, $arr))
		if(!preg_match_all('/%([YmdHis])/', self::BULK_UPLOAD_DATE_FORMAT, $arr))
			return false;
	
		$fields = $arr[1];
		
		return '/' . str_replace(array_keys($replace), $replace, self::BULK_UPLOAD_DATE_FORMAT) . '/';
	}
	
	/**
	 * @param string $str
	 * @return boolean
	 */
	private function isFormatedDate($str)
	{
		$regex = $this->getDateFormatRegex();
		
		return preg_match($regex, $str);
	}
	
	/**
	 * @param string $str
	 * @return int
	 */
	private function parseFormatedDate($str)
	{
		KalturaLog::debug("parseFormatedDate($str)");
		
		if(function_exists('strptime'))
		{
			$ret = strptime($str, self::BULK_UPLOAD_DATE_FORMAT);
			if($ret)
			{
				KalturaLog::debug("Formated Date [$ret] " . date('Y-m-d\TH:i:s', $ret));
				return $ret;
			}
		}
			
		$fields = null;
		$regex = $this->getDateFormatRegex($fields);
		
		$values = null;
		if(!preg_match($regex, $str, $values))
			return null;
			
		$hour = 0;
		$minute = 0;
		$second = 0;
		$month = 0;
		$day = 0;
		$year = 0;
		$is_dst = 0;
		
		foreach($fields as $index => $field)
		{
			$value = $values[$index + 1];
			
			switch($field)
			{
				case 'Y':
					$year = intval($value);
					break;
					
				case 'm':
					$month = intval($value);
					break;
					
				case 'd':
					$day = intval($value);
					break;
					
				case 'H':
					$hour = intval($value);
					break;
					
				case 'i':
					$minute = intval($value);
					break;
					
				case 's':
					$second = intval($value);
					break;
					
//				case 'T':
//					$date = date_parse($value);
//					$hour -= ($date['zone'] / 60);
//					break;
					
			}
		}
		
		KalturaLog::debug("gmmktime($hour, $minute, $second, $month, $day, $year)");
		$ret = gmmktime($hour, $minute, $second, $month, $day, $year);
		if($ret)
		{
			KalturaLog::debug("Formated Date [$ret] " . date('Y-m-d\TH:i:s', $ret));
			return $ret;
		}
			
		KalturaLog::debug("Formated Date [null]");
		return null;
	}
		
	/**
	 * @param string $str
	 * @return boolean
	 */
	private function isUrl($str)
	{
		KalturaLog::debug("isUrl($str)");
		
		$str = KCurlWrapper::encodeUrl($str);
		
		$strRegex = "^((https?)|(ftp)):\\/\\/" . "?(([0-9a-z_!~*'().&=+$%-]+:)?[0-9a-z_!~*'().&=+$%-]+@)?" . //user@
					"(([0-9]{1,3}\\.){3}[0-9]{1,3}" . // IP- 199.194.52.184
					"|" . // allows either IP or domain
					"([0-9a-z_!~*'()-]+\\.)*" . // tertiary domain(s)- www.
					"([0-9a-z][0-9a-z-]{0,61})?[0-9a-z]\\." . // second level domain
					"[a-z]{2,6})" . // first level domain- .com or .museum
					"(:[0-9]{1,4})?" . // port number- :80
					"((\\/?)|" . // a slash isn't required if there is no file name
					"(\\/[0-9a-z_!~*'().;?:@&=+$,%#-]+)+)$";
		
		return preg_match("/$strRegex/i", $str);
	}
	
	/**
	 * @param string $item
	 */
	public static function trimArray(&$item)
	{
		$item = trim($item);
	}
	
	private function startBulkUpload(KalturaBatchJob $job, KalturaBulkUploadJobData $bulkUploadJobData)
	{
		KalturaLog::debug("startBulkUpload($job->id)");

		$maxRecords = $this->taskConfig->params->maxRecords;
		$multiRequestSize = $this->taskConfig->params->multiRequestSize;
		KalturaLog::debug("MultiRequestSize: $multiRequestSize");
		
		// reporting start of work
		$this->updateJob($job, 'Fetching file', KalturaBatchJobStatus::QUEUED, 1);
		
		// opens the csv file
		$fileHandle = fopen($bulkUploadJobData->csvFilePath, "r");
		
		if(! $fileHandle) // fails and exit
			return $this->closeJob($job, KalturaBatchJobErrorTypes::APP, KalturaBatchJobAppErrors::CSV_FILE_NOT_FOUND, "File not found: $bulkUploadJobData->csvFilePath", KalturaBatchJobStatus::FAILED);
			
		KalturaLog::info("Opened file: $bulkUploadJobData->csvFilePath");
		
		$startLineNumber = 0;
		$bulkUploadLastResult = null;
		try{
			$bulkUploadLastResult = $this->kClient->batch->getBulkUploadLastResult($job->id);
		}
		catch(Exception $e){
			KalturaLog::err("getBulkUploadLastResult: " . $e->getMessage());
		}
		
		if($bulkUploadLastResult)
			$startLineNumber = $bulkUploadLastResult->lineIndex;
		
		$lineNumber = 0;
		while($lineNumber < $startLineNumber)
		{
			fgetcsv($fileHandle);
			$lineNumber++;
		}
		
		$multiRequestCounter = 0;
		$bulkUploadResults = array();
		$csvVersion = KalturaBulkUploadCsvVersion::V1;
		
		// start multi request for all invalid lines
		$this->kClient->startMultiRequest();
		
		$values = fgetcsv($fileHandle);
		while($values)
		{
			// send chunk of requests
			if($multiRequestCounter > $multiRequestSize)
			{
				$this->kClient->doMultiRequest();
				
				KalturaLog::info("Sent $multiRequestCounter invalid lines results");
				
				// check if job aborted
				if($this->isAborted($job))
					return;
				
				// start a new multi request
				$this->kClient->startMultiRequest();
				
				$multiRequestCounter = 0;
			}
			
			$lineNumber ++;
			
			// creates a result object 
			$bulkUploadResult = new KalturaBulkUploadResult();
			$bulkUploadResult->bulkUploadJobId = $job->id;
			$bulkUploadResult->lineIndex = $lineNumber;
			$bulkUploadResult->partnerId = $job->partnerId;
			$bulkUploadResult->rowData = join(',', $values);
			
			if(substr(trim($values[0]), 0, 1) == '#') // is a remark
			{
				$values = fgetcsv($fileHandle);
				continue;
			}
				
			// check variables count
			if(count($values) != self::VALUES_COUNT_V1 && count($values) != self::VALUES_COUNT_V2)
			{
				$bulkUploadResult->entryStatus = KalturaEntryStatus::ERROR_IMPORTING;
				$bulkUploadResult->errorDescription = "Wrong number of values on line $lineNumber";
				$this->kClient->batch->addBulkUploadResult($bulkUploadResult);
				$multiRequestCounter ++;
				$values = fgetcsv($fileHandle);
				continue;
			}
			
			// trim the values
			array_walk($values, array('KAsyncBulkUpload', 'trimArray'));
			
			// sets the result values
			$bulkUploadResult->title = $values[0];
			$bulkUploadResult->description = $values[1];
			$bulkUploadResult->tags = $values[2];
			$bulkUploadResult->url = $values[3];
			$bulkUploadResult->contentType = $values[4];
			$bulkUploadResult->entryStatus = KalturaEntryStatus::IMPORT;
			
			if(count($values) == self::VALUES_COUNT_V2)
			{
				$csvVersion = KalturaBulkUploadCsvVersion::V2;
				
			    $bulkUploadResult->conversionProfileId = is_numeric($values[5]) ? (int) $values[5] : null;
			    $bulkUploadResult->accessControlProfileId = is_numeric($values[6]) ? (int) $values[6] : null;
			    $bulkUploadResult->category = strlen($values[7]) ? $values[7] : null;
			    $scheduleStartDate = strlen($values[8]) ? $values[8] : null;
			    $scheduleEndDate = strlen($values[9]) ? $values[9] : null;
			    $bulkUploadResult->thumbnailUrl = strlen($values[10]) ? $values[10] : null;
			    $bulkUploadResult->partnerData = strlen($values[11]) ? $values[11] : null;
			}
			
			
			if($lineNumber > $maxRecords) // check max records
			{
				$bulkUploadResult->entryStatus = KalturaEntryStatus::ERROR_IMPORTING;
				$bulkUploadResult->errorDescription = "Exeeded max records count per bulk";
				$this->kClient->batch->addBulkUploadResult($bulkUploadResult);
				$multiRequestCounter ++;
			}
			elseif(! $this->isUrl($bulkUploadResult->url)) // validates the url
			{
				$bulkUploadResult->entryStatus = KalturaEntryStatus::ERROR_IMPORTING;
				$bulkUploadResult->errorDescription = "Invalid url '$bulkUploadResult->url' on line $lineNumber";
				$this->kClient->batch->addBulkUploadResult($bulkUploadResult);
				$multiRequestCounter ++;
			}
			elseif($scheduleStartDate && !$this->isFormatedDate($scheduleStartDate))
			{
				$bulkUploadResult->entryStatus = KalturaEntryStatus::ERROR_IMPORTING;
				$bulkUploadResult->errorDescription = "Invalid schedule start date '$scheduleStartDate' on line $lineNumber";
				$this->kClient->batch->addBulkUploadResult($bulkUploadResult);
				$multiRequestCounter ++;
			}
			elseif($scheduleEndDate && !$this->isFormatedDate($scheduleEndDate))
			{
				$bulkUploadResult->entryStatus = KalturaEntryStatus::ERROR_IMPORTING;
				$bulkUploadResult->errorDescription = "Invalid schedule end date '$scheduleEndDate' on line $lineNumber";
				$this->kClient->batch->addBulkUploadResult($bulkUploadResult);
				$multiRequestCounter ++;
			}
			else // store the valid results
			{
				$bulkUploadResult->scheduleStartDate = $this->parseFormatedDate($scheduleStartDate);
				$bulkUploadResult->scheduleEndDate = $this->parseFormatedDate($scheduleEndDate);
				
				$bulkUploadResults[] = $bulkUploadResult;
			}
			
			$values = fgetcsv($fileHandle);
		}
		fclose($fileHandle);
		
		// send all invalid results
		$this->kClient->doMultiRequest();
		
		KalturaLog::info("Sent $multiRequestCounter invalid lines results");
		KalturaLog::info("CSV file parsed, $lineNumber lines with " . ($lineNumber - count($bulkUploadResults)) . ' invalid records');
		
		$bulkUploadJobData->csvVersion = $csvVersion;
		
		// reports that the parsing done
		$msg = "CSV file parsed, $lineNumber lines with " . ($lineNumber - count($bulkUploadResults)) . ' invalid records';
		$updateData = new KalturaBulkUploadJobData();
		$updateData->csvVersion = $csvVersion;
		$this->updateJob($job, $msg, KalturaBatchJobStatus::PROCESSING, 2, $updateData);
		
		// check if job aborted
		if($this->isAborted($job))
			return;
		
		// start a multi request for add entries
		$this->startMultiRequestForPartnerId($job->partnerId);
		$multiRequestCounter = 0;
		$bulkUploadResultChunk = array(); // store the results of the created entries
		

		KalturaLog::info("job[$job->id] start creating entries");
		foreach($bulkUploadResults as $bulkUploadResult)
		{
			// send chunk of requests
			if($multiRequestCounter > $multiRequestSize)
			{
				// commit the multi request entries
				$requestResults = $this->doMultiRequestForPartnerId();
				
				if(count($requestResults) != count($bulkUploadResultChunk))
				{
					$err = __FILE__ . ', line: ' . __LINE__ . ' $requestResults and $$bulkUploadResultChunk must have the same size';
					return $this->closeJob($job, KalturaBatchJobErrorTypes::APP, null, $err, KalturaBatchJobStatus::FAILED);
				}
					
				// saving the results with the created enrty ids
				$this->updateEntriesResults($requestResults, $bulkUploadResultChunk);
				$bulkUploadResultChunk = array();
				
				// check if job aborted
				if($this->isAborted($job))
					return;
				
				// start a new multi request
				$this->startMultiRequestForPartnerId($job->partnerId);
				
				$multiRequestCounter = 0;
			}
			
			$mediaEntry = new KalturaMediaEntry();
			$mediaEntry->name = $bulkUploadResult->title;
			$mediaEntry->description = $bulkUploadResult->description;
			$mediaEntry->tags = $bulkUploadResult->tags;
			$mediaEntry->partnerId = $job->partnerId;
			$mediaEntry->userId = $bulkUploadJobData->userId;
			$mediaEntry->conversionQuality = $bulkUploadJobData->conversionProfileId;
			
			if($csvVersion == KalturaBulkUploadCsvVersion::V2)
			{
				if($bulkUploadResult->conversionProfileId)
			    	$mediaEntry->conversionQuality = $bulkUploadResult->conversionProfileId;
			    	
				if($bulkUploadResult->accessControlProfileId)
			    	$mediaEntry->accessControlId = $bulkUploadResult->accessControlProfileId;
			    	
			    if($bulkUploadResult->category)
			    	$mediaEntry->categories = $bulkUploadResult->category;
			    	
			    if($bulkUploadResult->scheduleStartDate)
			    	$mediaEntry->startDate = $bulkUploadResult->scheduleStartDate;
			    	
			    if($bulkUploadResult->scheduleEndDate)
			    	$mediaEntry->endDate = $bulkUploadResult->scheduleEndDate;
			    	
			    if($bulkUploadResult->thumbnailUrl)
			    	$mediaEntry->thumbnailUrl = $bulkUploadResult->thumbnailUrl;
			    	
			    if($bulkUploadResult->partnerData)
			    	$mediaEntry->partnerData = $bulkUploadResult->partnerData;
			}
			
			switch(strtolower($bulkUploadResult->contentType))
			{
				case 'image':
					$mediaEntry->mediaType = KalturaMediaType::IMAGE;
					break;
				
				case 'audio':
					$mediaEntry->mediaType = KalturaMediaType::AUDIO;
					break;
				
				default:
					$mediaEntry->mediaType = KalturaMediaType::VIDEO;
					break;
			}
			
			$bulkUploadResultChunk[] = $bulkUploadResult;
			$this->kClient->media->addFromBulk($mediaEntry, $bulkUploadResult->url, $job->id);
			$multiRequestCounter ++;
		}
		
		// commit the multi request entries
		$requestResults = $this->doMultiRequestForPartnerId();
		
		KalturaLog::info("job[$job->id] finish creating entries");
	
		if(count($requestResults) != count($bulkUploadResultChunk))
		{
			$err = __FILE__ . ', line: ' . __LINE__ . ' $requestResults and $$bulkUploadResultChunk must have the same size';
			return $this->closeJob($job, KalturaBatchJobErrorTypes::APP, null, $err, KalturaBatchJobStatus::FAILED);
		}
		
		// saving the results with the created enrty ids
		if(count($requestResults))
			$this->updateEntriesResults($requestResults, $bulkUploadResultChunk);
		
		// reports almost done
		// the closer will report finished after checking the imports and converts
		return $this->closeJob($job, null, null, 'Waiting for imports and conversion', KalturaBatchJobStatus::ALMOST_DONE);
	}
	
	/**
	 * @param int $partnerId
	 */
	private function startMultiRequestForPartnerId($partnerId)
	{
		$this->kClientConfig->partnerId = $partnerId;
		$this->kClient->setConfig($this->kClientConfig);
		
		$this->kClient->startMultiRequest();
	}
	
	/**
	 * @return array
	 */
	private function doMultiRequestForPartnerId()
	{
		$requestResults = $this->kClient->doMultiRequest();
		
		$this->kClientConfig->partnerId = $this->taskConfig->getPartnerId();
		$this->kClient->setConfig($this->kClientConfig);
		
		return $requestResults;
	}
	
	/**
	 * save the results for returned created entries
	 * 
	 * @param array $requestResults
	 * @param array $bulkUploadResults
	 */
	private function updateEntriesResults(array $requestResults, array $bulkUploadResults)
	{
		KalturaLog::debug("updateEntriesResults(" . count($requestResults) . ", " . count($bulkUploadResults) . ")");
		
		$this->kClient->startMultiRequest();
		
		KalturaLog::info("Updating " . count($requestResults) . " results");
		
		// checking the created entries
		foreach($requestResults as $index => $requestResult)
		{
			$bulkUploadResult = $bulkUploadResults[$index];
			
			if($requestResult instanceof Exception)
			{
				$bulkUploadResult->entryStatus = KalturaEntryStatus::ERROR_IMPORTING;
				$bulkUploadResult->errorDescription = $requestResult->getMessage();
				$this->kClient->batch->addBulkUploadResult($bulkUploadResult);
				continue;
			}
			
			if(! ($requestResult instanceof KalturaMediaEntry))
			{
				$bulkUploadResult->entryStatus = KalturaEntryStatus::ERROR_IMPORTING;
				$bulkUploadResult->errorDescription = "Returned type is " . get_class($requestResult) . ', KalturaMediaEntry was expected';
				$this->kClient->batch->addBulkUploadResult($bulkUploadResult);
				continue;
			}
			
			// update the results with the new entry id
			$bulkUploadResult->entryId = $requestResult->id;
			$this->kClient->batch->addBulkUploadResult($bulkUploadResult);
		}
		$this->kClient->doMultiRequest();
	}
	
	/**
	 * @param KalturaBatchJob $job
	 * @return boolean
	 */
	private function isAborted(KalturaBatchJob $job)
	{
		$batchJobResponse = $this->kClient->jobs->getBulkUploadStatus($job->id);
		$updatedJob = $batchJobResponse->batchJob;
		if($updatedJob->abort)
		{
			KalturaLog::info("job[$job->id] aborted");
			$this->closeJob($job, null, null, 'Aborted', KalturaBatchJobStatus::ABORTED);
			
			if($this->kClient->isMultiRequest())
				$this->kClient->doMultiRequest();
				
			return true;
		}
		return false;
	}
	
	protected function updateExclusiveJob($jobId, KalturaBatchJob $job, $entryStatus = null)
	{
		return $this->kClient->batch->updateExclusiveBulkUploadJob($jobId, $this->getExclusiveLockKey(), $job);
	}
	
	protected function freeExclusiveJob(KalturaBatchJob $job)
	{
		$resetExecutionAttempts = false;
		if($job->status == KalturaBatchJobStatus::ALMOST_DONE)
			$resetExecutionAttempts = true;
			
		$response = $this->kClient->batch->freeExclusiveBulkUploadJob($job->id, $this->getExclusiveLockKey(), $resetExecutionAttempts);
		
		KalturaLog::info("Queue size: $response->queueSize sent to scheduler");
		$this->saveSchedulerQueue(self::getType(), $response->queueSize);
		
		return $response->job;
	}
}
?>