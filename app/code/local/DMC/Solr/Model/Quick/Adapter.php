<?php
/**
 * Apache Solr Search Engine for Magento
 *
 * @category  DMC
 * @package   DMC_Solr
 * @author    Team Magento <magento@digitalmanufaktur.com>
 * @version   0.1.4
 */
 
$LIB_PATH = dirname(DMC_All_Model_Quick::getRoot()).DS.'lib'.DS.'DMC'.DS.'Solr';
require_once $LIB_PATH.DS.'Response.php';
require_once $LIB_PATH.DS.'Service.php';

class DMC_Solr_Model_Quick_Adapter extends Apache_Solr_Service
{
	public function __construct($host, $port, $path) {
		$this->setHost($host);
		$this->setPort($port);
		$this->setPath($path);

		$this->_initUrls();

		//set up the stream context for posting with file_get_contents
		$contextOpts = array(
			'http' => array(
				'method' => 'POST',
				'header' => "Content-Type: text/xml; charset=UTF-8\r\n" //php.net example showed \r\n at the end
			)
		);

		$this->_postContext = stream_context_create($contextOpts);
	}
	
	public function getDocumentTypes($inTypes=null)
	{
		if(is_null($this->_documentTypes)) {
			$config = Mage::getConfig()->getNode(self::XML_SOLR_DOCUMENT_TYPES)->children();
			foreach($config as $name=>$item) {
				$value = $item->children()->asArray();
				$this->_documentTypes[$name] = $value;
			}
		}
		if(is_array($inTypes) && count($inTypes)) {
			foreach($inTypes as $name) {
				$types[$name] = $this->_documentTypes[$name];
			}
		}
		else {
			$types = $this->_documentTypes;
		}
		
		return $types;
	}
	
	public function getDocumentType($type) {
		$types = $this->getDocumentTypes();
		return isset($types[$type]) ? $types[$type] : null;
	}
	
	public function getSearchUrl($query, $offset = NULL, $limit = NULL, $params = array()) {
		if (!is_array($params)) {
			$params = array();
		}

		//construct our full parameters
		//sending the version is important in case the format changes
		$params['version'] = self::SOLR_VERSION;

		//common parameters in this interface
		$params['wt'] = self::SOLR_WRITER;
		$params['q'] = $query;
		if(!is_null($offset)) $params['start'] = $offset;
		if(!is_null($limit)) $params['rows'] = $limit;

		//escape all parameters appropriately for inclusion in the GET parameters
		$escapedParams = array();

		do
		{
			//because some parameters can be included multiple times, loop through all
			//params and include their value or their first array value. unset values as
			//they are fully added so that the params list can be iteratively added.
			//
			//NOTE: could be done all at once, but this way makes the query string more
			//readable at little performance cost
			foreach ($params as $key => &$value)
			{
				if (is_array($value))
				{
					//parameter has multiple values that need passed
					//array_shift pops off the first value in the array and also removes it
					$escapedParams[] = urlencode($key) . '=' . urlencode(array_shift($value));

					if (empty($value))
					{
						unset($params[$key]);
					}
				}
				else
				{
					//simple, single value case
					$escapedParams[] = urlencode($key) . '=' . urlencode($value);
					unset($params[$key]);
				}
			}
		} while (!empty($params));
		return $this->_searchUrl . $this->_queryDelimiter . implode($this->_queryStringDelimiter, $escapedParams);
	}

	public function fetchAll($queryObject, $offset = NULL, $limit = NULL, $params = array())
	{
		$query = $queryObject->getQuery();
		$offset = $queryObject->getOffset();
		$limit = $queryObject->getLimit();
		$params = $queryObject->getParams();
		$url = $this->getSearchUrl($query, $offset, $limit, $params);
		$return = $this->_sendRawGet($url);
		return $return;
	}

	public function ping($timeout = self::DEFAULT_PING_TIMEOUT) {
		set_error_handler(array(get_class($this), 'ping_error'), E_ALL);
		$ping = parent::ping($timeout);
		restore_error_handler();
		if($this->_error || !$ping) {
			$this->_error = false;
			$this->_lastPing = NULL;
			return false;
		}
		else {
			$this->_lastPing = $ping;
			return true;
		}
	}
	
	static public function ping_error()
	{
	}
	
	public function getLastPingMessage() {
		if(!is_null($this->_lastPing)) {
			$message = '<font color="green">'.Mage::helper('solr')->__('Service is working. Responce time is ').sprintf("%01.5f", $this->_lastPing).Mage::helper('solr')->__(' sec.').'</font>';
		}
		else {
			$message = '<font color="red">'.Mage::helper('solr')->__('Solr service not responding').'</font>';
		}
		return $message;
	}
}
