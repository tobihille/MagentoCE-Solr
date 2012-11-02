<?php
/**
 * Apache Solr Search Engine for Magento
 *
 * @category  DMC
 * @package   DMC_Solr
 * @author    Team Magento <magento@digitalmanufaktur.com>
 * @version   0.1.4
 */
class DMC_Solr_Model_SolrServer_Adapter_Product_Document extends DMC_Solr_Model_SolrServer_Document
{
	protected $_type = 'product';
	
	public function setObject($object)
	{
		try {
			parent::setObject($object);
			
			Mage::helper('solr/log')->addDebugMessage('Add Product #'.$object->getId().' to index of store '.$this->getStoreId());
			$object->load($object->getId());
			
			foreach ( DMC_Solr_Model_SolrServer_Adapter_Product_TypeConverter::getStaticFields() as $key => $defaultValue ) {
				$methodName = '_get_value_'.$key;
				if(method_exists($this, $methodName)) {
					$value = $this->{$methodName}($object);
				}
				elseif(is_null($defaultValue)) continue;
				else {
					$value = $defaultValue;
				}
				if ( is_array( $value ) ) {
					foreach ( $value as $datum ) {
						$this->setMultiValue( $key, $datum );
					}
				}
				else {
					$this->_fields[$key] = $value;
				}
			}
			
			$this->_updatePositions($this->_fields, $object);
			
			foreach($object->getAttributes() as $name => $attribute) {
				$attrCode = $attribute->getAttributeCode();
				
				if(!DMC_Solr_Model_SolrServer_Adapter_Product_TypeConverter::isStaticField($attrCode)) {
					$inputType = $object->getResource()->getAttribute($attribute->getAttributeCode())->getFrontend()->getInputType($object);
					$typeConverter = new DMC_Solr_Model_SolrServer_Adapter_Product_TypeConverter($inputType);
					if(isset($typeConverter->solr_index)) {
						$values = $object->getResource()->getAttribute($attrCode)->getFrontend()->getValue($object);
						$indexes = $object->getData($attrCode);
						if ($attrCode == 'tier_price') {
							if (is_array($indexes)) {
								$res = '';
								foreach ($indexes as $one) {
									$res .= $one['website_price'] . ' ';
								}
								$indexes = $res;
							}
						}
						else if($inputType === 'multiselect') {
							$values = is_string($values) && strlen($values) ? explode(',', $values) : array();
							$indexes = (is_string($indexes) && strlen($indexes)) ? explode(',', $indexes) : array();
							array_walk($values, array($this, 'itemTrim'));
							array_walk($indexes, array($this, 'itemTrim'));
						}
						elseif($inputType === 'date') {
							$indexes = $this->dateConvert($indexes);
						}
						
						if(($attribute->getData('is_searchable') ||
							$attribute->getData('used_in_product_listing') ||
							$attribute->getData('is_visible_in_advanced_search')) && $typeConverter->isSearchable()) {
							$key = $typeConverter->solr_search_prefix.'search_'.$attrCode;
							if(is_array($indexes) && count($indexes)) {
								
								foreach ( $values as $value ) {
									$this->setMultiValue( $key, trim($value) );
								}
							}
							elseif(is_string($indexes) && strlen($indexes)) {
								$this->$key = trim($values);
							}
						}
						
						$key = $typeConverter->solr_index_prefix.'index_'.$attrCode;
						
						if(is_array($indexes) && count($indexes)) {
							
							foreach ( $indexes as $index ) {
								$this->setMultiValue( $key, trim($index) );
							}
						}
						elseif(is_string($indexes) && strlen($indexes)) {
							$this->$key = trim($indexes);
						}
						
						if(	$attribute->getData('used_for_sort_by') && $typeConverter->isSortable()) {
							$key = $typeConverter->solr_sort_prefix.'sort_'.$attrCode;
								
							if(is_array($indexes) && count($indexes)) {
								
								foreach ( $values as $value ) {
									$this->setMultiValue( $key, trim($value) );
								}
							}
							elseif(is_string($indexes) && strlen($indexes)) {
								$this->$key = trim($values);
							}
						}
					}
				}
			}
		}
		catch(DMC_Solr_Model_Catalog_Product_Exception $e) {
			Mage::helper('solr/log')->addDebugMessage($e->getMessage());
			return false;
		}
		return true;
	}
	
	private function _updatePositions(&$fields, $object) {
		$db = Mage::getSingleton('core/resource')->getConnection('core_read');
		
		$select = $db->select();
		$select->from( 'catalog_category_product_index' );
		$select->where('product_id = ?', $object->getId());
		$select->where('store_id = ?', $this->getStoreId());
		
		foreach ($db->fetchAll($select) as $row) {
			$name = DMC_Solr_Model_SolrServer_Adapter_Product_TypeConverter::SUBPREFIX_POSITION.$row['category_id'];
			$fields[$name] = $row['position'];
		}
	}

	
	private function getDynamicAttributeValue($key, $object) {
		return $object->getData($key);
	}
	
	private function _get_value_url($object) {
		$baseUrl = Mage::app()->getStore()->getBaseUrl();
		$object->setStore($this->getStoreId());
		$url = str_replace($baseUrl, '', $object->getProductUrl());
		return $url;
	}
	
	private function _get_value_thumb($object) {
		try {
			if(strlen($object->getSmallImage())) {
				$url = Mage::helper("catalog/image")->init($object, "small_image")->resize(35)->__toString();
				$baseUrl = Mage::app()->getStore()->getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
				$url = str_replace($baseUrl, '', $url);
				return $url;
			}
			else {
				return '';
			}
		}
		catch (Exception $e) {
			$url = '';
		}
	}
	
	private function _get_value_id($object) {
		return $object->getId();
	}
	
	private function _get_value_store_id($object) {
		return $this->getStoreId();
	}
	
	private function _get_value_visibility($object) {
		return $object->getData('visibility');
	}
	
	private function _get_value_status($object) {
		return $object->getStatus();
	}
	
	private function _get_image($object) {
		return $object->getImage();
	}
	
	private function _get_value_small_image($object) {
		return $object->getSmallImage();
	}
	
	private function _get_value_value_type_id($object) {
		return $object->getTypeId();
	}
	
	private function _get_value_category_ids($object) {
		$ids = $object->getCategoryIds();
		if (!count($ids)) {
			throw new DMC_Solr_Model_Catalog_Product_Exception('The product ' . $object->getId() . ' does not belong to any category');
		}
		return $ids;
	}
	
	private function _get_value_available_category_ids($object) {
		$ids = $this->getAvailableInAnchorCategories($object);
		if (!$ids && (int)Mage::getStoreConfig('solr/indexer/reindex_category')) {
			$categories = $object->getCategoryIds();
			if (!$categories) {
				throw new DMC_Solr_Model_Catalog_Product_Exception('The product ' . $object->getId() . ' does not belong to any category');
			}
			$toReindex = array();
			foreach ($categories as $one) {
				$toReindex[$one] = $one;
			}
			if ($toReindex) {
				if(Mage::helper('solr')->isCurrentVersionMore('1.6')) {
					Mage::getResourceModel('catalog/product')->refreshEnabledIndex($object);
				}
				else {
					Mage::getResourceSingleton('catalog/category')->refreshProductIndex($toReindex);
				}
			}
			$ids = $this->getAvailableInAnchorCategories($object);
		}
		if(!count($ids)) {
			throw new DMC_Solr_Model_Catalog_Product_Exception('The product ' . $object->getId() . ' does not belong to any category');
		}
		
		return array_unique($ids);
	}

	private function _get_value_weight($object) {
		return $object->getWeight();
	}
	
	private function _get_value_name($object) {
		return $object->getName();
	}
	
	private function _get_value_name_text($object) {
		return $object->getName();
	}
	
	private function _get_value_sku($object) {
		return $object->getSku();
	}

	private function _get_value_price($object) {
		return $object->getFinalPrice();
	}

	private function _get_value_in_stock($object) {
		return $object->isInStock() ? 1 : 0;
	}
	
	private function _get_value_description($object) {
		return $object->getDescription();
	}
	
	private function _get_value_short_description($object) {
		return $object->getShortDescription();
	}
	
	private function _get_value_rewrite_path($object) {
		return $object->getUrlModel()->getUrlPath($object);
	}
	
	private function _get_value_attribute_set_id($object) {
		return $object->getAttributeSetId();
	}
	
	private function _get_value_is_salable($object) {
            return 1;
		return $object->isSaleable() ? 1 : 0;
	}
	
	public function itemTrim(&$item1, $key) {
		$item1 = trim($item1);
	}
	
	public function dateConvert(&$item1) {
		$item1 = trim($item1);
		$item1 = str_replace(' ', 'T', $item1);
	}
	
	public function getAvailableInAnchorCategories($object) {
		$ids = array();
		$db = Mage::getSingleton('core/resource')->getConnection('core_read');
		$select = $db->select();
		$select->distinct()
			->from('catalog_category_product_index', array('category_id'))
			->where('product_id = ?', (int)$object->getEntityId());
		foreach ($db->fetchAll($select) as $row) {
			$ids[] = $row['category_id'];
		}
		
		return $ids;
	}
}
