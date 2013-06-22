<?php
/**
 * Apache Solr Search Engine for Magento
 *
 * @category  DMC
 * @package   DMC_Solr
 * @author    Team Magento <magento@digitalmanufaktur.com>
 * @version   0.1.4
 */
 
class DMC_Solr_Model_SolrServer_Adapter_Product extends DMC_Solr_Model_SolrServer_Adapter_Abstract
{
	protected $_type = 'product';
	
	protected $_matchedEntities = array(
		Mage_Catalog_Model_Product::ENTITY => array(
			Mage_Index_Model_Event::TYPE_SAVE,
			Mage_Index_Model_Event::TYPE_DELETE,
			Mage_Index_Model_Event::TYPE_MASS_ACTION,
		),
		Mage_Catalog_Model_Resource_Eav_Attribute::ENTITY => array(
			Mage_Index_Model_Event::TYPE_SAVE,
		),
	);
	
	public function registerEvent(Mage_Index_Model_Event $event)
	{
		
	}
	
	public function getSourceCollection($storeId = null)
	{
        if(!is_null($storeId))
        {
            $store = Mage::getModel('core/store')->load($storeId)->getCode();

            Mage::app()->setCurrentStore($store);

            return Mage::getModel('catalog/product')->
                getCollection()->
                //addStoreFilter($storeId)->//$website)->
                addAttributeToFilter('status', 1)->
                addAttributeToFilter('visibility', 4)->
                setOrder('entity_id', 'asc');
        }
        else
        {
            return Mage::getModel('catalog/product')->getCollection();
        }
	}
	
	public function processEvent(Mage_Index_Model_Event $event)
	{
		if((int)Mage::getStoreConfig('solr/indexer/product_update')) {
			$solr = Mage::helper('solr')->getSolr();
			$entity = $event->getEntity();
			$type = $event->getType();
			switch($entity) {
				case Mage_Catalog_Model_Product::ENTITY:
					if($type == Mage_Index_Model_Event::TYPE_MASS_ACTION) {
						//$this->_deleteProducts();
					}
					elseif($type == Mage_Index_Model_Event::TYPE_SAVE) {
						$object = $event->getDataObject();
						$document = $this->getSolrDocument();
						if($document->setObject($object)) {
							$solr->addDocument($document);
							$solr->addDocuments();
							$solr->commit();
						}
					}
					elseif($type == Mage_Index_Model_Event::TYPE_DELETE){
						//$object = $event->getDataObject();
						//$id = $object->getId();
						
						
						//$solr->deleteByQuery('id:'.$event->getEventId().' AND row_type:'.$this->getType());
						//$solr->commit();
					}
					break;
				case Mage_Catalog_Model_Resource_Eav_Attribute::ENTITY:
					$this->_skipReindex($event);
					break;
			}
		}
	}
}
