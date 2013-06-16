<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category    Mage
 * @package     Mage_CatalogSearch
 * @copyright   Copyright (c) 2009 Irubin Consulting Inc. DBA Varien (http://www.varien.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class DMC_Solr_Model_SolrServer_Adapter_Cms_Collection extends DMC_Solr_Model_SolrServer_Collection
{
	public function __construct($conn=null)
	{
		parent::__construct();
		$this->_init('cms/page');
	}

	protected function _init($model, $entityModel=null)
	{
		$this->setItemObjectClass(Mage::getConfig()->getModelClassName($model));
		return $this;
	}

	public function setStore($store)
	{
		$this->setStoreId(Mage::app()->getStore($store)->getId());

		return $this;
	}


	public function getStoreId()
	{
		if (is_null($this->_storeId)) {
			$this->setStoreId(Mage::app()->getStore()->getId());
		}
		return $this->_storeId;
	}

	public function setStoreId($storeId)
	{
		if ($storeId instanceof Mage_Core_Model_Store) {
			$storeId = $storeId->getId();
		}
		$this->_storeId = $storeId;
		$this->_productLimitationFilters['store_id'] = $this->_storeId;
		return $this;
	}

	protected function _renderFilters() {
		parent::_renderFilters();
		return $this;
	}

	public function getDefaultStoreId()
	{
		return Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID;
	}

	/**
	 * Add variable to bind list
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return Varien_Data_Collection_Db
	 */
	public function addBindParam($name, $value)
	{
		$this->_bindParams[$name] = $value;
		return $this;
	}

	/**
	 * Specify collection objects id field name
	 *
	 * @param string $fieldName
	 * @return Varien_Data_Collection_Db
	 */
	protected function _setIdFieldName($fieldName)
	{
		$this->_idFieldName = $fieldName;
		return $this;
	}

	/**
	 * Id field name getter
	 *
	 * @return string
	 */
	public function getIdFieldName()
	{
		return $this->_idFieldName;
	}

	/**
	 * Get collection item identifier
	 *
	 * @param Varien_Object $item
	 * @return mixed
	 */
	protected function _getItemId(Varien_Object $item)
	{
		if ($field = $this->getIdFieldName()) {
			return $item->getData($field);
		}
		return parent::_getItemId($item);
	}

	/**
	 * Set database connection adapter
	 *
	 * @param Zend_Db_Adapter_Abstract $conn
	 * @return Varien_Data_Collection_Db
	 */
	public function setConnection()
	{
		$this->_conn = Mage::helper('solr')->getSolr();
		return $this->_conn;
	}

	/**
	 * Get Zend_Db_Select instance
	 *
	 * @return Varien_Db_Select
	 */
	public function getSelect()
	{
		return $this->_select;
	}

	/**
	 * Retrieve connection object
	 *
	 * @return Zend_Db_Adapter_Abstract
	 */
	public function getConnection()
	{
		if(is_null($this->_conn)) {
			$this->setConnection();
		}
		return $this->_conn;
	}

	/**
	 * Get collection size
	 *
	 * @return int
	 */
	public function getSize()
	{
		if (is_null($this->_totalRecords)) {
			$select = clone $this->_select;
			$select->addField('id');
			$select->addField('row_id');
			$select->addField('row_type');
			$this->_fetchAll($select);
		}
		return intval($this->_totalRecords);
	}

	/**
	 * Get SQL for get record count
	 *
	 * @return Varien_Db_Select
	 */
	public function getSelectCountSql()
	{
		$this->_renderFilters();

		$countSelect = clone $this->getSelect();
		$countSelect->reset(DMC_Solr_Model_SolrServer_Select::PARAMS);
		$countSelect->reset(DMC_Solr_Model_SolrServer_Select::LIMIT);
		$countSelect->reset(DMC_Solr_Model_SolrServer_Select::OFFSET);

		return $countSelect;
		return 1;
	}

	/**
	 * Get sql select string or object
	 *
	 * @param   bool $stringMode
	 * @return  string || Zend_Db_Select
	 */
	function getSelectSql($stringMode = false)
	{
		echo $this->getConnection()->getSearchUrl($this->_select['query'], $this->_select['offset'], $this->_select['limit'], $this->getParams());
	}

	/**
	 * self::setOrder() alias
	 *
	 * @param string $field
	 * @param string $direction
	 * @return Varien_Data_Collection_Db
	 */
	public function addOrder($field, $direction = self::SORT_ORDER_DESC)
	{
		return $this->_setOrder($field, $direction);
	}

	/**
	 * Add select order to the beginning
	 *
	 * @param string $field
	 * @param string $direction
	 * @return Varien_Data_Collection_Db
	 */
	public function unshiftOrder($field, $direction = self::SORT_ORDER_DESC)
	{
		return $this->_setOrder($field, $direction, true);
	}

	/**
	 * Add field filter to collection
	 *
	 * If $attribute is an array will add OR condition with following format:
	 * array(
	 *     array('attribute'=>'firstname', 'like'=>'test%'),
	 *     array('attribute'=>'lastname', 'like'=>'test%'),
	 * )
	 *
	 * @see self::_getConditionSql for $condition
	 * @param string|array $attribute
	 * @param null|string|array $condition
	 * @return Mage_Eav_Model_Entity_Collection_Abstract
	 */
	public function addFieldToFilter($field, $condition=null)
	{
		$this->getSelect()->where($field, $condition);
		return $this;
	}

	/**
	 * Render sql select orders
	 *
	 * @return  Varien_Data_Collection_Db
	 */
	protected function _renderOrders()
	{
		foreach ($this->_orders as $orderExpr) {
			$this->_select->order($orderExpr);
		}
		return $this;
	}

	/**
	 * Render sql select limit
	 *
	 * @return  Varien_Data_Collection_Db
	 */
	protected function _renderLimit()
	{
		if($this->_pageSize){
			$this->_select->limitPage($this->getCurPage(), $this->_pageSize);
		}

		return $this;
	}

	/**
	 * Set select distinct
	 *
	 * @param bool $flag
	 */
	public function distinct($flag)
	{
		$this->_select->distinct($flag);
		return $this;
	}
	
	public function load($printQuery = false, $logQuery = false)
	{
		if ($this->isLoaded()) {
			return $this;
		}

		$this->_renderFilters()
			->_renderOrders()
			->_renderLimit();

		$this->printLogQuery($printQuery, $logQuery);

		$data = $this->getData();
		$this->resetData();

		if (is_array($data)) {
			$setIds = array();
			foreach ($data as $row) {
				$item = $this->getNewEmptyItem();
				if ($this->getIdFieldName()) {
					$item->setIdFieldName($this->getIdFieldName());
				}
				$typeConverter = new DMC_Solr_Model_SolrServer_Adapter_Cms_TypeConverter();
				foreach($row as $key => $value) {
					$productAttrName = $typeConverter->getProductAttributeName($key);
					if(!is_null($productAttrName)) {
						$row[$productAttrName] = $value;
					}
				}

				$row['page_id'] = $row['id'];
				$row['request_path'] = isset($row['rewrite_path']) ? $row['rewrite_path'] : null;

				$item->addData($row);
				$this->addItem($item);
			}
		}

		$this->_setIsLoaded();
		$this->_afterLoad();
		return $this;
	}

	/**
	 * Proces loaded collection data
	 *
	 * @return Varien_Data_Collection_Db
	 */
	protected function _afterLoadData()
	{
		return $this;
	}

	/**
	 * Reset loaded for collection data array
	 *
	 * @return Varien_Data_Collection_Db
	 */
	public function resetData()
	{
		$this->_data = null;
		return $this;
	}

	protected function _afterLoad()
	{
		return $this;
	}

	public function loadData($printQuery = false, $logQuery = false)
	{
		return $this->load($printQuery, $logQuery);
	}

	/**
	 * Print and/or log query
	 *
	 * @param boolean $printQuery
	 * @param boolean $logQuery
	 * @return  Varien_Data_Collection_Db
	 */
	public function printLogQuery($printQuery = false, $logQuery = false, $sql = null) {
		if ($printQuery) {
			echo is_null($sql) ? $this->getSelect()->__toString() : $sql;
		}

		if ($logQuery){
			Mage::log(is_null($sql) ? $this->getSelect()->__toString() : $sql);
		}
		return $this;
	}

	/**
	 * Reset collection
	 *
	 * @return Varien_Data_Collection_Db
	 */
	protected function _reset()
	{
		$this->getSelect()->reset();
		$this->_initSelect();
		$this->_setIsLoaded(false);
		$this->_items = array();
		$this->_data = null;
		return $this;
	}

	/**
	 * Fetch collection data
	 *
	 * @param   Zend_Db_Select $select
	 * @return  array
	 */
	protected function _fetchAll($select)
	{
		$product = Mage::getModel('catalog/product');
		$select->where('row_type:cms');
		$dataObject = $this->getConnection()->fetchAll($select);
		$data = $dataObject->__get('response');
		$facet = $dataObject->__get('facet_counts');
		if(isset($facet->facet_fields)) {
			$this->_facetCategoryCount = $facet->facet_fields->available_category_ids;
		}
		$this->_totalRecords = $data->numFound;
		$fields = null;
		$retData = array();

		foreach($data->docs as $row) {
			if(is_null($fields)) $fields = $row->getFieldNames();
			$retRow = array();
			foreach($fields as $field) {
				$retRow[$field] = $row->$field;
			}
			$retData[] = $retRow;
		}
		return $retData;
	}



	protected function _fetchStatistic($select)
	{
		$select->addField('row_id');
		$select->addField('row_type');
		$select->addField('attribute_set_id');
		$dataObject = $this->getConnection()->fetchAll($select);
		$data = $dataObject->__get('response');
		$fields = null;
		$retData = array();

		$this->_totalRecords = $data->numFound;

		if(count($data->docs)) {
			$setIds = array();
			foreach($data->docs as $row) {
				$setIds[] = $row->attribute_set_id;
			}
			$this->_setIds = array_unique($setIds);
		}
	}

	/**
	 * Get all data array for collection
	 *
	 * @return array
	 */
	public function getData()
	{
		if ($this->_data === null) {
			$this->_data = $this->_fetchAll($this->_select);
			$this->_afterLoadData();
		}
		return $this->_data;
	}

	/**
	 * Fetch collection data
	 *
	 * @param   Zend_Db_Select $select
	 * @return  array
	 */
	protected function _fetchOne($select)
	{
		$data = $this->getConnection();
		return $data;
	}

	/**
	 * Set Order field
	 *
	 * @param string $attribute
	 * @param string $dir
	 * @return Mage_CatalogSearch_Model_Mysql4_Fulltext_Collection
	 */
	public function setOrder($attributeCode, $dir='desc')
	{
		if ($attributeCode == 'relevance') {
			$this->getSelect()->order(array('field' => 'score', 'direct'=>$dir));
		}
		elseif ($attributeCode == 'position') {
			if(isset($this->_productLimitationFilters['category_id'])) {
				$field = DMC_Solr_Model_SolrServer_Adapter_Cms_TypeConverter::SUBPREFIX_POSITION.$this->_productLimitationFilters['category_id'];
				$this->getSelect()->order(array('field' => $field, 'direct'=>$dir));
			}
		}
		else {
			$entityType = $this->getEavConfig()->getEntityType('catalog_product');
			$attribute = Mage::getModel('catalog/entity_attribute')->loadByCode($entityType, $attributeCode);
			$typeConverter = new DMC_Solr_Model_SolrServer_Adapter_Cms_TypeConverter($attribute->getFrontend()->getInputType());
			$field = $typeConverter->solr_index_prefix.DMC_Solr_Model_SolrServer_Adapter_Cms_TypeConverter::SUBPREFIX_SORT.$attributeCode;
			$this->getSelect()->order(array('field' => $field, 'direct'=>$dir));
		}
		return $this;
	}

	/**
	 * Add attribute to entities in collection
	 *
	 * If $attribute=='*' select all attributes
	 *
	 * @param   array|string|integer|Mage_Core_Model_Config_Element $attribute
	 * @param   false|string $joinType flag for joining attribute
	 * @return  Mage_Eav_Model_Entity_Collection_Abstract
	 */
	public function addAttributeToSelect($attribute, $joinType=false)
	{
		if (is_array($attribute)) {
			foreach ($attribute as $a) {
				$this->addAttributeToSelect($a, $joinType);
			}
			return $this;
		}
		$this->_selectAttributes[] = $attribute;
		return $this;
	}


	/**
	 * Adding product count to categories collection
	 *
	 * @param   Mage_Eav_Model_Entity_Collection_Abstract $categoryCollection
	 * @return  Mage_Eav_Model_Entity_Collection_Abstract
	 */
	public function addCountToCategories($categoryCollection)
	{
		$isAnchor = array();
		$isNotAnchor = array();

		foreach ($categoryCollection as $category) {
			$_count = 0;
			$productCounts = $this->_facetCategoryCount;
			if (isset($productCounts[$category->getId()])) {
				$_count = $productCounts[$category->getId()];
			}
			$category->setProductCount($_count);
		}
		return $this;
	}

	public function addCategoryFilter(Mage_Catalog_Model_Category $category)
	{
		$this->_productLimitationFilters['category_id'] = $category->getId();
		if ($category->getIsAnchor()) {
			$this->getSelect()->where('available_category_ids:'.$category->getId());
		}
		else {
			$this->getSelect()->where('available_category_ids:'.$category->getId());
		}

		return $this;
	}

	/**
	 * Add search query filter
	 *
	 * @param   Mage_CatalogSearch_Model_Query $query
	 * @return  Mage_CatalogSearch_Model_Mysql4_Search_Collection
	 */
	public function addSearchFilter($query = NULL)
	{
		if(is_null($query)) $query = Mage::helper('catalogsearch')->getQuery();
		$where = '';
		$query = Apache_Solr_Service::escape($query);
		//$query = $this->addFuzzySearch($query);
		$where = 'attr_t_search_content_heading:'.$query.'*';
		$where .= ' OR attr_t_search_content:'.$query.'*';
		$where .= ' OR attr_t_search_title:'.$query.'*';
		$this->getSelect()->where($where);
		return $this;
	}

	/**
	 * Add store availability filter. Include availability product
	 * for store website
	 *
	 * @param   mixed $store
	 * @return  Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Collection
	 */
	public function addStoreFilter($store=null)
	{
		if (is_null($store)) {
			$store = $this->getStoreId();
		}
		$store = Mage::app()->getStore($store);

		if (!$store->isAdmin()) {
			$this->setStoreId($store);
			$where = 'store_id:'.$this->getStoreId();
			$this->getSelect()->where($where);
			return $this;
		}

		return $this;
	}

	/**
	 * Add website filter to collection
	 *
	 * @param Mage_Core_Model_Website|int|string|array $website
	 * @return Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Collection
	 */
	public function addWebsiteFilter($websites = null)
	{
		if (!is_array($websites)) {
			$websites = array(Mage::app()->getWebsite($websites)->getId());
		}

		$this->_productLimitationFilters['website_ids'] = $websites;
		$this->_applyProductLimitations();

		return $this;
	}

	/**
	 * Add price filter to collection
	 *
	 * @param Mage_Core_Model_Website|int|string|array $website
	 * @return Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Collection
	 */
	public function addPriceFilter($index, $range, $rate)
	{
		$from = $range * ($index - 1);
		$to = $range * $index;
		$this->getSelect()->where('price:['.$from.' TO '.$to.']');
	}

	public function applyFilterToCollection($cond, $condition = null) {
		$this->getSelect()->where($cond, $condition);
	}

	/**
	 * Add Price Data to result
	 *
	 * @param int $customerGroupId
	 * @param int $websiteId
	 * @return Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Collection
	 */
	public function addPriceData($customerGroupId = null, $websiteId = null)
	{
		return $this;
	}

	/**
	 * Set product visibility filter for enabled products
	 *
	 * @param array $visibility
	 * @return Mage_Catalog_Model_Resource_Eav_Mysql4_Product_Collection
	 */
	public function setVisibility($visibility)
	{
		if(is_numeric($visibility)) {
			(array)$visibility;
		}
		 
		if(isset($visibility) && is_array($visibility)) {
			foreach($visibility as $item) {
				$parts[] = 'visibility:'.$item;
			}
			$this->getSelect()->where(implode(' OR ', $parts));
		}
	}

	public function addUrlRewrite() {
		return $this;
	}

	public function getSetIds()
	{
		if(is_null($this->_setIds)) {
			$this->_renderFilters()
			->_renderOrders()
			->_renderLimit();

			$select = clone $this->_select;
			$this->_fetchStatistic($select);
		}
		return $this->_setIds;
	}

	public function getPriceStats()
	{
		return $this->_priceStats;
	}

	public function getAttributeCount($name) {
		$attrName = '_'.$name.'Count';
		if(isset($this->$attrName)){
			return $this->$attrName;
		}
		else return NULL;
	}

	public function addFuzzySearch($query)
	{
		if((int)Mage::getStoreConfig('solr/searcher/fuzzy_enable')) {
			$terms = explode(' ', $query);
			$factor = null;
			if ((float)Mage::getStoreConfig('solr/searcher/fuzzy_similarity_factor')) {
				$factor = (float)Mage::getStoreConfig('solr/searcher/fuzzy_similarity_factor');
			}
			foreach ($terms as $key => $one) {
				$terms[$key] = $one . '~' . $factor;
			}
			$query = implode(' ', $terms);
		}

		return $query;
	}
}
?>