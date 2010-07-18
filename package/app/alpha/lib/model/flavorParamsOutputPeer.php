<?php

/**
 * Subclass for performing query and update operations on the 'flavor_params_output' table.
 *
 * 
 *
 * @package lib.model
 */ 
class flavorParamsOutputPeer extends BaseflavorParamsOutputPeer
{
	
	/**
	 * 
	 * @param $flavorAssetId
	 * @param $flavorAssetVersion
	 * @param $con
	 * 
	 * @return flavorParamsOutput
	 */
	public static function retrieveByFlavorAssetId($flavorAssetId, $flavorAssetVersion = null, $con = null)
	{
		$criteria = new Criteria();

		$criteria->add(flavorParamsOutputPeer::FLAVOR_ASSET_ID, $flavorAssetId);
		
		if($flavorAssetVersion)
		{
			$criteria->add(flavorParamsOutputPeer::FLAVOR_ASSET_VERSION, $flavorAssetVersion);
		}
		else 
		{
			$criteria->addDescendingOrderByColumn(flavorParamsOutputPeer::FLAVOR_ASSET_VERSION);
		}

		return flavorParamsOutputPeer::doSelectOne($criteria, $con);
	}
	
	/**
	 * @param string $entryId
	 * @param string $tag
	 * @param $con
	 * @return array<flavorParamsOutput>
	 */
	public static function retrieveByEntryIdAndTag($entryId, $tag, $con = null)
	{
		$criteria = new Criteria();

		$criteria->add(flavorParamsOutputPeer::ENTRY_ID, $entryId);
		$criteria->addDescendingOrderByColumn(flavorParamsOutputPeer::FLAVOR_ASSET_VERSION);

		$flavorParamsOutputs = flavorParamsOutputPeer::doSelect($criteria, $con);
		
		$ret = array();
		
		foreach($flavorParamsOutputs as $flavorParamsOutput)
			if($flavorParamsOutput->hasTag($tag))
				$ret[] = $flavorParamsOutput;
		
		return $ret;
	}
	
	/**
	 * 
	 * @param $flavorAsset
	 * @param $con
	 * 
	 * @return flavorParamsOutput
	 */
	public static function retrieveByFlavorAsset(flavorAsset $flavorAsset, $con = null)
	{
		return self::retrieveByFlavorAssetId($flavorAsset->getId(), $flavorAsset->getVersion(), $con);
	}
	
	public static function doCopy(flavorParams $flavorParams, flavorParamsOutput $flavorParamsOutput)
	{
		$flavorParamsOutput->setFlavorParamsId($flavorParams->getId());
		$flavorParamsOutput->setFlavorParamsVersion($flavorParams->getVersion());
		$flavorParamsOutput->setName($flavorParams->getName());
		$flavorParamsOutput->setTags($flavorParams->getTags());
		$flavorParamsOutput->setDescription($flavorParams->getDescription());
		$flavorParamsOutput->setReadyBehavior($flavorParams->getReadyBehavior());
		$flavorParamsOutput->setIsDefault($flavorParams->getIsDefault());
		$flavorParamsOutput->setFormat($flavorParams->getFormat());
		$flavorParamsOutput->setVideoCodec($flavorParams->getVideoCodec());
		$flavorParamsOutput->setVideoBitrate($flavorParams->getVideoBitrate());
		$flavorParamsOutput->setAudioCodec($flavorParams->getAudioCodec());
		$flavorParamsOutput->setAudioBitrate($flavorParams->getAudioBitrate());
		$flavorParamsOutput->setAudioChannels($flavorParams->getAudioChannels());
		$flavorParamsOutput->setAudioSampleRate($flavorParams->getAudioSampleRate());
		$flavorParamsOutput->setAudioResolution($flavorParams->getAudioResolution());
		$flavorParamsOutput->setWidth($flavorParams->getWidth());
		$flavorParamsOutput->setHeight($flavorParams->getHeight());
		$flavorParamsOutput->setFrameRate($flavorParams->getFrameRate());
		$flavorParamsOutput->setGopSize($flavorParams->getGopSize());
		$flavorParamsOutput->setTwoPass($flavorParams->getTwoPass());
		$flavorParamsOutput->setConversionEngines($flavorParams->getConversionEngines());
		$flavorParamsOutput->setConversionEnginesExtraParams($flavorParams->getConversionEnginesExtraParams());
		$flavorParamsOutput->setCustomData($flavorParams->getCustomData());
		$flavorParamsOutput->save();
		
		return $flavorParamsOutput;
	}
}
