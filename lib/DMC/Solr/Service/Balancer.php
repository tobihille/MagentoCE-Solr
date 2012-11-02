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

require_once('Apache/Solr/Service.php');

/**
 * Reference Implementation for using multiple Solr services in a distribution. Functionality
 * includes:
 * 	routing of read / write operations
 * 	failover (on selection) for multiple read servers
 */
class Apache_Solr_Service_Balancer
{
	protected $_readableServices = array();
	protected $_writeableServices = array();

	protected $_currentReadService = null;
	protected $_currentWriteService = null;

	protected $_readPingTimeout = 0.01;
	protected $_writePingTimeout = 1;

	/**
	 * Constructor. Takes arrays of read and write service instances or descriptions
	 *
	 * @param array $readableServices
	 * @param array $writeableServices
	 */
	public function __construct($readableServices = array(), $writeableServices = array())
	{
		//setup readable services
		foreach ($readableServices as $service)
		{
			$this->addReadService($service);
		}

		//setup writeable services
		foreach ($writeableServices as $service)
		{
			$this->addWriteService($service);
		}
	}

	public function setReadPingTimeout($timeout)
	{
		$this->_readPingTimeout = $timeout;
	}

	public function setWritePingTimetou($timeout)
	{
		$this->_writePingTimeout = $timeout;
	}

	/**
	 * Generates a service ID
	 *
	 * @param string $host
	 * @param integer $port
	 * @param string $path
	 * @return string
	 */
	private function _getServiceId($host, $port, $path)
	{
		return $host . ':' . $port . $path;
	}

	/**
	 * Adds a service instance or service descriptor (if it is already
	 * not added)
	 *
	 * @param mixed $service
	 *
	 * @throws Exception If service descriptor is not valid
	 */
	public function addReadService($service)
	{
		if ($service instanceof Apache_Solr_Service)
		{
			$id = $this->_getServiceId($service->getHost(), $service->getPort(), $service->getPath());

			$this->_readableServices[$id] = $service;
		}
		else if (is_array($service))
		{
			if (isset($service['host']) && isset($service['port']) && isset($service['path']))
			{
				$id = $this->_getServiceId((string)$service['host'], (int)$service['port'], (string)$service['path']);

				$this->_readableServices[$id] = $service;
			}
			else
			{
				throw new Exception('A Readable Service description array does not have all required elements of host, port, and path');
			}
		}
	}

	/**
	 * Removes a service instance or descriptor from the available services
	 *
	 * @param mixed $service
	 *
	 * @throws Exception If service descriptor is not valid
	 */
	public function removeReadService($service)
	{
		$id = '';

		if ($service instanceof Apache_Solr_Service)
		{
			$id = $this->_getServiceId($service->getHost(), $service->getPort(), $service->getPath());
		}
		else if (is_array($service))
		{
			if (isset($service['host']) && isset($service['port']) && isset($service['path']))
			{
				$id = $this->_getServiceId((string)$service['host'], (int)$service['port'], (string)$service['path']);
			}
			else
			{
				throw new Exception('A Readable Service description array does not have all required elements of host, port, and path');
			}
		}

		if ($id)
		{
			unset($this->_readableServices[$id]);
		}
	}

	/**
	 * Adds a service instance or service descriptor (if it is already
	 * not added)
	 *
	 * @param mixed $service
	 *
	 * @throws Exception If service descriptor is not valid
	 */
	public function addWriteService($service)
	{
		if ($service instanceof Apache_Solr_Service)
		{
			$id = $this->_getServiceId($service->getHost(), $service->getPort(), $service->getPath());

			$this->_writeableServices[$id] = $service;
		}
		else if (is_array($service))
		{
			if (isset($service['host']) && isset($service['port']) && isset($service['path']))
			{
				$id = $this->_getServiceId((string)$service['host'], (int)$service['port'], (string)$service['path']);

				$this->_writeableServices[$id] = $service;
			}
			else
			{
				throw new Exception('A Writeable Service description array does not have all required elements of host, port, and path');
			}
		}
	}

	/**
	 * Removes a service instance or descriptor from the available services
	 *
	 * @param mixed $service
	 *
	 * @throws Exception If service descriptor is not valid
	 */
	public function removeWriteService($service)
	{
		$id = '';

		if ($service instanceof Apache_Solr_Service)
		{
			$id = $this->_getServiceId($service->getHost(), $service->getPort(), $service->getPath());
		}
		else if (is_array($service))
		{
			if (isset($service['host']) && isset($service['port']) && isset($service['path']))
			{
				$id = $this->_getServiceId((string)$service['host'], (int)$service['port'], (string)$service['path']);
			}
			else
			{
				throw new Exception('A Readable Service description array does not have all required elements of host, port, and path');
			}
		}

		if ($id)
		{
			unset($this->_writeableServices[$id]);
		}
	}

	/**
	 * Iterate through available read services and select the first with a ping
	 * that satisfies configured timeout restrictions (or the default)
	 *
	 * @return Apache_Solr_Service
	 *
	 * @throws Exception If there are no read services that meet requirements
	 */
	private function _selectReadService()
	{
		if (!$this->_currentReadService || !isset($this->_readableServices[$this->_currentReadService]))
		{
			foreach ($this->_readableServices as $id => $service)
			{
				if (is_array($service))
				{
					//convert the array definition to a client object
					$service = new Apache_Solr_Service($service['host'], $service['port'], $service['path']);
					$this->_readableServices[$id] = $service;
				}

				//check the service (make sure it pings quickly)
				if ($service->ping($this->_readPingTimeout) !== false)
				{
					$this->_currentReadService = $id;
					return $this->_readableServices[$this->_currentReadService];
				}
			}

			throw new Exception('No read services were available');
		}

		return $this->_readableServices[$this->_currentReadService];
	}

	/**
	 * Iterate through available write services and select the first with a ping
	 * that satisfies configured timeout restrictions (or the default)
	 *
	 * @return Apache_Solr_Service
	 *
	 * @throws Exception If there are no write services that meet requirements
	 */
	private function _selectWriteService()
	{
		if (!$this->_currentWriteService || !isset($this->_writeableServices[$this->_currentWriteService]))
		{
			foreach ($this->_writeableServices as $id => $service)
			{
				if (is_array($service))
				{
					//convert the array definition to a client object
					$service = new Apache_Solr_Service($service['host'], $service['port'], $service['path']);
					$this->_writeableServices[$id] = $service;
				}

				//check the service
				if ($service->ping($this->_writePingTimeout) !== false)
				{
					$this->_currentWriteService = $id;
					return $this->_writeableServices[$this->_currentWriteService];
				}
			}

			throw new Exception('No write services were available');
		}

		return $this->_writeableServices[$this->_currentWriteService];
	}

	/**
	 * Raw Add Method. Takes a raw post body and sends it to the update service.  Post body
	 * should be a complete and well formed "add" xml document.
	 *
	 * @param string $rawPost
	 * @return Apache_Solr_Response
	 *
	 * @throws Exception If an error occurs during the service call
	 */
	public function add($rawPost)
	{
		$service = $this->_selectWriteService();

		return $service->add($rawPost);
	}

	/**
	 * Add a Solr Document to the index
	 *
	 * @param Apache_Solr_Document $document
	 * @param boolean $allowDups
	 * @param boolean $overwritePending
	 * @param boolean $overwriteCommitted
	 * @return Apache_Solr_Response
	 *
	 * @throws Exception If an error occurs during the service call
	 */
	public function addDocument(Apache_Solr_Document $document, $allowDups = false, $overwritePending = true, $overwriteCommitted = true)
	{
		$service = $this->_selectWriteService();

		return $service->addDocument($document, $allowDups, $overwritePending, $overwriteCommitted);
	}

	/**
	 * Add an array of Solr Documents to the index all at once
	 *
	 * @param array $documents Should be an array of Apache_Solr_Document instances
	 * @param boolean $allowDups
	 * @param boolean $overwritePending
	 * @param boolean $overwriteCommitted
	 * @return Apache_Solr_Response
	 *
	 * @throws Exception If an error occurs during the service call
	 */
	public function addDocuments($documents, $allowDups = false, $overwritePending = true, $overwriteCommitted = true)
	{
		$service = $this->_selectWriteService();

		return $service->addDocuments($documents, $allowDups, $overwritePending, $overwriteCommitted);
	}

	/**
	 * Send a commit command.  Will be synchronous unless both wait parameters are set
	 * to false.
	 *
	 * @param boolean $waitFlush
	 * @param boolean $waitSearcher
	 * @return Apache_Solr_Response
	 *
	 * @throws Exception If an error occurs during the service call
	 */
	public function commit($waitFlush = true, $waitSearcher = true)
	{
		$service = $this->_selectWriteService();

		return $service->commit($waitFlush, $waitSearcher);
	}

	/**
	 * Raw Delete Method. Takes a raw post body and sends it to the update service. Body should be
	 * a complete and well formed "delete" xml document
	 *
	 * @param string $rawPost
	 * @return Apache_Solr_Response
	 *
	 * @throws Exception If an error occurs during the service call
	 */
	public function delete($rawPost)
	{
		$service = $this->_selectWriteService();

		return $service->delete($rawPost);
	}

	/**
	 * Create a delete document based on document ID
	 *
	 * @param string $id
	 * @param boolean $fromPending
	 * @param boolean $fromCommitted
	 * @return Apache_Solr_Response
	 *
	 * @throws Exception If an error occurs during the service call
	 */
	public function deleteById($id, $fromPending = true, $fromCommitted = true)
	{
		$service = $this->_selectWriteService();

		return $service->deleteById($id, $fromPending, $fromCommitted);
	}

	/**
	 * Create a delete document based on a query and submit it
	 *
	 * @param string $rawQuery
	 * @param boolean $fromPending
	 * @param boolean $fromCommitted
	 * @return Apache_Solr_Response
	 *
	 * @throws Exception If an error occurs during the service call
	 */
	public function deleteByQuery($rawQuery, $fromPending = true, $fromCommitted = true)
	{
		$service = $this->_selectWriteService();

		return $service->deleteByQuery($rawQuery, $fromPending, $fromCommitted);
	}

	/**
	 * Send an optimize command.  Will be synchronous unless both wait parameters are set
	 * to false.
	 *
	 * @param boolean $waitFlush
	 * @param boolean $waitSearcher
	 * @return Apache_Solr_Response
	 *
	 * @throws Exception If an error occurs during the service call
	 */
	public function optimize($waitFlush = true, $waitSearcher = true)
	{
		$service = $this->_selectWriteService();

		return $service->optimize($waitFlush, $waitSearcher);
	}

	/**
	 * Simple Search interface
	 *
	 * @param string $query The raw query string
	 * @param int $offset The starting offset for result documents
	 * @param int $limit The maximum number of result documents to return
	 * @param array $params key / value pairs for query parameters, use arrays for multivalued parameters
	 * @return Apache_Solr_Response
	 *
	 * @throws Exception If an error occurs during the service call
	 */
	public function search($query, $offset = 0, $limit = 10, $params = array())
	{
		$service = $this->_selectReadService();

		return $service->search($query, $offset, $limit, $params);
	}
}