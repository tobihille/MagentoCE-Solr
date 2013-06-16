<?php
/**
 * Apache Solr Search Engine for Magento
 *
 * @category  DMC
 * @package   DMC_Solr
 * @author    Team Magento <magento@digitalmanufaktur.com>
 * @version   0.1.4
 */
 
/**
 * @copyright Copyright 2007 Conduit Internet Technologies, Inc. (http://conduit-it.com)
 * @license Apache Licence, Version 2.0 (http://www.apache.org/licenses/LICENSE-2.0)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * @package Apache
 * @subpackage Solr
 * @author Donovan Jimenez <djimenez@conduit-it.com>
 */

/**
 * Holds Key / Value pairs that represent a Solr Document. Field values can be accessed
 * by direct dereferencing such as:
 * <code>
 * ...
 * $document->title = 'Something';
 * echo $document->title;
 * ...
 * </code>
 *
 * Additionally, the field values can be iterated with foreach
 *
 * <code>
 * foreach ($document as $key => $value)
 * {
 * ...
 * }
 * </code>
 */
class DMC_Solr_Model_SolrServer_Document extends Apache_Solr_Document //implements Iterator
{
	protected $_type = null;
	
	protected $_fields = array();
	
	protected $_storeId = null;
	
	protected $_object = null;
	
	public function __construct()
	{
		$this->_fields['row_type'] = $this->getType();
	}
	
	public function getStoreId()
	{
		return $this->_storeId;
	}
	
	public function __get($key)
	{
		if(isset($this->_fields[$key])) return $this->_fields[$key];
		return NULL;
	}

	public function __set($key, $value)
	{
		$this->_fields[$key] = $value;
	}

	public function __isset($key)
	{
		return isset($this->_fields[$key]);
	}

	public function __unset($key)
	{
		unset($this->_fields[$key]);
	}
	
	public function getType()
	{
		return $this->_type;
	}

	public function setMultiValue($key, $value)
	{
		if (!isset($this->_fields[$key]))
		{
			$this->_fields[$key] = array();
		}

		if (!is_array($this->_fields[$key]))
		{
			$this->_fields[$key] = array($this->_fields[$key]);
		}

		$this->_fields[$key][] = $value;
	}

	public function getFieldNames()
	{
		return array_keys($this->_fields);
	}

	public function rewind() {
		reset($this->_fields);
	}

	public function current() {
		return current($this->_fields);
	}

	public function key() {
		return key($this->_fields);
	}

	public function next() {
		return next($this->_fields);
	}
	
	public function valid() {
		return current($this->_fields) !== false;
	}
	
	public function getReindexer()
	{
		if(is_null($this->_reindexer)) {
			$this->_reindexer = DMC_Solr_Document_Reindexer::getInstance();
		}
		return $this->_reindexer;
	}
	
	protected function _getUniqueRowId()
	{
		if($this->getObject()->getId()) {
			return $this->getType().'_'.$this->getStoreId().'_'.$this->getObject()->getId();
		}
		return null;
	}
	
	public function getRowId()
	{
		return $this->_getUniqueRowId();
	}
	
	public function setObject($object)
	{
		$this->_object = $object;
		$this->_fields['row_id'] = $this->_getUniqueRowId();
	}
	
	public function setStoreId($storeId)
	{
		$this->_storeId = $storeId;
		$this->_fields['row_id'] = $this->_getUniqueRowId();
		$this->_fields['store_id'] = $this->_storeId;
	}
	
	public function getObject()
	{
		return $this->_object;
	}
}