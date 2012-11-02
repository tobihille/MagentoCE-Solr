<?php
/**
 * Apache Solr Search Engine for Magento
 *
 * @category  DMC
 * @package   DMC_Solr
 * @author    Team Magento <magento@digitalmanufaktur.com>
 * @version   0.1.4
 */
 
class DMC_Solr_Model_Catalog_Layer extends Mage_Catalog_Model_Layer
{
    /**
     * Product collections array
     *
     * @var array
     */
    protected $_productCollections = array();

    /**
     * Key which can be used for load/save aggregation data
     *
     * @var string
     */
    protected $_stateKey = null;

    /**
     * Get data aggregation object
     *
     * @return Mage_CatalogIndex_Model_Aggregation
     */
    public function getAggregator()
    {
        return Mage::getSingleton('catalogindex/aggregation');
    }

    /**
     * Get layer state key
     *
     * @return string
     */
    public function getStateKey()
    {
        if ($this->_stateKey === null) {
            $this->_stateKey = 'STORE_'.Mage::app()->getStore()->getId()
                . '_CAT_'.$this->getCurrentCategory()->getId()
                . '_CUSTGROUP_' . Mage::getSingleton('customer/session')->getCustomerGroupId();
        }
        return $this->_stateKey;
    }

    /**
     * Get default tags for current layer state
     *
     * @param   array $additionalTags
     * @return  array
     */
    public function getStateTags(array $additionalTags = array())
    {
        $additionalTags = array_merge($additionalTags, array(
            Mage_Catalog_Model_Category::CACHE_TAG.$this->getCurrentCategory()->getId()
        ));
        return $additionalTags;
    }
    
    public function getProductCollection()
    {
		if (isset($this->_productCollections[$this->getCurrentCategory()->getId()])) {
            $collection = $this->_productCollections[$this->getCurrentCategory()->getId()];
        }
        else {
                if(Mage::helper('solr')->isEnabledOnCatalog()) {
        		//$collection = Mage::getResourceModel('catalogsearch/fulltext_collection');
        		$collection = Mage::getModel('DMC_Solr_Model_SolrServer_Adapter_Product_Collection');
		        $collection->setStoreId($this->getStoreId());
		        $collection->addCategoryFilter($this->getCurrentCategory());
        		$this->prepareSolrProductCollection($collection);
        	}
        	else {
	            $collection = $this->getCurrentCategory()->getProductCollection();
	            $this->prepareProductCollection($collection);
        	}
            $this->_productCollections[$this->getCurrentCategory()->getId()] = $collection;
        }
        return $collection;
    }

    public function prepareProductCollection($collection)
    {
        $attributes = Mage::getSingleton('catalog/config')
            ->getProductAttributes();
        $collection->addAttributeToSelect($attributes)
            ->addMinimalPrice()
            ->addFinalPrice()
            ->addTaxPercents()
            //->addStoreFilter()
            ;
        Mage::getSingleton('catalog/product_status')->addVisibleFilterToCollection($collection);
        Mage::getSingleton('catalog/product_visibility')->addVisibleInCatalogFilterToCollection($collection);
        $collection->addUrlRewrite($this->getCurrentCategory()->getId());

        return $this;
    }

    public function prepareSolrProductCollection($collection)
    {
        $collection->addAttributeToSelect(Mage::getSingleton('catalog/config')->getProductAttributes())
            ->setStore(Mage::app()->getStore())
            //->addMinimalPrice()
            //->addFinalPrice()
            //->addTaxPercents()
            ->addStoreFilter()
            ->addUrlRewrite();

		$this->_addVisibleInSearchFilterToCollection($collection);
		
		$collection->addUrlRewrite($this->getCurrentCategory()->getId());
        return $this;
        
        
    }
    
	protected function _addVisibleInSearchFilterToCollection($collection) {
		$collection->setVisibility(Mage::getSingleton('catalog/product_visibility')->getVisibleInSearchIds());
	}
    
    /**
     * Apply layer
     * Method is colling after apply all filters, can be used
     * for prepare some index data before getting information
     * about existing intexes
     *
     * @return Mage_Catalog_Model_Layer
     */
    public function apply()
    {
        $stateSuffix = '';
        foreach ($this->getState()->getFilters() as $filterItem) {
            $stateSuffix.= '_'.$filterItem->getFilter()->getRequestVar()
                . '_' . $filterItem->getValueString();
        }
        if (!empty($stateSuffix)) {
            $this->_stateKey = $this->getStateKey().$stateSuffix;
        }
        return $this;
    }

    /**
     * Retrieve current category model
     * If no category found in registry, the root will be taken
     *
     * @return Mage_Catalog_Model_Category
     */
    public function getCurrentCategory()
    {
        $category = $this->getData('current_category');
        if (is_null($category)) {
            if ($category = Mage::registry('current_category')) {
                $this->setData('current_category', $category);
            }
            else {
                $category = Mage::getModel('catalog/category')->load($this->getCurrentStore()->getRootCategoryId());
                $this->setData('current_category', $category);
            }
        }
        return $category;
    }

    /**
     * Change current category object
     *
     * @param mixed $category
     * @return Mage_Catalog_Model_Layer
     */
    public function setCurrentCategory($category)
    {
        if (is_numeric($category)) {
            $category = Mage::getModel('catalog/category')->load($category);
        }
        if (!$category instanceof Mage_Catalog_Model_Category) {
            Mage::throwException(Mage::helper('catalog')->__('Category must be an instance of Mage_Catalog_Model_Category.'));
        }
        if (!$category->getId()) {
            Mage::throwException(Mage::helper('catalog')->__('Invalid category.'));
        }

        if ($category->getId() != $this->getCurrentCategory()->getId()) {
            $this->setData('current_category', $category);
        }

        return $this;
    }

    /**
     * Retrieve current store model
     *
     * @return Mage_Core_Model_Store
     */
    public function getCurrentStore()
    {
        return Mage::app()->getStore();
    }

    /**
     * Get collection of all filterable attributes for layer products set
     *
     * @return Mage_Catalog_Model_Resource_Eav_Mysql4_Attribute_Collection
     */
    public function getFilterableAttributes()
    {
//        $entity = Mage::getSingleton('eav/config')
//            ->getEntityType('catalog_product');

        $setIds = $this->_getSetIds();
        
        if (!$setIds) {
            return array();
        }
        /* @var $collection Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Attribute_Collection */
        $collection = Mage::getResourceModel('catalog/product_attribute_collection')
            ->setItemObjectClass('catalog/resource_eav_attribute');

        $collection->getSelect()->distinct(true);
        $collection
            ->setAttributeSetFilter($setIds)
            ->addStoreLabel(Mage::app()->getStore()->getId())
            ->setOrder('position', 'ASC');
        $collection = $this->_prepareAttributeCollection($collection);
        $collection->load();

        return $collection;
    }

    /**
     * Prepare attribute for use in layered navigation
     *
     * @param   Mage_Eav_Model_Entity_Attribute $attribute
     * @return  Mage_Eav_Model_Entity_Attribute
     */
    protected function _prepareAttribute($attribute)
    {
        Mage::getResourceSingleton('catalog/product')->getAttribute($attribute);
        return $attribute;
    }

    /**
     * Add filters to attribute collection
     *
     * @param   Mage_Catalog_Model_Resource_Eav_Mysql4_Attribute_Collection $collection
     * @return  Mage_Catalog_Model_Resource_Eav_Mysql4_Attribute_Collection
     */
    protected function _prepareAttributeCollection($collection)
    {
        if (Mage::helper('solr')->isEnabledOnCatalog()) {
            $collection->addFieldToFilter('additional_table.is_filterableBySolr', array('gt' => 0));
        } else {
            $collection->addIsFilterableFilter();
        }
        return $collection;
    }

    /**
     * Retrieve layer state object
     *
     * @return Mage_Catalog_Model_Layer_State
     */
    public function getState()
    {
        $state = $this->getData('state');
        if (is_null($state)) {
            Varien_Profiler::start(__METHOD__);
            $state = Mage::getModel('catalog/layer_state');
            $this->setData('state', $state);
            Varien_Profiler::stop(__METHOD__);
        }
        return $state;
    }

    /**
     * Get attribute sets idendifiers of current product set
     *
     * @return array
     */
    protected function _getSetIds()
    {
		$setIds = $this->getProductCollection()->getSetIds();
        return $setIds;
    }
}
