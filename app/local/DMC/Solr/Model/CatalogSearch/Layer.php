<?php
/**
 * Apache Solr Search Engine for Magento
 *
 * @category  DMC
 * @package   DMC_Solr
 * @author    Team Magento <magento@digitalmanufaktur.com>
 * @version   0.1.4
 */
 
class DMC_Solr_Model_CatalogSearch_Layer extends Mage_Catalog_Model_Layer
{
	protected $_selectAttributes = array();
	
	protected $_staticFields = array();
	
    public function getProductCollection()
    {
        if (isset($this->_productCollections[$this->getCurrentCategory()->getId()])) {
            $collection = $this->_productCollections[$this->getCurrentCategory()->getId()];
        }
        else {
            if(Mage::helper('solr')->isEnabledOnSearchResult()) {
        		//$collection = Mage::getResourceModel('catalogsearch/fulltext_collection');
        		$collection = Mage::getModel('DMC_Solr_Model_SolrServer_Adapter_Product_Collection');
        		$this->prepareSolrProductCollection($collection);
        	}
        	else {
            	$collection = Mage::getResourceModel('catalogsearch/fulltext_collection');
            	$this->prepareProductCollection($collection);
            	
        	}
            $this->_productCollections[$this->getCurrentCategory()->getId()] = $collection;
        }
        return $collection;
    }
    
    public function prepareProductCollection($collection)
    {
        $collection->addAttributeToSelect(Mage::getSingleton('catalog/config')->getProductAttributes())
            ->addSearchFilter(Mage::helper('catalogsearch')->getQuery()->getQueryText())
            ->setStore(Mage::app()->getStore())
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            ->addStoreFilter()
            ->addUrlRewrite();

        Mage::getSingleton('catalog/product_status')->addVisibleFilterToCollection($collection);
        Mage::getSingleton('catalog/product_visibility')->addVisibleInSearchFilterToCollection($collection);
        return $this;
    }
    
    public function prepareSolrProductCollection($collection)
    {
        $collection->addAttributeToSelect(Mage::getSingleton('catalog/config')->getProductAttributes())
            ->addSearchFilter(Mage::helper('catalogsearch')->getQuery()->getQueryText())
            ->setStore(Mage::app()->getStore())
            //->addMinimalPrice()
            //->addFinalPrice()
            //->addTaxPercents()
            ->addStoreFilter()
            ->addUrlRewrite();

		$this->_addVisibleInSearchFilterToCollection($collection);
        return $this;
    }
    
	protected function _addVisibleInSearchFilterToCollection($collection) {
		$collection->setVisibility(Mage::getSingleton('catalog/product_visibility')->getVisibleInSearchIds());
	}
	
    protected function _getSetIds()
    {
		$setIds = $this->getProductCollection()->getSetIds();
        return $setIds;
    }
    
    protected function _prepareAttributeCollection($collection)
    {
        if (Mage::helper('solr')->isEnabledOnSearchResult()) {
            $collection->addFieldToFilter('additional_table.is_filterableBySolr', array('gt' => 0));
        } else {
            $collection->addIsFilterableFilter();
        }
        return $collection;
    }    
}
