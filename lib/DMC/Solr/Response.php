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
 * Represents a Solr response.  Parses the raw response into a set of stdClass objects
 * and associative arrays for easy access.
 *
 * Currently requires json_decode which is bundled with PHP >= 5.2.0, Alternatively can be
 * installed with PECL.  Zend Framework also includes a purely PHP solution.
 *
 * @todo When Solr 1.3 is released, possibly convert to use PHP or Serialized PHP output writer
 */
class Apache_Solr_Response
{
	/**
	 * Holds the raw response used in construction
	 *
	 * @var string
	 */
	private $_rawResponse;

	/**
	 * Parsed values from the passed in http headers. Assumes UTF-8 XML (default Solr response format)
	 *
	 * @var string
	 */
	private $_httpStatus = 200;
	private $_httpStatusMessage = '';
	private $_type = 'text/xml';
	private $_encoding = 'UTF-8';

	private $_isParsed = false;
	private $_parsedData;
	private $_documentType = null;
	
	
	

	/**
	 * Constructor. Takes the raw HTTP response body and the exploded HTTP headers
	 *
	 * @param string $rawResponse
	 * @param array $httpHeaders
	 */
	public function __construct($rawResponse, $httpHeaders = array())
	{
		//Assume 0, 'Communication Error', utf-8, and  text/xml
		$status = 0;
		$statusMessage = 'Communication Error';
		$type = 'text/plain';
		$encoding = 'UTF-8';

		//iterate through headers for real status, type, and encoding
		if (is_array($httpHeaders) && count($httpHeaders) > 0)
		{
			//look at the first header for the HTTP status code
			//and message (errors are usually returned this way)
			if (substr($httpHeaders[0], 0, 4) == 'HTTP')
			{
				//$parts = split(' ', substr($httpHeaders[0], 9), 2);
				$parts = explode(' ', substr($httpHeaders[0], 9), 2);

				$status = $parts[0];
				$statusMessage = trim($parts[1]);

				array_shift($httpHeaders);
			}

			//Look for the Content-Type response header and determine type
			//and encoding from it (if possible)
			foreach ($httpHeaders as $header)
			{
				if (substr($header, 0, 13) == 'Content-Type:')
				{
					//split content type into two parts if possible
//					$parts = split(';', substr($header, 13), 2);
					$parts = explode(';', substr($header, 13), 2);

					$type = trim($parts[0]);

					if ($parts[1])
					{
						//split the encoding section again to get the value
//						$parts = split('=', $parts[1], 2);
						$parts = explode('=', $parts[1], 2);

						if ($parts[1])
						{
							$encoding = trim($parts[1]);
						}
					}

					break;
				}
			}
		}

		$this->_rawResponse = $rawResponse;
		$this->_type = $type;
		$this->_encoding = $encoding;
		$this->_httpStatus = $status;
		$this->_httpStatusMessage = $statusMessage;
	}
	
	public function setDocumentType($type) {
		
		$this->_documentType = $type;
	}

	/**
	 * Get the HTTP status code
	 *
	 * @return integer
	 */
	public function getHttpStatus()
	{
		return $this->_httpStatus;
	}

	/**
	 * Get the HTTP status message of the response
	 *
	 * @return string
	 */
	public function getHttpStatusMessage()
	{
		return $this->_httpStatusMessage;
	}

	/**
	 * Get content type of this Solr response
	 *
	 * @return string
	 */
	public function getType()
	{
		return $this->_type;
	}

	/**
	 * Get character encoding of this response. Should usually be utf-8, but just in case
	 *
	 * @return string
	 */
	public function getEncoding()
	{
		return $this->_encoding;
	}

	/**
	 * Get the raw response as it was given to this object
	 *
	 * @return string
	 */
	public function getRawResponse()
	{
		return $this->_rawResponse;
	}

	/**
	 * Magic get to expose the parsed data and to lazily load it
	 *
	 * @param unknown_type $key
	 * @return unknown
	 */
	public function __get($key)
	{
		if (!$this->_isParsed)
		{
			$this->_parseData();
			$this->_isParsed = true;
		}

		if (isset($this->_parsedData->$key))
		{
			return $this->_parsedData->$key;
		}

		return null;
	}

	/**
	 * Parse the raw response into the parsed_data array for access
	 */
	private function _parseData()
	{
		//An alternative would be to use Zend_Json::decode(...)
		$data = json_decode($this->_rawResponse);
		if(is_null($this->_documentType)) {
			$solr = Mage::helper('solr')->getSolr();
		}
		
		//convert $data->response->docs[*] to be Solr_Document objects
		if (isset($data->response) && is_array($data->response->docs))
		{
			$documents = array();
			foreach ($data->response->docs as $doc)
			{
				if(is_null($this->_documentType)) {
					$adapterName = $solr->getDocumentType($doc->row_type);
					$adapter = new $adapterName();
					$document = $adapter->getSolrDocument();
				}
				else {
					$docClass = $this->_documentType;
					$document = new $docClass();
				}
				
				foreach ($doc as $key => $value)
				{
					//If a result is an array with only a single
					//value then its nice to be able to access
					//it as if it were always a single value
					if (is_array($value) && count($value) <= 1)
					{
						$value = array_shift($value);
					}
					$document->$key = $value;
				}
				$documents[] = $document;
			}
			$data->response->docs = $documents;
		}
		
		//correct facet counts to make sense
		//converts array([field value], [facet count], [field value], [facet count] ...) format
		//to array([field value] => [facet count], ... ) format
		if (isset($data->facet_counts) && isset($data->facet_counts->facet_fields))
		{
			foreach ($data->facet_counts->facet_fields as $key => $facet_array)
			{
				$new_facet_array = array();

				while (count($facet_array) > 0)
				{
					$new_facet_array[array_shift($facet_array)] = array_shift($facet_array);
				}

				$data->facet_counts->facet_fields->$key = $new_facet_array;
			}
		}

		$this->_parsedData = $data;
	}
}