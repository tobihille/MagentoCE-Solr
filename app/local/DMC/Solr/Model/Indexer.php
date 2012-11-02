<?php
/**
 * Apache Solr Search Engine for Magento
 *
 * @category  DMC
 * @package   DMC_Solr
 * @author    Team Magento <magento@digitalmanufaktur.com>
 * @version   0.1.4
 */

class DMC_Solr_Model_Indexer extends Mage_Index_Model_Indexer_Abstract
{
	const PRODUCTS_BY_PERIOD = 100;
	
	protected function _getSolr()
	{
		return Mage::helper('solr')->getSolr();
	}
	
	/*
	public function matchEntityAndType($entity, $type) {
		$solr = Mage::helper('solr')->getSolr();
		return $solr->matchEntityAndType($entity, $type);
	}
	*/
	
	public function getName()
	{
		return Mage::helper('solr')->__('Product Solr Data');
	}
	
	public function getDescription()
	{
		return Mage::helper('solr')->__('Index product solr data');
	}
	
	protected function _registerEvent(Mage_Index_Model_Event $event)
	{
		/*
		var_dump($event);
		echo "<br><br><br>";
		$solr = $this->_getSolr();
		$solr->registerEvent($event);
		*/
	}
	
	protected function _processEvent(Mage_Index_Model_Event $event)
	{
		/*
		$solr = $this->_getSolr();
		var_dump($solr);
		echo "<br><br><br>";
		var_dump($event);
		echo "<br><br><br>";
		$solr->processEvent($event);
		die();
		*/
	}
	
	public function reindexAll($storeIds = null, $types = null)
	{
		$solr = $this->_getSolr();
		if(is_null($storeIds)) {
			$storeIds = $this->getStoresForReindex();
		}
		
		try {
			if(Mage::helper('solr')->isEnabled() && $solr->ping()) {
				foreach($storeIds as $storeId) {
					$this->reindexStore($storeId, $types);
				}
			}
			else {
				throw new Exception('Solr is unavailable, please check connection settings and solr server status');
			}
		}
		catch(Exception $e) {
			Mage::getSingleton('core/session')->addError($e->getMessage());
			if(Mage::helper('solr')->isDebugMode()) {
				Mage::helper('solr/log')->addMessage($e, false);
				Mage::getModel('core/session')->addError($e->getMessage());
			}
		}
		return $this;
	}
	
	public function reindexStore($storeCode, $types = null) {
		$solr = $this->_getSolr();
		$store = Mage::getModel('core/store')->load($storeCode);
		$types = $solr->getDocumentTypes();
		
		if($solr) {
			foreach($types as $name => $class) {
				$solr->deleteDocuments($name, $store->getId());
				$adapter = new $class();
				$items = $adapter->getSourceCollection();
				foreach($items as $item) {
					$doc = $adapter->getSolrDocument();
					if($doc->setObject($item)) {
						$doc->setStoreId($store->getId());
						if(!$solr->addDocument($doc)) {
							$solr->deleteDocument($doc);
						}
						$i++;
					}
					else {
						//echo $item->id.' !! ';
					}
					if ($i == self::PRODUCTS_BY_PERIOD) {
						$solr->addDocuments();
						$i = 0;
					}
				}
			}
			$solr->addDocuments();
			$solr->commit();
			$solr->optimize();
		}
	}
	
	public function getStoresForReindex()
	{
		$storeIds = array();
		$collections = Mage::getModel('core/store')->getCollection();
		$collections->addFieldToFilter('store_id', array('neq' => 0));
		$collections->load();
		foreach($collections as $store) {
			if(Mage::getStoreConfig('solr/general/enable', $store->getId())) {
				$storeIds[] = $store->getId();
			}
		}
		
		return $storeIds;
	}
}
