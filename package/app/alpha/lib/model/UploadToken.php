<?php

/**
 * Subclass for representing a row from the 'upload_token' table.
 *
 * 
 *
 * @package lib.model
 */ 
class UploadToken extends BaseUploadToken
{
	/**
	 * Token created but no upload has been started yet
	 */
	const UPLOAD_TOKEN_PENDING = 0;
	
	/**
	 * Upload didn't include the whole file 
	 */
	const UPLOAD_TOKEN_PARTIAL_UPLOAD = 1;
	
	/**
	 * Uploaded full file
	 */
	const UPLOAD_TOKEN_FULL_UPLOAD = 2;
	
	/**
	 * The entry was added
	 * @var int
	 */
	const UPLOAD_TOKEN_CLOSED = 3;
	
	/**
	 * The token timed out after a certain period of time
	 */
	const UPLOAD_TOKEN_TIMED_OUT = 4;
	
	/**
	 * Deleted via api
	 */
	const UPLOAD_TOKEN_DELETED = 5;
	
	public function save($con = null)
	{
		if ($this->isNew())
		{
			$this->setId($this->calculateId());
		}
		parent::save($con);
	}
	
	public function calculateId()
	{
		$dc = kDataCenterMgr::getCurrentDc();
		for ($i = 0; $i < 10; $i++)
		{
			$id = $dc["id"].'_'.md5(microtime(true));
			$existingObject = UploadTokenPeer::retrieveByPk($id);
			
			if (!$existingObject)
				return $id;
		}
		
		throw new Exception("Could not calculate unique id for upload token");
	}
	
	public function getPuserId()
	{
		return $this->getkuser()->getPuserId();
	}
}
