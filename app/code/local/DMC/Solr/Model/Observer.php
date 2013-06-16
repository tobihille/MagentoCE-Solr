<?php
/**
 * Apache Solr Search Engine for Magento
 *
 * @category  DMC
 * @package   DMC_Solr
 * @author    Team Magento <magento@digitalmanufaktur.com>
 * @version   0.1.4
 */
class DMC_Solr_Model_Observer extends Mage_Core_Model_Abstract
{
    public function catalog_product_save_commit_after($observer)
    {
    	if((int)Mage::getStoreConfig('solr/indexer/product_update')) {
			$object = $observer->getEvent()->getDataObject();
			$adapter = new DMC_Solr_Model_SolrServer_Adapter_Product();
			$document = $adapter->getSolrDocument();
			$solr = Mage::helper('solr')->getSolr();
			if($document->setObject($object)) {
				$solr->addDocument($document);
				$solr->addDocuments();
				$solr->commit();
			}
		}
    }
    
    public function catalog_product_delete_before($observer)
    {
        if((int)Mage::getStoreConfig('solr/indexer/product_update')) {
        	$object = $observer->getEvent()->getDataObject();
			if(is_object($object) && $object->getId()) {
				$adapter = new DMC_Solr_Model_SolrServer_Adapter_Product();
				$solr = Mage::helper('solr')->getSolr();
    			$solr->deleteByQuery('id:'.$object->getId().' AND row_type:'.$adapter->getType());
    			$solr->commit();
			}
    	}
    }

	public function catalog_entity_attribute_save_after($observer)
	{
		if((int)Mage::getStoreConfig('solr/indexer/product_update')) {
			//Mage::helper('solr')->reindexAll();
			//Mage::getSingleton('solr/indexer')->reindexAll();
		}
	}
	
    public function cms_page_save_after($observer)
    {
    	if((int)Mage::getStoreConfig('solr/indexer/cms_update')) {
    		$object = $observer->getEvent()->getDataObject();
			$adapter = new DMC_Solr_Model_SolrServer_Adapter_Cms();
			$document = $adapter->getSolrDocument();
			$solr = Mage::helper('solr')->getSolr();
			if($document->setObject($object)) {
				$solr->addDocument($document);
				$solr->addDocuments();
				$solr->commit();
			}
		}
    }
	
    public function cms_page_delete_before($observer)
    {
        if((int)Mage::getStoreConfig('solr/indexer/cms_update')) {
        	$object = $observer->getEvent()->getDataObject();
			if(is_object($object) && $object->getId()) {
				$adapter = new DMC_Solr_Model_SolrServer_Adapter_Cms();
				$solr = Mage::helper('solr')->getSolr();
    			$solr->deleteByQuery('id:'.$object->getId().' AND row_type:'.$adapter->getType());
    			$solr->commit();
			}
    	}
    }
}
